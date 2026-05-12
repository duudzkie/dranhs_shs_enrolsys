<?php
/**
 * Database Configuration
 * Uses Railway MySQL variables with local fallbacks.
 */

function env_value_config(array $keys, string $default = ''): string {
    foreach ($keys as $key) {
        $value = getenv($key);
        if ($value !== false && $value !== '') {
            return $value;
        }
    }

    return $default;
}

function default_db_host_config(?string $url_host = null): string {
    if (!empty($url_host)) {
        return $url_host;
    }

    $is_docker = file_exists('/.dockerenv');
    if ($is_docker) {
        return 'host.docker.internal';
    }

    return '127.0.0.1';
}

function parse_database_url_config(): array {
    $url = env_value_config(['MYSQL_URL', 'DATABASE_URL']);
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
$is_railway = getenv('RAILWAY_ENVIRONMENT') !== false || getenv('RAILWAY_PROJECT_ID') !== false;

define('DB_HOST', env_value_config(['MYSQLHOST', 'DB_HOST'], default_db_host_config($db_url['host'] ?? '')));
define('DB_PORT', (int)env_value_config(['MYSQLPORT', 'DB_PORT'], (string)($db_url['port'] ?? 3306)));
define('DB_USER', env_value_config(['MYSQLUSER', 'DB_USER'], ($db_url['user'] ?? '') ?: 'root'));
define('DB_PASS', env_value_config(['MYSQLPASSWORD', 'DB_PASS'], $db_url['pass'] ?? ''));
define('DB_NAME', env_value_config(['MYSQLDATABASE', 'DB_NAME'], ($db_url['name'] ?? '') ?: 'dranhswin'));
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
    global $db_scheme, $is_railway;
    if ($db_scheme === 'postgres' || $db_scheme === 'postgresql') {
        die('DATABASE_URL points to PostgreSQL, but this app requires MySQL.');
    }

    if ($is_railway && DB_HOST === '127.0.0.1') {
        die('Railway MySQL is not configured. Set MYSQLHOST, MYSQLPORT, MYSQLUSER, MYSQLPASSWORD, and MYSQLDATABASE on the app service.');
    }

    try {
        $conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME, DB_PORT);
    } catch (mysqli_sql_exception $e) {
        die('Connection failed: ' . $e->getMessage());
    }

    if ($conn->connect_error) {
        die('Connection failed: ' . $conn->connect_error);
    }
    $conn->set_charset('utf8mb4');
    return $conn;
}
