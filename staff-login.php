<?php
declare(strict_types=1);

require __DIR__ . '/config.php';
start_app_session();

if (isset($_SESSION['personnel_id'])) {
    header('Location: admin.php');
    exit;
}

$message = $_SESSION['login_message'] ?? '';
unset($_SESSION['login_message']);
$signedOut = ($_GET['signed_out'] ?? null) === '1';
$passwordChanged = ($_GET['password_changed'] ?? null) === '1';
$passwordReset = ($_GET['password_reset'] ?? null) === '1';
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#146c43">
    <title>Admin / Staff Login</title>
    <link rel="stylesheet" href="assets/css/login.css">
</head>
<body>
    <main class="page-shell">
        <section class="login-card" aria-labelledby="page-title">
            <div class="brand-mark" aria-hidden="true">A</div>
            <p class="eyebrow">REGISTRAR PORTAL</p>
            <h1 id="page-title">Welcome back</h1>
            <p class="intro">Sign in with your administrator or staff account.</p>

            <?php if ($signedOut): ?>
                <p class="notice notice-success" role="status">You have signed out. Sign in to continue.</p>
            <?php elseif ($passwordChanged): ?>
                <p class="notice notice-success" role="status">Your password has been changed. Sign in again to continue.</p>
            <?php elseif ($passwordReset): ?>
                <p class="notice notice-success" role="status">Your password has been reset. Sign in again to continue.</p>
            <?php endif; ?>

            <?php if ($message !== ''): ?>
                <p class="notice <?= $passwordChanged ? 'notice-success' : '' ?>" role="<?= $passwordChanged ? 'status' : 'alert' ?>"><?= htmlspecialchars($message, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></p>
            <?php endif; ?>

            <form action="login.php" method="post">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8') ?>">
                <div class="field">
                    <label for="username">Email or username</label>
                    <input id="username" name="username" type="text" autocomplete="username" required autofocus maxlength="120">
                </div>
                <div class="field">
                    <label for="password">Password</label>
                    <div class="password-wrap">
                        <input id="password" name="password" type="password" autocomplete="current-password" required>
                        <button class="password-toggle" type="button" aria-label="Show password" aria-pressed="false">Show</button>
                    </div>
                </div>
                <button class="submit-button" type="submit">Sign in <span aria-hidden="true">→</span></button>
            </form>

            <p class="setup-link"><a href="forgot-password.php">Forgot your password?</a></p>
            <p class="security-note"><span aria-hidden="true">●</span> Secure administrator and staff access</p>
        </section>
        <footer class="page-footer">Registrar portal · Protected sign-in</footer>
    </main>
    <script>
        const toggle = document.querySelector('.password-toggle');
        const password = document.querySelector('#password');

        toggle.addEventListener('click', () => {
            const isVisible = password.type === 'text';
            password.type = isVisible ? 'password' : 'text';
            toggle.textContent = isVisible ? 'Show' : 'Hide';
            toggle.setAttribute('aria-label', isVisible ? 'Show password' : 'Hide password');
            toggle.setAttribute('aria-pressed', String(!isVisible));
        });
    </script>
    <script src="assets/js/form-loading.js" defer></script>
</body>
</html>
