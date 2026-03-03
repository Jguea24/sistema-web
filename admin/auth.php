<?php

declare(strict_types=1);

function start_admin_session(): void
{
    if (session_status() === PHP_SESSION_NONE) {
        $isHttps = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';
        if (!headers_sent()) {
            session_set_cookie_params([
                'lifetime' => 0,
                'path' => '/',
                'secure' => $isHttps,
                'httponly' => true,
                'samesite' => 'Lax',
            ]);
        }
        session_name('sistema_web_admin');
        session_start();
    }
}

function is_admin_logged_in(): bool
{
    start_admin_session();
    return isset($_SESSION['admin_user']) && is_array($_SESSION['admin_user']);
}

function get_admin_user_name(): string
{
    start_admin_session();
    return (string) ($_SESSION['admin_user']['nombre'] ?? 'Administrador');
}

function get_admin_user_role(): string
{
    start_admin_session();
    return (string) ($_SESSION['admin_user']['rol'] ?? 'recepcion');
}

function admin_must_change_password(): bool
{
    start_admin_session();
    return (bool) ($_SESSION['admin_user']['debe_cambiar_password'] ?? false);
}

/**
 * @param array<int, string> $roles
 */
function has_any_admin_role(array $roles): bool
{
    $role = get_admin_user_role();
    return in_array($role, $roles, true);
}

function require_admin_login(): void
{
    if (!is_admin_logged_in()) {
        header('Location: login.php');
        exit;
    }

    $currentPage = basename((string) ($_SERVER['PHP_SELF'] ?? ''));
    if (
        admin_must_change_password()
        && !in_array($currentPage, ['cambiar_password.php', 'logout.php'], true)
    ) {
        header('Location: cambiar_password.php');
        exit;
    }
}

/**
 * @param array<int, string> $roles
 */
function require_admin_roles(array $roles): void
{
    require_admin_login();
    if (!has_any_admin_role($roles)) {
        http_response_code(403);
        exit('No tienes permisos para acceder a este modulo.');
    }
}

function admin_login(PDO $pdo, string $email, string $password): bool
{
    start_admin_session();

    $stmt = $pdo->prepare(
        'SELECT id, nombre, email, password_hash, rol, debe_cambiar_password, activo
         FROM usuarios_admin
         WHERE email = ?
         LIMIT 1'
    );
    $stmt->execute([$email]);
    $user = $stmt->fetch();

    if (!$user || (int) $user['activo'] !== 1 || !password_verify($password, (string) $user['password_hash'])) {
        return false;
    }

    session_regenerate_id(true);

    $_SESSION['admin_user'] = [
        'id' => (int) $user['id'],
        'nombre' => (string) $user['nombre'],
        'email' => (string) $user['email'],
        'rol' => (string) $user['rol'],
        'debe_cambiar_password' => (int) $user['debe_cambiar_password'] === 1,
    ];

    $update = $pdo->prepare('UPDATE usuarios_admin SET ultimo_login_en = NOW() WHERE id = ?');
    $update->execute([(int) $user['id']]);

    return true;
}

function admin_login_by_id(PDO $pdo, int $userId): bool
{
    start_admin_session();

    if ($userId <= 0) {
        return false;
    }

    $stmt = $pdo->prepare(
        'SELECT id, nombre, email, rol, debe_cambiar_password, activo
         FROM usuarios_admin
         WHERE id = ?
         LIMIT 1'
    );
    $stmt->execute([$userId]);
    $user = $stmt->fetch();

    if (!$user || (int) $user['activo'] !== 1) {
        return false;
    }

    session_regenerate_id(true);

    $_SESSION['admin_user'] = [
        'id' => (int) $user['id'],
        'nombre' => (string) $user['nombre'],
        'email' => (string) $user['email'],
        'rol' => (string) $user['rol'],
        'debe_cambiar_password' => (int) $user['debe_cambiar_password'] === 1,
    ];

    $update = $pdo->prepare('UPDATE usuarios_admin SET ultimo_login_en = NOW() WHERE id = ?');
    $update->execute([(int) $user['id']]);

    return true;
}

function admin_change_password(PDO $pdo, int $userId, string $newPassword): bool
{
    $hash = password_hash($newPassword, PASSWORD_DEFAULT);
    $stmt = $pdo->prepare(
        'UPDATE usuarios_admin
         SET password_hash = ?, debe_cambiar_password = 0
         WHERE id = ?'
    );

    $ok = $stmt->execute([$hash, $userId]);
    if ($ok) {
        start_admin_session();
        if (isset($_SESSION['admin_user'])) {
            $_SESSION['admin_user']['debe_cambiar_password'] = false;
        }
    }

    return $ok;
}

function admin_logout(): void
{
    start_admin_session();
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], $params['secure'], $params['httponly']);
    }
    session_destroy();
}

function csrf_token(): string
{
    start_admin_session();
    if (!isset($_SESSION['csrf_token']) || !is_string($_SESSION['csrf_token']) || $_SESSION['csrf_token'] === '') {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }

    return (string) $_SESSION['csrf_token'];
}

function csrf_input(): string
{
    return '<input type="hidden" name="_csrf" value="' . htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8') . '">';
}

function is_valid_csrf_token(?string $token): bool
{
    start_admin_session();
    $sessionToken = $_SESSION['csrf_token'] ?? '';
    return is_string($token) && is_string($sessionToken) && $sessionToken !== '' && hash_equals($sessionToken, $token);
}

function require_csrf_post(): void
{
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        return;
    }

    $token = isset($_POST['_csrf']) ? (string) $_POST['_csrf'] : null;
    if (!is_valid_csrf_token($token)) {
        http_response_code(419);
        exit('Solicitud invalida (CSRF). Recarga la pagina e intenta nuevamente.');
    }
}
