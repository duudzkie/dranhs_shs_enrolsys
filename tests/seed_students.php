<?php

$db_host = 'localhost';
$db_user = 'root';
$db_pass = '';
$db_name = 'dranhswin';
$conn = new mysqli($db_host, $db_user, $db_pass, $db_name);
if ($conn->connect_error) {
    die('Connection failed: ' . $conn->connect_error);
}

$records = [
    [
        'lrn' => '111122223333',
        'last_name' => 'Santos',
        'first_name' => 'Maria',
        'grade_level' => 'Grade 11',
        'student_type' => 'Grade 10 DRANHS Student',
        'birthdate' => '2009-04-22',
        'age' => 17,
        'sex' => 'Female',
        'place_of_birth' => 'Davao City',
        'mother_tongue' => 'Cebuano',
        'religion' => 'Catholic',
        'ip_community' => 'No',
        'family_4ps' => 'No',
        'father_last_name' => 'Santos',
        'father_first_name' => 'Jose',
        'father_contact' => '09171234567',
        'mother_last_name' => 'Santos',
        'mother_first_name' => 'Liza',
        'mother_contact' => '09179876543',
        'guardian_last_name' => 'Santos',
        'guardian_first_name' => 'Liza',
        'sped' => 'No',
        'pwd' => 'No',
        'semester' => '1st',
        'track' => 'Academic',
        'pathway' => 'STEM',
        'school_year' => '2026 - 2027',
        'height' => 155,
        'weight' => 50,
        'psa_birth_cert' => '1234567890',
        'street' => 'Brgy 1',
        'province' => 'Davao del Sur',
        'city' => 'Davao City',
        'barangay' => 'Catalunan Grande',
        'zip_code' => '8000',
        'living_with' => 'Parents',
        'prev_school' => 'DRANHS',
        'prev_school_year' => '2025-2026',
        'prev_section' => '10-A',
        'avatar_path' => ''
    ],
    [
        'lrn' => '222233334444',
        'last_name' => 'Reyes',
        'first_name' => 'Juan',
        'grade_level' => 'Grade 12',
        'student_type' => 'Transferee',
        'birthdate' => '2008-05-15',
        'age' => 18,
        'sex' => 'Male',
        'place_of_birth' => 'Davao City',
        'mother_tongue' => 'Cebuano',
        'religion' => 'Catholic',
        'ip_community' => 'No',
        'family_4ps' => 'No',
        'father_last_name' => 'Reyes',
        'father_first_name' => 'Marco',
        'father_contact' => '09173456789',
        'mother_last_name' => 'Reyes',
        'mother_first_name' => 'Ana',
        'mother_contact' => '09171239876',
        'guardian_last_name' => 'Reyes',
        'guardian_first_name' => 'Ana',
        'sped' => 'No',
        'pwd' => 'No',
        'semester' => '1st',
        'track' => 'Academic',
        'pathway' => 'GAS',
        'school_year' => '2026 - 2027',
        'height' => 165,
        'weight' => 60,
        'psa_birth_cert' => '2233445566',
        'street' => 'Brgy 2',
        'province' => 'Davao del Sur',
        'city' => 'Davao City',
        'barangay' => 'Matina Crossing',
        'zip_code' => '8000',
        'living_with' => 'Parents',
        'prev_school' => 'Other High School',
        'prev_school_year' => '2025-2026',
        'prev_section' => '11-B',
        'avatar_path' => ''
    ]
];

foreach ($records as $r) {
    $insert = sprintf(
        "INSERT INTO students (last_name, first_name, middle_name, extension_name, birthdate, age, sex, place_of_birth, mother_tongue, religion, ip_community, ip_specify, family_4ps, fps_id, father_last_name, father_first_name, father_middle_name, father_contact, mother_last_name, mother_first_name, mother_middle_name, mother_contact, guardian_last_name, guardian_first_name, guardian_middle_name, guardian_contact, sped, sped_diagnosis, pwd, pwd_id, semester, track, pathway, school_year, grade_level, lrn, student_type, height, weight, psa_birth_cert, avatar_path, street, province, city, barangay, zip_code, living_with, prev_school, prev_school_year, prev_section, created_at) VALUES ('%s', '%s', '%s', '%s', '%s', %d, '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', NOW())",
        $conn->real_escape_string($r['last_name']),
        $conn->real_escape_string($r['first_name']),
        $conn->real_escape_string($r['middle_name'] ?? ''),
        $conn->real_escape_string($r['extension_name'] ?? ''),
        $conn->real_escape_string($r['birthdate']),
        intval($r['age']),
        $conn->real_escape_string($r['sex']),
        $conn->real_escape_string($r['place_of_birth']),
        $conn->real_escape_string($r['mother_tongue']),
        $conn->real_escape_string($r['religion']),
        $conn->real_escape_string($r['ip_community']),
        $conn->real_escape_string($r['ip_specify'] ?? ''),
        $conn->real_escape_string($r['family_4ps']),
        $conn->real_escape_string($r['fps_id'] ?? ''),
        $conn->real_escape_string($r['father_last_name']),
        $conn->real_escape_string($r['father_first_name']),
        $conn->real_escape_string($r['father_middle_name'] ?? ''),
        $conn->real_escape_string($r['father_contact'] ?? ''),
        $conn->real_escape_string($r['mother_last_name']),
        $conn->real_escape_string($r['mother_first_name']),
        $conn->real_escape_string($r['mother_middle_name'] ?? ''),
        $conn->real_escape_string($r['mother_contact'] ?? ''),
        $conn->real_escape_string($r['guardian_last_name']),
        $conn->real_escape_string($r['guardian_first_name']),
        $conn->real_escape_string($r['guardian_middle_name'] ?? ''),
        $conn->real_escape_string($r['guardian_contact'] ?? ''),
        $conn->real_escape_string($r['sped']),
        $conn->real_escape_string($r['sped_diagnosis'] ?? ''),
        $conn->real_escape_string($r['pwd']),
        $conn->real_escape_string($r['pwd_id'] ?? ''),
        $conn->real_escape_string($r['semester']),
        $conn->real_escape_string($r['track']),
        $conn->real_escape_string($r['pathway']),
        $conn->real_escape_string($r['school_year']),
        $conn->real_escape_string($r['grade_level']),
        $conn->real_escape_string($r['lrn']),
        $conn->real_escape_string($r['student_type']),
        number_format($r['height'], 2, '.', ''),
        number_format($r['weight'], 2, '.', ''),
        $conn->real_escape_string($r['psa_birth_cert']),
        $conn->real_escape_string($r['avatar_path'] ?? ''),
        $conn->real_escape_string($r['street']),
        $conn->real_escape_string($r['province']),
        $conn->real_escape_string($r['city']),
        $conn->real_escape_string($r['barangay']),
        $conn->real_escape_string($r['zip_code']),
        $conn->real_escape_string($r['living_with']),
        $conn->real_escape_string($r['prev_school']),
        $conn->real_escape_string($r['prev_school_year']),
        $conn->real_escape_string($r['prev_section'])
    );

    if ($conn->query($insert) === TRUE) {
        echo "Inserted LRN {$r['lrn']} successfully.\n";
    } else {
        echo "Error inserting LRN {$r['lrn']}: " . $conn->error . "\n";
    }
}

$conn->close();

echo "Done.\n";
