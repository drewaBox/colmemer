<?php
declare(strict_types=1);

require __DIR__ . '/request_helpers.php';

$db = db();
$reference = strtoupper(trim((string) ($_GET['reference'] ?? '')));
$error = '';
$request = null;
$history = [];

if ($reference !== '') {
    if (strlen($reference) > 30 || preg_match('/^[A-Z0-9-]+$/D', $reference) !== 1) {
        $error = 'Enter a valid request reference number.';
    } else {
        $requestQuery = $db->prepare(
            'SELECT r.id, r.tracking_no, r.status, r.purpose, r.created_at,
                    r.expected_release_date, r.ready_at, r.completed_at,
                    r.registrar_remarks, d.name AS document_name,
                    COALESCE(NULLIF(u.full_name, \'\'), CONCAT(g.first_name, \' \', g.last_name)) AS requester_name
             FROM requests r
             LEFT JOIN users u ON u.id = r.student_id
             LEFT JOIN guest_request_details g ON g.request_id = r.id
             LEFT JOIN request_items i ON i.request_id = r.id
             LEFT JOIN documents d ON d.id = i.document_id
             WHERE r.tracking_no = :tracking_no
               AND r.deleted_at IS NULL
             LIMIT 1'
        );
        $requestQuery->execute(['tracking_no' => $reference]);
        $request = $requestQuery->fetch() ?: null;

        if ($request) {
            $historyQuery = $db->prepare(
                'SELECT old_status, new_status, remarks, created_at
                 FROM request_history
                 WHERE request_id = :request_id
                 ORDER BY created_at, id'
            );
            $historyQuery->execute(['request_id' => (int) $request['id']]);
            $history = $historyQuery->fetchAll();
        } else {
            $error = 'No request was found for that reference number. Check it and try again.';
        }
    }
}

$statusLabels = [
    'SUBMITTED' => 'Pending',
    'FOR_REVIEW' => 'Pending',
    'PROCESSING' => 'Processing',
    'READY' => 'Ready for Pickup',
    'COMPLETED' => 'Released',
    'REJECTED' => 'Rejected',
];
$currentStatus = $request
    ? ($statusLabels[(string) $request['status']] ?? 'Pending')
    : '';
$currentStatusClass = match ((string) ($request['status'] ?? '')) {
    'SUBMITTED', 'FOR_REVIEW' => 'status-pending',
    'PROCESSING' => 'status-processing',
    'READY' => 'status-ready',
    'COMPLETED' => 'status-completed',
    'REJECTED' => 'status-rejected',
    default => 'status-neutral',
};
$escape = static fn ($value): string => htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
$formatDate = static function (?string $value): string {
    if ($value === null || $value === '') {
        return '';
    }

    $date = new DateTimeImmutable($value);
    return $date->format('M j, Y · g:i A');
};

$steps = [
    ['status' => 'SUBMITTED', 'label' => 'Pending'],
    ['status' => 'PROCESSING', 'label' => 'Processing'],
    ['status' => 'READY', 'label' => 'Ready for Pickup'],
    ['status' => 'COMPLETED', 'label' => 'Released'],
];
$stageIndex = 0;
foreach ($history as $event) {
    $eventStage = match ($event['new_status']) {
        'PROCESSING' => 1,
        'READY' => 2,
        'COMPLETED' => 3,
        default => 0,
    };
    $stageIndex = max($stageIndex, $eventStage);
}
$currentStage = match ($request['status'] ?? '') {
    'PROCESSING' => 1,
    'READY' => 2,
    'COMPLETED' => 3,
    default => 0,
};
$stageIndex = max($stageIndex, $currentStage);

$stageDates = array_fill(0, count($steps), null);
$stageDates[0] = (string) ($request['created_at'] ?? '');
foreach ($history as $event) {
    $eventStage = match ($event['new_status']) {
        'PROCESSING' => 1,
        'READY' => 2,
        'COMPLETED' => 3,
        default => null,
    };
    if ($eventStage !== null) {
        $stageDates[$eventStage] = (string) $event['created_at'];
    }
}
if ($request && $request['status'] === 'READY' && $request['ready_at'] !== null) {
    $stageDates[2] ??= (string) $request['ready_at'];
}
if ($request && $request['status'] === 'COMPLETED' && $request['completed_at'] !== null) {
    $stageDates[3] ??= (string) $request['completed_at'];
}

$rejectionReason = trim((string) ($request['registrar_remarks'] ?? ''));
if ($rejectionReason === '') {
    foreach (array_reverse($history) as $event) {
        if ($event['new_status'] === 'REJECTED' && trim((string) $event['remarks']) !== '') {
            $rejectionReason = trim((string) $event['remarks']);
            break;
        }
    }
}
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#146c43">
    <title>Track a Request</title>
    <link rel="stylesheet" href="assets/css/login.css">
</head>
<body>
    <main class="page-shell">
        <section class="login-card tracker-card" aria-labelledby="page-title">
            <nav class="public-service-nav" aria-label="Registrar services">
                <a href="index.php" data-public-view="home">Home</a>
                <a href="index.php?view=request" data-public-view="request">Request</a>
                <a href="index.php?view=track" data-public-view="track" aria-current="page">Track</a>
            </nav>
            <div class="brand-mark" aria-hidden="true">R</div>
            <p class="eyebrow">REQUEST TRACKER</p>
            <h1 id="page-title">Track my Request</h1>
            <p class="intro">Use the unique reference number from your request confirmation email.</p>

            <form class="tracker-form" id="tracker-form" method="get" action="track.php">
                <div class="field">
                    <label for="reference">Unique request reference number</label>
                    <input id="reference" name="reference" type="text" value="<?= $escape($reference) ?>"
                           placeholder="e.g. REQ-ABC123" maxlength="30" autocomplete="off"
                           aria-describedby="tracker-live-status" required>
                    <span id="tracker-live-status" class="tracker-live-status" aria-live="polite"></span>
                </div>
            </form>
            <p class="csv-help">The request status is checked automatically while you type.</p>

            <?php if ($error !== ''): ?>
                <p class="notice tracker-notice toast-notification" role="alert"><?= $escape($error) ?></p>
            <?php endif; ?>

            <?php if ($request): ?>
                <section class="tracking-result" id="tracking-result" aria-live="polite">
                    <div class="tracking-result-heading">
                        <div>
                            <p class="eyebrow">REQUEST STATUS</p>
                            <h2><?= $escape($request['document_name'] ?? $request['purpose']) ?></h2>
                        </div>
                        <span class="tracking-status <?= $currentStatusClass ?>"><?= $escape($currentStatus) ?></span>
                    </div>
                    <dl class="tracking-summary">
                        <?php if (!empty($request['requester_name'])): ?>
                            <div>
                                <dt>Requester</dt>
                                <dd><?= $escape($request['requester_name']) ?></dd>
                            </div>
                        <?php endif; ?>
                        <div id="live-tracking-output"></div>
                        <div>
                            <dt>Reference number</dt>
                            <dd><?= $escape($request['tracking_no']) ?></dd>
                        </div>
                        <div>
                            <dt>Requested</dt>
                            <dd><?= $escape($formatDate((string) $request['created_at'])) ?></dd>
                        </div>
                        <?php if (!empty($request['expected_release_date'])): ?>
                            <div>
                                <dt>Estimated release</dt>
                                <dd><?= $escape((new DateTimeImmutable((string) $request['expected_release_date']))->format('M j, Y')) ?></dd>
                            </div>
                        <?php endif; ?>
                    </dl>
                    <?php if (!empty($request['expected_release_date'])
                        && in_array($request['status'], ['PROCESSING', 'READY', 'COMPLETED'], true)): ?>
                        <p class="request-date-disclaimer"><?= $escape(request_processing_delay_notice()) ?></p>
                    <?php endif; ?>

                    <?php if ($request['status'] === 'REJECTED'): ?>
                        <div class="notice tracker-notice" role="status">
                            This request was rejected.
                            <?php if ($rejectionReason !== ''): ?>
                                <br>Reason: <?= $escape($rejectionReason) ?>
                            <?php endif; ?>
                        </div>
                    <?php else: ?>
                        <ol class="request-timeline" aria-label="Request status timeline">
                            <?php foreach ($steps as $index => $step): ?>
                                <?php
                                $isComplete = $index <= $stageIndex;
                                $isCurrent = $index === $stageIndex;
                                ?>
                                <li class="<?= $isComplete ? 'is-complete' : '' ?><?= $isCurrent ? ' is-current' : '' ?>">
                                    <span class="timeline-marker" aria-hidden="true"></span>
                                    <div class="timeline-content">
                                        <strong><?= $escape($step['label']) ?></strong>
                                        <?php if ($stageDates[$index] !== null): ?>
                                            <time><?= $escape($formatDate($stageDates[$index])) ?></time>
                                        <?php endif; ?>
                                        <?php if ($index === 1 && $isComplete && !empty($request['expected_release_date'])): ?>
                                            <span>Estimated release <?= $escape((new DateTimeImmutable((string) $request['expected_release_date']))->format('M j, Y')) ?></span>
                                        <?php endif; ?>
                                    </div>
                                </li>
                            <?php endforeach; ?>
                        </ol>
                    <?php endif; ?>
                </section>
            <?php endif; ?>

        </section>
        <footer class="page-footer">Request tracking · Registrar services</footer>
    </main>
    <script>
        const trackerForm = document.getElementById('tracker-form');
        const referenceInput = document.getElementById('reference');
        const liveStatus = document.getElementById('tracker-live-status');
        const liveOutput = document.getElementById('live-tracking-output');
        let lookupTimer = null;
        let activeRequest = null;
        let lastReference = '';

        function showToast(message, type = 'error') {
            document.querySelectorAll('.live-toast').forEach((toast) => toast.remove());
            const toast = document.createElement('div');
            toast.className = `notice ${type === 'success' ? 'notice-success' : ''} toast-notification live-toast`;
            toast.setAttribute('role', type === 'success' ? 'status' : 'alert');
            toast.textContent = message;
            document.body.appendChild(toast);
            window.setTimeout(() => {
                toast.classList.add('is-dismissing');
                window.setTimeout(() => toast.remove(), 250);
            }, 5000);
        }

        function clearOldDynamicResult() {
            liveOutput.replaceChildren();
            const existingResult = document.getElementById('tracking-result');
            if (existingResult) {
                existingResult.remove();
            }
        }

        async function lookupReference(reference, quiet = false) {
            if (reference.length < 6) {
                clearOldDynamicResult();
                liveStatus.textContent = '';
                return;
            }
            if (activeRequest) {
                activeRequest.abort();
            }
            activeRequest = new AbortController();
            liveStatus.textContent = 'Checking...';

            try {
                const url = new URL('track.php', window.location.href);
                url.searchParams.set('reference', reference);
                const response = await fetch(url, {
                    headers: { 'X-Requested-With': 'XMLHttpRequest' },
                    signal: activeRequest.signal,
                    cache: 'no-store',
                });
                const html = await response.text();
                const page = new DOMParser().parseFromString(html, 'text/html');
                const result = page.getElementById('tracking-result');
                const error = page.querySelector('.tracker-notice');

                clearOldDynamicResult();
                if (result) {
                    liveOutput.appendChild(document.importNode(result, true));
                    liveStatus.textContent = 'Request found.';
                    if (!quiet) {
                        showToast('Request found. Your latest status is shown below.', 'success');
                    }
                } else if (error) {
                    liveStatus.textContent = 'Reference not found.';
                    if (!quiet) {
                        showToast(error.textContent.trim(), 'error');
                    }
                } else {
                    liveStatus.textContent = 'No matching request found.';
                    if (!quiet) {
                        showToast('No matching request was found.', 'error');
                    }
                }
            } catch (error) {
                if (error.name !== 'AbortError') {
                    liveStatus.textContent = 'Could not check the request.';
                    if (!quiet) {
                        showToast('Could not check the request. Please try again.', 'error');
                    }
                }
            }
        }

        referenceInput.addEventListener('input', () => {
            const reference = referenceInput.value.toUpperCase().replace(/[^A-Z0-9-]/g, '').trim();
            if (referenceInput.value !== reference) {
                referenceInput.value = reference;
            }
            window.clearTimeout(lookupTimer);
            const normalized = referenceInput.value.trim();
            if (normalized !== lastReference) {
                lastReference = normalized;
                lookupTimer = window.setTimeout(() => lookupReference(normalized), 550);
            }
        });

        trackerForm.addEventListener('submit', (event) => {
            event.preventDefault();
            const reference = referenceInput.value.trim();
            if (reference !== '') {
                window.clearTimeout(lookupTimer);
                lookupReference(reference);
            }
        });

        window.setInterval(() => {
            const reference = referenceInput.value.trim();
            if (reference.length >= 6 && document.getElementById('tracking-result')) {
                lookupReference(reference, true);
            }
        }, 10000);
        if (window.parent !== window) {
            document.body.classList.add('public-shell-embedded');
        }
        document.querySelectorAll('[data-public-view]').forEach((link) => {
            link.addEventListener('click', (event) => {
                if (window.parent === window) {
                    return;
                }
                event.preventDefault();
                window.parent.postMessage({
                    type: 'public-navigation',
                    view: link.dataset.publicView,
                }, window.location.origin);
            });
        });
    </script>
    <script src="assets/js/navigation-guard.js" defer></script>
    <script src="assets/js/form-loading.js" defer></script>
</body>
</html>
