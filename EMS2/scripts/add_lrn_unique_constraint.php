<?php
// add_lrn_unique_constraint.php - Add unique constraint to LRN column in students table

$db_host = 'localhost';
$db_user = 'root';
$db_pass = '';
$db_name = 'dranhswin';
$conn = new mysqli($db_host, $db_user, $db_pass, $db_name);

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

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