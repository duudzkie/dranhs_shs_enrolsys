<?php
$db_host='localhost';$db_user='root';$db_pass='';$db_name='dranhswin';
$conn=new mysqli($db_host,$db_user,$db_pass,$db_name);
if($conn->connect_error)die('Connect fail: '.$conn->connect_error);
$examples=[
 ['lrn'=>'111122223333','last_name'=>'Santos','first_name'=>'Maria','birthdate'=>'2009-04-22','age'=>17,'sex'=>'Female','place_of_birth'=>'Davao City','school_year'=>'2026 - 2027','grade_level'=>'Grade 11','student_type'=>'Grade 10 DRANHS Student'],
 ['lrn'=>'222233334444','last_name'=>'Reyes','first_name'=>'Juan','birthdate'=>'2008-05-15','age'=>18,'sex'=>'Male','place_of_birth'=>'Davao City','school_year'=>'2026 - 2027','grade_level'=>'Grade 12','student_type'=>'Transferee']
];
foreach($examples as $p){
    $sql=sprintf("INSERT INTO students (last_name, first_name, middle_name, extension_name, birthdate, age, sex, place_of_birth, mother_tongue, religion, ip_community, family_4ps, father_last_name, father_first_name, mother_last_name, mother_first_name, guardian_last_name, guardian_first_name, sped, pwd, semester, track, pathway, school_year, grade_level, lrn, student_type, street, province, city, barangay, zip_code, living_with, prev_school, prev_school_year, prev_section, psa_birth_cert, height, weight) VALUES ('%s','%s',NULL,NULL,'%s',%d,'%s','%s', 'Cebuano','Catholic','No','No','%s','%s','%s','%s','%s','%s','No','No','1st','Academic','STEM','%s','%s','%s','%s','Brgy 1','Davao del Sur','Davao City','Catalunan Grande','8000','Parents','DRANHS','2025-2026','10-A','1234567890',155,50)",
        $conn->real_escape_string($p['last_name']),
        $conn->real_escape_string($p['first_name']),
        $conn->real_escape_string($p['birthdate']),
        intval($p['age']),
        $conn->real_escape_string($p['sex']),
        $conn->real_escape_string($p['place_of_birth']),
        $conn->real_escape_string($p['last_name']),
        $conn->real_escape_string($p['first_name']),
        $conn->real_escape_string($p['last_name']),
        $conn->real_escape_string($p['first_name']),
        $conn->real_escape_string($p['last_name']),
        $conn->real_escape_string($p['first_name']),
        $conn->real_escape_string($p['school_year']),
        $conn->real_escape_string($p['grade_level']),
        $conn->real_escape_string($p['lrn']),
        $conn->real_escape_string($p['student_type'])
    );
    if($conn->query($sql)===TRUE){
        echo "inserted {$p['lrn']}\n";
    }else{
        echo "error {$p['lrn']}: " . $conn->error . "\n";
    }
}

$res=$conn->query("SELECT lrn,last_name,first_name,grade_level FROM students WHERE lrn IN ('111122223333','222233334444')");
while($r=mysqli_fetch_assoc($res)){
    echo json_encode($r)."\n";
}
$conn->close();
