<?php
/**
 * CSRF Protection Utility
 * Provides cryptographic token generation, form insertion, and request validation.
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/**
 * Get current CSRF token, generating a new one if missing
 */
function get_csrf_token(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/**
 * Output HTML hidden input with CSRF token
 */
function csrf_field(): string
{
    $token = htmlspecialchars(get_csrf_token(), ENT_QUOTES, 'UTF-8');
    return '<input type="hidden" name="csrf_token" value="' . $token . '">';
}

/**
 * Validate CSRF token from incoming POST request
 * Throws Exception or returns false if invalid
 */
function validate_csrf(?string $token = null): bool
{
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }

    $submitted = $token ?? ($_POST['csrf_token'] ?? '');
    $stored = $_SESSION['csrf_token'] ?? '';

    if (empty($submitted) || empty($stored)) {
        return false;
    }

    return hash_equals($stored, $submitted);
}

/**
 * Regenerate CSRF token (useful after login/logout or critical actions)
 */
function regenerate_csrf_token(): string
{
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    return $_SESSION['csrf_token'];
}
