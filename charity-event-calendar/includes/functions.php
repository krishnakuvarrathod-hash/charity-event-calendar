<?php
/**
 * Shared Helper Functions
 * Formatting, sanitization, flash messaging, rate limiting, and badge rendering.
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/csrf.php';

/**
 * Escape HTML output for XSS prevention
 */
function e(?string $value): string
{
    return htmlspecialchars($value ?? '', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/**
 * Format date and time
 */
function format_datetime(?string $datetime, string $format = 'M j, Y \a\t g:i A'): string
{
    if (empty($datetime)) {
        return 'N/A';
    }
    try {
        $dt = new DateTime($datetime);
        return $dt->format($format);
    } catch (Exception $e) {
        return e($datetime);
    }
}

/**
 * Format date only
 */
function format_date(?string $datetime, string $format = 'M j, Y'): string
{
    return format_datetime($datetime, $format);
}

/**
 * Format time only
 */
function format_time(?string $datetime, string $format = 'g:i A'): string
{
    return format_datetime($datetime, $format);
}

/**
 * Render Bootstrap badge for event status
 */
function render_status_badge(string $status): string
{
    $status = strtolower($status);
    switch ($status) {
        case 'approved':
            return '<span class="badge bg-success"><i class="bi bi-check-circle me-1"></i>Approved</span>';
        case 'pending':
            return '<span class="badge bg-warning text-dark"><i class="bi bi-hourglass-split me-1"></i>Pending Review</span>';
        case 'rejected':
            return '<span class="badge bg-danger"><i class="bi bi-x-circle me-1"></i>Rejected</span>';
        case 'cancelled':
            return '<span class="badge bg-secondary"><i class="bi bi-slash-circle me-1"></i>Cancelled</span>';
        case 'confirmed':
            return '<span class="badge bg-success"><i class="bi bi-check2 me-1"></i>Confirmed</span>';
        default:
            return '<span class="badge bg-info">' . e(ucfirst($status)) . '</span>';
    }
}

/**
 * Render category badge
 */
function render_category_badge(string $category): string
{
    $colors = [
        'Fundraising' => 'primary',
        'Food Donation' => 'success',
        'Volunteering' => 'info',
        'Health Drive' => 'danger',
        'Community Activity' => 'warning',
        'Education & Mentorship' => 'secondary',
    ];
    $color = $colors[$category] ?? 'dark';
    return '<span class="badge bg-' . $color . '-subtle text-' . $color . ' border border-' . $color . '-subtle">' . e($category) . '</span>';
}

/**
 * Flash messaging system
 */
function set_flash(string $type, string $message): void
{
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    $_SESSION['flash_messages'][] = [
        'type' => $type, // success, danger, warning, info
        'message' => $message,
    ];
}

/**
 * Retrieve and clear flash messages
 */
function get_flashes(): array
{
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    $flashes = $_SESSION['flash_messages'] ?? [];
    unset($_SESSION['flash_messages']);
    return $flashes;
}

/**
 * Standard categories list
 */
function get_standard_categories(): array
{
    return [
        'Fundraising',
        'Food Donation',
        'Volunteering',
        'Health Drive',
        'Community Activity',
        'Education & Mentorship'
    ];
}

/**
 * Rate Limiting: Check failed login attempts
 * Max 5 failed attempts in the last 15 minutes per IP/email
 */
function is_login_rate_limited(PDO $pdo, string $ip, string $email): bool
{
    try {
        $stmt = $pdo->prepare('
            SELECT COUNT(*) AS failed_count 
            FROM login_attempts 
            WHERE (ip_address = :ip OR email = :email) 
              AND attempt_time > (NOW() - INTERVAL 15 MINUTE)
        ');
        $stmt->execute(['ip' => $ip, 'email' => $email]);
        $row = $stmt->fetch();
        return ($row && $row['failed_count'] >= 5);
    } catch (\Throwable $e) {
        // If login_attempts table doesn't exist yet, do not block login
        return false;
    }
}

/**
 * Record a failed login attempt
 */
function record_failed_login(PDO $pdo, string $ip, string $email): void
{
    try {
        $stmt = $pdo->prepare('INSERT INTO login_attempts (ip_address, email) VALUES (:ip, :email)');
        $stmt->execute(['ip' => $ip, 'email' => $email]);
    } catch (\Throwable $e) {
        error_log('Failed to record login attempt: ' . $e->getMessage());
    }
}

/**
 * Clear failed login attempts on successful authentication
 */
function clear_failed_logins(PDO $pdo, string $ip, string $email): void
{
    try {
        $stmt = $pdo->prepare('DELETE FROM login_attempts WHERE ip_address = :ip OR email = :email');
        $stmt->execute(['ip' => $ip, 'email' => $email]);
    } catch (\Throwable $e) {
        // Fail silently
    }
}

/**
 * Safe image fallback
 */
function get_event_image(?string $url, string $category = 'Community'): string
{
    if (!empty($url) && filter_var($url, FILTER_VALIDATE_URL)) {
        return e($url);
    }
    
    // Categorized high-quality reliable Unsplash image fallbacks
    $placeholders = [
        'Fundraising' => 'https://images.unsplash.com/photo-1532629345422-7515f3d16bb9?auto=format&fit=crop&w=800&q=80',
        'Food Donation' => 'https://images.unsplash.com/photo-1593113598332-cd288d649433?auto=format&fit=crop&w=800&q=80',
        'Volunteering' => 'https://images.unsplash.com/photo-1559027615-cd4628902d4a?auto=format&fit=crop&w=800&q=80',
        'Health Drive' => 'https://images.unsplash.com/photo-1576091160399-112ba8d25d1d?auto=format&fit=crop&w=800&q=80',
        'Community Activity' => 'https://images.unsplash.com/photo-1511632765486-a01980e01a18?auto=format&fit=crop&w=800&q=80',
        'Education & Mentorship' => 'https://images.unsplash.com/photo-1509062522246-3755977927d7?auto=format&fit=crop&w=800&q=80',
    ];

    return $placeholders[$category] ?? 'https://images.unsplash.com/photo-1469571486292-0ba58a3f068b?auto=format&fit=crop&w=800&q=80';
}
