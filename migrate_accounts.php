<?php
/**
 * One-time migration: add new columns to users table and merge advisers_accounts data.
 * Safe to run multiple times (idempotent).
 */
require_once __DIR__ . '/db.php';
$conn = db_connect();
if ($conn->connect_error) die("DB connection failed");

$db = $conn->real_escape_string(DB_NAME);

function col_exists(mysqli $conn, string $db, string $table, string $col): bool {
    $r = $conn->query("SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA='{$db}' AND TABLE_NAME='{$table}' AND COLUMN_NAME='{$col}'");
    return $r && $r->num_rows > 0;
}

// ── Add new columns to users ──────────────────────────────────────────────────
if (!col_exists($conn, $db, 'users', 'email')) {
    $conn->query("ALTER TABLE users ADD COLUMN email VARCHAR(150) NULL UNIQUE AFTER username");
}
if (!col_exists($conn, $db, 'users', 'avatar')) {
    $conn->query("ALTER TABLE users ADD COLUMN avatar VARCHAR(255) NULL AFTER fullname");
}
if (!col_exists($conn, $db, 'users', 'roles')) {
    $conn->query("ALTER TABLE users ADD COLUMN roles VARCHAR(100) NOT NULL DEFAULT '' AFTER avatar");
}
if (!col_exists($conn, $db, 'users', 'is_admin')) {
    $conn->query("ALTER TABLE users ADD COLUMN is_admin TINYINT(1) NOT NULL DEFAULT 0 AFTER roles");
}
if (!col_exists($conn, $db, 'users', 'status')) {
    $conn->query("ALTER TABLE users ADD COLUMN status ENUM('active','disabled') NOT NULL DEFAULT 'active' AFTER is_admin");
}

// Rename fullname → full_name if needed (keep both for compat)
if (!col_exists($conn, $db, 'users', 'full_name') && col_exists($conn, $db, 'users', 'fullname')) {
    $conn->query("ALTER TABLE users CHANGE fullname full_name VARCHAR(150) NULL");
} elseif (!col_exists($conn, $db, 'users', 'full_name')) {
    $conn->query("ALTER TABLE users ADD COLUMN full_name VARCHAR(150) NULL AFTER password");
}

// ── Migrate existing role data ────────────────────────────────────────────────
// Convert old single 'role' ENUM to new 'roles' + 'is_admin' columns
$users = $conn->query("SELECT id, role FROM users");
if ($users) {
    while ($u = $users->fetch_assoc()) {
        $old_role = $u['role'] ?? '';
        $new_roles = '';
        $is_admin = 0;

        if ($old_role === 'admin') {
            $is_admin = 1;
            $new_roles = '';
        } elseif ($old_role === 'evaluator') {
            $new_roles = 'evaluator';
        } elseif ($old_role === 'encoder') {
            $new_roles = 'encoder';
        } elseif ($old_role === 'adviser') {
            $new_roles = ''; // adviser-only, no permission role
        }

        $stmt = $conn->prepare("UPDATE users SET roles = ?, is_admin = ? WHERE id = ? AND roles = ''");
        if ($stmt) { $stmt->bind_param("sii", $new_roles, $is_admin, $u['id']); $stmt->execute(); $stmt->close(); }
    }
}

// ── Merge advisers_accounts into users ────────────────────────────────────────
// For each adviser that has a user_id link, copy avatar to users table.
// For each adviser WITHOUT a user_id, create a new user account.
$adv_table = $conn->query("SHOW TABLES LIKE 'advisers_accounts'");
if ($adv_table && $adv_table->num_rows > 0) {
    $advisers = $conn->query("SELECT a.id, a.name, a.avatar, a.user_id, c.section_name, c.id AS classroom_id 
        FROM advisers_accounts a 
        LEFT JOIN classrooms c ON c.adviser_id = a.id");
    
    if ($advisers) {
        while ($adv = $advisers->fetch_assoc()) {
            $adv_id = (int)$adv['id'];
            $adv_name = trim($adv['name'] ?? '');
            $adv_avatar = $adv['avatar'] ?? null;
            $user_id = $adv['user_id'] ? (int)$adv['user_id'] : null;

            if ($user_id) {
                // Already linked — just copy avatar and full_name if missing
                $stmt = $conn->prepare("UPDATE users SET avatar = COALESCE(avatar, ?), full_name = COALESCE(full_name, ?) WHERE id = ?");
                if ($stmt) { $stmt->bind_param("ssi", $adv_avatar, $adv_name, $user_id); $stmt->execute(); $stmt->close(); }
            } else {
                // No user account — create one
                $username = strtolower(preg_replace('/[^a-z0-9]/i', '', explode(' ', $adv_name)[0])) . $adv_id;
                $password = password_hash('changeme123', PASSWORD_DEFAULT);
                
                $stmt = $conn->prepare("INSERT INTO users (username, password, full_name, avatar, roles, is_admin, status) VALUES (?, ?, ?, ?, '', 0, 'active')");
                if ($stmt) {
                    $stmt->bind_param("ssss", $username, $password, $adv_name, $adv_avatar);
                    if ($stmt->execute()) {
                        $new_user_id = $conn->insert_id;
                        
                        // Update classroom to point to users.id instead of advisers_accounts.id
                        if ($adv['classroom_id']) {
                            $upd = $conn->prepare("UPDATE classrooms SET adviser_id = ? WHERE id = ?");
                            if ($upd) { $upd->bind_param("ii", $new_user_id, $adv['classroom_id']); $upd->execute(); $upd->close(); }
                        }
                    }
                    $stmt->close();
                }
            }
        }
    }
    
    // For linked advisers, update classroom.adviser_id from advisers_accounts.id → users.id
    $linked = $conn->query("SELECT a.id AS adv_id, a.user_id FROM advisers_accounts a WHERE a.user_id IS NOT NULL");
    if ($linked) {
        while ($l = $linked->fetch_assoc()) {
            $conn->query("UPDATE classrooms SET adviser_id = {$l['user_id']} WHERE adviser_id = {$l['adv_id']}");
        }
    }
}

// ── Change the role column type to VARCHAR to support legacy reads ─────────
// The old ENUM('admin','evaluator','encoder','adviser') is too restrictive.
// We keep 'role' for backward compat but it becomes computed.
$col_info = $conn->query("SELECT COLUMN_TYPE FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA='{$db}' AND TABLE_NAME='users' AND COLUMN_NAME='role'");
if ($col_info && ($ci = $col_info->fetch_assoc())) {
    if (strpos($ci['COLUMN_TYPE'], 'enum') !== false) {
        $conn->query("ALTER TABLE users MODIFY COLUMN role VARCHAR(50) NOT NULL DEFAULT ''");
    }
}

// Only output when run directly (not included from login.php)
if (basename($_SERVER['SCRIPT_FILENAME'] ?? '') === 'migrate_accounts.php') {
    echo "Migration complete.\n";
    $conn->close();
}
