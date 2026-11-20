<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/config/database.php';
require_once dirname(__DIR__) . '/includes/layout_themes.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start([
        'cookie_httponly' => true,
        'cookie_samesite' => 'Lax',
        'use_strict_mode' => true,
    ]);
}

function e(?string $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function redirect(string $path): never
{
    header('Location: ' . base_url($path));
    exit;
}

function is_logged_in(): bool
{
    return !empty($_SESSION['user_id']);
}

function current_user(): ?array
{
    if (!is_logged_in()) {
        return null;
    }

    static $user = null;

    if ($user !== null) {
        return $user;
    }

    $stmt = db()->prepare(
        'SELECT UserID, FullName, Username, Email, UserType, Status
         FROM Users
         WHERE UserID = :id
         LIMIT 1'
    );
    $stmt->execute(['id' => (int) $_SESSION['user_id']]);
    $user = $stmt->fetch() ?: null;

    if (!$user || ($user['Status'] ?? '') !== 'Active') {
        logout_user();
        return null;
    }

    return $user;
}

function require_login(): void
{
    if (!is_logged_in()) {
        $_SESSION['redirect_after_login'] = $_SERVER['REQUEST_URI'] ?? base_url('dashboard/');
        redirect('login.php');
    }

    if (current_user() === null) {
        redirect('login.php');
    }
}

function logout_user(): void
{
    $_SESSION = [];

    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], $params['secure'], $params['httponly']);
    }

    session_destroy();
}

function flash_set(string $type, string $message): void
{
    $_SESSION['flash'] = ['type' => $type, 'message' => $message];
}

function flash_get(): ?array
{
    if (empty($_SESSION['flash'])) {
        return null;
    }

    $flash = $_SESSION['flash'];
    unset($_SESSION['flash']);

    return $flash;
}

function csrf_token(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }

    return $_SESSION['csrf_token'];
}

function csrf_verify(?string $token): bool
{
    return is_string($token)
        && isset($_SESSION['csrf_token'])
        && hash_equals($_SESSION['csrf_token'], $token);
}

function format_money($amount): string
{
    return number_format((float) $amount, 2);
}

function format_datetime(?string $value, string $format = 'd M Y, h:i A'): string
{
    if (!$value) {
        return '—';
    }

    $ts = strtotime($value);
    return $ts ? date($format, $ts) : e($value);
}

function format_date(?string $value, string $format = 'd M Y'): string
{
    return format_datetime($value, $format);
}

function nav_active(string $key, string $current): string
{
    return $key === $current ? 'active' : '';
}

/**
 * Resolve top-level module section from a page nav key.
 */
function resolve_nav_section(string $currentNav): string
{
    $exact = [
        'dashboard' => 'home',
        'home' => 'home',
        'properties' => 'property',
        'blocks' => 'property',
        'floors' => 'property',
        'units' => 'property',
    ];

    if (isset($exact[$currentNav])) {
        return $exact[$currentNav];
    }

    $prefixes = [
        'resident' => 'resident',
        'billing' => 'billing',
        'visitor' => 'visitor',
        'service' => 'service',
        'complaint' => 'complaint',
        'coord' => 'coord',
        'report' => 'report',
        'user' => 'user',
    ];

    foreach ($prefixes as $prefix => $section) {
        if (str_starts_with($currentNav, $prefix)) {
            return $section;
        }
    }

    return $currentNav;
}

function nav_section_active(string $section, string $currentNav): string
{
    return resolve_nav_section($currentNav) === $section ? 'active' : '';
}

function users_exist(): bool
{
    return (int) db()->query('SELECT COUNT(*) FROM Users')->fetchColumn() > 0;
}

/**
 * Safe query helper that returns a fallback on failure (logs error).
 */
function safe_query(callable $callback, $fallback = null)
{
    try {
        return $callback(db());
    } catch (Throwable $e) {
        error_log('Query error: ' . $e->getMessage());
        return $fallback;
    }
}
