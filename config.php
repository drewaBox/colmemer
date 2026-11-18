<?php
declare(strict_types=1);

function local_app_config(): array
{
    static $config = null;
    if ($config !== null) {
        return $config;
    }

    $path = __DIR__ . '/config.local.php';
    $config = is_file($path) ? require $path : [];
    if (!is_array($config)) {
        throw new RuntimeException('config.local.php must return an array of application settings.');
    }

    return $config;
}

function app_setting(string $key, mixed $default = null): mixed
{
    $environmentValue = getenv($key);
    if ($environmentValue !== false) {
        return $environmentValue;
    }

    return local_app_config()[$key] ?? $default;
}

function db(): PDO
{
    static $connection = null;
    if ($connection instanceof PDO) {
        return $connection;
    }

    $settings = [];
    foreach (['DB_HOST', 'DB_PORT', 'DB_NAME', 'DB_USER', 'DB_PASSWORD'] as $key) {
        $settings[$key] = app_setting($key);
    }
    foreach (['DB_HOST', 'DB_NAME', 'DB_USER', 'DB_PASSWORD'] as $key) {
        if (!is_string($settings[$key])) {
            throw new RuntimeException(
                'Database configuration is missing. Set ' . $key . ' in config.local.php or the PHP environment.'
            );
        }
    }
    if ($settings['DB_PORT'] === null || $settings['DB_PORT'] === '') {
        $settings['DB_PORT'] = 3306;
    }
    $port = filter_var($settings['DB_PORT'], FILTER_VALIDATE_INT);
    if (!is_int($port) || $port < 1 || $port > 65535) {
        throw new RuntimeException('DB_PORT must be a valid TCP port number.');
    }

    $connection = new PDO(
        "mysql:host={$settings['DB_HOST']};port={$port};dbname={$settings['DB_NAME']};charset=utf8mb4",
        $settings['DB_USER'],
        $settings['DB_PASSWORD'],
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]
    );

    return $connection;
}

function start_app_session(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        return;
    }

    session_set_cookie_params([
        'httponly' => true,
        'secure' => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
        'samesite' => 'Lax',
    ]);
    session_start();
}

function csrf_token(): string
{
    if (!isset($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }

    return $_SESSION['csrf_token'];
}

function valid_csrf_token(?string $token): bool
{
    return is_string($token)
        && isset($_SESSION['csrf_token'])
        && hash_equals($_SESSION['csrf_token'], $token);
}

function require_personnel(array $allowedRoles = ['admin', 'staff'], bool $allowForcedPasswordChange = false): void
{
    start_app_session();

    if (!isset($_SESSION['personnel_id'], $_SESSION['personnel_role'])) {
        header('Location: staff-login.php');
        exit;
    }

    $personnelId = filter_var($_SESSION['personnel_id'], FILTER_VALIDATE_INT);
    if (!is_int($personnelId) || $personnelId < 1) {
        $_SESSION = [];
        session_regenerate_id(true);
        header('Location: staff-login.php');
        exit;
    }

    $accountQuery = db()->prepare(
        "SELECT role, is_active, is_approved, must_change_password
         FROM users
         WHERE id = :id AND role IN ('admin', 'registrar')
         LIMIT 1"
    );
    $accountQuery->execute(['id' => $personnelId]);
    $account = $accountQuery->fetch();
    if (!$account || (int) $account['is_active'] !== 1 || (int) $account['is_approved'] !== 1) {
        $_SESSION = [];
        session_regenerate_id(true);
        header('Location: staff-login.php');
        exit;
    }

    $_SESSION['personnel_role'] = $account['role'] === 'admin' ? 'admin' : 'staff';
    $_SESSION['personnel_must_change_password'] = (int) $account['must_change_password'];

    if (!in_array($_SESSION['personnel_role'], $allowedRoles, true)) {
        http_response_code(403);
        exit('You do not have permission to access this page.');
    }

    if (!$allowForcedPasswordChange && !empty($_SESSION['personnel_must_change_password'])) {
        header('Location: change-password.php?required=1');
        exit;
    }
}

function verify_personnel_password(PDO $connection, string $password): bool
{
    $personnelId = filter_var($_SESSION['personnel_id'] ?? null, FILTER_VALIDATE_INT);
    if (!is_int($personnelId) || $personnelId < 1 || $password === '') {
        return false;
    }

    $statement = $connection->prepare(
        "SELECT password_hash
         FROM users
         WHERE id = :id
           AND role IN ('admin', 'registrar')
           AND is_active = 1
           AND is_approved = 1
         LIMIT 1"
    );
    $statement->execute(['id' => $personnelId]);
    $passwordHash = $statement->fetchColumn();

    return is_string($passwordHash) && password_verify($password, $passwordHash);
}
