<?php
require_once __DIR__ . '/session.php';
ems2_session_start();
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/activity_log.php';

// Log logout before clearing session
$_logout_conn = db_connect();
if (!$_logout_conn->connect_error) {
    $timeout = isset($_GET['timeout']) ? ' (session timeout)' : '';
    log_activity($_logout_conn, 'logout', 'User logged out' . $timeout);
    $_logout_conn->close();
}

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
