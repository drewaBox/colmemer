<?php
declare(strict_types=1);

require __DIR__ . '/config.php';
require_once __DIR__ . '/audit_helpers.php';
require_once __DIR__ . '/mailer.php';
start_app_session();
require_personnel(['admin']);

function staff_username_from_email(PDO $db, string $email): string
{
    $localPart = explode('@', $email, 2)[0] ?? '';
    $base = trim((string) preg_replace('/[^a-z0-9._-]/', '', strtolower($localPart)), '._-');
    if ($base === '') {
        $base = 'staff';
    }

    for ($suffix = 1; $suffix < 100000; $suffix++) {
        $tail = $suffix === 1 ? '' : (string) $suffix;
        $candidate = substr($base, 0, 50 - strlen($tail)) . $tail;
        $statement = $db->prepare('SELECT 1 FROM users WHERE username = :username LIMIT 1');
        $statement->execute(['username' => $candidate]);
        if ($statement->fetchColumn() === false) {
            return $candidate;
        }
    }

    throw new RuntimeException('A unique staff username could not be generated.');
}

function deliver_staff_invitation(
    PDO $db,
    int $staffId,
    string $fullName,
    string $email,
    string $role,
    string $temporaryPassword
): void {
    $roleLabel = $role === 'admin' ? 'Administrator' : 'Registrar staff';
    $body = "Hello {$fullName},\n\n"
        . "An account has been created for you in the Registrar Portal.\n\n"
        . "Sign-in email: {$email}\n"
        . "Role: {$roleLabel}\n"
        . "Temporary password: {$temporaryPassword}\n\n"
        . "You must choose a new password at your first sign-in. After changing it, "
        . "you will be signed out and must sign in again with your new password.\n\n"
        . "If you were not expecting this account, contact the Registrar administrator.";
    send_registrar_email($email, 'Your Registrar Portal account', $body);

    $db->beginTransaction();
    try {
        $activate = $db->prepare(
            'UPDATE users
             SET is_active = 1
             WHERE id = :id AND is_active = 0 AND must_change_password = 1'
        );
        $activate->execute(['id' => $staffId]);
        if ($activate->rowCount() !== 1) {
            throw new RuntimeException('The invitation was sent, but the account could not be activated.');
        }
        record_audit_event(
            $db,
            (int) $_SESSION['personnel_id'],
            (string) ($_SESSION['personnel_name'] ?? $_SESSION['personnel_username']),
            'activated',
            'personnel_account',
            (string) $staffId,
            'Activated a personnel account after sending its invitation.'
        );
        $db->commit();
    } catch (Throwable $exception) {
        if ($db->inTransaction()) {
            $db->rollBack();
        }
        throw $exception;
    }
}

