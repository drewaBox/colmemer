<?php
declare(strict_types=1);

require __DIR__ . '/config.php';
start_app_session();
require_personnel(['admin']);

$period = is_string($_GET['period'] ?? null) ? $_GET['period'] : 'monthly';
if (!in_array($period, ['daily', 'monthly', 'yearly'], true)) {
    $period = 'monthly';
}
$requestedDate = is_string($_GET['date'] ?? null) ? $_GET['date'] : '';
$now = new DateTimeImmutable('today');
$dateFormat = match ($period) {
    'daily' => 'Y-m-d',
    'monthly' => 'Y-m',
    'yearly' => 'Y',
};
$defaultDate = match ($period) {
    'daily' => $now->format('Y-m-d'),
    'monthly' => $now->format('Y-m'),
    'yearly' => $now->format('Y'),
};
$dateValue = $requestedDate !== '' ? $requestedDate : $defaultDate;
$dateObject = DateTimeImmutable::createFromFormat('!' . $dateFormat, $dateValue);
$dateErrors = DateTimeImmutable::getLastErrors();
if ($dateObject === false
    || ($dateErrors !== false && ($dateErrors['warning_count'] > 0 || $dateErrors['error_count'] > 0))
    || $dateObject->format($dateFormat) !== $dateValue) {
    $dateValue = $defaultDate;
    $dateObject = DateTimeImmutable::createFromFormat('!' . $dateFormat, $dateValue);
}
$start = $dateObject;
$end = match ($period) {
    'daily' => $start->modify('+1 day'),
    'monthly' => $start->modify('+1 month'),
    'yearly' => $start->modify('+1 year'),
};
$startSql = $start->format('Y-m-d 00:00:00');
$endSql = $end->format('Y-m-d 00:00:00');
$periodKeyExpression = match ($period) {
    'daily' => 'HOUR(r.created_at)',
    'monthly' => 'DATE(r.created_at)',
    'yearly' => 'MONTH(r.created_at)',
};

