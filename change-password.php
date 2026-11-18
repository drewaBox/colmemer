<?php
declare(strict_types=1);

require __DIR__ . '/config.php';
require_once __DIR__ . '/audit_helpers.php';
start_app_session();
require_personnel(['admin', 'staff'], true);

$error = '';
$personnelId = (int) $_SESSION['personnel_id'];
$forcedPasswordChange = !empty($_SESSION['personnel_must_change_password']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!valid_csrf_token($_POST['csrf_token'] ?? null)) {
        http_response_code(400);
        exit('Invalid form token. Please reload this page and try again.');
    }

    $currentPassword = is_string($_POST['current_password'] ?? null) ? $_POST['current_password'] : '';
    $newPassword = is_string($_POST['new_password'] ?? null) ? $_POST['new_password'] : '';
    $passwordConfirmation = is_string($_POST['password_confirmation'] ?? null)
        ? $_POST['password_confirmation']
        : '';

    if ((!$forcedPasswordChange && $currentPassword === '')
        || $newPassword === ''
        || $passwordConfirmation === '') {
        $error = 'Complete all password fields.';
    } elseif (strlen($newPassword) < 12) {
        $error = 'Choose a password with at least 12 characters.';
    } elseif (strlen($newPassword) > 72) {
        $error = 'Choose a password no longer than 72 bytes.';
    } elseif (!hash_equals($newPassword, $passwordConfirmation)) {
        $error = 'The new passwords do not match.';
    } else {
        try {
            $db = db();
            $accountQuery = $db->prepare(
                "SELECT password_hash
                 FROM users
                 WHERE id = :id
                   AND role IN ('admin', 'registrar')
                   AND is_active = 1
                   AND is_approved = 1
                 LIMIT 1"
            );
            $accountQuery->execute(['id' => $personnelId]);
            $passwordHash = $accountQuery->fetchColumn();

            if (!is_string($passwordHash)) {
                $error = 'Your password could not be verified. Please sign in again.';
            } elseif (!$forcedPasswordChange && !password_verify($currentPassword, $passwordHash)) {
                $error = 'Your current password is incorrect.';
            } elseif (password_verify($newPassword, $passwordHash)) {
                $error = 'Choose a password different from your current password.';
            } else {
                $db->beginTransaction();
                try {
                    $updatePassword = $db->prepare(
                        'UPDATE users
                         SET password_hash = :password_hash, password_changed_at = CURRENT_TIMESTAMP,
                             must_change_password = 0
                         WHERE id = :id'
                    );
                    $updatePassword->execute([
                        'password_hash' => password_hash($newPassword, PASSWORD_DEFAULT),
                        'id' => $personnelId,
                    ]);
                    if ($updatePassword->rowCount() !== 1) {
                        throw new RuntimeException('The password change was not saved.');
                    }
                    record_audit_event(
                        $db,
                        $personnelId,
                        (string) ($_SESSION['personnel_name'] ?? $_SESSION['personnel_username']),
                        'password_changed',
                        'personnel_account',
                        (string) $personnelId,
                        'Personnel password changed.'
                    );
                    $db->commit();
                } catch (Throwable $exception) {
                    if ($db->inTransaction()) {
                        $db->rollBack();
                    }
                    throw $exception;
                }

                $_SESSION = [];
                session_regenerate_id(true);
                header('Location: staff-login.php?password_changed=1');
                exit;
            }
        } catch (PDOException $exception) {
            error_log('Personnel password change database error: ' . $exception->getMessage());
            $error = 'The password could not be changed. Please try again.';
        } catch (Throwable $exception) {
            error_log('Personnel password change failed: ' . $exception->getMessage());
            $error = 'The password could not be changed. Please try again.';
        }
    }
}

$escape = static fn (string $value): string =>
    htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#146c43">
    <title>Change password</title>
    <link rel="stylesheet" href="assets/css/login.css">
</head>
<body>
    <main class="page-shell">
        <section class="login-card" aria-labelledby="page-title">
            <p class="eyebrow">ACCOUNT SECURITY</p>
            <h1 id="page-title">Change password</h1>
            <p class="intro"><?= $forcedPasswordChange
                ? 'Your temporary password must be replaced before you can access the portal. Afterward, you will be signed out; sign in again with your new password.'
                : 'After your password is changed, you will be signed out and asked to sign in again.' ?></p>

            <?php if ($error !== ''): ?>
                <p class="notice" role="alert"><?= $escape($error) ?></p>
            <?php endif; ?>

            <form method="post" action="change-password.php">
                <input type="hidden" name="csrf_token" value="<?= $escape(csrf_token()) ?>">
                <?php if (!$forcedPasswordChange): ?>
                    <div class="field">
                        <label for="current_password">Current password</label>
                        <input id="current_password" name="current_password" type="password" autocomplete="current-password" required>
                    </div>
                <?php endif; ?>
                <div class="field">
                    <label for="new_password">New password</label>
                    <input id="new_password" name="new_password" type="password" autocomplete="new-password" minlength="12" maxlength="72" required>
                </div>
                <div class="field">
                    <label for="password_confirmation">Confirm new password</label>
                    <input id="password_confirmation" name="password_confirmation" type="password" autocomplete="new-password" minlength="12" maxlength="72" required>
                </div>
                <button class="submit-button" type="submit">Change password</button>
                <p class="setup-link"><a href="admin.php">Cancel and return to the workspace</a></p>
            </form>
        </section>
    </main>
    <script src="assets/js/form-loading.js" defer></script>
</body>
</html>
