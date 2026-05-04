<?php
/**
 * db.php — Central database configuration
 * Reads from environment variables (Railway) or falls back to local defaults.
 *
 * Railway env vars to set:
 *   MYSQLHOST, MYSQLUSER, MYSQLPASSWORD, MYSQLDATABASE, MYSQLPORT
 */

define('DB_HOST', getenv('MYSQLHOST')     ?: getenv('DB_HOST')     ?: 'localhost');
define('DB_USER', getenv('MYSQLUSER')     ?: getenv('DB_USER')     ?: 'root');
define('DB_PASS', getenv('MYSQLPASSWORD') ?: getenv('DB_PASS')     ?: '');
define('DB_NAME', getenv('MYSQLDATABASE') ?: getenv('DB_NAME')     ?: 'dranhswin');
define('DB_PORT', (int)(getenv('MYSQLPORT') ?: getenv('DB_PORT')   ?: 3306));

/**
 * Get a new MySQLi connection using env-based config.
 */
function db_connect(): mysqli {
    $conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME, DB_PORT);
    if ($conn->connect_error) {
        // In production, don't expose DB errors
        if (getenv('RAILWAY_ENVIRONMENT') || getenv('APP_ENV') === 'production') {
            http_response_code(503);
            die(json_encode(['error' => 'Service temporarily unavailable.']));
        }
        die('Database connection failed: ' . $conn->connect_error);
    }
    $conn->set_charset('utf8mb4');
    return $conn;
}
