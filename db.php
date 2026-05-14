<?php
/**
 * db.php — Central database configuration
 * Reads from environment variables (Railway) or falls back to local defaults.
 *
 * Railway env vars to set:
 *   MYSQLHOST, MYSQLUSER, MYSQLPASSWORD, MYSQLDATABASE, MYSQLPORT
 */

function env_value(array $keys, string $default = ''): string {
    foreach ($keys as $key) {
        $value = getenv($key);
        if ($value !== false && $value !== '') {
            return $value;
        }
    }

    return $default;
}

function default_db_host(?string $url_host = null): string {
    if (!empty($url_host)) {
        return $url_host;
    }

    $is_docker = file_exists('/.dockerenv');
    if ($is_docker) {
        return 'host.docker.internal';
    }

    return '127.0.0.1';
}

function parse_database_url(): array {
    $url = env_value(['MYSQL_URL', 'DATABASE_URL']);
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
$is_railway = getenv('RAILWAY_ENVIRONMENT') !== false || getenv('RAILWAY_PROJECT_ID') !== false;

define('DB_HOST', env_value(['MYSQLHOST', 'DB_HOST'], default_db_host($db_url['host'] ?? '')));
define('DB_USER', env_value(['MYSQLUSER', 'DB_USER'], ($db_url['user'] ?? '') ?: 'root'));
define('DB_PASS', env_value(['MYSQLPASSWORD', 'DB_PASS'], $db_url['pass'] ?? ''));
define('DB_NAME', env_value(['MYSQLDATABASE', 'DB_NAME'], ($db_url['name'] ?? '') ?: 'dranhswin'));
define('DB_PORT', (int)env_value(['MYSQLPORT', 'DB_PORT'], (string)($db_url['port'] ?? 3306)));

/**
 * Get a new MySQLi connection using env-based config.
 */
function db_connect(): mysqli {
    global $db_scheme, $is_railway;
    if ($db_scheme === 'postgres' || $db_scheme === 'postgresql') {
        http_response_code(500);
        die('DATABASE_URL points to PostgreSQL, but this app requires MySQL (mysqli).');
    }

    if ($is_railway && DB_HOST === '127.0.0.1') {
        http_response_code(500);
        die('Railway MySQL is not configured. Set MYSQLHOST, MYSQLPORT, MYSQLUSER, MYSQLPASSWORD, and MYSQLDATABASE on the app service.');
    }

    try {
        $conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME, DB_PORT);
    } catch (mysqli_sql_exception $e) {
        if ($is_railway || getenv('APP_ENV') === 'production') {
            http_response_code(503);
            die(json_encode(['error' => 'Service temporarily unavailable.']));
        }

        die('Database connection failed: ' . $e->getMessage());
    }

    if ($conn->connect_error) {
        // In production, don't expose DB errors
        if ($is_railway || getenv('APP_ENV') === 'production') {
            http_response_code(503);
            die(json_encode(['error' => 'Service temporarily unavailable.']));
        }
        die('Database connection failed: ' . $conn->connect_error);
    }
    $conn->set_charset('utf8mb4');
    return $conn;
}
