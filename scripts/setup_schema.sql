-- ============================================================
-- DRANHS Portal — Complete Database Schema
-- Run this to initialize all tables on a fresh deployment.
-- All statements use IF NOT EXISTS for idempotent execution.
-- ============================================================

-- Users (authentication)
CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    fullname VARCHAR(150) NULL,
    role ENUM('admin', 'evaluator', 'encoder', 'adviser') NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- System Settings (key-value config store)
CREATE TABLE IF NOT EXISTS system_settings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    setting_key VARCHAR(100) NOT NULL UNIQUE,
    setting_value TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Default system settings
INSERT IGNORE INTO system_settings (setting_key, setting_value) VALUES
('academic_year', CONCAT(YEAR(CURDATE()), ' - ', YEAR(CURDATE()) + 1)),
('active_semester', '1st'),
('enrollment_status', 'locked'),
('phase_start_date', ''),
('stem_qualifier_enabled', '1');

-- Students (main enrollment data)
CREATE TABLE IF NOT EXISTS students (
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
    enrollment_status VARCHAR(30) DEFAULT 'for_evaluation',
    assigned_section VARCHAR(100),
    height DECIMAL(5,2),
    weight DECIMAL(5,2),
    psa_birth_cert VARCHAR(50),
    avatar_path VARCHAR(255),
    street VARCHAR(255),
    province VARCHAR(100),
    city VARCHAR(100),
    barangay VARCHAR(100),
    zip_code VARCHAR(10),
    living_with VARCHAR(50),
    prev_school VARCHAR(255),
    prev_school_year VARCHAR(20),
    prev_section VARCHAR(100),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY unique_lrn (lrn)
);

-- Sections registry
CREATE TABLE IF NOT EXISTS add_sections (
    id INT AUTO_INCREMENT PRIMARY KEY,
    grade_level ENUM('10', '11', '12') NOT NULL,
    name VARCHAR(100) NOT NULL,
    room VARCHAR(50),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY unique_section (grade_level, name)
);

-- Faculty Advisers
CREATE TABLE IF NOT EXISTS advisers_accounts (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(150) NOT NULL,
    avatar VARCHAR(255),
    user_id INT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Classrooms (section + adviser + capacity)
CREATE TABLE IF NOT EXISTS classrooms (
    id INT AUTO_INCREMENT PRIMARY KEY,
    grade_level VARCHAR(20) NOT NULL,
    track VARCHAR(50),
    pathway_strand VARCHAR(100),
    section_name VARCHAR(100) NOT NULL,
    adviser_id INT NULL,
    adviser_name VARCHAR(150) NULL,
    max_capacity INT NOT NULL DEFAULT 40,
    group_chat_url VARCHAR(500) NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Encodings (encoding stage data)
CREATE TABLE IF NOT EXISTS encodings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    student_id INT NOT NULL,
    assigned_section VARCHAR(100),
    pathway_strand VARCHAR(100),
    encoded_by INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- STEM Qualifiers
CREATE TABLE IF NOT EXISTS stem_qualifiers (
    id INT AUTO_INCREMENT PRIMARY KEY,
    lrn VARCHAR(12) NOT NULL,
    pathway_cluster VARCHAR(100),
    school_year VARCHAR(20),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Grade 11 Completers (for Grade 12 enrollment verification)
CREATE TABLE IF NOT EXISTS g11_completers (
    id INT AUTO_INCREMENT PRIMARY KEY,
    lrn VARCHAR(12) NOT NULL,
    last_name VARCHAR(100),
    first_name VARCHAR(100),
    middle_name VARCHAR(100),
    section VARCHAR(100),
    strand VARCHAR(100),
    completer_status VARCHAR(50) NOT NULL DEFAULT 'regular',
    school_year VARCHAR(20),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Watchlist (flagged students)
CREATE TABLE IF NOT EXISTS watchlist (
    id INT AUTO_INCREMENT PRIMARY KEY,
    lrn VARCHAR(12) NOT NULL,
    issue_type VARCHAR(100),
    issue_details TEXT,
    school_year VARCHAR(20),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Room Annex (manual room mapping outside Bldg 14/15)
CREATE TABLE IF NOT EXISTS room_annex (
    id INT AUTO_INCREMENT PRIMARY KEY,
    section_name VARCHAR(100) NOT NULL,
    building_number VARCHAR(50) NOT NULL,
    floor_number VARCHAR(20) NOT NULL,
    room_number VARCHAR(50) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Room Facilities (labs, faculty rooms, etc.)
CREATE TABLE IF NOT EXISTS room_facilities (
    id INT AUTO_INCREMENT PRIMARY KEY,
    facility_name VARCHAR(150) NOT NULL,
    building_number VARCHAR(50) NOT NULL,
    floor_number VARCHAR(20) NOT NULL,
    room_number VARCHAR(50) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Default admin users (password: password123)
INSERT INTO users (username, password, role) VALUES
('admin', '$2y$10$7vIslO6P4erEc7DxqxArb.x603s3FrT.GY4EwyxaMVE0qqbuY7TBO', 'admin'),
('evaluator', '$2y$10$7vIslO6P4erEc7DxqxArb.x603s3FrT.GY4EwyxaMVE0qqbuY7TBO', 'evaluator'),
('encoder', '$2y$10$7vIslO6P4erEc7DxqxArb.x603s3FrT.GY4EwyxaMVE0qqbuY7TBO', 'encoder')
ON DUPLICATE KEY UPDATE password = VALUES(password), role = VALUES(role);
