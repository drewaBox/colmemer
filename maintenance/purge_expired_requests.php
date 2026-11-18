<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require dirname(__DIR__) . '/request_helpers.php';

$db = db();
$batchSize = 500;
$purgedCount = 0;

try {
    do {
        $db->beginTransaction();
        $expiredQuery = $db->query(
            'SELECT id, deletion_audit_id
             FROM requests
             WHERE deleted_at <= DATE_SUB(NOW(), INTERVAL 30 DAY)
             ORDER BY deleted_at, id
             LIMIT ' . $batchSize . '
             FOR UPDATE'
        );
        $expiredRequests = $expiredQuery->fetchAll();
        if ($expiredRequests === []) {
            $db->commit();
            break;
        }

        $markPurged = $db->prepare(
            'UPDATE request_deletion_audit
             SET purged_at = NOW()
             WHERE id = :audit_id AND purged_at IS NULL AND restored_at IS NULL'
        );
        $requestIds = [];
        foreach ($expiredRequests as $request) {
            $auditId = (int) ($request['deletion_audit_id'] ?? 0);
            if ($auditId < 1) {
                throw new RuntimeException('An expired archived request is missing its deletion audit record.');
            }
            $markPurged->execute(['audit_id' => $auditId]);
            if ($markPurged->rowCount() !== 1) {
                throw new RuntimeException('An expired archived request has an invalid or already finalized audit record.');
            }
            $requestIds[] = (int) $request['id'];
        }

        $placeholders = implode(', ', array_fill(0, count($requestIds), '?'));
        $deleteRequests = $db->prepare(
            'DELETE FROM requests
             WHERE id IN (' . $placeholders . ')
               AND deleted_at <= DATE_SUB(NOW(), INTERVAL 30 DAY)'
        );
        $deleteRequests->execute($requestIds);
        if ($deleteRequests->rowCount() !== count($requestIds)) {
            throw new RuntimeException('The expired request batch changed while it was being purged.');
        }

        $db->commit();
        $purgedCount += count($requestIds);
    } while (count($expiredRequests) === $batchSize);

    fwrite(STDOUT, 'Purged ' . $purgedCount . ' expired request(s).' . PHP_EOL);
} catch (Throwable $exception) {
    if ($db->inTransaction()) {
        $db->rollBack();
    }
    error_log('Expired request purge failed: ' . $exception->getMessage());
    fwrite(STDERR, 'Expired request purge failed: ' . $exception->getMessage() . PHP_EOL);
    exit(1);
}
