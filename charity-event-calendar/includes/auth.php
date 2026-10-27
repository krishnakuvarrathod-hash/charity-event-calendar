<?php
/**
 * Authentication & Role-Based Access Control (RBAC)
 * Handles session security, login validation, role enforcement, and resource ownership.
 */

// Initialize secure session settings before starting session
if (session_status() === PHP_SESSION_NONE) {
    $secure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ||
              (!empty($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https');
    
    // Check if forced via env
    if (filter_var(getenv('SESSION_SECURE_COOKIE') ?: 'false', FILTER_VALIDATE_BOOLEAN)) {
        $secure = true;
    }

    session_set_cookie_params([
        'lifetime' => 0, // Session ends when browser closes
        'path'     => '/',
        'domain'   => '',
        'secure'   => $secure,
        'httponly' => true,
        'samesite' => 'Lax'
    ]);

    session_start();
}

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/functions.php';

/**
 * Check if a user is currently authenticated
 */
function is_logged_in(): bool
{
    return isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
}

/**
 * Get current authenticated user details from session
 */
function current_user(): ?array
{
    if (!is_logged_in()) {
        return null;
    }
    return [
        'id'        => (int)$_SESSION['user_id'],
        'full_name' => $_SESSION['user_name'] ?? 'User',
        'email'     => $_SESSION['user_email'] ?? '',
        'role'      => $_SESSION['user_role'] ?? 'visitor',
    ];
}

/**
 * Get current authenticated user ID
 */
function current_user_id(): ?int
{
    return is_logged_in() ? (int)$_SESSION['user_id'] : null;
}

/**
 * Get current authenticated user role
 */
function current_user_role(): ?string
{
    return is_logged_in() ? ($_SESSION['user_role'] ?? 'visitor') : null;
}

/**
 * Check if the current user possesses a specific role
 */
function has_role(string|array $roles): bool
{
    if (!is_logged_in()) {
        return false;
    }
    $currentRole = current_user_role();
    if (is_array($roles)) {
        return in_array($currentRole, $roles, true);
    }
    return $currentRole === $roles;
}

/**
 * Require the user to be logged in; redirect to login if not
 */
function require_login(): void
{
    if (!is_logged_in()) {
        set_flash('warning', 'Please sign in to access this page.');
        $redirect = urlencode($_SERVER['REQUEST_URI'] ?? '');
        header("Location: /login.php?redirect={$redirect}");
        exit;
    }
}

/**
 * Require specific role(s). If unauthorized, terminate with 403 or redirect
 */
function require_role(string|array $roles): void
{
    require_login();
    if (!has_role($roles)) {
        http_response_code(403);
        set_flash('danger', 'Access denied. You do not have permission to view this resource.');
        header('Location: /index.php');
        exit;
    }
}

/**
 * Fetch charity associated with an organizer
 */
function get_organizer_charity(PDO $pdo, int $userId): ?array
{
    $stmt = $pdo->prepare('SELECT * FROM charities WHERE user_id = :user_id LIMIT 1');
    $stmt->execute(['user_id' => $userId]);
    $charity = $stmt->fetch();
    return $charity ?: null;
}

/**
 * Strictly verify that an organizer owns the charity hosting the given event
 * Administrators always have permission.
 */
function verify_event_ownership(PDO $pdo, int $eventId, int $userId): bool
{
    if (has_role('admin')) {
        return true;
    }

    $stmt = $pdo->prepare('
        SELECT e.id 
        FROM events e
        INNER JOIN charities c ON e.charity_id = c.id
        WHERE e.id = :event_id AND c.user_id = :user_id
        LIMIT 1
    ');
    $stmt->execute([
        'event_id' => $eventId,
        'user_id'  => $userId
    ]);
    return (bool)$stmt->fetch();
}

/**
 * Establish authenticated session for user
 */
function login_user(array $user): void
{
    session_regenerate_id(true);
    $_SESSION['user_id'] = (int)$user['id'];
    $_SESSION['user_name'] = $user['full_name'];
    $_SESSION['user_email'] = $user['email'];
    $_SESSION['user_role'] = $user['role'];
    regenerate_csrf_token();
}

/**
 * Terminate session securely
 */
function logout_user(): void
{
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(
            session_name(),
            '',
            time() - 42000,
            $params['path'],
            $params['domain'],
            $params['secure'],
            $params['httponly']
        );
    }
    session_destroy();
}
