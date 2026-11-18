<?php
declare(strict_types=1);

require __DIR__ . '/config.php';
require_once __DIR__ . '/audit_helpers.php';
start_app_session();
require_personnel(['admin']);

$search = trim(is_string($_GET['q'] ?? null) ? $_GET['q'] : '');
$search = mb_substr($search, 0, 100);
$statement = db()->prepare(
    'SELECT id, actor_name, action, entity_type, entity_id, summary, created_at
     FROM audit_events
     ORDER BY created_at DESC, id DESC
     LIMIT 250'
);
$statement->execute();
$events = $statement->fetchAll();
$escape = static fn ($value): string => htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#146c43">
    <title>Audit log</title>
    <link rel="stylesheet" href="assets/css/login.css">
</head>
<body>
    <main class="portal-shell">
        <header class="portal-header">
            <div>
                <p class="eyebrow">ADMINISTRATION</p>
                <h1>Audit log</h1>
                <p class="intro">Student profile, enrollment, roster, request-status, and registrar-account changes.</p>
            </div>
        </header>
        <section class="portal-panel">
            <form class="student-list-controls" method="get" action="audit-log.php">
                <label class="field" for="audit-search">
                    <span>Search activity</span>
                    <input id="audit-search" name="q" type="search" maxlength="100"
                           value="<?= $escape($search) ?>" placeholder="Actor, action, record, or summary"
                           data-live-search="#audit-events-table" data-live-search-empty="audit-no-results">
                </label>
                <?php if ($search !== ''): ?><a class="student-filter-clear" href="audit-log.php">Clear search</a><?php endif; ?>
            </form>
            <p class="panel-intro">Showing up to 250 newest matching events.</p>
            <p id="audit-no-results" class="empty-state" hidden>No audit events match this search.</p>
            <?php if ($events !== []): ?>
                <div class="table-wrap" id="audit-events-table">
                    <table class="data-table">
                        <thead>
                            <tr><th scope="col">Date and time</th><th scope="col">Actor</th>
                                <th scope="col">Action</th><th scope="col">Record</th><th scope="col">Summary</th></tr>
                        </thead>
                        <tbody>
                        <?php foreach ($events as $event): ?>
                            <tr data-live-search-row data-live-search-text="<?= $escape(implode(' ', [
                                (new DateTimeImmutable((string) $event['created_at']))->format('M j, Y g:i A'),
                                $event['actor_name'],
                                ucfirst((string) $event['action']),
                                $event['entity_type'],
                                $event['entity_id'] !== null ? '#' . $event['entity_id'] : '',
                                $event['summary'],
                            ])) ?>">
                                <td><?= $escape((new DateTimeImmutable((string) $event['created_at']))->format('M j, Y g:i A')) ?></td>
                                <td><?= $escape($event['actor_name']) ?></td>
                                <td><?= $escape(ucfirst((string) $event['action'])) ?></td>
                                <td><?= $escape($event['entity_type']) ?><?= $event['entity_id'] !== null ? ' #' . $escape($event['entity_id']) : '' ?></td>
                                <td><?= $escape($event['summary']) ?></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </section>
    </main>
        <script src="assets/js/live-search.js"></script>
    <script src="assets/js/form-loading.js" defer></script>
</body>
</html>
