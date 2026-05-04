<?php
/**
 * check_status.php — Student enrollment status lookup (public, no auth required)
 * Returns JSON for a given LRN
 * Usage: check_status.php?lrn=123456789012
 */

header('Content-Type: application/json');
header('X-Content-Type-Options: nosniff');
require_once __DIR__ . '/db.php';

$lrn = trim($_GET['lrn'] ?? '');

// Basic validation — LRN is 12 digits
if (!preg_match('/^\d{10,12}$/', $lrn)) {
    echo json_encode(['found' => false, 'message' => 'Please enter a valid LRN (10–12 digits).']);
    exit;
}

$conn = db_connect();

$stmt = $conn->prepare("
    SELECT
        s.id, s.lrn, s.last_name, s.first_name, s.middle_name, s.extension_name,
        s.grade_level, s.track, s.pathway_strand, s.school_year, s.semester,
        s.enrollment_status, s.assigned_section,
        COALESCE(e.id_photo_path, '') AS photo_path
    FROM students s
    LEFT JOIN encodings e ON e.student_id = s.id
    WHERE s.lrn = ?
    LIMIT 1
");
$stmt->bind_param("s", $lrn);
$stmt->execute();
$row = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$row) {
    $conn->close();
    echo json_encode(['found' => false, 'message' => 'No record found for LRN <strong>' . htmlspecialchars($lrn) . '</strong>. Please check and try again.']);
    exit;
}

// Get classroom info (adviser + group chat link) via assigned section
$classroom = null;
if (!empty($row['assigned_section'])) {
    $cs = $conn->prepare("
        SELECT c.adviser_name, c.group_chat_url, a.avatar
        FROM classrooms c
        LEFT JOIN advisers_accounts a ON a.id = c.adviser_id
        WHERE c.section_name = ?
        LIMIT 1
    ");
    $cs->bind_param("s", $row['assigned_section']);
    $cs->execute();
    $classroom = $cs->get_result()->fetch_assoc();
    $cs->close();
}

$conn->close();

// Resolve pathway label
require_once __DIR__ . '/pathway_strand_catalog.php';
$pathway_label = get_pathway_strand_label($row['grade_level'] ?? '', $row['pathway_strand'] ?? '');

// Grade number only
$grade_num = str_replace('Grade ', '', $row['grade_level'] ?? '');

// Status label + step
$status = $row['enrollment_status'] ?? 'for_evaluation';
$status_steps = [
    'for_evaluation' => 1,
    'for_encoding'   => 2,
    'enrolled'       => 4,
    'withdrawn'      => 0,
];
$step = $status_steps[$status] ?? 1;

$status_labels = [
    'for_evaluation' => 'For Evaluation',
    'for_encoding'   => 'For Encoding',
    'enrolled'       => 'Enrolled',
    'withdrawn'      => 'Withdrawn',
];

echo json_encode([
    'found'         => true,
    'lrn'           => $row['lrn'],
    'full_name'     => strtoupper(trim(
        ($row['last_name'] ?? '') . ', ' .
        ($row['first_name'] ?? '') .
        (!empty($row['middle_name']) ? ' ' . strtoupper(substr($row['middle_name'], 0, 1)) . '.' : '') .
        (!empty($row['extension_name']) ? ' ' . $row['extension_name'] : '')
    )),
    'school_year'   => $row['school_year'] ?? '',
    'grade_level'   => $row['grade_level'] ?? '',
    'grade_num'     => $grade_num,
    'track'         => $row['track'] ?? '',
    'pathway'       => $pathway_label,
    'section'       => $row['assigned_section'] ?? '',
    'semester'      => $row['semester'] ?? '',
    'status'        => $status,
    'status_label'  => $status_labels[$status] ?? 'Unknown',
    'step'          => $step,
    'photo_path'    => $row['photo_path'] ?? '',
    'adviser'       => $classroom['adviser_name'] ?? '',
    'group_chat_url'=> $classroom['group_chat_url'] ?? '',
]);
