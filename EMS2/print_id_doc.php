<?php
/**
 * print_id_doc.php — Generate filled ID card (.docx) from ID-Temp.docx
 * Template: uploads/templates/ID-Temp.docx
 * Usage:    print_id_doc.php?id=STUDENT_ID
 *           print_id_doc.php?id=STUDENT_ID&preview=1  (inline stream)
 */

session_start();
if (empty($_SESSION['user_id'])) {
    http_response_code(403);
    exit('Access denied.');
}

$student_id = (int)($_GET['id'] ?? 0);
if (!$student_id) exit('Invalid student ID.');

require_once __DIR__ . '/vendor/autoload.php';
require_once __DIR__ . '/pathway_strand_catalog.php';

use PhpOffice\PhpWord\TemplateProcessor;

$conn = new mysqli('localhost', 'root', '', 'dranhswin');
if ($conn->connect_error) exit('Database error.');

$stmt = $conn->prepare("
    SELECT s.*, COALESCE(e.id_photo_path, '') AS _id_photo_path
    FROM students s
    LEFT JOIN encodings e ON e.student_id = s.id
    WHERE s.id = ? LIMIT 1
");
$stmt->bind_param("i", $student_id);
$stmt->execute();
$row = $stmt->get_result()->fetch_assoc();
$stmt->close();
$conn->close();

if (!$row) exit('Student not found.');

$template_path = __DIR__ . '/uploads/templates/ID-Temp.docx';
if (!file_exists($template_path)) {
    exit('ID template not found. Please upload ID-Temp.docx to uploads/templates/');
}

// ── Build values ──────────────────────────────────────────────────────────────
$full_name = strtoupper(trim(
    ($row['last_name']  ?? '') . ', ' .
    ($row['first_name'] ?? '') .
    (!empty($row['middle_name'])    ? ' ' . strtoupper(substr($row['middle_name'], 0, 1)) . '.' : '') .
    (!empty($row['extension_name']) ? ' ' . $row['extension_name'] : '')
));
$lrn        = $row['lrn'] ?: 'N/A';
$photo_path = $row['_id_photo_path'] ?? '';

// ── QR Code ───────────────────────────────────────────────────────────────────
// Encode: LRN, Name, School Year, Semester, Grade Level, Track, Pathway, Status
$qr_data = implode('|', [
    'LRN:'    . $lrn,
    'NAME:'   . $full_name,
    'SY:'     . ($row['school_year']       ?? ''),
    'SEM:'    . ($row['semester']          ?? ''),
    'GRADE:'  . ($row['grade_level']       ?? ''),
    'TRACK:'  . ($row['track']             ?? ''),
    'STRAND:' . ($row['pathway_strand']    ?? ''),
    'STATUS:' . strtoupper($row['enrollment_status'] ?? ''),
]);
$qr_url     = 'https://api.qrserver.com/v1/create-qr-code/?size=400x400&ecc=M&data=' . urlencode($qr_data);
$qr_tmp     = sys_get_temp_dir() . '/qr_id_' . $student_id . '_' . time() . '.png';
$qr_ok      = false;
$qr_content = @file_get_contents($qr_url);
if ($qr_content !== false) {
    file_put_contents($qr_tmp, $qr_content);
    $qr_ok = true;
}

// ── Process template ──────────────────────────────────────────────────────────
$tpl = new TemplateProcessor($template_path);

$tpl->setValue('FULL_NAME', htmlspecialchars($full_name));
$tpl->setValue('LRN',       htmlspecialchars($lrn));

// ID Photo — 1.0" × 1.0" = 96px with ratio maintained
$photo_abs = __DIR__ . '/' . $photo_path;
if ($photo_path && file_exists($photo_abs)) {
    try {
        $tpl->setImageValue('ID_PHOTO', [
            'path'   => $photo_abs,
            'width'  => 96,
            'height' => 96,
            'ratio'  => true,
        ]);
    } catch (Exception $e) {
        $tpl->setValue('ID_PHOTO', '[Photo]');
    }
} else {
    $tpl->setValue('ID_PHOTO', '');
}

// QR Code — 0.75" × 0.75" = 72px
if ($qr_ok && file_exists($qr_tmp)) {
    try {
        $tpl->setImageValue('QR_CODE', [
            'path'   => $qr_tmp,
            'width'  => 72,
            'height' => 72,
            'ratio'  => true,
        ]);
    } catch (Exception $e) {
        $tpl->setValue('QR_CODE', '[QR]');
    }
} else {
    $tpl->setValue('QR_CODE', '');
}

// ── Save to temp ──────────────────────────────────────────────────────────────
$out_tmp = sys_get_temp_dir() . '/ID_' . $student_id . '_' . time() . '.docx';
$tpl->saveAs($out_tmp);

// ── Filename: LASTNAME_FI_LRN_ID.docx ────────────────────────────────────────
$last     = strtoupper(preg_replace('/[^a-zA-Z0-9]/', '', $row['last_name']  ?? ''));
$fi       = strtoupper(substr(preg_replace('/[^a-zA-Z]/', '', $row['first_name'] ?? ''), 0, 1));
$lrn_f    = preg_replace('/[^0-9]/', '', $lrn);
$filename = $last . '_' . $fi . '_' . $lrn_f . '_ID.docx';

$is_preview = isset($_GET['preview']) && $_GET['preview'] === '1';

header('Content-Type: application/vnd.openxmlformats-officedocument.wordprocessingml.document');
header('Content-Disposition: ' . ($is_preview ? 'inline' : 'attachment') . '; filename="' . $filename . '"');
header('Content-Length: ' . filesize($out_tmp));
header('Cache-Control: no-cache, no-store, must-revalidate');
header('Pragma: no-cache');

readfile($out_tmp);

@unlink($out_tmp);
if ($qr_ok) @unlink($qr_tmp);
exit;
