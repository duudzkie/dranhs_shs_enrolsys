<?php
/**
 * Railway MySQL Setup Script
 * Run this once to initialize the database.
 * Safe to run multiple times — uses IF NOT EXISTS and ON DUPLICATE KEY.
 */

require_once __DIR__ . '/config_db.php';

try {
    $conn = getMySQLiConnection();

    // Run the complete schema (all tables + default users)
    $sql = file_get_contents(__DIR__ . '/EMS2/scripts/setup_schema.sql');
    if ($sql === false) {
        echo "❌ Could not read setup_schema.sql\n";
        exit(1);
    }

    if ($conn->multi_query($sql)) {
        // Process all result sets from multi_query
        do {
            if ($result = $conn->store_result()) {
                $result->free();
            }
        } while ($conn->next_result());

        if ($conn->error) {
            echo "⚠️ Warning: " . $conn->error . "\n";
        }

        echo "✅ MySQL database initialized successfully!\n";
        echo "Database: " . DB_NAME . "\n";
        echo "Host: " . DB_HOST . "\n";
    } else {
        echo "❌ Error: " . $conn->error . "\n";
    }
    $conn->close();
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
    exit(1);
}
