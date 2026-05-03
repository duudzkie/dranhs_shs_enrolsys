<?php
if (session_status() === PHP_SESSION_NONE) session_start();

// Only auto-redirect if session is fully valid
if (isset($_SESSION['user_id']) && isset($_SESSION['logged_in']) && $_SESSION['logged_in'] === true) {
    // Verify user still exists in DB before redirecting
    $conn = new mysqli('localhost', 'root', '', 'dranhswin');
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
    session_start();
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    //
    // DATABASE CONNECTION
    //
    $db_host = 'localhost';
    $db_user = 'root';
    $db_pass = '';
    $db_name = 'dranhswin';

    $conn = new mysqli($db_host, $db_user, $db_pass, $db_name);
    if ($conn->connect_error) {
        die("Connection failed: " . $conn->connect_error);
    }

    $username = $_POST['username'];
    $password = $_POST['password'];

    $sql = "SELECT * FROM users WHERE username = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("s", $username);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        $user = $result->fetch_assoc();
        if (password_verify($password, $user['password'])) {
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['role'] = $user['role'];
            $_SESSION['logged_in'] = true;

            // If adviser, store their linked adviser_id and section
            if ($user['role'] === 'adviser') {
                // Ensure user_id column exists (MySQL 5.7 safe)
                $chk = $conn->query("SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA='dranhswin' AND TABLE_NAME='advisers_accounts' AND COLUMN_NAME='user_id'");
                if ($chk && $chk->num_rows === 0) {
                    $conn->query("ALTER TABLE advisers_accounts ADD COLUMN user_id INT NULL");
                }
                $adv_stmt = $conn->prepare("SELECT a.id AS adviser_id, c.section_name, c.id AS classroom_id
                    FROM advisers_accounts a
                    LEFT JOIN classrooms c ON c.adviser_id = a.id
                    WHERE a.user_id = ? LIMIT 1");
                if ($adv_stmt) {
                    $adv_stmt->bind_param("i", $user['id']);
                    $adv_stmt->execute();
                    $adv_row = $adv_stmt->get_result()->fetch_assoc();
                    $adv_stmt->close();
                    $_SESSION['adviser_id']      = $adv_row['adviser_id']   ?? null;
                    $_SESSION['adviser_section'] = $adv_row['section_name'] ?? null;
                    $_SESSION['adviser_classroom_id'] = $adv_row['classroom_id'] ?? null;
                }
            }

            header('Location: admin/admin.php');
            exit;
        } else {
            // Wrong password — redirect back to index with error
            $stmt->close();
            $conn->close();
            header('Location: index.php?auth_error=pass');
            exit;
        }
    } else {
        // User not found — redirect back to index with error
        $stmt->close();
        $conn->close();
        header('Location: index.php?auth_error=user');
        exit;
    }

    $stmt->close();
    $conn->close();
}