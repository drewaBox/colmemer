<?php
declare(strict_types=1);

require __DIR__ . '/request_helpers.php';
start_app_session();
require_personnel();

$personnelName = (string) ($_SESSION['personnel_name'] ?? $_SESSION['personnel_username']);
$authSuccess = is_string($_SESSION['auth_success'] ?? null) ? $_SESSION['auth_success'] : '';
unset($_SESSION['auth_success']);
$name = htmlspecialchars($personnelName, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
$avatarLetter = htmlspecialchars(mb_strtoupper(mb_substr($personnelName, 0, 1)), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
$role = $_SESSION['personnel_role'] === 'admin' ? 'Administrator' : 'Staff';
$views = [
    'requests' => ['label' => 'Requests', 'path' => 'request-management.php'],
    'students' => ['label' => 'Students', 'path' => 'students.php'],
];
if ($_SESSION['personnel_role'] === 'admin') {
    $views['reports'] = ['label' => 'Reports', 'path' => 'reports.php'];
    $views['audit'] = ['label' => 'Audit log', 'path' => 'audit-log.php'];
    $views['staff'] = ['label' => 'Staff', 'path' => 'personnel.php'];
    $views['email'] = ['label' => 'Notifications & email', 'path' => 'email-templates.php'];
    $views['documents'] = ['label' => 'Documents', 'path' => 'document-management.php'];
    $views['courses'] = ['label' => 'Courses', 'path' => 'course-management.php'];
}
$requestedView = is_string($_GET['view'] ?? null) ? $_GET['view'] : 'overview';
$currentView = $requestedView === 'overview' || isset($views[$requestedView]) ? $requestedView : 'overview';
$transactionCounts = [];
$overviewRequests = [];
$overviewError = '';
$transactionStatusLabels = [
    'SUBMITTED' => 'Pending',
    'FOR_REVIEW' => 'Pending',
    'AI_VERIFYING' => 'Verifying',
    'NEEDS_CORRECTION' => 'Needs correction',
    'PROCESSING' => 'Processing',
    'READY' => 'Ready for pickup',
    'COMPLETED' => 'Released',
    'REJECTED' => 'Rejected',
    'CANCELLED' => 'Cancelled',
];
$overviewActionsForStatus = static fn (string $status): array => match ($status) {
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
$overviewStatusClass = static fn (string $status): string => match ($status) {
    'SUBMITTED', 'FOR_REVIEW' => 'status-pending',
    'PROCESSING' => 'status-processing',
    'READY' => 'status-ready',
    default => 'status-neutral',
};

if ($currentView === 'overview') {
    try {
        $db = db();
        $transactionCounts = $db->query(
            "SELECT COUNT(*) AS total,
                    COALESCE(SUM(status IN ('SUBMITTED', 'FOR_REVIEW')), 0) AS pending,
                    COALESCE(SUM(status = 'PROCESSING'), 0) AS processing,
                    COALESCE(SUM(status = 'READY'), 0) AS ready,
                    COALESCE(SUM(status = 'COMPLETED'), 0) AS released,
                    COALESCE(SUM(status = 'REJECTED'), 0) AS rejected
             FROM requests
             WHERE deleted_at IS NULL"
        )->fetch();
        $overviewRequests = $db->query(
            "SELECT r.id, r.tracking_no, r.created_at, r.expected_release_date, r.status,
                    COALESCE(MAX(d.processing_max_days), 0) AS processing_max_days,
                    COALESCE(NULLIF(u.full_name, ''), NULLIF(TRIM(CONCAT_WS(' ', g.first_name, g.last_name)), ''), 'Requester') AS requester_name,
                    COALESCE(GROUP_CONCAT(DISTINCT NULLIF(d.name, '') ORDER BY d.name SEPARATOR ', '),
                             NULLIF(r.purpose, ''), 'Document request') AS document_name
             FROM requests r
             LEFT JOIN users u ON u.id = r.student_id
             LEFT JOIN guest_request_details g ON g.request_id = r.id
             LEFT JOIN request_items i ON i.request_id = r.id
             LEFT JOIN documents d ON d.id = i.document_id
             WHERE r.deleted_at IS NULL AND r.status NOT IN ('COMPLETED', 'REJECTED')
             GROUP BY r.id, r.tracking_no, r.created_at, r.status,
                      r.expected_release_date, u.full_name, g.first_name, g.last_name, r.purpose
             ORDER BY CASE
                          WHEN r.expected_release_date < CURRENT_DATE THEN 0
                          WHEN r.expected_release_date IS NOT NULL THEN 1
                          ELSE 2
                      END,
                      r.expected_release_date ASC,
                      r.created_at ASC,
                      r.id ASC"
        )->fetchAll();
    } catch (PDOException $exception) {
        error_log('Registrar transaction overview database error: ' . $exception->getMessage());
        $overviewError = 'Transaction overview is unavailable. Please try again later.';
    }
}

if (($_GET['overview_data'] ?? '') === '1') {
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store, private');
    if ($currentView !== 'overview') {
        http_response_code(400);
        echo json_encode(['error' => 'Overview data was requested for an invalid view.']);
        exit;
    }
    if ($overviewError !== '') {
        http_response_code(503);
        echo json_encode(['error' => $overviewError]);
        exit;
    }

    $overviewRequestData = array_map(
        static fn (array $request): array => [
            'request_id' => (int) $request['id'],
            'tracking_no' => (string) $request['tracking_no'],
            'requester_name' => (string) $request['requester_name'],
            'document_name' => (string) $request['document_name'],
            'created_date' => (new DateTimeImmutable((string) $request['created_at']))->format('M j, Y'),
            'expected_release_date' => $request['expected_release_date'] !== null
                ? (string) $request['expected_release_date']
                : '',
            'expected_release_label' => $request['expected_release_date'] !== null
                ? (new DateTimeImmutable((string) $request['expected_release_date']))->format('M j, Y')
                : '—',
            'default_release_date' => in_array((string) $request['status'], ['SUBMITTED', 'FOR_REVIEW'], true)
                ? estimate_document_release_date((int) $request['processing_max_days'])->format('Y-m-d')
                : (string) ($request['expected_release_date'] ?? ''),
            'status_code' => (string) $request['status'],
            'status' => $transactionStatusLabels[(string) $request['status']]
                ?? (string) $request['status'],
            'status_class' => $overviewStatusClass((string) $request['status']),
            'actions' => $overviewActionsForStatus((string) $request['status']),
        ],
        $overviewRequests
    );
    echo json_encode([
        'counts' => array_map(static fn ($count): int => (int) $count, $transactionCounts),
        'requests' => $overviewRequestData,
    ], JSON_THROW_ON_ERROR | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT);
    exit;
}
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#146c43">
    <title>Registrar Portal</title>
    <link rel="stylesheet" href="assets/css/login.css">
</head>
<body class="admin-page">
    <?php if ($authSuccess !== ''): ?>
        <div class="notice notice-success toast-notification" role="status"><?= htmlspecialchars($authSuccess, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></div>
    <?php endif; ?>
    <main class="admin-workspace">
        <div class="admin-sidebar-backdrop" data-sidebar-close></div>
        <aside class="admin-sidebar" id="admin-sidebar" aria-label="Registrar navigation">
            <a class="admin-brand" href="admin.php?view=overview" data-admin-view="overview" aria-label="Registrar workspace home">
                <span class="admin-brand-mark" aria-hidden="true">R</span>
                <span><strong>Registrar</strong><small>Workspace</small></span>
            </a>
            <p class="admin-nav-label">WORKSPACE</p>
            <nav class="admin-navigation" aria-label="Admin pages">
                <a class="admin-nav-link <?= $currentView === 'overview' ? 'is-current' : '' ?>"
                   href="admin.php?view=overview" data-admin-view="overview"
                   <?= $currentView === 'overview' ? 'aria-current="page"' : '' ?>>
                    <span class="admin-nav-icon" aria-hidden="true"><svg viewBox="0 0 24 24"><path d="m3.5 10 8.5-7 8.5 7v10.5h-6v-7h-5v7h-6V10Z"/></svg></span>
                    <span>Overview</span>
                </a>
                <?php foreach ($views as $viewKey => $view): ?>
                    <?php
                    $viewIcons = [
                        'requests' => '<path d="M5 4.5h14v15H5z"/><path d="M8 8h8M8 12h5m-5 4h3"/>',
                        'students' => '<circle cx="9" cy="8" r="3"/><path d="M3.5 19v-1.5a5.5 5.5 0 0 1 11 0V19zM16 5.5a3 3 0 0 1 0 5.8m2 3a4.5 4.5 0 0 1 2.5 4.1V19"/>',
                        'email' => '<rect x="3" y="5" width="18" height="14" rx="2"/><path d="m4 7 8 6 8-6"/>',
                        'documents' => '<path d="M7 3.5h7l4 4v13H7z"/><path d="M14 3.5v4h4M10 12h5m-5 3.5h5"/>',
                        'courses' => '<path d="m3 8 9-4.5L21 8l-9 4.5L3 8Z"/><path d="M6.5 10v5.5c3.4 2.5 7.6 2.5 11 0V10M21 8v7"/>',
                        'reports' => '<path d="M4 19V5m0 14h16M8 15l3-4 3 2 5-6"/>',
                        'audit' => '<circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/>',
                        'staff' => '<circle cx="9" cy="8" r="3"/><path d="M3.5 19v-1.5a5.5 5.5 0 0 1 11 0V19zM17 8h4m-2-2v4"/>',
                    ];
                    ?>
                    <?php if ($viewKey === 'reports'): ?>
                        <p class="admin-nav-label">ADMINISTRATION</p>
                    <?php endif; ?>
                    <a class="admin-nav-link <?= $currentView === $viewKey ? 'is-current' : '' ?>"
                       href="admin.php?view=<?= rawurlencode($viewKey) ?>" data-admin-view="<?= htmlspecialchars($viewKey, ENT_QUOTES, 'UTF-8') ?>"
                       <?= $currentView === $viewKey ? 'aria-current="page"' : '' ?>>
                        <span class="admin-nav-icon" aria-hidden="true"><svg viewBox="0 0 24 24"><?= $viewIcons[$viewKey] ?></svg></span>
                        <span><?= htmlspecialchars($view['label'], ENT_QUOTES, 'UTF-8') ?></span>
                    </a>
                <?php endforeach; ?>
            </nav>
            <div class="admin-sidebar-footer">
                <span class="admin-session-indicator" aria-hidden="true"></span>
                <span>Protected session</span>
                <form action="logout.php" method="post" data-confirm-signout>
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8') ?>">
                    <button class="admin-signout" type="submit">Sign out</button>
                </form>
            </div>
        </aside>
        <section class="admin-main">
            <header class="admin-topbar">
                <div class="admin-topbar-leading">
                    <button class="admin-menu-toggle" type="button" aria-controls="admin-sidebar" aria-expanded="false"
                            aria-label="Open navigation menu" data-sidebar-toggle>
                        <span></span><span></span><span></span>
                    </button>
                    <p class="eyebrow">REGISTRAR PORTAL · <?= htmlspecialchars(strtoupper($role), ENT_QUOTES, 'UTF-8') ?></p>
                </div>
                <div class="admin-user-chip">
                    <span class="admin-user-avatar" aria-hidden="true"><?= $avatarLetter ?></span>
                    <span><?= $name ?><?php if (!empty($_SESSION['personnel_email'])): ?><small class="admin-user-email"><?= htmlspecialchars((string) $_SESSION['personnel_email'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></small><?php endif; ?></span>
                    <a class="admin-change-password" href="change-password.php">Change password</a>
                </div>
            </header>
            <div class="admin-view-host">
                <section class="admin-overview" data-admin-overview <?= $currentView === 'overview' ? '' : 'hidden' ?> aria-labelledby="page-title">
                    <div class="admin-overview-heading">
                        <h1 id="page-title">Request overview</h1>
                    </div>
                    <?php if ($overviewError !== ''): ?>
                        <p class="notice" role="alert" data-overview-server-error><?= htmlspecialchars($overviewError, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></p>
                    <?php endif; ?>
                    <p class="notice" role="alert" data-overview-refresh-error hidden></p>
                    <section class="summary-grid transaction-summary" aria-label="Request totals">
                        <article class="summary-card"><p>Total requests</p><strong data-overview-count="total"><?= (int) ($transactionCounts['total'] ?? 0) ?></strong></article>
                        <article class="summary-card"><p>Pending</p><strong data-overview-count="pending"><?= (int) ($transactionCounts['pending'] ?? 0) ?></strong></article>
                        <article class="summary-card"><p>Processing</p><strong data-overview-count="processing"><?= (int) ($transactionCounts['processing'] ?? 0) ?></strong></article>
                        <article class="summary-card"><p>Ready for pickup</p><strong data-overview-count="ready"><?= (int) ($transactionCounts['ready'] ?? 0) ?></strong></article>
                        <article class="summary-card"><p>Released</p><strong data-overview-count="released"><?= (int) ($transactionCounts['released'] ?? 0) ?></strong></article>
                        <article class="summary-card"><p>Rejected</p><strong data-overview-count="rejected"><?= (int) ($transactionCounts['rejected'] ?? 0) ?></strong></article>
                    </section>
                    <p class="notice notice-success" role="status" data-overview-action-notice hidden></p>
                    <section class="portal-panel transaction-panel" aria-labelledby="overview-requests-title">
                        <h2 id="overview-requests-title">Requests to process</h2>
                        <p>Overdue and soonest-due requests appear first; requests without an expected date are ordered oldest-first.</p>
                        <p class="empty-state" data-overview-requests-empty <?= $overviewRequests === [] ? '' : 'hidden' ?>>No requests to process.</p>
                        <div class="request-card-grid" data-overview-requests-grid <?= $overviewRequests === [] ? 'hidden' : '' ?>>
                            <?php foreach ($overviewRequests as $request): ?>
                                <?php
                                $requestStatus = (string) $request['status'];
                                $requestActions = $overviewActionsForStatus($requestStatus);
                                $defaultReleaseDate = in_array($requestStatus, ['SUBMITTED', 'FOR_REVIEW'], true)
                                    ? estimate_document_release_date((int) $request['processing_max_days'])->format('Y-m-d')
                                    : (string) ($request['expected_release_date'] ?? '');
                                $statusClass = $overviewStatusClass($requestStatus);
                                ?>
                                <button class="document-card request-management-card" type="button"
                                        data-open-overview-request
                                        aria-haspopup="dialog"
                                        aria-label="Open request <?= htmlspecialchars((string) $request['tracking_no'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?> details"
                                        data-request-id="<?= (int) $request['id'] ?>"
                                        data-request-reference="<?= htmlspecialchars((string) $request['tracking_no'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>"
                                        data-requester-name="<?= htmlspecialchars((string) $request['requester_name'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>"
                                        data-document-name="<?= htmlspecialchars((string) $request['document_name'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>"
                                        data-status-code="<?= htmlspecialchars($requestStatus, ENT_QUOTES, 'UTF-8') ?>"
                                        data-status-label="<?= htmlspecialchars($transactionStatusLabels[$requestStatus] ?? $requestStatus, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>"
                                        data-status-class="<?= htmlspecialchars($statusClass, ENT_QUOTES, 'UTF-8') ?>"
                                        data-created-date="<?= htmlspecialchars((new DateTimeImmutable((string) $request['created_at']))->format('M j, Y'), ENT_QUOTES, 'UTF-8') ?>"
                                        data-expected-release-date="<?= htmlspecialchars((string) ($request['expected_release_date'] ?? ''), ENT_QUOTES, 'UTF-8') ?>"
                                        data-expected-release-label="<?= $request['expected_release_date'] !== null
                                            ? htmlspecialchars((new DateTimeImmutable((string) $request['expected_release_date']))->format('M j, Y'), ENT_QUOTES, 'UTF-8')
                                            : '' ?>"
                                        data-default-release-date="<?= htmlspecialchars($defaultReleaseDate, ENT_QUOTES, 'UTF-8') ?>"
                                        data-request-actions="<?= htmlspecialchars((string) json_encode(
                                            $requestActions,
                                            JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT
                                        ), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>">
                                    <span class="request-card-content">
                                        <span class="request-card-column">
                                            <span class="request-card-label">Name</span>
                                            <span class="document-card-name"><?= htmlspecialchars((string) $request['requester_name'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></span>
                                            <span class="request-card-reference"><?= htmlspecialchars((string) $request['tracking_no'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></span>
                                        </span>
                                        <span class="request-card-column">
                                            <span class="request-card-label">Document</span>
                                            <span class="request-card-document"><?= htmlspecialchars((string) $request['document_name'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></span>
                                        </span>
                                    </span>
                                    <span class="status-pill <?= htmlspecialchars($statusClass, ENT_QUOTES, 'UTF-8') ?>">
                                        <?= htmlspecialchars($transactionStatusLabels[$requestStatus] ?? $requestStatus, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>
                                    </span>
                                    <span class="request-card-label">Submitted <?= htmlspecialchars((new DateTimeImmutable((string) $request['created_at']))->format('M j, Y'), ENT_QUOTES, 'UTF-8') ?></span>
                                </button>
                            <?php endforeach; ?>
                        </div>
                    </section>
                    <dialog class="workflow-dialog request-workflow-dialog" id="overview-request-details-dialog"
                            aria-labelledby="overview-request-details-title">
                        <div class="workflow-dialog-heading">
                            <div>
                                <p class="eyebrow">REQUEST DETAILS</p>
                                <h2 id="overview-request-details-title" data-overview-details-reference></h2>
                                <p class="workflow-step-label" data-overview-details-requester></p>
                            </div>
                            <button class="workflow-dialog-close" type="button" data-overview-details-close
                                    aria-label="Close request details">×</button>
                        </div>
                        <dl class="request-detail-list">
                            <div><dt>Document</dt><dd data-overview-details-document></dd></div>
                            <div><dt>Current status</dt><dd data-overview-details-status></dd></div>
                            <div><dt>Requested</dt><dd data-overview-details-created></dd></div>
                            <div data-overview-details-release-row hidden>
                                <dt>Expected release</dt><dd data-overview-details-release></dd>
                            </div>
                        </dl>
                        <div class="request-decision-options" role="group" aria-label="Available request actions"
                             data-overview-details-actions></div>
                        <p class="request-terminal-note" data-overview-details-no-actions hidden>
                            No action is currently available for this request.
                        </p>
                    </dialog>
                    <dialog class="workflow-dialog request-confirmation-dialog" id="overview-request-action-dialog"
                            aria-labelledby="overview-action-title" aria-describedby="overview-action-message">
                        <form class="request-update-form" id="overview-request-action-form">
                            <input type="hidden" name="overview_action" value="1">
                            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8') ?>">
                            <input type="hidden" name="request_action" value="">
                            <input type="hidden" name="request_id" value="">
                            <input type="hidden" name="next_status" value="">
                            <p class="eyebrow">REQUEST ACTION</p>
                            <h2 id="overview-action-title" data-overview-action-title>Confirm action</h2>
                            <p id="overview-action-message" class="request-terminal-note"
                               data-overview-action-message></p>
                            <label data-overview-release-date-field hidden for="overview-release-date">
                                Expected release date
                                <input id="overview-release-date" name="expected_release_date" type="date"
                                       data-overview-release-date>
                            </label>
                            <label data-overview-rejection-field hidden for="overview-rejection-reason">
                                Rejection reason
                                <textarea id="overview-rejection-reason" name="rejection_reason"
                                          maxlength="500" rows="3"
                                          placeholder="Enter the reason for rejecting this request"
                                          data-overview-rejection-reason></textarea>
                            </label>
                            <p class="notice" role="alert" data-overview-action-error hidden></p>
                            <div class="workflow-actions">
                                <button class="secondary-button" type="button" data-overview-action-cancel>Cancel</button>
                                <button class="table-action-button" type="submit" data-overview-action-submit>Confirm</button>
                            </div>
                        </form>
                    </dialog>
                </section>
                <iframe class="admin-view-frame" id="admin-view-frame" title="Registrar workspace page"
                        <?= $currentView === 'overview' ? 'hidden' : 'src="' . htmlspecialchars($views[$currentView]['path'], ENT_QUOTES, 'UTF-8') . '"' ?>
                        loading="eager"></iframe>
            </div>
            <footer class="admin-footer">Registrar access · Actions are attributed to your personnel account</footer>
        </section>
    </main>
    <script src="assets/js/toast-notifications.js" defer></script>
    <script>
        const availableAdminViews = <?= json_encode(
            array_map(static fn (array $view): string => $view['path'], $views),
            JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT
        ) ?>;
        const adminFrame = document.getElementById('admin-view-frame');
        const adminOverview = document.querySelector('[data-admin-overview]');
        const adminViewLinks = document.querySelectorAll('[data-admin-view]');
        const adminSidebar = document.getElementById('admin-sidebar');
        const sidebarToggle = document.querySelector('[data-sidebar-toggle]');
        const overviewRefreshError = document.querySelector('[data-overview-refresh-error]');
        const overviewActionNotice = document.querySelector('[data-overview-action-notice]');
        const overviewDetailsDialog = document.getElementById('overview-request-details-dialog');
        const overviewDetailsReference = overviewDetailsDialog.querySelector('[data-overview-details-reference]');
        const overviewDetailsRequester = overviewDetailsDialog.querySelector('[data-overview-details-requester]');
        const overviewDetailsDocument = overviewDetailsDialog.querySelector('[data-overview-details-document]');
        const overviewDetailsStatus = overviewDetailsDialog.querySelector('[data-overview-details-status]');
        const overviewDetailsCreated = overviewDetailsDialog.querySelector('[data-overview-details-created]');
        const overviewDetailsReleaseRow = overviewDetailsDialog.querySelector('[data-overview-details-release-row]');
        const overviewDetailsRelease = overviewDetailsDialog.querySelector('[data-overview-details-release]');
        const overviewDetailsActions = overviewDetailsDialog.querySelector('[data-overview-details-actions]');
        const overviewDetailsNoActions = overviewDetailsDialog.querySelector('[data-overview-details-no-actions]');
        const overviewActionDialog = document.getElementById('overview-request-action-dialog');
        const overviewActionForm = document.getElementById('overview-request-action-form');
        const overviewActionTitle = overviewActionDialog.querySelector('[data-overview-action-title]');
        const overviewActionMessage = overviewActionDialog.querySelector('[data-overview-action-message]');
        const overviewReleaseDateField = overviewActionForm.querySelector('[data-overview-release-date-field]');
        const overviewReleaseDateInput = overviewActionForm.querySelector('[data-overview-release-date]');
        const overviewRejectionField = overviewActionForm.querySelector('[data-overview-rejection-field]');
        const overviewRejectionInput = overviewActionForm.querySelector('[data-overview-rejection-reason]');
        const overviewActionError = overviewActionForm.querySelector('[data-overview-action-error]');
        const overviewActionCancel = overviewActionForm.querySelector('[data-overview-action-cancel]');
        const overviewActionSubmit = overviewActionForm.querySelector('[data-overview-action-submit]');
        let overviewRefreshSequence = 0;
        let adminDialogScrollY = null;
        let overviewActionSubmitting = false;
        let returnToOverviewDetailsDialog = null;

        function resetOverviewActionForm() {
            overviewActionForm.reset();
            overviewReleaseDateField.hidden = true;
            overviewReleaseDateInput.required = false;
            overviewRejectionField.hidden = true;
            overviewRejectionInput.required = false;
            overviewActionError.hidden = true;
            overviewActionError.textContent = '';
            overviewActionSubmitting = false;
            overviewActionCancel.disabled = false;
            overviewActionSubmit.disabled = false;
            overviewActionSubmit.classList.remove('is-loading', 'danger-button');
            overviewActionSubmit.classList.add('table-action-button');
            overviewActionSubmit.removeAttribute('aria-busy');
            overviewActionSubmit.textContent = 'Confirm';
        }

        adminOverview.addEventListener('click', (event) => {
            const requestCard = event.target.closest('[data-open-overview-request]');
            if (!requestCard) {
                return;
            }

            overviewDetailsReference.textContent = requestCard.dataset.requestReference;
            overviewDetailsRequester.textContent = requestCard.dataset.requesterName;
            overviewDetailsDocument.textContent = requestCard.dataset.documentName;
            overviewDetailsStatus.textContent = requestCard.dataset.statusLabel;
            overviewDetailsCreated.textContent = requestCard.dataset.createdDate;
            overviewDetailsReleaseRow.hidden = requestCard.dataset.expectedReleaseLabel === '';
            overviewDetailsRelease.textContent = requestCard.dataset.expectedReleaseLabel || '';
            overviewDetailsActions.replaceChildren();

            const actions = JSON.parse(requestCard.dataset.requestActions || '[]');
            actions.forEach((action) => {
                const button = document.createElement('button');
                button.type = 'button';
                button.className = action.class;
                button.textContent = action.label;
                button.dataset.overviewRequestAction = '';
                button.dataset.actionStatus = action.status;
                button.dataset.actionLabel = action.label;
                button.dataset.requestId = requestCard.dataset.requestId;
                button.dataset.requestReference = requestCard.dataset.requestReference;
                button.dataset.defaultReleaseDate = requestCard.dataset.defaultReleaseDate;
                overviewDetailsActions.appendChild(button);
            });
            overviewDetailsNoActions.hidden = actions.length > 0;
            overviewDetailsDialog.showModal();
        });

        overviewDetailsDialog.querySelector('[data-overview-details-close]').addEventListener('click', () => {
            overviewDetailsDialog.close();
        });
        overviewDetailsDialog.addEventListener('click', (event) => {
            if (event.target === overviewDetailsDialog) {
                overviewDetailsDialog.close();
            }
        });

        function openOverviewAction(actionButton) {
            resetOverviewActionForm();
            returnToOverviewDetailsDialog = overviewDetailsDialog;
            if (overviewDetailsDialog.open) {
                overviewDetailsDialog.close();
            }
            const status = actionButton.dataset.actionStatus;
            const actionLabel = actionButton.dataset.actionLabel;
            const trackingNumber = actionButton.dataset.requestReference;
            overviewActionForm.elements.request_id.value = actionButton.dataset.requestId;
            overviewActionForm.elements.next_status.value = status;
            overviewActionTitle.textContent = `${actionLabel}?`;
            overviewActionMessage.textContent = `Are you sure you want to ${actionLabel.toLowerCase()} for request ${trackingNumber}?`;
            overviewActionSubmit.textContent = actionLabel;

            if (status === 'PROCESSING') {
                overviewReleaseDateField.hidden = false;
                overviewReleaseDateInput.required = true;
                overviewReleaseDateInput.value = actionButton.dataset.defaultReleaseDate || '';
                overviewActionTitle.textContent = 'Process request?';
                overviewActionMessage.textContent = `Set an expected release date to process request ${trackingNumber}.`;
            } else if (status === 'REJECTED') {
                overviewRejectionField.hidden = false;
                overviewRejectionInput.required = true;
                overviewActionTitle.textContent = 'Reject request?';
                overviewActionMessage.textContent =
                    `Are you sure you want to reject request ${trackingNumber}? The requester will be notified with the reason.`;
                overviewActionSubmit.classList.remove('table-action-button');
                overviewActionSubmit.classList.add('danger-button');
            } else if (status === 'READY') {
                overviewActionTitle.textContent = 'Mark ready for pickup?';
                overviewActionMessage.textContent = `Mark request ${trackingNumber} as ready for pickup?`;
            } else if (status === 'COMPLETED') {
                overviewActionTitle.textContent = 'Release request?';
                overviewActionMessage.textContent = `Release request ${trackingNumber} to the requester?`;
            }

            overviewActionDialog.showModal();
        }

        overviewDetailsDialog.addEventListener('click', (event) => {
            const actionButton = event.target.closest('[data-overview-request-action]');
            if (actionButton) {
                openOverviewAction(actionButton);
            }
        });

        overviewActionDialog.querySelector('[data-overview-action-cancel]').addEventListener('click', () => {
            if (!overviewActionSubmitting) {
                overviewActionDialog.close();
            }
        });
        overviewActionDialog.addEventListener('click', (event) => {
            if (event.target === overviewActionDialog && !overviewActionSubmitting) {
                overviewActionDialog.close();
            }
        });
        overviewActionDialog.addEventListener('cancel', (event) => {
            if (overviewActionSubmitting) {
                event.preventDefault();
            }
        });
        overviewActionDialog.addEventListener('close', () => {
            const shouldReturnToDetails = !overviewActionSubmitting
                && returnToOverviewDetailsDialog?.isConnected;
            returnToOverviewDetailsDialog = null;
            resetOverviewActionForm();
            if (shouldReturnToDetails) {
                overviewDetailsDialog.showModal();
            }
        });

        overviewActionForm.addEventListener('submit', async (event) => {
            event.preventDefault();
            if (!overviewActionForm.reportValidity() || overviewActionSubmit.disabled) {
                return;
            }

            overviewActionSubmitting = true;
            overviewActionCancel.disabled = true;
            overviewActionSubmit.disabled = true;
            overviewActionSubmit.classList.add('is-loading');
            overviewActionSubmit.setAttribute('aria-busy', 'true');
            overviewActionSubmit.textContent = 'Saving…';
            overviewActionError.hidden = true;
            try {
                const response = await fetch('request-management.php', {
                    method: 'POST',
                    body: new FormData(overviewActionForm),
                    credentials: 'same-origin',
                    headers: { Accept: 'application/json' },
                });
                let result;
                try {
                    result = await response.json();
                } catch {
                    throw new Error('The request could not be updated because the server returned an invalid response.');
                }
                if (!response.ok || result.ok !== true) {
                    throw new Error(result.message || 'The request could not be updated. Please try again.');
                }

                overviewActionNotice.textContent = result.message;
                overviewActionNotice.hidden = false;
                overviewActionDialog.close();
                await refreshOverviewData();
            } catch (error) {
                console.error('Unable to process overview request action:', error);
                overviewActionError.textContent = error instanceof Error
                    ? error.message
                    : 'The request could not be updated. Please try again.';
                overviewActionError.hidden = false;
            } finally {
                overviewActionSubmitting = false;
                overviewActionCancel.disabled = false;
                overviewActionSubmit.disabled = false;
                overviewActionSubmit.classList.remove('is-loading');
                overviewActionSubmit.removeAttribute('aria-busy');
                overviewActionSubmit.textContent = 'Confirm';
            }
        });

        async function refreshOverviewData() {
            const refreshSequence = ++overviewRefreshSequence;
            const endpoint = new URL('admin.php', window.location.href);
            endpoint.searchParams.set('view', 'overview');
            endpoint.searchParams.set('overview_data', '1');

            try {
                const response = await fetch(endpoint, {
                    headers: { Accept: 'application/json' },
                    cache: 'no-store',
                });
                const data = await response.json();
                if (!response.ok || !data.counts || !Array.isArray(data.requests)) {
                    throw new Error(data.error || 'Overview data could not be refreshed.');
                }
                if (refreshSequence !== overviewRefreshSequence) {
                    return;
                }

                document.querySelectorAll('[data-overview-count]').forEach((counter) => {
                    const count = data.counts[counter.dataset.overviewCount];
                    counter.textContent = String(Number(count) || 0);
                });

                const requestGrid = document.querySelector('[data-overview-requests-grid]');
                const emptyRequests = document.querySelector('[data-overview-requests-empty]');
                const cards = data.requests.map((request) => {
                    const card = document.createElement('button');
                    card.type = 'button';
                    card.className = 'document-card request-management-card';
                    card.dataset.openOverviewRequest = '';
                    card.dataset.requestId = String(request.request_id);
                    card.dataset.requestReference = request.tracking_no;
                    card.dataset.requesterName = request.requester_name;
                    card.dataset.documentName = request.document_name;
                    card.dataset.statusCode = request.status_code;
                    card.dataset.statusLabel = request.status;
                    card.dataset.statusClass = request.status_class;
                    card.dataset.createdDate = request.created_date;
                    card.dataset.expectedReleaseDate = request.expected_release_date;
                    card.dataset.expectedReleaseLabel = request.expected_release_label;
                    card.dataset.defaultReleaseDate = request.default_release_date;
                    card.dataset.requestActions = JSON.stringify(request.actions);
                    card.setAttribute('aria-haspopup', 'dialog');
                    card.setAttribute('aria-label', `Open request ${request.tracking_no} details`);

                    const content = document.createElement('span');
                    content.className = 'request-card-content';
                    const requesterColumn = document.createElement('span');
                    requesterColumn.className = 'request-card-column';
                    const requesterLabel = document.createElement('span');
                    requesterLabel.className = 'request-card-label';
                    requesterLabel.textContent = 'Name';
                    const requesterName = document.createElement('span');
                    requesterName.className = 'document-card-name';
                    requesterName.textContent = request.requester_name;
                    const reference = document.createElement('span');
                    reference.className = 'request-card-reference';
                    reference.textContent = request.tracking_no;
                    requesterColumn.append(requesterLabel, requesterName, reference);

                    const documentColumn = document.createElement('span');
                    documentColumn.className = 'request-card-column';
                    const documentLabel = document.createElement('span');
                    documentLabel.className = 'request-card-label';
                    documentLabel.textContent = 'Document';
                    const documentName = document.createElement('span');
                    documentName.className = 'request-card-document';
                    documentName.textContent = request.document_name;
                    documentColumn.append(documentLabel, documentName);
                    content.append(requesterColumn, documentColumn);
                    card.appendChild(content);

                    const status = document.createElement('span');
                    status.className = `status-pill ${request.status_class}`;
                    status.textContent = request.status;
                    card.appendChild(status);

                    const submitted = document.createElement('span');
                    submitted.className = 'request-card-label';
                    submitted.textContent = `Submitted ${request.created_date}`;
                    card.appendChild(submitted);
                    return card;
                });
                requestGrid.replaceChildren(...cards);
                requestGrid.hidden = cards.length === 0;
                emptyRequests.hidden = cards.length !== 0;
                overviewRefreshError.hidden = true;
                document.querySelector('[data-overview-server-error]')?.setAttribute('hidden', '');
            } catch (error) {
                if (refreshSequence !== overviewRefreshSequence) {
                    return;
                }
                console.error('Unable to refresh registrar overview:', error);
                overviewRefreshError.textContent = 'Overview data could not be refreshed. Please try again.';
                overviewRefreshError.hidden = false;
            }
        }

        function adminVisibleViewportHeight() {
            return Math.max(1, Math.floor(window.visualViewport?.height || window.innerHeight));
        }

        function fitAdminFrame() {
            if (adminFrame.hidden || document.body.classList.contains('admin-dialog-open')) {
                return;
            }

            const frameDocument = adminFrame.contentDocument;
            if (!frameDocument?.body || !frameDocument.documentElement) {
                return;
            }

            frameDocument.documentElement.style.height = 'auto';
            frameDocument.documentElement.style.overflow = 'hidden';
            frameDocument.body.style.height = 'auto';
            frameDocument.body.style.minHeight = '0';
            frameDocument.body.style.overflow = 'hidden';
            const frameHeight = Math.max(
                frameDocument.documentElement.scrollHeight,
                frameDocument.body.scrollHeight
            );
            if (frameHeight > 0) {
                adminFrame.style.height = `${frameHeight}px`;
            }
        }

        function placeAdminFrameForDialog() {
            adminFrame.style.height = `${adminVisibleViewportHeight()}px`;
            adminFrame.scrollIntoView({ block: 'start' });
        }

        function syncAdminDialogViewport() {
            const frameDocument = adminFrame.contentDocument;
            const dialogOpen = Boolean(frameDocument?.querySelector('dialog[open]'));
            const wasLocked = document.body.classList.contains('admin-dialog-open');
            if (dialogOpen && !wasLocked) {
                adminDialogScrollY = window.scrollY;
            }
            document.body.classList.toggle('admin-dialog-open', dialogOpen);
            if (dialogOpen) {
                placeAdminFrameForDialog();
            } else {
                fitAdminFrame();
                if (wasLocked && adminDialogScrollY !== null) {
                    window.scrollTo(0, adminDialogScrollY);
                    adminDialogScrollY = null;
                }
            }
        }

        function updateAdminDialogViewport() {
            if (document.body.classList.contains('admin-dialog-open')) {
                placeAdminFrameForDialog();
            } else {
                fitAdminFrame();
            }
        }

        window.addEventListener('resize', updateAdminDialogViewport);
        window.visualViewport?.addEventListener('resize', updateAdminDialogViewport);

        adminFrame.addEventListener('load', () => {
            refreshOverviewData();
            const frameDocument = adminFrame.contentDocument;
            if (!frameDocument?.body) {
                return;
            }
            const dialogObserver = new MutationObserver(syncAdminDialogViewport);
            frameDocument.querySelectorAll('dialog').forEach((dialog) => {
                dialogObserver.observe(dialog, { attributes: true, attributeFilter: ['open'] });
            });
            syncAdminDialogViewport();
            if (!document.body.classList.contains('admin-dialog-open')) {
                fitAdminFrame();
            }
            if ('ResizeObserver' in window) {
                new ResizeObserver(fitAdminFrame).observe(frameDocument.body);
            }
        });

        function setAdminView(view, updateHistory = true) {
            if (view !== 'overview' && !availableAdminViews[view]) {
                view = 'overview';
            }
            if (view === 'overview') {
                adminFrame.hidden = true;
                adminOverview.hidden = false;
                refreshOverviewData();
            } else {
                adminOverview.hidden = true;
                adminFrame.hidden = false;
                if (adminFrame.dataset.view !== view) {
                    adminFrame.contentWindow.location.replace(availableAdminViews[view]);
                    adminFrame.dataset.view = view;
                } else {
                    fitAdminFrame();
                }
            }
            adminViewLinks.forEach((link) => {
                const active = link.dataset.adminView === view;
                link.classList.toggle('is-current', active);
                if (active) {
                    link.setAttribute('aria-current', 'page');
                } else {
                    link.removeAttribute('aria-current');
                }
            });
            closeAdminSidebar(document.body.classList.contains('admin-sidebar-open'));
            if (updateHistory) {
                const url = new URL(window.location.href);
                if (view === 'overview') {
                    url.searchParams.delete('view');
                } else {
                    url.searchParams.set('view', view);
                }
                window.history.pushState({ view }, '', url);
            }
        }

        adminViewLinks.forEach((link) => {
            link.addEventListener('click', (event) => {
                if (event.button !== 0 || event.metaKey || event.ctrlKey || event.shiftKey || event.altKey) {
                    return;
                }
                event.preventDefault();
                setAdminView(link.dataset.adminView);
            });
        });

        window.addEventListener('popstate', () => {
            setAdminView(new URL(window.location.href).searchParams.get('view') || 'overview', false);
        });
        window.addEventListener('focus', refreshOverviewData);

        function closeAdminSidebar(restoreFocus = false) {
            document.body.classList.remove('admin-sidebar-open');
            sidebarToggle.setAttribute('aria-expanded', 'false');
            sidebarToggle.setAttribute('aria-label', 'Open navigation menu');
            if (restoreFocus && window.matchMedia('(max-width: 1100px)').matches) {
                sidebarToggle.focus();
            }
        }

        sidebarToggle.addEventListener('click', () => {
            const isOpen = document.body.classList.toggle('admin-sidebar-open');
            sidebarToggle.setAttribute('aria-expanded', String(isOpen));
            sidebarToggle.setAttribute('aria-label', isOpen ? 'Close navigation menu' : 'Open navigation menu');
            if (isOpen) {
                adminSidebar.querySelector('.admin-nav-link.is-current')?.focus();
            }
        });
        document.querySelectorAll('[data-sidebar-close]').forEach((backdrop) => {
            backdrop.addEventListener('click', () => closeAdminSidebar(true));
        });
        document.addEventListener('keydown', (event) => {
            if (event.key === 'Escape' && document.body.classList.contains('admin-sidebar-open')) {
                closeAdminSidebar(true);
            }
        });
        window.addEventListener('resize', () => {
            if (!window.matchMedia('(max-width: 1100px)').matches) {
                closeAdminSidebar();
            }
        });
        document.querySelectorAll('[data-confirm-signout]').forEach((form) => {
            let signOutConfirmed = false;
            form.addEventListener('submit', (event) => {
                if (signOutConfirmed) {
                    return;
                }
                event.preventDefault();
                window.__appNavigationGuard.confirmLeave('leave').then((leave) => {
                    if (leave) {
                        signOutConfirmed = true;
                        form.requestSubmit();
                    }
                });
            });
        });

        if (<?= json_encode($currentView) ?> !== 'overview') {
            adminFrame.dataset.view = <?= json_encode($currentView) ?>;
        }
    </script>
    <script src="assets/js/navigation-guard.js" defer></script>
    <script src="assets/js/form-loading.js" defer></script>
</body>
</html>
