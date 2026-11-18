<?php
declare(strict_types=1);

require __DIR__ . '/config.php';
require_once __DIR__ . '/audit_helpers.php';
require_once __DIR__ . '/mailer.php';
start_app_session();

if (isset($_SESSION['personnel_id'])) {
    header('Location: admin.php');
    exit;
}

$error = '';
$notice = is_string($_SESSION['password_reset_notice'] ?? null)
    ? $_SESSION['password_reset_notice']
    : '';
unset($_SESSION['password_reset_notice']);
$resetEmail = is_string($_SESSION['password_reset_email'] ?? null)
    ? $_SESSION['password_reset_email']
    : '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!valid_csrf_token($_POST['csrf_token'] ?? null)) {
        http_response_code(400);
        exit('Invalid form token. Please reload this page and try again.');
    }

    $action = is_string($_POST['action'] ?? null) ? $_POST['action'] : '';
    if ($action === 'request_code') {
        $email = is_string($_POST['email'] ?? null) ? strtolower(trim($_POST['email'])) : '';
        if (mb_strlen($email) > 120 || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = 'Enter a valid staff email address.';
        } else {
            $_SESSION['password_reset_email'] = $email;
            try {
                $db = db();
                $accountQuery = $db->prepare(
                    "SELECT id, full_name, email
                     FROM users
                     WHERE LOWER(email) = LOWER(:email)
                       AND role IN ('admin', 'registrar')
                       AND is_active = 1 AND is_approved = 1
                     LIMIT 1"
                );
                $accountQuery->execute(['email' => $email]);
                $account = $accountQuery->fetch();
                if ($account) {
                    $db->beginTransaction();
                    $lockAccount = $db->prepare(
                        "SELECT id FROM users
                         WHERE id = :id AND role IN ('admin', 'registrar')
                           AND is_active = 1 AND is_approved = 1
                         FOR UPDATE"
                    );
                    $lockAccount->execute(['id' => $account['id']]);
                    $activeAccountId = $lockAccount->fetchColumn();
                    $recentCodes = $db->prepare(
                        'SELECT COUNT(*) FROM personnel_password_reset_codes
                         WHERE user_id = :user_id AND created_at >= DATE_SUB(NOW(), INTERVAL 1 HOUR)'
                    );
                    $recentCodes->execute(['user_id' => $account['id']]);
                    $tooManyCodes = (int) $recentCodes->fetchColumn() >= 3;
                    $cooldown = $db->prepare(
                        'SELECT 1 FROM personnel_password_reset_codes
                         WHERE user_id = :user_id AND created_at >= DATE_SUB(NOW(), INTERVAL 60 SECOND)
                         LIMIT 1'
                    );
                    $cooldown->execute(['user_id' => $account['id']]);
                    $inCooldown = $cooldown->fetchColumn() !== false;
                    if ($activeAccountId !== false && !$tooManyCodes && !$inCooldown) {
                        $db->prepare(
                            'UPDATE personnel_password_reset_codes
                             SET consumed_at = COALESCE(consumed_at, NOW())
                             WHERE user_id = :user_id AND consumed_at IS NULL'
                        )->execute(['user_id' => $account['id']]);
                        $db->exec(
                            'DELETE FROM personnel_password_reset_codes
                             WHERE expires_at < DATE_SUB(NOW(), INTERVAL 30 DAY)'
                        );
                        $code = (string) random_int(10000000, 99999999);
                        $insertCode = $db->prepare(
                            'INSERT INTO personnel_password_reset_codes
                                (user_id, code_hash, expires_at)
                             VALUES (:user_id, :code_hash, DATE_ADD(NOW(), INTERVAL 15 MINUTE))'
                        );
                        $insertCode->execute([
                            'user_id' => $account['id'],
                            'code_hash' => password_hash($code, PASSWORD_DEFAULT),
                        ]);
                        $db->commit();
                        try {
                            send_registrar_email(
                                (string) $account['email'],
                                'Your Registrar Portal password reset code',
                                "Hello {$account['full_name']},\n\n"
                                    . "Your password reset verification code is: {$code}\n\n"
                                    . "This code expires in 15 minutes and can be used once. "
                                    . "If you did not request a password reset, you can ignore this email."
                            );
                            $_SESSION['password_reset_notice'] =
                                'If an active staff account matches that email, a verification code has been sent. Check your inbox and spam folder.';
                        } catch (RuntimeException $exception) {
                            error_log('Personnel password reset email delivery failed: ' . $exception->getMessage());
                            $db->prepare(
                                'UPDATE personnel_password_reset_codes
                                 SET consumed_at = NOW()
                                 WHERE user_id = :user_id AND consumed_at IS NULL'
                            )->execute(['user_id' => $account['id']]);
                            $_SESSION['password_reset_notice'] =
                                'Password recovery email is temporarily unavailable. Please contact the Registrar administrator.';
                        }
                    } else {
                        $db->commit();
                        $_SESSION['password_reset_notice'] =
                            'If an active staff account matches that email, a verification code has been sent. Check your inbox and spam folder.';
                    }
                } else {
                    $_SESSION['password_reset_notice'] =
                        'If an active staff account matches that email, a verification code has been sent. Check your inbox and spam folder.';
                }
                header('Location: forgot-password.php');
                exit;
            } catch (PDOException $exception) {
                if (isset($db) && $db->inTransaction()) {
                    $db->rollBack();
                }
                error_log('Personnel password reset database error: ' . $exception->getMessage());
                $error = 'Password recovery is unavailable because of a database error. Please try again later.';
            } catch (Throwable $exception) {
                if (isset($db) && $db->inTransaction()) {
                    $db->rollBack();
                }
                error_log('Personnel password reset request failed: ' . $exception->getMessage());
                $error = 'The verification code could not be sent. Please try again later.';
            }
        }
    } elseif ($action === 'reset_password') {
        $code = is_string($_POST['code'] ?? null) ? trim($_POST['code']) : '';
        $newPassword = is_string($_POST['new_password'] ?? null) ? $_POST['new_password'] : '';
        $passwordConfirmation = is_string($_POST['password_confirmation'] ?? null)
            ? $_POST['password_confirmation']
            : '';
        if ($resetEmail === '') {
            $error = 'Request a new verification code before resetting your password.';
        } elseif (!preg_match('/^[0-9]{8}$/D', $code)) {
            $error = 'Enter the 8-digit verification code from your email.';
        } elseif (strlen($newPassword) < 12 || strlen($newPassword) > 72) {
            $error = 'Choose a new password between 12 and 72 bytes.';
        } elseif (!hash_equals($newPassword, $passwordConfirmation)) {
            $error = 'The password confirmation does not match.';
        } else {
            try {
                $db = db();
                $db->beginTransaction();
                $accountQuery = $db->prepare(
                    "SELECT id, full_name, password_hash
                     FROM users
                     WHERE LOWER(email) = LOWER(:email)
                       AND role IN ('admin', 'registrar')
                       AND is_active = 1 AND is_approved = 1
                     LIMIT 1 FOR UPDATE"
                );
                $accountQuery->execute(['email' => $resetEmail]);
                $account = $accountQuery->fetch();
                $codeRow = false;
                if ($account) {
                    $codeQuery = $db->prepare(
                        'SELECT id, code_hash, attempts
                         FROM personnel_password_reset_codes
                         WHERE user_id = :user_id AND consumed_at IS NULL
                           AND expires_at > NOW() AND attempts < 5
                         ORDER BY id DESC LIMIT 1 FOR UPDATE'
                    );
                    $codeQuery->execute(['user_id' => $account['id']]);
                    $codeRow = $codeQuery->fetch();
                }

                if (!$account || !$codeRow || !password_verify($code, (string) $codeRow['code_hash'])) {
                    if ($account && $codeRow) {
                        $incrementAttempts = $db->prepare(
                            'UPDATE personnel_password_reset_codes
                             SET attempts = attempts + 1
                             WHERE id = :id AND attempts < 5'
                        );
                        $incrementAttempts->execute(['id' => $codeRow['id']]);
                    }
                    $db->commit();
                    $error = 'The verification code is invalid, expired, or already used. Request a new code and try again.';
                } elseif (password_verify($newPassword, (string) $account['password_hash'])) {
                    $db->rollBack();
                    $error = 'Choose a password different from your current password.';
                } else {
                    $updatePassword = $db->prepare(
                        'UPDATE users
                         SET password_hash = :password_hash,
                             password_changed_at = CURRENT_TIMESTAMP,
                             must_change_password = 0
                         WHERE id = :id'
                    );
                    $updatePassword->execute([
                        'password_hash' => password_hash($newPassword, PASSWORD_DEFAULT),
                        'id' => $account['id'],
                    ]);
                    $consumeCodes = $db->prepare(
                        'UPDATE personnel_password_reset_codes
                         SET consumed_at = NOW()
                         WHERE user_id = :user_id AND consumed_at IS NULL'
                    );
                    $consumeCodes->execute(['user_id' => $account['id']]);
                    record_audit_event(
                        $db,
                        (int) $account['id'],
                        (string) $account['full_name'],
                        'password_reset',
                        'personnel_account',
                        (string) $account['id'],
                        'Personnel password reset completed after email verification.'
                    );
                    $db->commit();
                    unset($_SESSION['password_reset_email'], $_SESSION['password_reset_notice']);
                    header('Location: staff-login.php?password_reset=1');
                    exit;
                }
            } catch (PDOException $exception) {
                if (isset($db) && $db->inTransaction()) {
                    $db->rollBack();
                }
                error_log('Personnel password reset database error: ' . $exception->getMessage());
                $error = 'The password could not be reset because of a database error. Please try again later.';
            } catch (Throwable $exception) {
                if (isset($db) && $db->inTransaction()) {
                    $db->rollBack();
                }
                error_log('Personnel password reset failed: ' . $exception->getMessage());
                $error = 'The password could not be reset. Please try again later.';
            }
        }
    } else {
        http_response_code(400);
        exit('Invalid password recovery action.');
    }
}

