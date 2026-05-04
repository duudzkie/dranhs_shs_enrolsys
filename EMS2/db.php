<?php
/**
 * db.php — Central database configuration
 * Reads from environment variables (Railway) or falls back to local defaults.
 *
 * Railway env vars to set:
 *   MYSQLHOST, MYSQLUSER, MYSQLPASSWORD, MYSQLDATABASE, MYSQLPORT
 */

function parse_database_url(): array {
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

$db_url = parse_database_url();
$db_scheme = $db_url['scheme'] ?? '';

define('DB_HOST', getenv('MYSQLHOST')     ?: getenv('DB_HOST')     ?: ($db_url['host'] ?? '') ?: 'localhost');
define('DB_USER', getenv('MYSQLUSER')     ?: getenv('DB_USER')     ?: ($db_url['user'] ?? '') ?: 'root');
define('DB_PASS', getenv('MYSQLPASSWORD') ?: getenv('DB_PASS')     ?: ($db_url['pass'] ?? '') ?: '');
define('DB_NAME', getenv('MYSQLDATABASE') ?: getenv('DB_NAME')     ?: ($db_url['name'] ?? '') ?: 'dranhswin');
define('DB_PORT', (int)(getenv('MYSQLPORT') ?: getenv('DB_PORT')   ?: ($db_url['port'] ?? 3306)));

/**
 * Get a new MySQLi connection using env-based config.
 */
function db_connect(): mysqli {
    global $db_scheme;
    if ($db_scheme === 'postgres' || $db_scheme === 'postgresql') {
        http_response_code(500);
        die('DATABASE_URL points to PostgreSQL, but this app requires MySQL (mysqli).');
    }

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