$db = db();
$totalsStatement = $db->prepare(
    'SELECT COUNT(*) AS total,
            COALESCE(SUM(status IN (\'SUBMITTED\', \'AI_VERIFYING\', \'FOR_REVIEW\', \'NEEDS_CORRECTION\')), 0) AS pending,
            COALESCE(SUM(status = \'PROCESSING\'), 0) AS processing,
            COALESCE(SUM(status = \'READY\'), 0) AS ready,
            COALESCE(SUM(status = \'COMPLETED\'), 0) AS released,
            COALESCE(SUM(status = \'REJECTED\'), 0) AS rejected,
            COALESCE(SUM(status = \'CANCELLED\'), 0) AS cancelled
     FROM requests
     WHERE deleted_at IS NULL AND created_at >= :start_at AND created_at < :end_at'
);
$totalsStatement->execute(['start_at' => $startSql, 'end_at' => $endSql]);
$totals = $totalsStatement->fetch();

$timelineStatement = $db->prepare(
    "SELECT {$periodKeyExpression} AS period_key, COUNT(*) AS total
     FROM requests r
     WHERE r.deleted_at IS NULL AND r.created_at >= :start_at AND r.created_at < :end_at
     GROUP BY {$periodKeyExpression}
     ORDER BY {$periodKeyExpression}"
);
$timelineStatement->execute(['start_at' => $startSql, 'end_at' => $endSql]);
$timeline = $timelineStatement->fetchAll();
foreach ($timeline as &$timelineRow) {
    $timelineRow['period_label'] = match ($period) {
        'daily' => sprintf('%02d:00', (int) $timelineRow['period_key']),
        'monthly' => (new DateTimeImmutable((string) $timelineRow['period_key']))->format('M j'),
        'yearly' => DateTimeImmutable::createFromFormat('!m', (string) $timelineRow['period_key'])->format('M'),
    };
}
unset($timelineRow);

$documentStatement = $db->prepare(
    'SELECT COALESCE(NULLIF(d.name, \'\'), NULLIF(r.purpose, \'\'), \'Other\') AS document_name,
            COUNT(DISTINCT r.id) AS request_count
     FROM requests r
     LEFT JOIN request_items i ON i.request_id = r.id
     LEFT JOIN documents d ON d.id = i.document_id
     WHERE r.deleted_at IS NULL AND r.created_at >= :start_at AND r.created_at < :end_at
     GROUP BY document_name
     ORDER BY request_count DESC, document_name
     LIMIT 20'
);
$documentStatement->execute(['start_at' => $startSql, 'end_at' => $endSql]);
$documents = $documentStatement->fetchAll();

$enrollmentSummary = [];
$activeTerm = $db->query(
    'SELECT t.id, ay.start_year, ay.end_year, t.semester
     FROM academic_terms t
     JOIN academic_years ay ON ay.id = t.academic_year_id
     WHERE t.is_current = 1
     ORDER BY t.id DESC
     LIMIT 1'
)->fetch();
if ($activeTerm) {
    $enrollmentStatement = $db->prepare(
        'SELECT c.name AS course_name, m.year_level, COUNT(DISTINCT m.student_id) AS student_count
         FROM student_roster_memberships m
         JOIN student_import_batches b ON b.id = m.import_batch_id AND b.is_current = 1
         JOIN student_enrollments e ON e.id = m.enrollment_id
         JOIN courses c ON c.id = m.course_id
         WHERE m.academic_term_id = :term_id AND m.is_active = 1
         GROUP BY c.name, m.year_level
         ORDER BY c.name, m.year_level'
    );
    $enrollmentStatement->execute(['term_id' => $activeTerm['id']]);
    $enrollmentSummary = $enrollmentStatement->fetchAll();
}

$periodLabel = match ($period) {
    'daily' => $start->format('F j, Y'),
    'monthly' => $start->format('F Y'),
    'yearly' => $start->format('Y'),
};
$escape = static fn ($value): string => htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
$statusTotals = [
    'Pending' => (int) $totals['pending'],
    'Processing' => (int) $totals['processing'],
    'Ready for pickup' => (int) $totals['ready'],
    'Released' => (int) $totals['released'],
    'Rejected' => (int) $totals['rejected'],
    'Cancelled' => (int) $totals['cancelled'],
];
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#146c43">
    <title>Registrar reports</title>
    <link rel="stylesheet" href="assets/css/login.css">
</head>
<body>
    <main class="portal-shell">
        <header class="portal-header report-controls">
            <div>
                <p class="eyebrow">ADMINISTRATION</p>
                <h1>Reports</h1>
                <p class="intro">Printable request activity reports and active-period enrollment summaries.</p>
            </div>
            <button class="secondary-button no-print" type="button" onclick="window.print()">Print report</button>
        </header>
        <section class="portal-panel no-print" aria-label="Report options">
            <form class="student-list-controls" method="get" action="reports.php">
                <label class="field" for="report-period">
                    <span>Report period</span>
                    <select id="report-period" name="period">
                        <option value="daily" <?= $period === 'daily' ? 'selected' : '' ?>>Daily</option>
                        <option value="monthly" <?= $period === 'monthly' ? 'selected' : '' ?>>Monthly</option>
                        <option value="yearly" <?= $period === 'yearly' ? 'selected' : '' ?>>Yearly</option>
                    </select>
                </label>
                <label class="field" for="report-date">
                    <span><?= $period === 'daily' ? 'Date' : ($period === 'monthly' ? 'Month' : 'Year') ?></span>
                    <input id="report-date" name="date"
                           type="<?= $period === 'daily' ? 'date' : ($period === 'monthly' ? 'month' : 'number') ?>"
                           value="<?= $escape($dateValue) ?>" <?= $period === 'yearly' ? 'min="2000" max="2100"' : '' ?> required>
                </label>
                <button class="secondary-button" type="submit">Generate report</button>
            </form>
        </section>
        <section class="report-print-area" aria-labelledby="report-title">
            <div class="report-heading">
                <p class="eyebrow">REGISTRAR PORTAL</p>
                <h2 id="report-title"><?= $escape(ucfirst($period)) ?> request report — <?= $escape($periodLabel) ?></h2>
                <p class="panel-intro">Generated <?= $escape((new DateTimeImmutable())->format('M j, Y g:i A')) ?></p>
            </div>
            <section class="summary-grid transaction-summary" aria-label="Request totals">
                <article class="summary-card"><p>Total requests</p><strong><?= (int) $totals['total'] ?></strong></article>
                <?php foreach ($statusTotals as $label => $count): ?>
                    <article class="summary-card"><p><?= $escape($label) ?></p><strong><?= $count ?></strong></article>
                <?php endforeach; ?>
            </section>
            <section class="portal-panel">
                <h2><?= $period === 'daily' ? 'Requests by hour' : ($period === 'monthly' ? 'Requests by day' : 'Requests by month') ?></h2>
                <?php if ($timeline === []): ?>
                    <p class="empty-state">No requests were submitted during this period.</p>
                <?php else: ?>
                    <div class="table-wrap"><table class="data-table">
                        <thead><tr><th scope="col"><?= $period === 'daily' ? 'Hour' : 'Date' ?></th><th scope="col">Requests</th></tr></thead>
                        <tbody><?php foreach ($timeline as $row): ?>
                            <tr><td><?= $escape($row['period_label']) ?></td><td><?= (int) $row['total'] ?></td></tr>
                        <?php endforeach; ?></tbody>
                    </table></div>
                <?php endif; ?>
            </section>
            <section class="portal-panel">
                <h2>Requests by document</h2>
                <?php if ($documents === []): ?>
                    <p class="empty-state">No document requests were recorded during this period.</p>
                <?php else: ?>
                    <div class="table-wrap"><table class="data-table">
                        <thead><tr><th scope="col">Document or purpose</th><th scope="col">Requests</th></tr></thead>
                        <tbody><?php foreach ($documents as $row): ?>
                            <tr><td><?= $escape($row['document_name']) ?></td><td><?= (int) $row['request_count'] ?></td></tr>
                        <?php endforeach; ?></tbody>
                    </table></div>
                <?php endif; ?>
            </section>
            <section class="portal-panel">
                <h2>Active-period enrollment by course and year</h2>
                <?php if (!$activeTerm): ?>
                    <p class="empty-state">No academic period is currently active.</p>
                <?php else: ?>
                    <p class="panel-intro">
                        <?= $escape($activeTerm['start_year'] . '-' . $activeTerm['end_year'] . ' · '
                            . ((int) $activeTerm['semester'] === 1 ? '1st' : '2nd') . ' semester') ?>
                    </p>
                    <?php if ($enrollmentSummary === []): ?>
                        <p class="empty-state">No active enrollments are recorded for this period.</p>
                    <?php else: ?>
                        <div class="table-wrap"><table class="data-table">
                            <thead><tr><th scope="col">Course</th><th scope="col">Year level</th><th scope="col">Students</th></tr></thead>
                            <tbody><?php foreach ($enrollmentSummary as $row): ?>
                                <tr><td><?= $escape($row['course_name']) ?></td><td><?= (int) $row['year_level'] ?></td><td><?= (int) $row['student_count'] ?></td></tr>
                            <?php endforeach; ?></tbody>
                        </table></div>
                    <?php endif; ?>
                <?php endif; ?>
            </section>
        </section>
    </main>
    <script src="assets/js/form-loading.js" defer></script>
</body>
</html>