$db = db();
$error = '';
$notice = $_SESSION['personnel_notice'] ?? '';
unset($_SESSION['personnel_notice']);
$openCreateDialog = false;
$createFormValues = ['full_name' => '', 'email' => '', 'role' => 'registrar'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!valid_csrf_token($_POST['csrf_token'] ?? null)) {
        http_response_code(400);
        exit('Invalid form token. Please reload this page and try again.');
    }

    $action = is_string($_POST['action'] ?? null) ? $_POST['action'] : 'create';
    $staffId = filter_var($_POST['staff_id'] ?? null, FILTER_VALIDATE_INT);
    $actorId = (int) $_SESSION['personnel_id'];
    $actorName = (string) ($_SESSION['personnel_name'] ?? $_SESSION['personnel_username']);

    if ($action === 'update_role') {
        $role = is_string($_POST['role'] ?? null) ? $_POST['role'] : '';
        if (!is_int($staffId) || $staffId < 1 || !in_array($role, ['admin', 'registrar'], true)) {
            $error = 'Choose a valid staff account and role.';
        } elseif ($staffId === $actorId) {
            $error = 'You cannot change your own administrator role.';
        } else {
            try {
                $db->beginTransaction();
                $accountQuery = $db->prepare(
                    "SELECT role, is_active, email FROM users
                     WHERE id = :id AND role IN ('admin', 'registrar')
                     FOR UPDATE"
                );
                $accountQuery->execute(['id' => $staffId]);
                $account = $accountQuery->fetch();
                if (!$account) {
                    throw new InvalidArgumentException('That staff account could not be found.');
                }
                $oldRole = (string) $account['role'];
                if ($oldRole === $role) {
                    throw new InvalidArgumentException('That account already has the selected role.');
                }
                if (!filter_var((string) $account['email'], FILTER_VALIDATE_EMAIL)) {
                    throw new InvalidArgumentException('Add a valid email address before changing this account role.');
                }
                if ($oldRole === 'admin' && (int) $account['is_active'] === 1) {
                    $activeAdministrators = $db->query(
                        "SELECT id FROM users
                         WHERE role = 'admin' AND is_active = 1 AND is_approved = 1
                         ORDER BY id FOR UPDATE"
                    )->fetchAll(PDO::FETCH_COLUMN);
                    if (count($activeAdministrators) <= 1) {
                        throw new InvalidArgumentException('The last active administrator cannot be demoted.');
                    }
                }
                $updateRole = $db->prepare(
                    "UPDATE users SET role = :role
                     WHERE id = :id AND role IN ('admin', 'registrar')"
                );
                $updateRole->execute(['role' => $role, 'id' => $staffId]);
                record_audit_event(
                    $db,
                    $actorId,
                    $actorName,
                    'role_changed',
                    'personnel_account',
                    (string) $staffId,
                    'Changed a personnel account role.',
                    ['from' => $oldRole, 'to' => $role]
                );
                $db->commit();
                $_SESSION['personnel_notice'] = 'Staff role updated.';
                header('Location: personnel.php');
                exit;
            } catch (InvalidArgumentException $exception) {
                if ($db->inTransaction()) {
                    $db->rollBack();
                }
                $error = $exception->getMessage();
            } catch (Throwable $exception) {
                if ($db->inTransaction()) {
                    $db->rollBack();
                }
                error_log('Staff role update failed: ' . $exception->getMessage());
                $error = 'The staff role could not be updated. Check the database and try again.';
            }
        }
    } elseif ($action === 'update_email') {
        $email = is_string($_POST['email'] ?? null) ? strtolower(trim($_POST['email'])) : '';
        if (!is_int($staffId) || $staffId < 1) {
            $error = 'Choose a valid staff account.';
        } elseif (mb_strlen($email) > 120 || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = 'Enter a valid email address (maximum 120 characters).';
        } else {
            try {
                $db->beginTransaction();
                $accountQuery = $db->prepare(
                    "SELECT id, email FROM users
                     WHERE id = :id AND role IN ('admin', 'registrar')
                     FOR UPDATE"
                );
                $accountQuery->execute(['id' => $staffId]);
                $account = $accountQuery->fetch();
                if (!$account) {
                    throw new InvalidArgumentException('That staff account could not be found.');
                }
                if (strcasecmp((string) ($account['email'] ?? ''), $email) === 0) {
                    throw new InvalidArgumentException('No changes were made to the email address.');
                }
                $duplicateEmail = $db->prepare(
                    "SELECT id FROM users
                     WHERE id <> :id AND role IN ('admin', 'registrar')
                       AND LOWER(email) = LOWER(:email)
                     LIMIT 1 FOR UPDATE"
                );
                $duplicateEmail->execute(['id' => $staffId, 'email' => $email]);
                if ($duplicateEmail->fetchColumn() !== false) {
                    throw new InvalidArgumentException('Another personnel account already uses that email address.');
                }
                $updateEmail = $db->prepare('UPDATE users SET email = :email WHERE id = :id');
                $updateEmail->execute(['email' => $email, 'id' => $staffId]);
                $db->prepare(
                    'UPDATE personnel_password_reset_codes
                     SET consumed_at = COALESCE(consumed_at, NOW())
                     WHERE user_id = :user_id AND consumed_at IS NULL'
                )->execute(['user_id' => $staffId]);
                if ($staffId === $actorId) {
                    $_SESSION['personnel_email'] = $email;
                }
                record_audit_event(
                    $db,
                    $actorId,
                    $actorName,
                    'email_updated',
                    'personnel_account',
                    (string) $staffId,
                    'Updated the email address for a personnel account.'
                );
                $db->commit();
                $_SESSION['personnel_notice'] = 'Staff email address updated.';
                header('Location: personnel.php');
                exit;
            } catch (InvalidArgumentException $exception) {
                if ($db->inTransaction()) {
                    $db->rollBack();
                }
                $error = $exception->getMessage();
            } catch (Throwable $exception) {
                if ($db->inTransaction()) {
                    $db->rollBack();
                }
                error_log('Staff email update failed: ' . $exception->getMessage());
                $error = 'The staff email address could not be updated. Please try again.';
            }
        }
    } elseif ($action === 'deactivate') {
        if (!is_int($staffId) || $staffId < 1) {
            $error = 'Choose a valid staff account.';
        } elseif ($staffId === $actorId) {
            $error = 'You cannot deactivate your own account.';
        } else {
            try {
                $db->beginTransaction();
                $accountQuery = $db->prepare(
                    "SELECT role, is_active FROM users
                     WHERE id = :id AND role IN ('admin', 'registrar')
                     FOR UPDATE"
                );
                $accountQuery->execute(['id' => $staffId]);
                $account = $accountQuery->fetch();
                if (!$account) {
                    throw new InvalidArgumentException('That staff account could not be found.');
                }
                if ((int) $account['is_active'] !== 1) {
                    throw new InvalidArgumentException('That staff account is already inactive.');
                }
                if ($account['role'] === 'admin') {
                    $activeAdministrators = $db->query(
                        "SELECT id FROM users
                         WHERE role = 'admin' AND is_active = 1 AND is_approved = 1
                         ORDER BY id FOR UPDATE"
                    )->fetchAll(PDO::FETCH_COLUMN);
                    if (count($activeAdministrators) <= 1) {
                        throw new InvalidArgumentException('The last active administrator cannot be deactivated.');
                    }
                }
                $db->prepare(
                    'UPDATE personnel_password_reset_codes
                     SET consumed_at = COALESCE(consumed_at, NOW())
                     WHERE user_id = :user_id AND consumed_at IS NULL'
                )->execute(['user_id' => $staffId]);
                $deactivate = $db->prepare(
                    "UPDATE users SET is_active = 0
                     WHERE id = :id AND role IN ('admin', 'registrar') AND is_active = 1"
                );
                $deactivate->execute(['id' => $staffId]);
                if ($deactivate->rowCount() !== 1) {
                    throw new RuntimeException('The staff account could not be deactivated.');
                }
                record_audit_event(
                    $db,
                    $actorId,
                    $actorName,
                    'deactivated',
                    'personnel_account',
                    (string) $staffId,
                    'Deactivated a personnel account.'
                );
                $db->commit();
                $_SESSION['personnel_notice'] = 'Staff account deactivated.';
                header('Location: personnel.php');
                exit;
            } catch (InvalidArgumentException $exception) {
                if ($db->inTransaction()) {
                    $db->rollBack();
                }
                $error = $exception->getMessage();
            } catch (Throwable $exception) {
                if ($db->inTransaction()) {
                    $db->rollBack();
                }
                error_log('Staff account deactivation failed: ' . $exception->getMessage());
                $error = 'The staff account could not be deactivated. Check the database and try again.';
            }
        }
    } elseif ($action === 'activate') {
        if (!is_int($staffId) || $staffId < 1) {
            $error = 'Choose a valid staff account.';
        } else {
            try {
                $db->beginTransaction();
                $accountQuery = $db->prepare(
                    "SELECT is_active, must_change_password FROM users
                     WHERE id = :id AND role IN ('admin', 'registrar')
                     FOR UPDATE"
                );
                $accountQuery->execute(['id' => $staffId]);
                $account = $accountQuery->fetch();
                if (!$account) {
                    throw new InvalidArgumentException('That staff account could not be found.');
                }
                if ((int) $account['is_active'] === 1) {
                    throw new InvalidArgumentException('That staff account is already active.');
                }
                if ((int) $account['must_change_password'] === 1) {
                    throw new InvalidArgumentException('Send the pending invitation to activate this account.');
                }
                $activate = $db->prepare(
                    "UPDATE users SET is_active = 1
                     WHERE id = :id AND role IN ('admin', 'registrar')
                       AND is_active = 0 AND must_change_password = 0"
                );
                $activate->execute(['id' => $staffId]);
                if ($activate->rowCount() !== 1) {
                    throw new RuntimeException('The staff account could not be activated.');
                }
                record_audit_event(
                    $db,
                    $actorId,
                    $actorName,
                    'activated',
                    'personnel_account',
                    (string) $staffId,
                    'Reactivated a deactivated personnel account.'
                );
                $db->commit();
                $_SESSION['personnel_notice'] = 'Staff account activated.';
                header('Location: personnel.php');
                exit;
            } catch (InvalidArgumentException $exception) {
                if ($db->inTransaction()) {
                    $db->rollBack();
                }
                $error = $exception->getMessage();
            } catch (Throwable $exception) {
                if ($db->inTransaction()) {
                    $db->rollBack();
                }
                error_log('Staff account activation failed: ' . $exception->getMessage());
                $error = 'The staff account could not be activated. Check the database and try again.';
            }
        }
    } elseif ($action === 'send_invitation') {
        if (!is_int($staffId) || $staffId < 1) {
            $error = 'Choose a valid staff account.';
        } else {
            $temporaryPassword = bin2hex(random_bytes(9));
            try {
                $db->beginTransaction();
                $accountQuery = $db->prepare(
                    "SELECT username, full_name, email, role, is_active, must_change_password
                     FROM users
                     WHERE id = :id AND role IN ('admin', 'registrar')
                     FOR UPDATE"
                );
                $accountQuery->execute(['id' => $staffId]);
                $account = $accountQuery->fetch();
                if (!$account || (int) $account['is_active'] === 1
                    || (int) $account['must_change_password'] !== 1) {
                    throw new InvalidArgumentException('Only an account with a pending invitation can be invited again.');
                }
                $updateCredentials = $db->prepare(
                    'UPDATE users
                     SET password_hash = :password_hash, password_changed_at = NULL,
                         must_change_password = 1, is_approved = 1
                     WHERE id = :id'
                );
                $updateCredentials->execute([
                    'password_hash' => password_hash($temporaryPassword, PASSWORD_DEFAULT),
                    'id' => $staffId,
                ]);
                record_audit_event(
                    $db,
                    $actorId,
                    $actorName,
                    'invitation_resent',
                    'personnel_account',
                    (string) $staffId,
                    'Issued a new temporary password for an inactive personnel account.'
                );
                $db->commit();

                deliver_staff_invitation(
                    $db,
                    $staffId,
                    (string) $account['full_name'],
                    (string) $account['email'],
                    (string) $account['role'],
                    $temporaryPassword
                );
                $_SESSION['personnel_notice'] = 'Invitation sent. The staff account is now active.';
                header('Location: personnel.php');
                exit;
            } catch (InvalidArgumentException $exception) {
                if ($db->inTransaction()) {
                    $db->rollBack();
                }
                $error = $exception->getMessage();
            } catch (Throwable $exception) {
                if ($db->inTransaction()) {
                    $db->rollBack();
                }
                error_log('Staff invitation delivery failed: ' . $exception->getMessage());
                $error = 'The invitation could not be delivered or the account could not be activated. The account remains inactive; check SMTP/database settings and try again.';
            }
        }
    } elseif ($action === 'create') {
        $fullName = is_string($_POST['full_name'] ?? null) ? trim($_POST['full_name']) : '';
        $email = is_string($_POST['email'] ?? null) ? strtolower(trim($_POST['email'])) : '';
        $role = is_string($_POST['role'] ?? null) ? $_POST['role'] : '';
        $openCreateDialog = true;
        $createFormValues = ['full_name' => $fullName, 'email' => $email, 'role' => $role];
        if ($fullName === '' || mb_strlen($fullName) > 120
            || preg_match('/[\x00-\x1F\x7F]/', $fullName) === 1) {
            $error = 'Enter a full name of no more than 120 characters.';
        } elseif (mb_strlen($email) > 120 || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = 'Enter a valid email address (maximum 120 characters).';
        } elseif (!in_array($role, ['admin', 'registrar'], true)) {
            $error = 'Choose Administrator or Registrar staff as the role.';
        } else {
            $temporaryPassword = bin2hex(random_bytes(9));
            try {
                $db->beginTransaction();
                $duplicateEmail = $db->prepare(
                    "SELECT id FROM users
                     WHERE role IN ('admin', 'registrar') AND LOWER(email) = LOWER(:email)
                     LIMIT 1 FOR UPDATE"
                );
                $duplicateEmail->execute(['email' => $email]);
                if ($duplicateEmail->fetchColumn() !== false) {
                    throw new InvalidArgumentException('A personnel account already uses that email address.');
                }
                $username = staff_username_from_email($db, $email);
                $insert = $db->prepare(
                    'INSERT INTO users
                        (username, password_hash, password_changed_at, must_change_password,
                         full_name, email, role, is_active, is_approved)
                     VALUES (:username, :password_hash, NULL, 1, :full_name, :email, :role, 0, 1)'
                );
                $insert->execute([
                    'username' => $username,
                    'password_hash' => password_hash($temporaryPassword, PASSWORD_DEFAULT),
                    'full_name' => $fullName,
                    'email' => $email,
                    'role' => $role,
                ]);
                $staffId = (int) $db->lastInsertId();
                record_audit_event(
                    $db,
                    $actorId,
                    $actorName,
                    'created',
                    'personnel_account',
                    (string) $staffId,
                    'Created a personnel account pending invitation delivery.',
                    ['role' => $role]
                );
                $db->commit();

                deliver_staff_invitation($db, $staffId, $fullName, $email, $role, $temporaryPassword);
                $_SESSION['personnel_notice'] = 'Account created and invitation emailed. The account is active.';
                header('Location: personnel.php');
                exit;
            } catch (InvalidArgumentException $exception) {
                if ($db->inTransaction()) {
                    $db->rollBack();
                }
                $error = $exception->getMessage();
            } catch (Throwable $exception) {
                if ($db->inTransaction()) {
                    $db->rollBack();
                }
                error_log('Personnel account creation failed: ' . $exception->getMessage());
                $error = 'The account could not be created or its invitation could not be delivered. If the account appears as inactive, use Send invitation again after checking SMTP settings.';
            }
        }
    } else {
        http_response_code(400);
        exit('Invalid staff management action.');
    }
}

