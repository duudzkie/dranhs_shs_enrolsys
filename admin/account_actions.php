<?php
/**
 * Account backend actions — handles all POST operations for accounts.
 * Included by account.php.
 */
require_once __DIR__ . '/../activity_log.php';
require_once __DIR__ . '/../mailer.php';

$toast_message = '';
$toast_type = 'success';

// Ensure uploads dir
$upload_dir = __DIR__ . '/../uploads/avatars/';
if (!is_dir($upload_dir)) mkdir($upload_dir, 0755, true);

$current_user_id = (int)($_SESSION['user_id'] ?? 0);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $action = $_POST['action'];
    $admin_pass = $_POST['admin_password'] ?? '';

    // Verify admin password
    $pw_stmt = $conn->prepare("SELECT password, is_admin FROM users WHERE id = ? LIMIT 1");
    $pw_stmt->bind_param("i", $current_user_id);
    $pw_stmt->execute();
    $pw_row = $pw_stmt->get_result()->fetch_assoc();
    $pw_stmt->close();

    if (!$pw_row || !password_verify($admin_pass, $pw_row['password'])) {
        $toast_message = 'Invalid admin password.';
        $toast_type = 'error';
    } else {

        // ── CREATE ACCOUNT ─────────────────────────────────────────
        if ($action === 'create_account') {
            $full_name = trim($_POST['full_name'] ?? '');
            $username  = trim($_POST['new_username'] ?? '');
            $email     = trim($_POST['email'] ?? '') ?: null;
            $password  = $_POST['new_password'] ?? '';
            $is_admin  = isset($_POST['is_admin']) ? 1 : 0;
            $role_arr  = $_POST['roles'] ?? [];
            $roles_str = implode(',', array_filter($role_arr));
            $is_adviser = isset($_POST['is_adviser']) ? 1 : 0;
            $adv_classroom = (int)($_POST['adviser_classroom_id'] ?? 0);

            if ($full_name === '' || $username === '' || $password === '') {
                $toast_message = 'Full name, username, and password are required.';
                $toast_type = 'error';
            } elseif (strlen($password) < 8) {
                $toast_message = 'Password must be at least 8 characters.';
                $toast_type = 'error';
            } else {
                // Check duplicate username/email
                $dup = $conn->prepare("SELECT id FROM users WHERE username = ? OR (email IS NOT NULL AND email = ? AND ? IS NOT NULL) LIMIT 1");
                $dup->bind_param("sss", $username, $email, $email);
                $dup->execute();
                if ($dup->get_result()->num_rows > 0) {
                    $toast_message = 'Username or email already exists.';
                    $toast_type = 'error';
                } else {
                    $hashed = password_hash($password, PASSWORD_DEFAULT);
                    // Handle avatar upload
                    $avatar_path = null;
                    if (isset($_FILES['avatar']) && $_FILES['avatar']['error'] === 0) {
                        $ext = strtolower(pathinfo($_FILES['avatar']['name'], PATHINFO_EXTENSION));
                        if (in_array($ext, ['jpg','jpeg','png','webp']) && $_FILES['avatar']['size'] <= 5*1024*1024) {
                            $fname = 'user_' . time() . '_' . rand(1000,9999) . '.' . $ext;
                            move_uploaded_file($_FILES['avatar']['tmp_name'], $upload_dir . $fname);
                            $avatar_path = 'uploads/avatars/' . $fname;
                        }
                    }
                    $stmt = $conn->prepare("INSERT INTO users (username, email, password, full_name, avatar, roles, is_admin, status) VALUES (?,?,?,?,?,?,?,'active')");
                    $stmt->bind_param("ssssssi", $username, $email, $hashed, $full_name, $avatar_path, $roles_str, $is_admin);
                    if ($stmt->execute()) {
                        $new_id = $conn->insert_id;
                        // Link as adviser if checked
                        if ($is_adviser && $adv_classroom > 0) {
                            $adv_upd = $conn->prepare("UPDATE classrooms SET adviser_id = ?, adviser_name = ? WHERE id = ?");
                            $adv_upd->bind_param("isi", $new_id, $full_name, $adv_classroom);
                            $adv_upd->execute();
                            $adv_upd->close();
                        }
                        log_activity($conn, 'user_created', 'Created account: ' . $username . ' (' . $full_name . ')', 'user', $new_id);
                        $toast_message = 'Account created successfully.';
                        // Store for email send
                        $_SESSION['_last_created_user'] = [
                            'id' => $new_id, 'username' => $username, 'password' => $password,
                            'email' => $email, 'full_name' => $full_name, 'roles' => $roles_str
                        ];
                    } else {
                        $toast_message = 'Error creating account.';
                        $toast_type = 'error';
                    }
                    $stmt->close();
                }
                $dup->close();
            }

        // ── EDIT ACCOUNT ──────────────────────────────────────────
        } elseif ($action === 'edit_account') {
            $target_id = (int)($_POST['target_user_id'] ?? 0);
            $full_name = trim($_POST['full_name'] ?? '');
            $username  = trim($_POST['new_username'] ?? '');
            $email     = trim($_POST['email'] ?? '') ?: null;
            $new_pass  = $_POST['new_password'] ?? '';
            $is_admin  = isset($_POST['is_admin']) ? 1 : 0;
            $role_arr  = $_POST['roles'] ?? [];
            $roles_str = implode(',', array_filter($role_arr));
            $is_adviser = isset($_POST['is_adviser']) ? 1 : 0;
            $adv_classroom = (int)($_POST['adviser_classroom_id'] ?? 0);

            if ($target_id > 0 && $full_name !== '' && $username !== '') {
                // Check duplicate (excluding self)
                $dup = $conn->prepare("SELECT id FROM users WHERE (username = ? OR (email IS NOT NULL AND email = ? AND ? IS NOT NULL)) AND id != ? LIMIT 1");
                $dup->bind_param("sssi", $username, $email, $email, $target_id);
                $dup->execute();
                if ($dup->get_result()->num_rows > 0) {
                    $toast_message = 'Username or email already taken.';
                    $toast_type = 'error';
                } else {
                    // Handle avatar
                    $avatar_sql = '';
                    $avatar_path = null;
                    if (isset($_FILES['avatar']) && $_FILES['avatar']['error'] === 0) {
                        $ext = strtolower(pathinfo($_FILES['avatar']['name'], PATHINFO_EXTENSION));
                        if (in_array($ext, ['jpg','jpeg','png','webp']) && $_FILES['avatar']['size'] <= 5*1024*1024) {
                            $fname = 'user_' . $target_id . '_' . time() . '.' . $ext;
                            move_uploaded_file($_FILES['avatar']['tmp_name'], $upload_dir . $fname);
                            $avatar_path = 'uploads/avatars/' . $fname;
                        }
                    }

                    if ($new_pass !== '' && strlen($new_pass) >= 8) {
                        $hashed = password_hash($new_pass, PASSWORD_DEFAULT);
                        if ($avatar_path) {
                            $stmt = $conn->prepare("UPDATE users SET username=?, email=?, password=?, full_name=?, avatar=?, roles=?, is_admin=? WHERE id=?");
                            $stmt->bind_param("ssssssi" . "i", $username, $email, $hashed, $full_name, $avatar_path, $roles_str, $is_admin, $target_id);
                        } else {
                            $stmt = $conn->prepare("UPDATE users SET username=?, email=?, password=?, full_name=?, roles=?, is_admin=? WHERE id=?");
                            $stmt->bind_param("sssssii", $username, $email, $hashed, $full_name, $roles_str, $is_admin, $target_id);
                        }
                    } else {
                        if ($avatar_path) {
                            $stmt = $conn->prepare("UPDATE users SET username=?, email=?, full_name=?, avatar=?, roles=?, is_admin=? WHERE id=?");
                            $stmt->bind_param("sssssii", $username, $email, $full_name, $avatar_path, $roles_str, $is_admin, $target_id);
                        } else {
                            $stmt = $conn->prepare("UPDATE users SET username=?, email=?, full_name=?, roles=?, is_admin=? WHERE id=?");
                            $stmt->bind_param("ssssii", $username, $email, $full_name, $roles_str, $is_admin, $target_id);
                        }
                    }
                    $stmt->execute();
                    $stmt->close();

                    // Update adviser assignment
                    // First remove from any classroom
                    $conn->query("UPDATE classrooms SET adviser_id = NULL, adviser_name = NULL WHERE adviser_id = $target_id");
                    // Then assign if checked
                    if ($is_adviser && $adv_classroom > 0) {
                        $adv_upd = $conn->prepare("UPDATE classrooms SET adviser_id = ?, adviser_name = ? WHERE id = ?");
                        $adv_upd->bind_param("isi", $target_id, $full_name, $adv_classroom);
                        $adv_upd->execute();
                        $adv_upd->close();
                    }

                    log_activity($conn, 'user_updated', 'Updated account: ' . $username, 'user', $target_id);
                    $toast_message = 'Account updated successfully.';
                }
                $dup->close();
            }

        // ── DELETE ACCOUNT ────────────────────────────────────────
        } elseif ($action === 'delete_account') {
            $target_id = (int)($_POST['target_user_id'] ?? 0);
            if ($target_id > 0 && $target_id !== $current_user_id) {
                // Get name for log
                $nm = $conn->prepare("SELECT username FROM users WHERE id=?");
                $nm->bind_param("i", $target_id);
                $nm->execute();
                $nm_r = $nm->get_result()->fetch_assoc();
                $nm->close();
                $del_name = $nm_r['username'] ?? 'unknown';

                // Remove adviser links
                $conn->query("UPDATE classrooms SET adviser_id = NULL, adviser_name = NULL WHERE adviser_id = $target_id");
                // Delete user
                $stmt = $conn->prepare("DELETE FROM users WHERE id = ?");
                $stmt->bind_param("i", $target_id);
                $stmt->execute();
                $stmt->close();

                log_activity($conn, 'user_deleted', 'Deleted account: ' . $del_name, 'user', $target_id);
                $toast_message = 'Account deleted.';
                $toast_type = 'error';
            } else {
                $toast_message = 'Cannot delete your own account.';
                $toast_type = 'error';
            }

        // ── TOGGLE STATUS ─────────────────────────────────────────
        } elseif ($action === 'toggle_status') {
            $target_id = (int)($_POST['target_user_id'] ?? 0);
            $new_status = ($_POST['new_status'] ?? 'active') === 'disabled' ? 'disabled' : 'active';
            if ($target_id > 0 && $target_id !== $current_user_id) {
                $stmt = $conn->prepare("UPDATE users SET status = ? WHERE id = ?");
                $stmt->bind_param("si", $new_status, $target_id);
                $stmt->execute();
                $stmt->close();
                log_activity($conn, 'user_updated', 'Set account #' . $target_id . ' to ' . $new_status, 'user', $target_id);
                $toast_message = 'Account ' . $new_status . '.';
            }

        // ── SEND CREDENTIALS EMAIL ────────────────────────────────
        } elseif ($action === 'send_credentials') {
            $target_id = (int)($_POST['target_user_id'] ?? 0);
            $send_pass = $_POST['send_password'] ?? '';
            $u = $conn->prepare("SELECT username, email, full_name, roles, is_admin FROM users WHERE id=?");
            $u->bind_param("i", $target_id);
            $u->execute();
            $user_data = $u->get_result()->fetch_assoc();
            $u->close();

            if (!$user_data || empty($user_data['email'])) {
                $toast_message = 'No email address set for this user.';
                $toast_type = 'error';
            } elseif ($send_pass === '') {
                $toast_message = 'Password is required to send credentials.';
                $toast_type = 'error';
            } else {
                $roles_display = $user_data['is_admin'] ? 'Admin' : ucwords(str_replace(',', ', ', $user_data['roles'] ?: 'Faculty'));
                $login_url = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http') . '://' . $_SERVER['HTTP_HOST'] . dirname(dirname($_SERVER['REQUEST_URI'])) . '/index.php';
                $html = build_credential_email($user_data['full_name'], $user_data['username'], $send_pass, $roles_display, $login_url);
                $result = send_email($user_data['email'], $user_data['full_name'], 'Your DRANHS SmartEnroll Account', $html);
                $toast_message = $result['message'];
                $toast_type = $result['success'] ? 'success' : 'error';
                if ($result['success']) {
                    log_activity($conn, 'credentials_sent', 'Sent credentials email to ' . $user_data['email'], 'user', $target_id);
                }
            }
        }
    }
}

// ── Fetch data ────────────────────────────────────────────────────────────────
$users = [];
$userResult = $conn->query("SELECT u.*, c.section_name AS adviser_section, c.id AS adviser_classroom_id, c.grade_level AS adviser_grade_level
    FROM users u
    LEFT JOIN classrooms c ON c.adviser_id = u.id
    ORDER BY u.is_admin DESC, u.full_name ASC");
if ($userResult) {
    while ($row = $userResult->fetch_assoc()) $users[] = $row;
}

// Fetch classrooms for adviser assignment dropdown
$classrooms = [];
$cr_res = $conn->query("SELECT id, grade_level, section_name, adviser_id FROM classrooms ORDER BY grade_level, section_name");
if ($cr_res) {
    while ($row = $cr_res->fetch_assoc()) $classrooms[] = $row;
}
$classrooms_json = json_encode($classrooms);
