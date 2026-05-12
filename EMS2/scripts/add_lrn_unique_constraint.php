<?php
// add_lrn_unique_constraint.php - Add unique constraint to LRN column in students table
require_once __DIR__ . '/../db.php';

$conn = db_connect();

// Check if unique constraint already exists
$result = $conn->query("SHOW INDEX FROM students WHERE Column_name = 'lrn' AND Non_unique = 0");
if ($result->num_rows > 0) {
    echo "Unique constraint on LRN already exists.\n";
} else {
    // Add unique constraint on LRN column
    $sql = "ALTER TABLE students ADD UNIQUE (lrn)";
    if ($conn->query($sql) === TRUE) {
        echo "Unique constraint added successfully to prevent duplicate LRNs.\n";
    } else {
        echo "Error adding unique constraint: " . $conn->error . "\n";
    }
}

$conn->close();
?>