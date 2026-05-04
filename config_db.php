<?php
/**
 * Database Configuration
 * Uses Railway MySQL variables with local fallbacks.
 */

function parse_database_url_config(): array {
    $url = getenv('DATABASE_URL') ?: '';
    if ($url === '') {
        return [];
    }

    $parts = parse_url($url);
    if ($parts === false) {
        return [];
    }

    return [
        'scheme' => strtolower((string)($parts['scheme'] ?? '')),
        'host'   => (string)($parts['host'] ?? ''),
        'user'   => (string)($parts['user'] ?? ''),
        'pass'   => (string)($parts['pass'] ?? ''),
        'name'   => ltrim((string)($parts['path'] ?? ''), '/'),
        'port'   => (int)($parts['port'] ?? 3306),
    ];
}

$db_url = parse_database_url_config();
$db_scheme = $db_url['scheme'] ?? '';

define('DB_HOST', getenv('MYSQLHOST')     ?: getenv('DB_HOST')     ?: ($db_url['host'] ?? '') ?: 'localhost');
define('DB_PORT', (int)(getenv('MYSQLPORT') ?: getenv('DB_PORT')   ?: ($db_url['port'] ?? 3306)));
define('DB_USER', getenv('MYSQLUSER')     ?: getenv('DB_USER')     ?: ($db_url['user'] ?? '') ?: 'root');
define('DB_PASS', getenv('MYSQLPASSWORD') ?: getenv('DB_PASS')     ?: ($db_url['pass'] ?? '') ?: '');
define('DB_NAME', getenv('MYSQLDATABASE') ?: getenv('DB_NAME')     ?: ($db_url['name'] ?? '') ?: 'dranhswin');
define('DB_TYPE', 'mysql');

/**
 * Get PDO connection (works with both MySQL and PostgreSQL)
 */
function getDBConnection() {
    global $db_scheme;
    if ($db_scheme === 'postgres' || $db_scheme === 'postgresql') {
        throw new RuntimeException('DATABASE_URL is PostgreSQL. This codebase is configured for MySQL.');
    }

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
    global $db_scheme;
    if ($db_scheme === 'postgres' || $db_scheme === 'postgresql') {
        die('DATABASE_URL points to PostgreSQL, but this app requires MySQL.');
    }

    $conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME, DB_PORT);
    if ($conn->connect_error) {
        die('Connection failed: ' . $conn->connect_error);
    }
    $conn->set_charset('utf8mb4');
    return $conn;
}
