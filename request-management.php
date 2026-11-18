<?php
declare(strict_types=1);

require __DIR__ . '/request_helpers.php';
require_once __DIR__ . '/audit_helpers.php';
start_app_session();
require_personnel();

$db = db();
$error = '';
$reopenConfirmation = null;
$notice = $_SESSION['request_management_notice'] ?? '';
unset($_SESSION['request_management_notice']);

$statusLabels = [
    'SUBMITTED' => 'Pending',
    'AI_VERIFYING' => 'Verifying',
    'FOR_REVIEW' => 'Pending',
    'NEEDS_CORRECTION' => 'Needs correction',
    'PROCESSING' => 'Processing',
    'READY' => 'Ready for Pickup',
    'COMPLETED' => 'Released',
    'REJECTED' => 'Rejected',
    'CANCELLED' => 'Cancelled',
];
$isOverviewAction = $_SERVER['REQUEST_METHOD'] === 'POST'
    && ($_POST['overview_action'] ?? '') === '1';
$isAdmin = ($_SESSION['personnel_role'] ?? '') === 'admin';
$view = (string) ($_GET['view'] ?? 'active');
if (!in_array($view, ['active', 'archive', 'history'], true)) {
    $view = 'active';
}
if (!$isAdmin && $view !== 'active') {
    http_response_code(403);
    exit('Only administrators can view deleted requests and deletion history.');
}
$requestAction = (string) ($_POST['request_action'] ?? '');

