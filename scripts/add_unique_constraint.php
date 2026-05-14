<?php
require_once __DIR__ . '/../db.php';
$db = db_connect();

// Add unique constraint to prevent duplicate section names per grade level
$sql = "ALTER TABLE add_sections ADD UNIQUE KEY unique_section (grade_level, name)";

if ($db->query($sql) === TRUE) {
    echo 'Unique constraint added successfully to prevent duplicate sections.';
} else {
    echo 'Error: ' . $db->error;
    // If constraint already exists, that's fine
    if (strpos($db->error, 'Duplicate key name') !== false) {
        echo ' (Constraint may already exist)';
    }
}
$db->close();
?>