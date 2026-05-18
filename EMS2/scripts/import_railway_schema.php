<?php

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

function env_required(string $key): string
{
    $value = getenv($key);
    if ($value === false || $value === '') {
        fwrite(STDERR, "Missing required environment variable: {$key}\n");
        exit(1);
    }

    return $value;
}

$host = env_required('MYSQLHOST');
$port = (int) env_required('MYSQLPORT');
$user = env_required('MYSQLUSER');
$pass = env_required('MYSQLPASSWORD');
$db   = env_required('MYSQLDATABASE');

$schemaPath = __DIR__ . '/setup_schema.sql';
if (!file_exists($schemaPath)) {
    fwrite(STDERR, "Schema file not found: {$schemaPath}\n");
    exit(1);
}

$sql = file_get_contents($schemaPath);
if ($sql === false) {
    fwrite(STDERR, "Failed to read schema file: {$schemaPath}\n");
    exit(1);
}

try {
    $conn = new mysqli($host, $user, $pass, $db, $port);
    $conn->set_charset('utf8mb4');

    $conn->multi_query($sql);
    do {
        if ($result = $conn->store_result()) {
            $result->free();
        }
    } while ($conn->more_results() && $conn->next_result());

    echo "Schema imported successfully into {$db} on {$host}:{$port}\n";
    $conn->close();
} catch (Throwable $e) {
    fwrite(STDERR, "Import failed: " . $e->getMessage() . "\n");
    exit(1);
}
