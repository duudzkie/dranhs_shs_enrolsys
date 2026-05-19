<?php
require_once __DIR__ . '/session.php';
ems2_session_start();

// Clear session data
$_SESSION = [];

// Destroy session cookie (if used)
if (ini_get('session.use_cookies')) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params['path'], $params['domain'], $params['secure'], $params['httponly']
    );
}

session_destroy();

// Redirect back to the login page (preserve timeout notice when applicable)
$query = isset($_GET['timeout']) ? 'timeout=1' : '';
ems2_login_redirect($query);