$escape = static fn ($value): string => htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#146c43">
    <title>Forgot password</title>
    <link rel="stylesheet" href="assets/css/login.css">
</head>
<body>
    <main class="page-shell">
        <section class="login-card" aria-labelledby="page-title">
            <p class="eyebrow">STAFF ACCOUNT SECURITY</p>
            <h1 id="page-title">Reset password</h1>
            <p class="intro">Request an email verification code, then choose a new password.</p>
            <?php if ($notice !== ''): ?><p class="notice notice-success" role="status"><?= $escape($notice) ?></p><?php endif; ?>
            <?php if ($error !== ''): ?><p class="notice" role="alert"><?= $escape($error) ?></p><?php endif; ?>
            <form method="post" action="forgot-password.php">
                <input type="hidden" name="csrf_token" value="<?= $escape(csrf_token()) ?>">
                <input type="hidden" name="action" value="request_code">
                <div class="field">
                    <label for="reset-email">Staff email</label>
                    <input id="reset-email" name="email" type="email" maxlength="120"
                           value="<?= $escape($resetEmail) ?>" autocomplete="email" required>
                </div>
                <button class="submit-button" type="submit">Email verification code</button>
            </form>
            <?php if ($resetEmail !== ''): ?>
                <form method="post" action="forgot-password.php" class="password-reset-form">
                    <input type="hidden" name="csrf_token" value="<?= $escape(csrf_token()) ?>">
                    <input type="hidden" name="action" value="reset_password">
                    <p class="panel-intro">If a code was sent to <strong><?= $escape($resetEmail) ?></strong>, enter it below.</p>
                    <div class="field">
                        <label for="reset-code">8-digit verification code</label>
                        <input id="reset-code" name="code" type="text" inputmode="numeric"
                               pattern="[0-9]{8}" maxlength="8" autocomplete="one-time-code" required>
                    </div>
                    <div class="field">
                        <label for="new-password">New password</label>
                        <input id="new-password" name="new_password" type="password" minlength="12"
                               maxlength="72" autocomplete="new-password" required>
                    </div>
                    <div class="field">
                        <label for="confirm-password">Confirm new password</label>
                        <input id="confirm-password" name="password_confirmation" type="password"
                               minlength="12" maxlength="72" autocomplete="new-password" required>
                    </div>
                    <button class="submit-button" type="submit">Verify code and reset password</button>
                </form>
            <?php endif; ?>
            <p class="setup-link"><a href="staff-login.php">Back to sign in</a></p>
        </section>
    </main>
    <script src="assets/js/form-loading.js" defer></script>
</body>
</html>
