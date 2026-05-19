<?php
require_once __DIR__ . '/session.php';
ems2_session_start();
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/activity_log.php';

// Login form lives on index.php; this script only handles POST authentication.
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    $query = $_SERVER['QUERY_STRING'] ?? '';
    ems2_login_redirect($query);
}

// Only auto-redirect if session is fully valid
if (isset($_SESSION['user_id']) && isset($_SESSION['logged_in']) && $_SESSION['logged_in'] === true) {
    // Verify user still exists in DB before redirecting
    $conn = db_connect();
    if (!$conn->connect_error) {
        $chk = $conn->prepare("SELECT id FROM users WHERE id = ? LIMIT 1");
        $chk->bind_param("i", $_SESSION['user_id']);
        $chk->execute();
        $valid = $chk->get_result()->num_rows > 0;
        $chk->close();
        $conn->close();
        if ($valid) {
            header('Location: admin/admin.php');
            exit;
        }
    }
    // Invalid session — destroy and continue to login form
    session_destroy();
    ems2_session_start();
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $conn = db_connect();

    $login_input = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    // Support login by username OR email
    $sql = "SELECT * FROM users WHERE username = ? OR email = ? LIMIT 1";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ss", $login_input, $login_input);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        $user = $result->fetch_assoc();

        // Check if account is disabled
        if (($user['status'] ?? 'active') === 'disabled') {
            log_activity($conn, 'login_failed', 'Disabled account attempted login: ' . $login_input, 'user', (int)$user['id']);
            $stmt->close();
            $conn->close();
            header('Location: index.php?auth_error=disabled');
            exit;
        }

        if (password_verify($password, $user['password'])) {
            // Build role string for backward compat
            $user_roles = trim($user['roles'] ?? '');
            $is_admin = (int)($user['is_admin'] ?? 0);
            $role_display = $is_admin ? 'admin' : ($user_roles ?: 'faculty');

            $_SESSION['user_id']   = $user['id'];
            $_SESSION['username']  = $user['username'];
            $_SESSION['full_name'] = $user['full_name'] ?? $user['username'];
            $_SESSION['role']      = $role_display;    // backward compat
            $_SESSION['roles']     = $user_roles;      // new: comma-separated
            $_SESSION['is_admin']  = $is_admin;        // new: admin flag
            $_SESSION['logged_in'] = true;
            session_regenerate_id(true);
            $_SESSION['_last_activity'] = time();

            // Log successful login
            log_activity($conn, 'login', 'User logged in (' . $role_display . ')', 'user', (int)$user['id']);

            // Check if user is assigned as adviser to any classroom
            $adv_stmt = $conn->prepare("SELECT id AS classroom_id, section_name
                FROM classrooms WHERE adviser_id = ? LIMIT 1");
            if ($adv_stmt) {
                $adv_stmt->bind_param("i", $user['id']);
                $adv_stmt->execute();
                $adv_row = $adv_stmt->get_result()->fetch_assoc();
                $adv_stmt->close();
                $_SESSION['adviser_section']      = $adv_row['section_name'] ?? null;
                $_SESSION['adviser_classroom_id'] = $adv_row['classroom_id'] ?? null;
            }

            header('Location: admin/admin.php');
            exit;
        } else {
            // Wrong password — redirect back to index with error
            $_SESSION['username'] = $login_input; // temp for logging
            log_activity($conn, 'login_failed', 'Wrong password for user: ' . $login_input, 'user', (int)$user['id']);
            unset($_SESSION['username']);
            $stmt->close();
            $conn->close();
            header('Location: index.php?auth_error=pass');
            exit;
        }
    } else {
        // User not found — redirect back to index with error
        log_activity($conn, 'login_failed', 'Unknown login attempted: ' . $login_input);
        $stmt->close();
        $conn->close();
        header('Location: index.php?auth_error=user');
        exit;
    }

    $stmt->close();
    $conn->close();
}