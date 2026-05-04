<?php
/**
 * Database Configuration
 * Uses Railway MySQL variables with local fallbacks.
 */

define('DB_HOST', getenv('MYSQLHOST')     ?: getenv('DB_HOST')     ?: 'localhost');
define('DB_PORT', (int)(getenv('MYSQLPORT') ?: getenv('DB_PORT')   ?: 3306));
define('DB_USER', getenv('MYSQLUSER')     ?: getenv('DB_USER')     ?: 'root');
define('DB_PASS', getenv('MYSQLPASSWORD') ?: getenv('DB_PASS')     ?: '');
define('DB_NAME', getenv('MYSQLDATABASE') ?: getenv('DB_NAME')     ?: 'dranhswin');
define('DB_TYPE', 'mysql');

/**
 * Get PDO connection (works with both MySQL and PostgreSQL)
 */
function getDBConnection() {
    try {
        $dsn = sprintf(
            'mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4',
            DB_HOST,
            DB_PORT,
            DB_NAME
        );
        
        $conn = new PDO($dsn, DB_USER, DB_PASS, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);
        
        return $conn;
    } catch (PDOException $e) {
        die('Database Connection Error: ' . $e->getMessage());
    }
}

/**
 * For mysqli compatibility (legacy code)
 * Uses procedural style for backward compatibility
 */
function getMySQLiConnection() {
    $conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME, DB_PORT);
    if ($conn->connect_error) {
        die('Connection failed: ' . $conn->connect_error);
    }
    $conn->set_charset('utf8mb4');
    return $conn;
}
