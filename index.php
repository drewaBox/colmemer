<?php
declare(strict_types=1);

if (in_array(rtrim((string) ($_SERVER['PATH_INFO'] ?? ''), '/'), ['/admin'], true)) {
    $applicationPath = str_replace('\\', '/', dirname((string) ($_SERVER['SCRIPT_NAME'] ?? '/index.php')));
    $applicationPath = $applicationPath === '/' || $applicationPath === '.'
        ? ''
        : '/' . trim($applicationPath, '/');
    header('Location: ' . $applicationPath . '/staff-login.php', true, 302);
    exit;
}

$publicViews = [
    'request' => ['label' => 'Request a document', 'path' => 'request.php'],
    'track' => ['label' => 'Track my request', 'path' => 'track.php'],
];
$requestedView = is_string($_GET['view'] ?? null) ? $_GET['view'] : 'home';
$currentView = $requestedView === 'home' || isset($publicViews[$requestedView]) ? $requestedView : 'home';
$signedOut = ($_GET['signed_out'] ?? null) === '1';
$trackingReference = is_string($_GET['reference'] ?? null)
    && preg_match('/^[A-Za-z0-9-]{1,30}$/D', $_GET['reference']) === 1
        ? strtoupper($_GET['reference'])
        : '';
$initialPublicPath = $publicViews[$currentView]['path'] ?? '';
if ($currentView === 'track' && $trackingReference !== '') {
    $initialPublicPath .= '?reference=' . rawurlencode($trackingReference);
}
$escape = static fn ($value): string => htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#146c43">
    <title>COLM Document Request System</title>
    <link rel="stylesheet" href="assets/css/login.css">
