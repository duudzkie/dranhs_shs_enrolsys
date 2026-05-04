<?php
/**
 * Railway PostgreSQL Setup Script
 * Run this once to initialize the database
 */

require_once __DIR__ . '/config_db.php';

try {
    if (DB_TYPE === 'postgres') {
        $conn = getDBConnection();
        
        // Read and execute PostgreSQL setup script
        $sql = file_get_contents(__DIR__ . '/EMS2/scripts/setup_users_postgres.sql');
        $conn->exec($sql);
        
        echo "✅ PostgreSQL database initialized successfully!\n";
        echo "Database: " . DB_NAME . "\n";
        echo "Host: " . DB_HOST . "\n";
        
    } else {
        $conn = getMySQLiConnection();
        
        // Read and execute MySQL setup script
        $sql = file_get_contents(__DIR__ . '/EMS2/scripts/setup_users.sql');
        if ($conn->multi_query($sql)) {
            while ($conn->next_result()) {
                // Process multiple statements
            }
            echo "✅ MySQL database initialized successfully!\n";
            echo "Database: " . DB_NAME . "\n";
            echo "Host: " . DB_HOST . "\n";
        } else {
            echo "❌ Error: " . $conn->error . "\n";
        }
        $conn->close();
    }
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
    exit(1);
}
