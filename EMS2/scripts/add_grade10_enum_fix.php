<?php
// Run once to allow Grade 10 values in add_sections.grade_level enum.
$db_host = 'localhost';
$db_user = 'root';
$db_pass = '';
$db_name = 'dranhswin';
$conn = new mysqli($db_host, $db_user, $db_pass, $db_name);
if ($conn->connect_error) {
    die('Connection failed: ' . $conn->connect_error);
}

$sql = "ALTER TABLE add_sections MODIFY grade_level ENUM('10','11','12') NOT NULL";
if ($conn->query($sql) === TRUE) {
    echo 'add_sections.grade_level enum has been updated to include 10.';
} else {
    echo 'Error updating enum: ' . $conn->error;
}

$conn->close();
