<?php
/**
 * download_id_assets.php
 * Serves ID photo, QR code, or ZIP of both for a student.
 * Usage:
 *   ?id=STUDENT_ID&type=photo   → download ID photo
 *   ?id=STUDENT_ID&type=qr      → download QR code PNG
 *   ?id=STUDENT_ID&type=zip     → download ZIP of both
 */

session_start();
if (empty($_SESSION['user_id'])) {
    http_response_code(403);
    exit('Access denied.');
}

$student_id = (int)($_GET['id']   ?? 0);
$type       = $_GET['type'] ?? 'photo';
if (!$student_id) exit('Invalid student ID.');

$conn = new mysqli('localhost', 'root', '', 'dranhswin');
if ($conn->connect_error) exit('Database error.');

$stmt = $conn->prepare("
    SELECT s.lrn, s.last_name, s.first_name, COALESCE(e.id_photo_path, '') AS _id_photo_path
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

// ── Build filename base: LASTNAME_FI_LRN ─────────────────────────────────────
$last    = strtoupper(preg_replace('/[^a-zA-Z0-9]/', '', $row['last_name']  ?? ''));
$fi      = strtoupper(substr(preg_replace('/[^a-zA-Z]/', '', $row['first_name'] ?? ''), 0, 1));
$lrn_f   = preg_replace('/[^0-9]/', '', $row['lrn'] ?? '');
$base    = $last . '_' . $fi . '_' . $lrn_f;

// ── QR temp file ─────────────────────────────────────────────────────────────
$full_name = strtoupper(trim(
    ($row['last_name'] ?? '') . ', ' . ($row['first_name'] ?? '')
));
$qr_data   = implode('|', [
    'LRN:'    . ($row['lrn'] ?? ''),
    'NAME:'   . $full_name,
    'SY:'     . ($row['school_year']       ?? ''),
    'SEM:'    . ($row['semester']          ?? ''),
    'GRADE:'  . ($row['grade_level']       ?? ''),
    'TRACK:'  . ($row['track']             ?? ''),
    'STRAND:' . ($row['pathway_strand']    ?? ''),
    'STATUS:' . strtoupper($row['enrollment_status'] ?? ''),
]);
$qr_url    = 'https://api.qrserver.com/v1/create-qr-code/?size=400x400&ecc=M&data=' . urlencode($qr_data);
$qr_tmp    = sys_get_temp_dir() . '/qr_dl_' . $student_id . '.png';
$qr_ok     = false;
if (!file_exists($qr_tmp) || (time() - filemtime($qr_tmp)) > 300) {
    $qr_content = @file_get_contents($qr_url);
    if ($qr_content !== false) {
        file_put_contents($qr_tmp, $qr_content);
        $qr_ok = true;
    }
} else {
    $qr_ok = true;
}

// ── Photo path ────────────────────────────────────────────────────────────────
$photo_path = $row['_id_photo_path'] ?? '';
$photo_abs  = $photo_path ? __DIR__ . '/' . $photo_path : '';
$photo_ext  = $photo_path ? strtolower(pathinfo($photo_path, PATHINFO_EXTENSION)) : 'jpg';

// ── Serve based on type ───────────────────────────────────────────────────────
if ($type === 'photo') {
    if (!$photo_abs || !file_exists($photo_abs)) exit('No photo available.');
    $mime = in_array($photo_ext, ['png']) ? 'image/png' : 'image/jpeg';
    header('Content-Type: ' . $mime);
    header('Content-Disposition: attachment; filename="' . $base . '_photo.' . $photo_ext . '"');
    header('Content-Length: ' . filesize($photo_abs));
    readfile($photo_abs);
    exit;
}

if ($type === 'qr') {
    if (!$qr_ok || !file_exists($qr_tmp)) exit('QR generation failed.');
    header('Content-Type: image/png');
    header('Content-Disposition: attachment; filename="' . $base . '_qr.png"');
    header('Content-Length: ' . filesize($qr_tmp));
    readfile($qr_tmp);
    exit;
}

if ($type === 'zip') {
    $zip_tmp = sys_get_temp_dir() . '/id_assets_' . $student_id . '_' . time() . '.zip';
    $zip = new ZipArchive();
    if ($zip->open($zip_tmp, ZipArchive::CREATE) !== true) exit('Cannot create ZIP.');

    if ($photo_abs && file_exists($photo_abs)) {
        $zip->addFile($photo_abs, $base . '_photo.' . $photo_ext);
    }
    if ($qr_ok && file_exists($qr_tmp)) {
        $zip->addFile($qr_tmp, $base . '_qr.png');
    }
    $zip->close();

    header('Content-Type: application/zip');
    header('Content-Disposition: attachment; filename="' . $base . '_id_assets.zip"');
    header('Content-Length: ' . filesize($zip_tmp));
    readfile($zip_tmp);
    @unlink($zip_tmp);
    exit;
}

exit('Invalid type.');