</head>
<body class="public-landing">
    <?php if ($signedOut): ?>
        <div class="notice notice-success toast-notification" role="status" data-clean-url>You have signed out.</div>
    <?php endif; ?>
    <div class="public-site">
        <header class="public-header">
            <a class="public-brand" href="index.php" data-public-view="home" aria-label="COLM Document Request System home">
                <span class="public-brand-mark" aria-hidden="true">
                    <svg viewBox="0 0 48 48" focusable="false">
                        <path d="M24 3 39 9v12c0 11-6.4 18.5-15 24C15.4 39.5 9 32 9 21V9l15-6Z"/>
                        <path d="M30.5 17.5a8 8 0 1 0 0 13M19 18v12m5-12v12"/>
                    </svg>
                </span>
                <span class="public-brand-copy"><strong>COLM</strong><small>Document Request System</small></span>
            </a>
            <button class="public-menu-toggle" type="button" aria-controls="public-navigation" aria-expanded="false"
                    aria-label="Open navigation menu" data-public-menu-toggle>
                <span></span><span></span><span></span>
            </button>
            <nav class="public-nav" id="public-navigation" aria-label="Main navigation">
                <a class="public-nav-link <?= $currentView === 'home' ? 'is-current' : '' ?>"
                   href="index.php" data-public-view="home"
                   <?= $currentView === 'home' ? 'aria-current="page"' : '' ?>>
                    <svg viewBox="0 0 24 24" aria-hidden="true"><path d="m3.5 10 8.5-7 8.5 7v10.5h-6v-7h-5v7h-6V10Z"/></svg>
                    <span>Home</span>
                </a>
                <a class="public-nav-link <?= $currentView === 'request' ? 'is-current' : '' ?>"
                   href="index.php?view=request" data-public-view="request"
                   <?= $currentView === 'request' ? 'aria-current="page"' : '' ?>>
                    <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M6 3.5h8l5 5v12H6z"/><path d="M14 3.5v5h5M9 13h7m-7 3.5h7"/></svg>
                    <span>Request</span>
                </a>
                <a class="public-nav-link <?= $currentView === 'track' ? 'is-current' : '' ?>"
                   href="index.php?view=track<?= $trackingReference !== '' ? '&amp;reference=' . rawurlencode($trackingReference) : '' ?>"
                   data-public-view="track" <?= $currentView === 'track' ? 'aria-current="page"' : '' ?>>
                    <svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="10.5" cy="10.5" r="6.5"/><path d="m15.5 15.5 5 5M10.5 7v4l2.5 1.5"/></svg>
                    <span>Track</span>
                </a>
            </nav>
            <span class="public-header-spacer" aria-hidden="true"></span>
        </header>

        <main class="public-main">
            <section class="landing-hero" data-public-overview <?= $currentView === 'home' ? '' : 'hidden' ?> aria-labelledby="landing-title">
                <div class="landing-hero-content">
                    <p class="landing-eyebrow"><span></span> COLM <i aria-hidden="true">|</i> Registrar Services</p>
                    <h1 id="landing-title">Get Your School Documents<br class="landing-title-break"> Fast, Easy, and Convenient</h1>
                    <p class="landing-description">Request your school documents online without the hassle. Fill out the form, track your request, and get updates in real time.</p>
                    <div class="landing-actions">
                        <a class="landing-primary-action" href="index.php?view=request" data-public-view="request">
                            Request a Document <span aria-hidden="true">→</span>
                        </a>
                        <a class="landing-secondary-action" href="index.php?view=track" data-public-view="track">
                            Track My Request
                        </a>
                    </div>
                </div>
                <div class="landing-art-caption" aria-hidden="true">COLLEGE OF OUR LADY OF MERCY</div>
            </section>

            <section class="landing-help" id="how-it-works" data-public-help <?= $currentView === 'home' ? '' : 'hidden' ?>>
                <p>Need a document? Submit a request online, then use your reference number to follow its progress.</p>
            </section>

            <iframe class="public-view-frame" id="public-view-frame" title="Registrar document services"
                    <?= $currentView === 'home' ? 'hidden' : 'src="' . $escape($initialPublicPath) . '"' ?>
                    loading="eager"></iframe>
        </main>
        <footer class="public-footer">
            <span>COLM · Registrar Document Request System</span>
            <span>Official document requests, made simple.</span>
        </footer>
    </div>
    <script src="assets/js/toast-notifications.js" defer></script>
    <script src="assets/js/navigation-guard.js" defer></script>
    <script>
        const availablePublicViews = <?= json_encode(
            array_map(static fn (array $view): string => $view['path'], $publicViews),
            JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT
        ) ?>;
        const publicFrame = document.getElementById('public-view-frame');
        const publicOverview = document.querySelector('[data-public-overview]');
        const publicHelp = document.querySelector('[data-public-help]');
        const publicViewLinks = document.querySelectorAll('[data-public-view]');
        const publicNav = document.getElementById('public-navigation');
        const publicMenuToggle = document.querySelector('[data-public-menu-toggle]');
        let publicDialogScrollY = null;

        function publicVisibleViewportHeight() {
            return Math.max(1, Math.floor(window.visualViewport?.height || window.innerHeight));
        }

        function closePublicMenu() {
            document.body.classList.remove('public-menu-open');
            publicMenuToggle.setAttribute('aria-expanded', 'false');
            publicMenuToggle.setAttribute('aria-label', 'Open navigation menu');
        }

        function fitPublicFrame() {
            if (publicFrame.hidden || document.documentElement.classList.contains('public-dialog-open')) {
                return;
            }

            const frameDocument = publicFrame.contentDocument;
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
                publicFrame.style.height = `${frameHeight}px`;
            }
        }

        function placePublicFrameForDialog() {
            publicFrame.style.height = `${publicVisibleViewportHeight()}px`;
            publicFrame.scrollIntoView({ block: 'start' });
        }

        function setPublicDialogScrollLock(locked) {
            const wasLocked = document.documentElement.classList.contains('public-dialog-open');
            if (locked && !wasLocked) {
                publicDialogScrollY = window.scrollY;
            }
            document.documentElement.classList.toggle('public-dialog-open', locked);
            document.body.classList.toggle('public-dialog-open', locked);
            if (locked) {
                placePublicFrameForDialog();
            } else {
                fitPublicFrame();
                if (wasLocked && publicDialogScrollY !== null) {
                    window.scrollTo(0, publicDialogScrollY);
                    publicDialogScrollY = null;
                }
            }
        }

        function updatePublicDialogViewport() {
            if (document.documentElement.classList.contains('public-dialog-open')) {
                placePublicFrameForDialog();
            } else {
                fitPublicFrame();
            }
        }

        window.addEventListener('resize', updatePublicDialogViewport);
        window.visualViewport?.addEventListener('resize', updatePublicDialogViewport);

        function setPublicView(view, updateHistory = true) {
            setPublicDialogScrollLock(false);
            closePublicMenu();
            if (view !== 'home' && !availablePublicViews[view]) {
                view = 'home';
            }
            if (view === 'home') {
                publicFrame.hidden = true;
                publicOverview.hidden = false;
                publicHelp.hidden = false;
            } else {
                publicOverview.hidden = true;
                publicHelp.hidden = true;
                publicFrame.hidden = false;
                if (publicFrame.dataset.view !== view) {
                    const reference = view === 'track'
                        ? new URL(window.location.href).searchParams.get('reference')
                        : null;
                    const framePath = availablePublicViews[view]
                        + (reference ? '?reference=' + encodeURIComponent(reference) : '');
                    publicFrame.contentWindow.location.replace(framePath);
                    publicFrame.dataset.view = view;
                }
            }
            publicViewLinks.forEach((link) => {
                const active = link.dataset.publicView === view;
                link.classList.toggle('is-current', active);
                if (active) {
                    link.setAttribute('aria-current', 'page');
                } else {
                    link.removeAttribute('aria-current');
                }
            });
            if (updateHistory) {
                const url = new URL(window.location.href);
                if (view === 'home') {
                    url.searchParams.delete('view');
                    url.searchParams.delete('reference');
                } else {
                    url.searchParams.set('view', view);
                    if (view !== 'track') {
                        url.searchParams.delete('reference');
                    }
                }
                window.history.pushState({ view }, '', url);
            }
        }

        publicViewLinks.forEach((link) => {
            link.addEventListener('click', (event) => {
                event.preventDefault();
                setPublicView(link.dataset.publicView || 'home');
            });
        });

        publicMenuToggle.addEventListener('click', () => {
            const isOpen = document.body.classList.toggle('public-menu-open');
            publicMenuToggle.setAttribute('aria-expanded', String(isOpen));
            publicMenuToggle.setAttribute('aria-label', isOpen ? 'Close navigation menu' : 'Open navigation menu');
        });

        document.addEventListener('click', (event) => {
            if (!publicNav.contains(event.target) && !publicMenuToggle.contains(event.target)) {
                closePublicMenu();
            }
        });

        window.addEventListener('resize', () => {
            if (window.innerWidth > 620) {
                closePublicMenu();
            }
        });

        document.addEventListener('keydown', (event) => {
            if (event.key === 'Escape') {
                closePublicMenu();
            }
        });

        publicFrame.addEventListener('load', () => {
            setPublicDialogScrollLock(false);
            fitPublicFrame();
            if (publicFrame.contentDocument?.body && 'ResizeObserver' in window) {
                new ResizeObserver(fitPublicFrame).observe(publicFrame.contentDocument.body);
            }
        });

        window.addEventListener('message', (event) => {
            if (event.origin !== window.location.origin || event.source !== publicFrame.contentWindow) {
                return;
            }
            if (event.data?.type === 'public-dialog-scroll-lock' && typeof event.data.locked === 'boolean') {
                setPublicDialogScrollLock(event.data.locked);
                return;
            }
            if (event.data?.type !== 'public-navigation' || typeof event.data.view !== 'string') {
                return;
            }
            setPublicView(event.data.view);
        });

        window.addEventListener('popstate', () => {
            const url = new URL(window.location.href);
            setPublicView(url.searchParams.get('view') || 'home', false);
        });

        if (<?= json_encode($currentView) ?> !== 'home') {
            publicFrame.dataset.view = <?= json_encode($currentView) ?>;
        }
    </script>
</body>
</html>
