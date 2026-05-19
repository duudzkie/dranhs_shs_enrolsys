<?php

declare(strict_types=1);

require_once __DIR__ . '/db.php';

$expectedKey = getenv('SETUP_DB_KEY');
$providedKey = $_GET['key'] ?? '';

if ($expectedKey === false || $expectedKey === '') {
    http_response_code(503);
    header('Content-Type: text/plain; charset=UTF-8');
    echo "SETUP_DB_KEY is not configured.\n";
    exit;
}

if (!hash_equals($expectedKey, (string) $providedKey)) {
    http_response_code(403);
    header('Content-Type: text/plain; charset=UTF-8');
    echo "Forbidden.\n";
    exit;
}

$schemaPath = __DIR__ . '/scripts/setup_schema.sql';
if (!is_file($schemaPath)) {
    http_response_code(500);
    header('Content-Type: text/plain; charset=UTF-8');
    echo "Schema file not found.\n";
    exit;
}

$sql = file_get_contents($schemaPath);
if ($sql === false) {
    http_response_code(500);
    header('Content-Type: text/plain; charset=UTF-8');
    echo "Failed to read schema file.\n";
    exit;
}

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

try {
    $conn = db_connect();
    $conn->multi_query($sql);

    do {
        if ($result = $conn->store_result()) {
            $result->free();
        }
    } while ($conn->more_results() && $conn->next_result());

    header('Content-Type: text/plain; charset=UTF-8');
    echo "Database schema initialized successfully.\n";
} catch (Throwable $e) {
    http_response_code(500);
    header('Content-Type: text/plain; charset=UTF-8');
    echo "Setup failed: " . $e->getMessage() . "\n";
    exit;
}