$staffAccounts = $db->query(
    "SELECT id, username, full_name, email, role, is_active, must_change_password,
            password_changed_at, created_at
     FROM users
     WHERE role IN ('admin', 'registrar')
     ORDER BY CASE role WHEN 'admin' THEN 0 ELSE 1 END, full_name, username"
)->fetchAll();
$escape = static fn ($value): string => htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#146c43">
    <title>Staff management</title>
    <link rel="stylesheet" href="assets/css/login.css">
</head>
<body>
    <main class="portal-shell">
        <header class="portal-header">
            <div>
                <p class="eyebrow">ADMINISTRATION</p>
                <h1>Staff management</h1>
                <p class="intro">Create accounts, review account details, change roles, and manage access.</p>
            </div>
        </header>
        <?php if ($notice !== ''): ?>
            <p class="notice notice-success" role="status"><?= $escape($notice) ?></p>
        <?php endif; ?>
        <?php if ($error !== '' && !$openCreateDialog): ?>
            <p class="notice" role="alert"><?= $escape($error) ?></p>
        <?php endif; ?>
        <section class="portal-panel" aria-labelledby="staff-list-title">
            <div class="staff-panel-heading">
                <div>
                    <h2 id="staff-list-title">Staff accounts</h2>
                    <p class="panel-intro">Select an account card to view its details and management actions.</p>
                </div>
                <button class="table-action-button" type="button" data-open-create-account>
                    Create account
                </button>
            </div>
            <?php if ($staffAccounts === []): ?>
                <p class="empty-state">No staff accounts have been created.</p>
            <?php else: ?>
                <div class="staff-account-grid">
                    <?php foreach ($staffAccounts as $staff): ?>
                        <?php
                        $staffId = (int) $staff['id'];
                        $isCurrentAccount = $staffId === (int) $_SESSION['personnel_id'];
                        $isActive = (int) $staff['is_active'] === 1;
                        $invitationPending = !$isActive && (int) $staff['must_change_password'] === 1;
                        $statusLabel = $isActive
                            ? 'Active'
                            : ($invitationPending ? 'Invitation pending' : 'Deactivated');
                        $roleLabel = $staff['role'] === 'admin' ? 'Administrator' : 'Registrar staff';
                        ?>
                        <details class="staff-account-card">
                            <summary>
                                <span class="staff-card-name"><?= $escape($staff['full_name']) ?></span>
                                <span class="staff-card-role"><?= $escape($roleLabel) ?></span>
                                <span class="staff-status-badge <?= $isActive ? 'is-active' : ($invitationPending ? 'is-pending' : 'is-inactive') ?>">
                                    <?= $escape($statusLabel) ?>
                                </span>
                                <span class="staff-card-prompt">View details</span>
                            </summary>
                            <div class="staff-account-details">
                                <dl class="staff-detail-list">
                                    <div><dt>Full name</dt><dd><?= $escape($staff['full_name']) ?></dd></div>
                                    <div><dt>Email</dt><dd><?= $escape($staff['email']) ?></dd></div>
                                    <div><dt>Username</dt><dd><?= $escape($staff['username']) ?></dd></div>
                                    <div><dt>Role</dt><dd><?= $escape($roleLabel) ?></dd></div>
                                    <div><dt>Status</dt><dd><?= $escape($statusLabel) ?></dd></div>
                                    <div><dt>Created</dt><dd><?= $escape((new DateTimeImmutable((string) $staff['created_at']))->format('M j, Y')) ?></dd></div>
                                    <div><dt>Password</dt><dd>
                                        <?php if ($invitationPending || ($isActive && (int) $staff['must_change_password'] === 1)): ?>
                                            Password change required
                                        <?php elseif ($staff['password_changed_at'] !== null): ?>
                                            Changed <?= $escape((new DateTimeImmutable((string) $staff['password_changed_at']))->format('M j, Y')) ?>
                                        <?php else: ?>
                                            Not changed
                                        <?php endif; ?>
                                    </dd></div>
                                    <?php if ($isCurrentAccount): ?>
                                        <div><dt>Note</dt><dd>This is your account.</dd></div>
                                    <?php endif; ?>
                                </dl>

                                <div class="staff-account-forms">
                                    <form class="staff-management-form" method="post" action="personnel.php">
                                        <input type="hidden" name="csrf_token" value="<?= $escape(csrf_token()) ?>">
                                        <input type="hidden" name="action" value="update_email">
                                        <input type="hidden" name="staff_id" value="<?= $staffId ?>">
                                        <label for="staff-email-<?= $staffId ?>">Update email</label>
                                        <input id="staff-email-<?= $staffId ?>" name="email" type="email"
                                               maxlength="120" value="<?= $escape($staff['email']) ?>" required>
                                        <button class="secondary-button" type="submit">Save email</button>
                                    </form>
                                    <?php if (!$isCurrentAccount): ?>
                                        <form class="staff-management-form" method="post" action="personnel.php">
                                            <input type="hidden" name="csrf_token" value="<?= $escape(csrf_token()) ?>">
                                            <input type="hidden" name="action" value="update_role">
                                            <input type="hidden" name="staff_id" value="<?= $staffId ?>">
                                            <label for="staff-role-<?= $staffId ?>">Change role</label>
                                            <select id="staff-role-<?= $staffId ?>" name="role">
                                                <option value="registrar" <?= $staff['role'] === 'registrar' ? 'selected' : '' ?>>Registrar staff</option>
                                                <option value="admin" <?= $staff['role'] === 'admin' ? 'selected' : '' ?>>Administrator</option>
                                            </select>
                                            <button class="secondary-button" type="submit">Change role</button>
                                        </form>
                                    <?php endif; ?>
                                </div>

                                <div class="staff-account-actions">
                                    <?php if ($invitationPending): ?>
                                        <form method="post" action="personnel.php">
                                            <input type="hidden" name="csrf_token" value="<?= $escape(csrf_token()) ?>">
                                            <input type="hidden" name="action" value="send_invitation">
                                            <input type="hidden" name="staff_id" value="<?= $staffId ?>">
                                            <button class="secondary-button" type="submit">Send invitation again</button>
                                        </form>
                                    <?php elseif ($isActive && !$isCurrentAccount): ?>
                                        <form method="post" action="personnel.php" data-confirm-deactivation>
                                            <input type="hidden" name="csrf_token" value="<?= $escape(csrf_token()) ?>">
                                            <input type="hidden" name="action" value="deactivate">
                                            <input type="hidden" name="staff_id" value="<?= $staffId ?>">
                                            <button class="danger-button" type="submit">Deactivate account</button>
                                        </form>
                                    <?php elseif (!$isActive): ?>
                                        <form method="post" action="personnel.php">
                                            <input type="hidden" name="csrf_token" value="<?= $escape(csrf_token()) ?>">
                                            <input type="hidden" name="action" value="activate">
                                            <input type="hidden" name="staff_id" value="<?= $staffId ?>">
                                            <button class="secondary-button" type="submit">Activate account</button>
                                        </form>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </details>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </section>
    </main>
    <dialog class="workflow-dialog staff-create-dialog" id="create-staff-dialog"
            aria-labelledby="create-staff-title" data-open-on-load="<?= $openCreateDialog ? '1' : '0' ?>">
        <div class="workflow-dialog-heading">
            <div>
                <p class="eyebrow">STAFF MANAGEMENT</p>
                <h2 id="create-staff-title">Create account</h2>
            </div>
            <button class="workflow-dialog-close" type="button" data-close-create-account aria-label="Close create account dialog">×</button>
        </div>
        <?php if ($openCreateDialog && $error !== ''): ?>
            <p class="notice" role="alert"><?= $escape($error) ?></p>
        <?php endif; ?>
        <p class="panel-intro">An invitation with a temporary password will be emailed. The user must change it at first sign-in.</p>
        <form class="staff-create-form" method="post" action="personnel.php">
            <input type="hidden" name="csrf_token" value="<?= $escape(csrf_token()) ?>">
            <input type="hidden" name="action" value="create">
            <label for="staff-full-name">Full name</label>
            <input id="staff-full-name" name="full_name" type="text" maxlength="120" autocomplete="name"
                   value="<?= $escape($createFormValues['full_name']) ?>" required>
            <label for="staff-email">Email address</label>
            <input id="staff-email" name="email" type="email" maxlength="120" autocomplete="email"
                   value="<?= $escape($createFormValues['email']) ?>" required>
            <label for="staff-create-role">Role</label>
            <select id="staff-create-role" name="role" required>
                <option value="registrar" <?= $createFormValues['role'] === 'registrar' ? 'selected' : '' ?>>Registrar staff</option>
                <option value="admin" <?= $createFormValues['role'] === 'admin' ? 'selected' : '' ?>>Administrator</option>
            </select>
            <div class="staff-dialog-actions">
                <button class="secondary-button" type="button" data-close-create-account>Cancel</button>
                <button class="table-action-button" type="submit">Create account</button>
            </div>
        </form>
    </dialog>
    <script>
        const createStaffDialog = document.querySelector('#create-staff-dialog');
        if (createStaffDialog.dataset.openOnLoad === '1') {
            createStaffDialog.showModal();
        }
        document.querySelector('[data-open-create-account]').addEventListener('click', () => {
            createStaffDialog.showModal();
        });
        document.querySelectorAll('[data-close-create-account]').forEach((button) => {
            button.addEventListener('click', () => createStaffDialog.close());
        });
        createStaffDialog.addEventListener('click', (event) => {
            if (event.target === createStaffDialog) {
                createStaffDialog.close();
            }
        });
        document.querySelectorAll('[data-confirm-deactivation]').forEach((form) => {
            form.addEventListener('submit', (event) => {
                if (!window.confirm('Deactivate this account? The user will lose portal access.')) {
                    event.preventDefault();
                }
            });
        });
    </script>
    <script src="assets/js/form-loading.js" defer></script>
</body>
</html>