if ($_SERVER['REQUEST_METHOD'] === 'POST' && in_array($requestAction, ['archive_request', 'restore_request'], true)) {
    if (!valid_csrf_token($_POST['csrf_token'] ?? null)) {
        http_response_code(400);
        exit('Invalid form token. Please reload this page and try again.');
    }

    $requestId = filter_var($_POST['request_id'] ?? null, FILTER_VALIDATE_INT);
    if (!$isAdmin) {
        http_response_code(403);
        exit('Only administrators can archive or restore requests.');
    }

    if (!is_int($requestId) || $requestId < 1) {
        $error = 'Choose a valid request.';
    } else {
        $passwordConfirmationFailed = false;
        $currentPassword = is_string($_POST['current_password'] ?? null) ? $_POST['current_password'] : '';
        try {
            $adminName = (string) (
                $_SESSION['personnel_name']
                ?? $_SESSION['personnel_username']
                ?? ('Administrator #' . (int) $_SESSION['personnel_id'])
            );
            if ($requestAction === 'archive_request') {
                if (!verify_personnel_password($db, $currentPassword)) {
                    $passwordConfirmationFailed = true;
                    throw new InvalidArgumentException(
                        $currentPassword === ''
                            ? 'Enter your current password to confirm deletion.'
                            : 'Your current password is incorrect.'
                    );
                }
                $trackingNumber = archive_document_request($db, $requestId, $adminName);
                $_SESSION['request_management_notice'] =
                    'Request ' . $trackingNumber . ' deleted. It can be restored for 30 days.';
                header('Location: request-management.php?view=archive');
                exit;
            }
            $trackingNumber = restore_archived_document_request(
                $db,
                $requestId,
                (int) $_SESSION['personnel_id'],
                $adminName
            );
            $_SESSION['request_management_notice'] = 'Request ' . $trackingNumber . ' restored to the active request list.';
            header('Location: request-management.php');
            exit;
        } catch (InvalidArgumentException $exception) {
            $error = $exception->getMessage();
            if ($passwordConfirmationFailed) {
                $reopenConfirmation = [
                    'action_type' => 'archive',
                    'request_id' => $requestId,
                    'error' => $error,
                ];
                $error = '';
            }
        } catch (PDOException $exception) {
            error_log('Request archive database error: ' . $exception->getMessage());
            $error = 'The request could not be archived or restored. Check the database and try again.';
        } catch (RuntimeException|JsonException $exception) {
            error_log('Request archive operation failed: ' . $exception->getMessage());
            $error = 'The request could not be archived or restored. Please try again.';
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !in_array($requestAction, ['archive_request', 'restore_request'], true)) {
    if (!valid_csrf_token($_POST['csrf_token'] ?? null)) {
        if ($isOverviewAction) {
            http_response_code(400);
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(['ok' => false, 'message' => 'Invalid form token. Reload the page and try again.']);
            exit;
        }
        http_response_code(400);
        exit('Invalid form token. Please reload this page and try again.');
    }

    $requestId = filter_var($_POST['request_id'] ?? null, FILTER_VALIDATE_INT);
    $nextStatus = (string) ($_POST['next_status'] ?? '');
    $requestedReleaseDate = trim((string) ($_POST['expected_release_date'] ?? ''));
    $rejectionReason = is_string($_POST['rejection_reason'] ?? null)
        ? trim($_POST['rejection_reason'])
        : '';
    if (!is_int($requestId) || $requestId < 1) {
        $error = 'Choose a valid request.';
    } elseif (!array_key_exists($nextStatus, $statusLabels) || $nextStatus === 'CANCELLED') {
        $error = 'Choose a valid request status.';
    } elseif ($nextStatus === 'REJECTED' && $rejectionReason === '') {
        $error = 'Enter a rejection reason.';
    } elseif ($nextStatus === 'REJECTED' && mb_strlen($rejectionReason) > 500) {
        $error = 'The rejection reason must be no more than 500 characters.';
    } else {
        try {
            $db->beginTransaction();
            $requestQuery = $db->prepare(
                'SELECT r.id, r.status, r.expected_release_date, r.registrar_remarks,
                        r.tracking_no, r.purpose, d.name AS document_name,
                        COALESCE(d.processing_max_days, 0) AS processing_max_days,
                        COALESCE(NULLIF(u.email, \'\'), g.email) AS requester_email,
                        COALESCE(NULLIF(u.full_name, \'\'),
                            CONCAT_WS(\' \', g.first_name, NULLIF(g.middle_name, \'\'), g.last_name)) AS requester_name
                 FROM requests r
                 LEFT JOIN users u ON u.id = r.student_id
                 LEFT JOIN guest_request_details g ON g.request_id = r.id
                 LEFT JOIN request_items i ON i.request_id = r.id
                 LEFT JOIN documents d ON d.id = i.document_id
                 WHERE r.id = :id AND r.deleted_at IS NULL
                 LIMIT 1
                 FOR UPDATE'
            );
            $requestQuery->execute(['id' => $requestId]);
            $request = $requestQuery->fetch();
            if (!$request) {
                throw new InvalidArgumentException('That active request could not be found.');
            }

            $currentStatus = (string) $request['status'];
            $allowedNextStatuses = match ($currentStatus) {
                'SUBMITTED', 'FOR_REVIEW' => ['PROCESSING', 'REJECTED'],
                'PROCESSING' => ['READY', 'REJECTED'],
                'READY' => ['READY', 'COMPLETED'],
                default => [],
            };
            if (!in_array($nextStatus, $allowedNextStatuses, true)) {
                throw new InvalidArgumentException('This request has already reached a final status or cannot move to that status.');
            }

            $historyRemark = '';
            $sendProcessingNotification = false;
            if ($nextStatus === 'PROCESSING') {
                $processingDays = (int) $request['processing_max_days'];
                $systemExpectedDate = estimate_document_release_date($processingDays)->format('Y-m-d');
                $releaseDateParts = explode('-', $requestedReleaseDate);
                if (count($releaseDateParts) !== 3
                    || !checkdate(
                        (int) $releaseDateParts[1],
                        (int) $releaseDateParts[2],
                        (int) $releaseDateParts[0]
                    )
                    || !preg_match('/^\d{4}-\d{2}-\d{2}$/D', $requestedReleaseDate)) {
                    throw new InvalidArgumentException('Choose a valid expected release date.');
                }
                $newExpectedDate = $requestedReleaseDate;
                $sendProcessingNotification = true;
                $processingTime = $processingDays === 0
                    ? 'same day'
                    : $processingDays . ' ' . ($processingDays === 1 ? 'weekday' : 'weekdays');
                $historyRemark = 'Processing started with an estimated release date of ' . $newExpectedDate
                    . ($newExpectedDate === $systemExpectedDate
                        ? ' based on the document processing time of ' . $processingTime . '.'
                        : '. The system estimate was ' . $systemExpectedDate . '.');
                $update = $db->prepare(
                    'UPDATE requests
                     SET status = :status, expected_release_date = :expected_release_date,
                         registrar_remarks = NULL
                     WHERE id = :id'
                );
                $update->execute([
                    'status' => $nextStatus,
                    'expected_release_date' => $newExpectedDate,
                    'id' => $requestId,
                ]);
            } elseif ($nextStatus === 'READY') {
                $historyRemark = 'Document is ready for pickup.';
                $update = $db->prepare(
                    'UPDATE requests SET status = :status, ready_at = NOW() WHERE id = :id'
                );
                $update->execute(['status' => $nextStatus, 'id' => $requestId]);
            } elseif ($nextStatus === 'COMPLETED') {
                $historyRemark = 'Document released to requester.';
                $update = $db->prepare(
                    'UPDATE requests SET status = :status, completed_at = NOW() WHERE id = :id'
                );
                $update->execute(['status' => $nextStatus, 'id' => $requestId]);
            } else {
                $historyRemark = $rejectionReason;
                $update = $db->prepare(
                    'UPDATE requests SET status = :status, registrar_remarks = :remarks WHERE id = :id'
                );
                $update->execute([
                    'status' => $nextStatus,
                    'remarks' => $rejectionReason,
                    'id' => $requestId,
                ]);
            }

            if ($nextStatus !== $currentStatus || $historyRemark !== '') {
                $historyInsert = $db->prepare(
                    'INSERT INTO request_history
                        (request_id, old_status, new_status, changed_by, remarks)
                     VALUES (:request_id, :old_status, :new_status, :changed_by, :remarks)'
                );
                $historyInsert->execute([
                    'request_id' => $requestId,
                    'old_status' => $currentStatus,
                    'new_status' => $nextStatus,
                    'changed_by' => (int) $_SESSION['personnel_id'],
                    'remarks' => $historyRemark,
                ]);
                record_audit_event(
                    $db,
                    (int) $_SESSION['personnel_id'],
                    (string) ($_SESSION['personnel_name'] ?? $_SESSION['personnel_username']),
                    'status_changed',
                    'request',
                    (string) $requestId,
                    'Request ' . (string) $request['tracking_no'] . ' changed from '
                        . $currentStatus . ' to ' . $nextStatus . '.',
                    ['old_status' => $currentStatus, 'new_status' => $nextStatus]
                );
            }
            $db->commit();

            $notification = '';
            $notificationKey = null;
            $notificationValues = [];
            $documentName = (string) ($request['document_name'] ?: $request['purpose']);
            if ($nextStatus === 'PROCESSING' && $sendProcessingNotification) {
                $notificationKey = 'processing';
                $notificationValues = [
                    'document_name' => $documentName,
                    'tracking_number' => (string) $request['tracking_no'],
                    'expected_release_date' => (new DateTimeImmutable($newExpectedDate))->format('F j, Y'),
                ];
            } elseif ($nextStatus !== $currentStatus && $nextStatus === 'READY') {
                $notificationKey = 'ready';
                $notificationValues = [
                    'document_name' => $documentName,
                    'tracking_number' => (string) $request['tracking_no'],
                ];
            } elseif ($nextStatus !== $currentStatus && $nextStatus === 'REJECTED') {
                $notificationKey = 'rejected';
                $notificationValues = [
                    'document_name' => $documentName,
                    'tracking_number' => (string) $request['tracking_no'],
                    'rejection_reason' => $rejectionReason,
                ];
            }

            if ($notificationKey !== null) {
                $recipient = (string) ($request['requester_email'] ?? '');
                if (!filter_var($recipient, FILTER_VALIDATE_EMAIL)) {
                    $notification = ' The status was saved, but no valid requester email is available for notification.';
                } else {
                    try {
                        $sent = send_registrar_template($db, $notificationKey, $recipient, $notificationValues);
                        $notification = $sent
                            ? ' Notification email sent.'
                            : ' Email notification is disabled by the administrator.';
                    } catch (RuntimeException $exception) {
                        error_log('Request status notification failed for ' . $request['tracking_no'] . ': ' . $exception->getMessage());
                        $notification = ' The status was saved, but notification email could not be sent: ' . $exception->getMessage();
                    }
                }
            }

            $successMessage =
                'Request ' . $request['tracking_no'] . ' updated to ' . $statusLabels[$nextStatus] . '.' . $notification;
            if ($isOverviewAction) {
                header('Content-Type: application/json; charset=utf-8');
                header('Cache-Control: no-store, private');
                echo json_encode(
                    ['ok' => true, 'message' => $successMessage],
                    JSON_THROW_ON_ERROR | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT
                );
                exit;
            }
            $_SESSION['request_management_notice'] = $successMessage;
            header('Location: request-management.php');
            exit;
        } catch (InvalidArgumentException $exception) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            $error = $exception->getMessage();
        } catch (PDOException $exception) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            error_log('Request management database error: ' . $exception->getMessage());
            $error = 'The request could not be updated. Check the database and try again.';
        }
    }
}

if (!$isOverviewAction && $error !== '' && ($nextStatus ?? '') === 'REJECTED'
    && is_int($requestId ?? null) && $requestId > 0) {
    $reopenConfirmation = [
        'action_type' => 'status',
        'request_id' => $requestId,
        'next_status' => 'REJECTED',
        'error' => $error,
        'rejection_reason' => mb_substr($rejectionReason, 0, 500),
    ];
    $error = '';
}

if ($isOverviewAction && $error !== '') {
    http_response_code(422);
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store, private');
    echo json_encode(
        ['ok' => false, 'message' => $error],
        JSON_THROW_ON_ERROR | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT
    );
    exit;
}

$isArchiveView = $view === 'archive';
$isHistoryView = $view === 'history';
$filterStatus = $view === 'active' ? (string) ($_GET['status'] ?? '') : '';
$search = trim((string) ($_GET['q'] ?? ''));
$validFilters = ['', 'SUBMITTED', 'PROCESSING', 'READY', 'COMPLETED', 'REJECTED'];
if (!in_array($filterStatus, $validFilters, true)) {
    $filterStatus = '';
}

$where = [$isArchiveView
    ? 'r.deleted_at IS NOT NULL AND r.deleted_at > DATE_SUB(NOW(), INTERVAL 30 DAY)'
    : 'r.deleted_at IS NULL'];
$params = [];
if ($filterStatus !== '') {
    if ($filterStatus === 'SUBMITTED') {
        $where[] = "r.status IN ('SUBMITTED', 'FOR_REVIEW')";
    } else {
        $where[] = 'r.status = :status';
        $params['status'] = $filterStatus;
    }
}
if ($search !== '') {
    $where[] = '(r.tracking_no LIKE :tracking_search OR u.full_name LIKE :full_name_search
        OR u.student_no LIKE :student_no_search OR g.student_number LIKE :student_number_search
        OR g.first_name LIKE :first_name_search
        OR g.last_name LIKE :last_name_search OR d.name LIKE :document_search)';
    $searchPattern = '%' . $search . '%';
    $params['tracking_search'] = $searchPattern;
    $params['full_name_search'] = $searchPattern;
    $params['student_no_search'] = $searchPattern;
    $params['student_number_search'] = $searchPattern;
    $params['first_name_search'] = $searchPattern;
    $params['last_name_search'] = $searchPattern;
    $params['document_search'] = $searchPattern;
}
$requestSql = 'SELECT r.id, r.tracking_no, r.status, r.created_at, r.expected_release_date,
                      r.purpose, r.registrar_remarks, r.deleted_at,
                      a.deleted_by_name,
                      COALESCE(NULLIF(u.full_name, \'\'),
                          CONCAT_WS(\' \', g.first_name, NULLIF(g.middle_name, \'\'), g.last_name)) AS requester_name,
                      COALESCE(u.student_no, g.student_number, \'—\') AS student_no,
                      COALESCE(NULLIF(u.email, \'\'), g.email) AS requester_email,
                      COALESCE(d.name, r.purpose) AS document_name,
                      COALESCE(d.processing_max_days, 0) AS processing_max_days,
                      g.requester_status, g.first_name, g.middle_name, g.last_name, g.course,
                      g.address_line, g.province, g.city_municipality, g.barangay, g.birthdate,
                      g.gender, g.mobile_number, g.elementary_school, g.elementary_year,
                      g.junior_high_school, g.junior_high_year, g.senior_high_school,
                      g.senior_high_year, g.academic_year, g.request_purpose
               FROM requests r
               LEFT JOIN users u ON u.id = r.student_id
               LEFT JOIN guest_request_details g ON g.request_id = r.id
               LEFT JOIN request_items i ON i.request_id = r.id
               LEFT JOIN documents d ON d.id = i.document_id
               LEFT JOIN request_deletion_audit a ON a.id = r.deletion_audit_id'
    . ($where ? ' WHERE ' . implode(' AND ', $where) : '')
    . ' ORDER BY r.created_at ' . ($isArchiveView ? 'DESC, r.id DESC' : 'ASC, r.id ASC');
$requests = [];
if (!$isHistoryView) {
    $requestQuery = $db->prepare($requestSql);
    $requestQuery->execute($params);
    $requests = $requestQuery->fetchAll();
}

$deletionHistory = [];
if ($isHistoryView) {
    $deletionHistory = $db->query(
        'SELECT id, tracking_no, document_name, final_status, requested_at, deleted_at,
                deleted_by_name, status_history, purged_at, restored_at, restored_by_name
         FROM request_deletion_audit
         ORDER BY deleted_at DESC, id DESC
         LIMIT 200'
    )->fetchAll();
}

$escape = static fn ($value): string => htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
$hasDetail = static fn ($value): bool => $value !== null && trim((string) $value) !== '';
$formatDate = static fn ($value): string => $value ? (new DateTimeImmutable((string) $value))->format('M j, Y') : '—';
$formatDateTime = static fn ($value): string => $value ? (new DateTimeImmutable((string) $value))->format('M j, Y g:i A') : '—';
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#146c43">
    <title>Request Management</title>
    <link rel="stylesheet" href="assets/css/login.css?v=request-password-retry">
</head>
<body>
    <main class="portal-shell">
        <header class="portal-header">
            <div>
                <p class="eyebrow">REGISTRAR PORTAL</p>
                <h1>Request management</h1>
            </div>
        </header>

        <nav class="request-management-navigation" aria-label="Request navigation">
            <a class="request-main-navigation-link" href="request-management.php"
               <?= $view === 'active' ? 'aria-current="page"' : '' ?>>Active requests</a>
            <?php if ($isAdmin): ?>
                <details class="request-records-dropdown" <?= $view !== 'active' ? 'open' : '' ?>>
                    <summary>Archive &amp; history</summary>
                    <div class="request-records-menu">
                        <a href="request-management.php?view=archive"
                           <?= $isArchiveView ? 'aria-current="page"' : '' ?>>Deleted (30 days)</a>
                        <a href="request-management.php?view=history"
                           <?= $isHistoryView ? 'aria-current="page"' : '' ?>>Deletion history</a>
                    </div>
                </details>
            <?php endif; ?>
        </nav>

        <?php if ($notice !== ''): ?>
            <p class="notice notice-success toast-notification" role="status"><?= $escape($notice) ?></p>
        <?php endif; ?>
        <?php if ($error !== ''): ?>
            <p class="notice" role="alert"><?= $escape($error) ?></p>
        <?php endif; ?>

        <section class="portal-panel" aria-labelledby="requests-title">
            <div class="request-list-heading">
                <div>
                    <h2 id="requests-title"><?= $isHistoryView ? 'Deletion history' : ($isArchiveView ? 'Deleted requests' : 'Requests') ?></h2>
                    <?php if ($isArchiveView): ?>
                        <p>Archived requests can be restored for 30 days. After that, the request and its personal details are permanently removed.</p>
                    <?php elseif ($isHistoryView): ?>
                        <p>Compact audit records remain after purge; requester names, contact details, addresses, and request reasons are not retained here.</p>
                    <?php endif; ?>
                </div>
                <?php if (!$isHistoryView): ?>
                    <form class="request-filters" method="get" action="request-management.php">
                        <input type="hidden" name="view" value="<?= $escape($view) ?>">
                        <?php if (!$isArchiveView): ?>
                            <label class="sr-only" for="status-filter">Filter by status</label>
                            <select id="status-filter" name="status">
                                <option value="">All statuses</option>
                                <option value="SUBMITTED" <?= $filterStatus === 'SUBMITTED' ? 'selected' : '' ?>>Pending</option>
                                <option value="PROCESSING" <?= $filterStatus === 'PROCESSING' ? 'selected' : '' ?>>Processing</option>
                                <option value="READY" <?= $filterStatus === 'READY' ? 'selected' : '' ?>>Ready for Pickup</option>
                                <option value="COMPLETED" <?= $filterStatus === 'COMPLETED' ? 'selected' : '' ?>>Released</option>
                                <option value="REJECTED" <?= $filterStatus === 'REJECTED' ? 'selected' : '' ?>>Rejected</option>
                            </select>
                        <?php endif; ?>
                        <label class="sr-only" for="request-search">Search requests</label>
                        <input id="request-search" name="q" type="search" value="<?= $escape($search) ?>"
                               placeholder="Reference, name, or ID" data-live-search="#request-card-grid"
                               data-live-search-empty="request-no-results">
                    </form>
                <?php endif; ?>
            </div>

            <?php if ($isHistoryView): ?>
                <?php if ($deletionHistory === []): ?>
                    <p class="empty-state">No request deletion history yet.</p>
                <?php else: ?>
                    <div class="request-audit-table-wrap">
                        <table class="request-audit-table">
                            <thead>
                                <tr>
                                    <th scope="col">Reference / document</th>
                                    <th scope="col">Final status</th>
                                    <th scope="col">Requested</th>
                                    <th scope="col">Deleted by / on</th>
                                    <th scope="col">Record status</th>
                                    <th scope="col">Status history</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($deletionHistory as $audit): ?>
                                    <?php
                                    $auditEvents = json_decode((string) $audit['status_history'], true);
                                    $recordState = !empty($audit['restored_at'])
                                        ? 'Restored ' . $formatDateTime($audit['restored_at'])
                                        : (!empty($audit['purged_at'])
                                            ? 'Permanently deleted ' . $formatDateTime($audit['purged_at'])
                                            : 'In 30-day archive');
                                    ?>
                                    <tr>
                                        <td>
                                            <strong><?= $escape($audit['tracking_no']) ?></strong>
                                            <span><?= $escape($audit['document_name']) ?></span>
                                        </td>
                                        <td><?= $escape($statusLabels[(string) $audit['final_status']] ?? $audit['final_status']) ?></td>
                                        <td><?= $escape($formatDateTime($audit['requested_at'])) ?></td>
                                        <td><?= $escape($audit['deleted_by_name']) ?><span><?= $escape($formatDateTime($audit['deleted_at'])) ?></span></td>
                                        <td><?= $escape($recordState) ?></td>
                                        <td>
                                            <details>
                                                <summary>View status history</summary>
                                                <?php if (is_array($auditEvents)): ?>
                                                    <?php if ($auditEvents === []): ?>
                                                        <p>No status changes were recorded.</p>
                                                    <?php else: ?>
                                                        <ol>
                                                            <?php foreach ($auditEvents as $event): ?>
                                                                <li>
                                                                    <?= $escape($statusLabels[(string) ($event['old_status'] ?? '')] ?? ($event['old_status'] ?? 'Unknown')) ?>
                                                                    to
                                                                    <?= $escape($statusLabels[(string) ($event['new_status'] ?? '')] ?? ($event['new_status'] ?? 'Unknown')) ?>
                                                                    <span><?= $escape($formatDateTime($event['created_at'] ?? null)) ?> · <?= $escape($event['changed_by_name'] ?? 'Registrar staff') ?></span>
                                                                </li>
                                                            <?php endforeach; ?>
                                                        </ol>
                                                    <?php endif; ?>
                                                <?php else: ?>
                                                    <p>Status history is unavailable because its audit record is invalid.</p>
                                                <?php endif; ?>
                                            </details>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            <?php elseif ($requests === []): ?>
                <p id="request-no-results" class="empty-state">No matching requests.</p>
            <?php else: ?>
                <div id="request-card-grid" class="request-card-grid">
                    <?php foreach ($requests as $request): ?>
                        <?php
                        $status = (string) $request['status'];
                        $systemExpectedDate = in_array($status, ['SUBMITTED', 'FOR_REVIEW'], true)
                            ? estimate_document_release_date((int) $request['processing_max_days'])->format('Y-m-d')
                            : '';
                        $statusClass = match ($status) {
                            'SUBMITTED', 'FOR_REVIEW' => 'status-pending',
                            'PROCESSING' => 'status-processing',
                            'READY' => 'status-ready',
                            'COMPLETED' => 'status-completed',
                            'REJECTED' => 'status-rejected',
                            default => 'status-neutral',
                        };
                        $actions = $isArchiveView ? [] : match ($status) {
                            'SUBMITTED', 'FOR_REVIEW' => [
                                ['status' => 'PROCESSING', 'label' => 'Process', 'class' => 'table-action-button'],
                                ['status' => 'REJECTED', 'label' => 'Reject request', 'class' => 'danger-button'],
                            ],
                            'PROCESSING' => [
                                ['status' => 'READY', 'label' => 'Mark ready for pickup', 'class' => 'table-action-button'],
                                ['status' => 'REJECTED', 'label' => 'Reject request', 'class' => 'danger-button'],
                            ],
                            'READY' => [
                                ['status' => 'COMPLETED', 'label' => 'Release', 'class' => 'table-action-button'],
                            ],
                            default => [],
                        };
                        $dialogId = 'request-details-' . (int) $request['id'];
                        $address = implode(', ', array_filter([
                            $request['address_line'],
                            $request['barangay'],
                            $request['city_municipality'],
                            $request['province'],
                        ], static fn ($value): bool => is_string($value) && trim($value) !== ''));
                        $studentNumber = trim((string) $request['student_no']);
                        $purgeAt = $isArchiveView
                            ? (new DateTimeImmutable((string) $request['deleted_at']))->modify('+30 days')
                            : null;
                        ?>
                        <button class="document-card request-management-card" type="button"
                                data-open-request-dialog="<?= $escape($dialogId) ?>"
                                aria-haspopup="dialog"
                                aria-label="Open request <?= $escape($request['tracking_no']) ?> details and actions"
                                data-live-search-row
                                data-live-search-text="<?= $escape(implode(' ', [
                                    $request['tracking_no'],
                                    $request['requester_name'],
                                    $request['student_no'],
                                    $request['document_name'],
                                    $statusLabels[$status] ?? 'Pending',
                                ])) ?>">
                            <span class="request-card-content">
                                <span class="request-card-column">
                                    <span class="request-card-label">Name</span>
                                    <span class="document-card-name"><?= $escape($request['requester_name'] ?: 'Requester') ?></span>
                                    <span class="request-card-reference"><?= $escape($request['tracking_no']) ?></span>
                                </span>
                                <span class="request-card-column">
                                    <span class="request-card-label">Document</span>
                                    <span class="request-card-document"><?= $escape($request['document_name']) ?></span>
                                </span>
                            </span>
                            <span class="status-pill <?= $statusClass ?>">
                                <?= $escape($statusLabels[$status] ?? 'Pending') ?>
                            </span>
                            <?php if ($isArchiveView): ?>
                                <span class="request-card-label">Purge after <?= $escape($purgeAt->format('M j, Y g:i A')) ?></span>
                            <?php endif; ?>
                        </button>
                        <dialog class="workflow-dialog request-workflow-dialog"
                                id="<?= $escape($dialogId) ?>"
                                aria-labelledby="request-dialog-title-<?= (int) $request['id'] ?>">
                            <div class="workflow-dialog-heading">
                                <div>
                                    <p class="eyebrow">REQUEST DETAILS</p>
                                    <h2 id="request-dialog-title-<?= (int) $request['id'] ?>">
                                        <?= $escape($request['tracking_no']) ?>
                                    </h2>
                                    <p class="workflow-step-label">
                                        <?= $escape($request['requester_name'] ?: 'Requester') ?>
                                    </p>
                                </div>
                                <button class="workflow-dialog-close" type="button" data-close-workflow
                                        aria-label="Close request details">×</button>
                            </div>
                            <dl class="request-detail-list">
                                <?php if ($studentNumber !== '' && $studentNumber !== '—'): ?>
                                    <div><dt>Student ID</dt><dd><?= $escape($studentNumber) ?></dd></div>
                                <?php endif; ?>
                                <div><dt>Document</dt><dd><?= $escape($request['document_name']) ?></dd></div>
                                <div><dt>Current status</dt><dd><?= $escape($statusLabels[$status] ?? 'Pending') ?></dd></div>
                                <div><dt>Requested</dt><dd><?= $escape($formatDate($request['created_at'])) ?></dd></div>
                                <?php if ($hasDetail($request['expected_release_date'])): ?>
                                    <div><dt>Estimated release</dt><dd><?= $escape($formatDate($request['expected_release_date'])) ?></dd></div>
                                <?php endif; ?>
                                <?php if ($isArchiveView): ?>
                                    <div><dt>Deleted by</dt><dd><?= $escape($request['deleted_by_name'] ?: 'Administrator') ?></dd></div>
                                    <div><dt>Archived on</dt><dd><?= $escape($formatDateTime($request['deleted_at'])) ?></dd></div>
                                    <div><dt>Purge after</dt><dd><?= $escape($purgeAt->format('M j, Y g:i A')) ?></dd></div>
                                <?php endif; ?>
                                <?php if (in_array($request['requester_status'], ['alumni', 'currently_enrolled'], true)): ?>
                                    <?php if ($hasDetail($request['course'])): ?>
                                        <div><dt>Course</dt><dd><?= $escape($request['course']) ?></dd></div>
                                    <?php endif; ?>
                                    <?php if ($hasDetail($request['birthdate'])): ?>
                                        <div><dt>Birthdate</dt><dd><?= $escape($formatDate($request['birthdate'])) ?></dd></div>
                                    <?php endif; ?>
                                    <?php if ($hasDetail($request['gender'])): ?>
                                        <div><dt>Gender</dt><dd><?= $escape($request['gender']) ?></dd></div>
                                    <?php endif; ?>
                                    <?php if ($hasDetail($request['mobile_number'])): ?>
                                        <div><dt>Mobile number</dt><dd><?= $escape($request['mobile_number']) ?></dd></div>
                                    <?php endif; ?>
                                    <?php if ($address !== ''): ?>
                                        <div><dt>Address</dt><dd><?= $escape($address) ?></dd></div>
                                    <?php endif; ?>
                                    <?php if ($hasDetail($request['academic_year'])): ?>
                                        <div><dt>College year graduated</dt><dd><?= $escape($request['academic_year']) ?></dd></div>
                                    <?php endif; ?>
                                    <?php if ($hasDetail($request['elementary_school']) || $hasDetail($request['elementary_year'])): ?>
                                        <div><dt>Elementary school</dt><dd><?= $escape($request['elementary_school']) ?><?= $hasDetail($request['elementary_year']) ? ' · ' . $escape($request['elementary_year']) : '' ?></dd></div>
                                    <?php endif; ?>
                                    <?php if ($hasDetail($request['junior_high_school']) || $hasDetail($request['junior_high_year'])): ?>
                                        <div><dt>Junior high school</dt><dd><?= $escape($request['junior_high_school']) ?><?= $hasDetail($request['junior_high_year']) ? ' · ' . $escape($request['junior_high_year']) : '' ?></dd></div>
                                    <?php endif; ?>
                                    <?php if ($hasDetail($request['senior_high_school']) || $hasDetail($request['senior_high_year'])): ?>
                                        <div><dt>Senior high school</dt><dd><?= $escape($request['senior_high_school']) ?><?= $hasDetail($request['senior_high_year']) ? ' · ' . $escape($request['senior_high_year']) : '' ?></dd></div>
                                    <?php endif; ?>
                                    <?php if ($hasDetail($request['request_purpose'])): ?>
                                        <div><dt>Request purpose</dt><dd><?= $escape($request['request_purpose']) ?></dd></div>
                                    <?php endif; ?>
                                <?php endif; ?>
                                <?php if ($hasDetail($request['registrar_remarks'])): ?>
                                    <div class="request-detail-remarks"><dt>Registrar remarks</dt><dd><?= $escape($request['registrar_remarks']) ?></dd></div>
                                <?php endif; ?>
                            </dl>
                            <?php if (!empty($request['expected_release_date'])): ?>
                                <p class="request-date-disclaimer"><?= $escape(request_processing_delay_notice()) ?></p>
                            <?php endif; ?>
                            <?php if ($isArchiveView): ?>
                                <?php if ($isAdmin): ?>
                                    <button class="table-action-button" type="button"
                                            data-open-confirmation data-action-type="restore"
                                            data-request-id="<?= (int) $request['id'] ?>"
                                            data-action-label="Restore request">
                                        Restore request
                                    </button>
                                <?php endif; ?>
                            <?php elseif ($actions === [] && !$isAdmin): ?>
                                <p class="request-terminal-note">This request has no further status actions.</p>
                            <?php else: ?>
                                <div class="request-decision-options" role="group" aria-label="Available request decisions">
                                    <?php foreach ($actions as $action): ?>
                                        <button class="<?= $escape($action['class']) ?>" type="button"
                                                data-open-confirmation data-action-type="status"
                                                data-request-id="<?= (int) $request['id'] ?>"
                                                data-next-status="<?= $escape($action['status']) ?>"
                                                data-action-label="<?= $escape($action['label']) ?>"
                                                <?= $action['status'] === 'PROCESSING' ? 'data-default-release-date="' . $escape($systemExpectedDate) . '"' : '' ?>>
                                            <?= $escape($action['label']) ?>
                                        </button>
                                    <?php endforeach; ?>
                                    <?php if (!$isArchiveView && $isAdmin): ?>
                                        <button class="danger-button request-icon-button" type="button"
                                                data-open-confirmation data-action-type="archive"
                                                data-request-id="<?= (int) $request['id'] ?>"
                                                data-action-label="Delete request"
                                                aria-label="Delete request" title="Delete request">
                                            <svg viewBox="0 0 24 24" aria-hidden="true">
                                                <path d="M4 7h16M10 11v6m4-6v6M6 7l1 14h10l1-14M9 7V4h6v3"></path>
                                            </svg>
                                        </button>
                                    <?php endif; ?>
                                </div>
                            <?php endif; ?>
                            <?php if (!$isArchiveView && $isAdmin): ?>
                                <p class="request-terminal-note">Deleted requests can be restored for 30 days.</p>
                            <?php endif; ?>
                        </dialog>
                    <?php endforeach; ?>
                </div>
                <p id="request-no-results" class="empty-state live-search-empty" hidden>No matching requests.</p>
            <?php endif; ?>
        </section>
        <dialog class="workflow-dialog request-confirmation-dialog" id="request-confirmation-dialog"
                aria-labelledby="request-confirmation-title" aria-describedby="request-confirmation-message"
                <?= $reopenConfirmation !== null
                    ? 'data-reopen-confirmation="' . $escape((string) json_encode(
                        $reopenConfirmation,
                        JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP | JSON_INVALID_UTF8_SUBSTITUTE
                    )) . '"'
                    : '' ?>>
            <form class="request-update-form" id="request-confirmation-form" method="post"
                  action="request-management.php" data-loading-form>
                <input type="hidden" name="csrf_token" value="<?= $escape(csrf_token()) ?>">
                <input type="hidden" name="request_action" value="" data-confirm-request-action>
                <input type="hidden" name="request_id" value="" data-confirm-request-id>
                <input type="hidden" name="next_status" value="" data-confirm-next-status>
                <p class="eyebrow">CONFIRM ACTION</p>
                <h2 id="request-confirmation-title" data-confirm-title>Confirm action</h2>
                <p id="request-confirmation-message" class="request-terminal-note" data-confirm-message></p>
                <label data-confirm-release-date-field hidden for="confirmation-release-date">
                    Expected release date
                    <input id="confirmation-release-date" name="expected_release_date" type="date"
                           data-confirm-release-date>
                </label>
                <label data-confirm-rejection-field hidden for="confirmation-rejection-reason">
                    Rejection reason
                    <textarea id="confirmation-rejection-reason" name="rejection_reason"
                              maxlength="500" rows="3"
                              placeholder="Enter the reason for rejecting this request"
                              data-confirm-rejection-reason></textarea>
                </label>
                <label data-confirm-password-field hidden for="confirmation-current-password">
                    Current password
                    <input id="confirmation-current-password" name="current_password" type="password"
                           autocomplete="current-password" data-confirm-password>
                </label>
                <div class="workflow-actions">
                    <button class="secondary-button" type="button" data-close-confirmation>Cancel</button>
                    <button class="table-action-button" type="submit" data-confirm-submit
                            data-loading-button data-loading-text="Saving…">
                        Confirm
                    </button>
                </div>
            </form>
        </dialog>
        <footer class="page-footer">Request updates are recorded in the request history.</footer>
    </main>
    <script>
        const statusFilter = document.getElementById('status-filter');
        statusFilter?.addEventListener('change', (event) => {
            event.currentTarget.form.requestSubmit();
        });

        document.querySelectorAll('[data-open-request-dialog]').forEach((button) => {
            button.addEventListener('click', () => {
                document.getElementById(button.dataset.openRequestDialog)?.showModal();
            });
        });

        document.querySelectorAll('.request-workflow-dialog').forEach((dialog) => {
            dialog.querySelectorAll('[data-close-workflow]').forEach((button) => {
                button.addEventListener('click', () => dialog.close());
            });

            dialog.addEventListener('click', (event) => {
                if (event.target === dialog) {
                    dialog.close();
                }
            });
        });

        const confirmationDialog = document.getElementById('request-confirmation-dialog');
        const confirmationForm = document.getElementById('request-confirmation-form');
        const confirmationTitle = confirmationDialog.querySelector('[data-confirm-title]');
        const confirmationMessage = confirmationDialog.querySelector('[data-confirm-message]');
        const requestActionInput = confirmationForm.querySelector('[data-confirm-request-action]');
        const requestIdInput = confirmationForm.querySelector('[data-confirm-request-id]');
        const nextStatusInput = confirmationForm.querySelector('[data-confirm-next-status]');
        const releaseDateField = confirmationForm.querySelector('[data-confirm-release-date-field]');
        const releaseDateInput = confirmationForm.querySelector('[data-confirm-release-date]');
        const rejectionField = confirmationForm.querySelector('[data-confirm-rejection-field]');
        const rejectionInput = confirmationForm.querySelector('[data-confirm-rejection-reason]');
        const passwordField = confirmationForm.querySelector('[data-confirm-password-field]');
        const passwordInput = confirmationForm.querySelector('[data-confirm-password]');
        const confirmSubmit = confirmationForm.querySelector('[data-confirm-submit]');
        let returnToRequestDialog = null;
        let submittingConfirmation = false;

        function resetConfirmationFields() {
            confirmationMessage.classList.remove('request-password-error');
            releaseDateField.hidden = true;
            releaseDateInput.required = false;
            releaseDateInput.value = '';
            rejectionField.hidden = true;
            rejectionInput.required = false;
            rejectionInput.value = '';
            passwordField.hidden = true;
            passwordInput.required = false;
            confirmSubmit.classList.remove('danger-button');
            confirmSubmit.classList.add('table-action-button');
            confirmSubmit.textContent = 'Confirm';
        }

        document.querySelectorAll('[data-open-confirmation]').forEach((button) => {
            button.addEventListener('click', () => {
                const actionType = button.dataset.actionType;
                const actionLabel = button.dataset.actionLabel;
                const status = button.dataset.nextStatus || '';

                confirmationForm.reset();
                resetConfirmationFields();
                returnToRequestDialog = button.closest('.request-workflow-dialog');
                if (returnToRequestDialog?.open) {
                    returnToRequestDialog.close();
                }

                requestIdInput.value = button.dataset.requestId;
                requestActionInput.value = actionType === 'status' ? '' : `${actionType}_request`;
                nextStatusInput.value = status;
                submittingConfirmation = false;

                if (actionType === 'archive') {
                    confirmationTitle.textContent = 'Delete request?';
                    confirmationMessage.textContent =
                        'Are you sure you want to delete this request? It can be restored for 30 days.';
                    passwordField.hidden = false;
                    passwordInput.required = true;
                    confirmSubmit.textContent = 'Delete request';
                    confirmSubmit.classList.remove('table-action-button');
                    confirmSubmit.classList.add('danger-button');
                } else if (actionType === 'restore') {
                    confirmationTitle.textContent = 'Restore request?';
                    confirmationMessage.textContent = 'Are you sure you want to restore this request to the active list?';
                    confirmSubmit.textContent = 'Restore request';
                } else {
                    confirmationTitle.textContent = `${actionLabel}?`;
                    confirmationMessage.textContent =
                        `Are you sure you want to ${actionLabel.charAt(0).toLowerCase()}${actionLabel.slice(1)}?`;
                    confirmSubmit.textContent = 'Confirm';
                    const needsRejectionReason = status === 'REJECTED';
                    const needsReleaseDate = status === 'PROCESSING';
                    releaseDateField.hidden = !needsReleaseDate;
                    releaseDateInput.required = needsReleaseDate;
                    if (needsReleaseDate) {
                        releaseDateInput.value = button.dataset.defaultReleaseDate || '';
                    }
                    rejectionField.hidden = !needsRejectionReason;
                    rejectionInput.required = needsRejectionReason;
                    if (needsRejectionReason) {
                        confirmationTitle.textContent = 'Reject request?';
                        confirmationMessage.textContent =
                            'Are you sure you want to reject this request? The requester will be notified with the reason.';
                        confirmSubmit.textContent = 'Reject request';
                        confirmSubmit.classList.remove('table-action-button');
                        confirmSubmit.classList.add('danger-button');
                    }
                    if (status === 'COMPLETED') {
                        confirmationTitle.textContent = 'Release request?';
                        confirmationMessage.textContent = 'Release this document to the requester?';
                        confirmSubmit.textContent = 'Release';
                    }
                }

                confirmationDialog.showModal();
                if (passwordInput.required) {
                    passwordInput.focus();
                }
            });
        });

        confirmationDialog.querySelectorAll('[data-close-confirmation]').forEach((button) => {
            button.addEventListener('click', () => confirmationDialog.close());
        });
        confirmationDialog.addEventListener('click', (event) => {
            if (event.target === confirmationDialog) {
                confirmationDialog.close();
            }
        });
        confirmationForm.addEventListener('submit', () => {
            submittingConfirmation = true;
        });
        confirmationDialog.addEventListener('close', () => {
            confirmationForm.reset();
            resetConfirmationFields();
            if (!submittingConfirmation && returnToRequestDialog?.isConnected) {
                returnToRequestDialog.showModal();
            }
            returnToRequestDialog = null;
        });

        if (confirmationDialog.dataset.reopenConfirmation) {
            const failedConfirmation = JSON.parse(confirmationDialog.dataset.reopenConfirmation);
            const isArchive = failedConfirmation.action_type === 'archive';
            const isRejection = failedConfirmation.next_status === 'REJECTED';
            returnToRequestDialog = document.getElementById(
                `request-details-${failedConfirmation.request_id}`
            );
            requestIdInput.value = failedConfirmation.request_id;
            requestActionInput.value = isArchive ? 'archive_request' : '';
            nextStatusInput.value = failedConfirmation.next_status || '';
            confirmationTitle.textContent = isArchive
                ? 'Delete request?'
                : (isRejection ? 'Reject request?' : 'Release request?');
            confirmationMessage.textContent = failedConfirmation.error;
            confirmationMessage.classList.add('request-password-error');
            rejectionField.hidden = !isRejection;
            rejectionInput.required = isRejection;
            rejectionInput.value = failedConfirmation.rejection_reason || '';
            passwordField.hidden = !isArchive;
            passwordInput.required = isArchive;
            confirmSubmit.textContent = isArchive
                ? 'Delete request'
                : (isRejection ? 'Reject request' : 'Release');
            if (isArchive || isRejection) {
                confirmSubmit.classList.remove('table-action-button');
                confirmSubmit.classList.add('danger-button');
            }
            confirmationDialog.showModal();
            if (isArchive) {
                passwordInput.focus();
            }
        }
    </script>
    <script src="assets/js/live-search.js"></script>
    <script src="assets/js/form-loading.js" defer></script>
    <script src="assets/js/toast-notifications.js" defer></script>
    <script src="assets/js/navigation-guard.js" defer></script>
</body>
</html>
