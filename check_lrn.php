<?php
header('Content-Type: application/json');

$db_host = 'localhost';
$db_user = 'root';
$db_pass = '';
$db_name = 'dranhswin';
$conn = new mysqli($db_host, $db_user, $db_pass, $db_name);

if ($conn->connect_error) {
    http_response_code(500);
    echo json_encode([
        'ok' => false,
        'exists' => false,
        'message' => 'Database connection failed.'
    ]);
    exit;
}

$lrn = isset($_GET['lrn']) ? preg_replace('/\D/', '', $_GET['lrn']) : '';

if ($lrn === '') {
    echo json_encode([
        'ok' => true,
        'exists' => false,
        'message' => ''
    ]);
    $conn->close();
    exit;
}

if (!preg_match('/^\d{12}$/', $lrn)) {
    echo json_encode([
        'ok' => true,
        'exists' => false,
        'message' => 'LRN must be exactly 12 digits.'
    ]);
    $conn->close();
    exit;
}

$stmt = $conn->prepare("SELECT id FROM students WHERE lrn = ? LIMIT 1");
if (!$stmt) {
    http_response_code(500);
    echo json_encode([
        'ok' => false,
        'exists' => false,
        'message' => 'Unable to prepare LRN check.'
    ]);
    $conn->close();
    exit;
}

$stmt->bind_param("s", $lrn);
$stmt->execute();
$stmt->store_result();
$exists = $stmt->num_rows > 0;
$stmt->close();
$conn->close();

echo json_encode([
    'ok' => true,
    'exists' => $exists,
    'message' => $exists ? 'This LRN already exists in the database.' : 'LRN is available.'
]);
?>
