<?php
require_once __DIR__ . '/../db.php';
$db = db_connect();

$sql = "CREATE TABLE IF NOT EXISTS students (
    id INT AUTO_INCREMENT PRIMARY KEY,
    last_name VARCHAR(100) NOT NULL,
    first_name VARCHAR(100) NOT NULL,
    middle_name VARCHAR(100),
    extension_name VARCHAR(10),
    birthdate DATE NOT NULL,
    age INT,
    sex ENUM('Male', 'Female') NOT NULL,
    place_of_birth VARCHAR(255) NOT NULL,
    mother_tongue VARCHAR(100),
    religion VARCHAR(100),
    ip_community ENUM('Yes', 'No') DEFAULT 'No',
    ip_specify VARCHAR(255),
    family_4ps ENUM('Yes', 'No') DEFAULT 'No',
    fps_id VARCHAR(50),
    father_last_name VARCHAR(100),
    father_first_name VARCHAR(100),
    father_middle_name VARCHAR(100),
    father_contact VARCHAR(50),
    mother_last_name VARCHAR(100),
    mother_first_name VARCHAR(100),
    mother_middle_name VARCHAR(100),
    mother_contact VARCHAR(50),
    guardian_last_name VARCHAR(100),
    guardian_first_name VARCHAR(100),
    guardian_middle_name VARCHAR(100),
    guardian_contact VARCHAR(50),
    sped ENUM('Yes', 'No') DEFAULT 'No',
    sped_diagnosis TEXT,
    pwd ENUM('Yes', 'No') DEFAULT 'No',
    pwd_id VARCHAR(50),
    semester VARCHAR(10),
    track VARCHAR(50),
    pathway_strand VARCHAR(100),
    school_year VARCHAR(20),
    grade_level VARCHAR(20),
    lrn VARCHAR(12),
    student_type VARCHAR(100),
    height DECIMAL(5,2),
    weight DECIMAL(5,2),
    psa_birth_cert VARCHAR(50),
    avatar_path VARCHAR(255),
    photo_path VARCHAR(500) NULL,
    street VARCHAR(255),
    province VARCHAR(100),
    city VARCHAR(100),
    barangay VARCHAR(100),
    zip_code VARCHAR(10),
    living_with VARCHAR(50),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)";

if ($db->query($sql) === TRUE) {
    echo 'Table created successfully';
} else {
    echo 'Error: ' . $db->error;
}
$db->close();
?>
