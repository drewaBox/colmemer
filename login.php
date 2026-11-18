<?php
declare(strict_types=1);

require __DIR__ . '/config.php';
start_app_session();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    header('Allow: POST');
    exit('Method not allowed.');
}

if (!valid_csrf_token($_POST['csrf_token'] ?? null)) {
    http_response_code(400);
    exit('Invalid form token. Please return to the login page and try again.');
}

$username = trim((string) ($_POST['username'] ?? ''));
$password = (string) ($_POST['password'] ?? '');

if ($username === '' || $password === '') {
    $_SESSION['login_message'] = 'Enter your username and password.';
    header('Location: staff-login.php');
    exit;
}

try {
    $statement = db()->prepare(
        "SELECT id, username, full_name, email, role, password_hash, must_change_password
         FROM users
         WHERE (username = :username OR (role IN ('admin', 'registrar') AND LOWER(email) = LOWER(:email)))
           AND role IN ('admin', 'registrar')
           AND is_active = 1
           AND is_approved = 1
         ORDER BY CASE WHEN username = :username_priority THEN 0 ELSE 1 END, id
         LIMIT 1"
    );
    $statement->execute([
        'username' => $username,
        'email' => $username,
        'username_priority' => $username,
    ]);
    $account = $statement->fetch();
} catch (PDOException $exception) {
    error_log('Admin login database error: ' . $exception->getMessage());
    http_response_code(503);
    exit('Login is temporarily unavailable. Please try again later.');
}

if (!$account || !password_verify($password, $account['password_hash'])) {
    $_SESSION['login_message'] = 'The username or password is incorrect.';
    header('Location: staff-login.php');
    exit;
}

session_regenerate_id(true);
$_SESSION['personnel_id'] = (int) $account['id'];
$_SESSION['personnel_username'] = $account['username'];
$_SESSION['personnel_name'] = $account['full_name'] ?? $account['username'];
$_SESSION['personnel_email'] = (string) ($account['email'] ?? '');
$_SESSION['personnel_role'] = $account['role'] === 'admin' ? 'admin' : 'staff';
$_SESSION['personnel_must_change_password'] = (int) $account['must_change_password'] === 1;
if ($_SESSION['personnel_must_change_password']) {
    header('Location: change-password.php?required=1');
    exit;
}
$_SESSION['auth_success'] = 'Signed in successfully.';
unset($_SESSION['csrf_token']);

header('Location: admin.php');
exit;
