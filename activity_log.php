<?php
/**
 * Activity Log Helper
 * Provides a reusable function to log system events into the `activity_logs` table.
 * Include this file wherever you need to record an action.
 */

/**
 * Ensure the activity_logs table exists (idempotent — safe to call every request).
 */
function ensure_activity_log_table(mysqli $conn): void
{
    static $checked = false;
    if ($checked) return;
    $checked = true;

    $conn->query("CREATE TABLE IF NOT EXISTS activity_logs (
        id          INT AUTO_INCREMENT PRIMARY KEY,
        user_id     INT          NULL,
        username    VARCHAR(100) NOT NULL DEFAULT 'system',
        role        VARCHAR(50)  NOT NULL DEFAULT '',
        action      VARCHAR(50)  NOT NULL,
        details     TEXT         NULL,
        target_type VARCHAR(50)  NULL,
        target_id   INT          NULL,
        ip_address  VARCHAR(45)  NULL,
        created_at  TIMESTAMP    DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_action   (action),
        INDEX idx_created  (created_at),
        INDEX idx_user     (user_id)
    )");
}

/**
 * Insert a log entry.
 *
 * @param mysqli      $conn        Active DB connection
 * @param string      $action      Action key (e.g. 'login', 'student_verified')
 * @param string      $details     Human-readable description
 * @param string|null $target_type Entity type ('student', 'user', 'classroom', …)
 * @param int|null    $target_id   ID of the affected entity
 */
function log_activity(
    mysqli  $conn,
    string  $action,
    string  $details = '',
    ?string $target_type = null,
    ?int    $target_id = null
): void {
    ensure_activity_log_table($conn);

    $user_id  = $_SESSION['user_id']  ?? null;
    $username = $_SESSION['username'] ?? 'guest';
    $role     = $_SESSION['role']     ?? '';
    $ip       = $_SERVER['HTTP_X_FORWARDED_FOR']
                ?? $_SERVER['HTTP_X_REAL_IP']
                ?? $_SERVER['REMOTE_ADDR']
                ?? '';
    // Take only the first IP if the header contains a chain
    if (strpos($ip, ',') !== false) {
        $ip = trim(explode(',', $ip)[0]);
    }

    $stmt = $conn->prepare(
        "INSERT INTO activity_logs
            (user_id, username, role, action, details, target_type, target_id, ip_address)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?)"
    );
    if (!$stmt) return;

    $stmt->bind_param(
        'isssssis',
        $user_id,
        $username,
        $role,
        $action,
        $details,
        $target_type,
        $target_id,
        $ip
    );
    $stmt->execute();
    $stmt->close();
}
