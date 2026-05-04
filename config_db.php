<?php
/**
 * Database Configuration
 * Auto-detects Railway PostgreSQL or uses MySQL fallback
 */

// Check if we're on Railway with PostgreSQL
if (!empty($_ENV['DATABASE_URL'])) {
    // Parse Railway PostgreSQL connection string
    $db_url = parse_url($_ENV['DATABASE_URL']);
    
    define('DB_HOST', $db_url['host'] ?? 'localhost');
    define('DB_PORT', $db_url['port'] ?? 5432);
    define('DB_USER', $db_url['user'] ?? 'postgres');
    define('DB_PASS', $db_url['pass'] ?? '');
    define('DB_NAME', ltrim($db_url['path'] ?? '/railway', '/'));
    define('DB_TYPE', 'postgres');
    
} else {
    // Local MySQL fallback
    define('DB_HOST', $_ENV['DB_HOST'] ?? 'localhost');
    define('DB_PORT', $_ENV['DB_PORT'] ?? 3306);
    define('DB_USER', $_ENV['DB_USER'] ?? 'root');
    define('DB_PASS', $_ENV['DB_PASS'] ?? '');
    define('DB_NAME', $_ENV['DB_NAME'] ?? 'dranhswin');
    define('DB_TYPE', 'mysql');
}

/**
 * Get PDO connection (works with both MySQL and PostgreSQL)
 */
function getDBConnection() {
    try {
        if (DB_TYPE === 'postgres') {
            $dsn = sprintf(
                'pgsql:host=%s;port=%d;dbname=%s',
                DB_HOST,
                DB_PORT,
                DB_NAME
            );
        } else {
            $dsn = sprintf(
                'mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4',
                DB_HOST,
                DB_PORT,
                DB_NAME
            );
        }
        
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
