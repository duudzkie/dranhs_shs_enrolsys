<?php
/**
 * Shared session bootstrap for local XAMPP and Railway (HTTPS behind proxy).
 */
function ems2_session_start(): void
{
    if (session_status() !== PHP_SESSION_NONE) {
        return;
    }

    $is_https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && strtolower((string) $_SERVER['HTTP_X_FORWARDED_PROTO']) === 'https')
        || (getenv('RAILWAY_ENVIRONMENT') !== false);

    if ($is_https) {
        ini_set('session.cookie_secure', '1');
    }

    ini_set('session.cookie_samesite', 'Lax');
    ini_set('session.use_strict_mode', '1');
    ini_set('session.cookie_httponly', '1');

    $save_path = sys_get_temp_dir() . '/ems2_sessions';
    if (!is_dir($save_path)) {
        @mkdir($save_path, 0770, true);
    }
    if (is_dir($save_path) && is_writable($save_path)) {
        session_save_path($save_path);
    }

    session_start();
}

function ems2_login_redirect(string $query = ''): void
{
    // Determine if we are currently in a subdirectory (like admin/)
    $in_admin = strpos($_SERVER['SCRIPT_NAME'], '/admin/') !== false;
    $prefix = $in_admin ? '../' : '';
    $url = $prefix . 'index.php' . ($query !== '' ? '?' . ltrim($query, '?') : '');
    header('Location: ' . $url);
    exit;
}
