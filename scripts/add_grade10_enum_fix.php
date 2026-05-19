<?php
// Run once to allow Grade 10 values in add_sections.grade_level enum.
require_once __DIR__ . '/../db.php';

$conn = db_connect();

$sql = "ALTER TABLE add_sections MODIFY grade_level ENUM('10','11','12') NOT NULL";
if ($conn->query($sql) === TRUE) {
    echo 'add_sections.grade_level enum has been updated to include 10.';
} else {
    echo 'Error updating enum: ' . $conn->error;
}

$conn->close();
