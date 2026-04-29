<?php
/**
 * print_document.php — Generate filled BEEF enrollment form (.docx)
 * Template: uploads/templates/BEEF-Temp.docx
 * Paper:    8.5" × 13" (Legal/Folio)
 * Usage:    print_document.php?id=STUDENT_ID
 *
 * Requires: phpoffice/phpword (installed via Composer)
 */

session_start();
if (empty($_SESSION['user_id'])) {
    http_response_code(403);
    exit('Access denied.');
}

$student_id = (int)($_GET['id'] ?? 0);
if (!$student_id) exit('Invalid student ID.');

// ── Autoload ──────────────────────────────────────────────────────────────────
require_once __DIR__ . '/vendor/autoload.php';
require_once __DIR__ . '/pathway_strand_catalog.php';

use PhpOffice\PhpWord\TemplateProcessor;

// ── DB ────────────────────────────────────────────────────────────────────────
$conn = new mysqli('localhost', 'root', '', 'dranhswin');
if ($conn->connect_error) exit('Database error.');

$stmt = $conn->prepare("
    SELECT s.*, COALESCE(e.id_photo_path, '') AS _id_photo_path
    FROM students s
    LEFT JOIN encodings e ON e.student_id = s.id
    WHERE s.id = ?
    LIMIT 1
");
$stmt->bind_param("i", $student_id);
$stmt->execute();
$row = $stmt->get_result()->fetch_assoc();
$stmt->close();
$conn->close();

if (!$row) exit('Student not found.');

// ── Template path ─────────────────────────────────────────────────────────────
$template_path = __DIR__ . '/uploads/templates/BEEF-Temp.docx';
if (!file_exists($template_path)) {
    exit('Template not found. Please upload BEEF-Temp.docx to uploads/templates/');
}

// ── Helper: checkbox ──────────────────────────────────────────────────────────
function cb($value, $yes_values = ['Yes', '1', 'yes', 'true']) {
    return in_array($value, $yes_values, true) ? '☑' : '☐';
}

// ── Build values ──────────────────────────────────────────────────────────────
$full_name = strtoupper(trim(
    ($row['last_name']  ?? '') . ', ' .
    ($row['first_name'] ?? '') .
    (!empty($row['middle_name'])    ? ' ' . strtoupper(substr($row['middle_name'], 0, 1)) . '.' : '') .
    (!empty($row['extension_name']) ? ' ' . $row['extension_name'] : '')
));

$lrn         = $row['lrn']          ?: '';
$grade_level = $row['grade_level']  ?: '';
$track       = $row['track']        ?: '';
$pathway     = get_pathway_strand_label($grade_level, $row['pathway_strand'] ?? '');
$photo_path  = $row['_id_photo_path'] ?? '';

// Sex checkboxes
$sex = strtolower(trim($row['sex'] ?? ''));
$sex_male   = ($sex === 'male')   ? '☑' : '☐';
$sex_female = ($sex === 'female') ? '☑' : '☐';

// Semester
$sem = trim($row['semester'] ?? '');
$sem_1st = ($sem === '1st') ? '☑' : '☐';
$sem_2nd = ($sem === '2nd') ? '☑' : '☐';

// ── QR Code — download to temp file ──────────────────────────────────────────
$qr_data    = 'LRN:' . $lrn . '|NAME:' . $full_name;
$qr_url     = 'https://api.qrserver.com/v1/create-qr-code/?size=300x300&ecc=M&data=' . urlencode($qr_data);
$qr_tmp     = sys_get_temp_dir() . '/qr_' . $student_id . '_' . time() . '.png';
$qr_ok      = false;
$qr_content = @file_get_contents($qr_url);
if ($qr_content !== false) {
    file_put_contents($qr_tmp, $qr_content);
    $qr_ok = true;
}

// ── Process template ──────────────────────────────────────────────────────────
$tpl = new TemplateProcessor($template_path);

// Section 1 — General Info
$tpl->setValue('STUDENT_TYPE',     htmlspecialchars($row['student_type']      ?? ''));
$tpl->setValue('LRN',              htmlspecialchars($lrn));
$tpl->setValue('GRADE_LEVEL',      htmlspecialchars($grade_level));
$tpl->setValue('SCHOOL_YEAR',      htmlspecialchars($row['school_year']       ?? ''));
$tpl->setValue('PREV_SCHOOL_YEAR', htmlspecialchars($row['prev_school_year']  ?? ''));
$tpl->setValue('PREV_SECTION',     htmlspecialchars($row['prev_section']      ?? ''));
$tpl->setValue('HEIGHT',           htmlspecialchars($row['height']            ?? ''));
$tpl->setValue('WEIGHT',           htmlspecialchars($row['weight']            ?? ''));

// Section 2 — Personal Info
$tpl->setValue('PSA_BIRTH_CERT',   htmlspecialchars($row['psa_birth_cert']    ?? ''));
$tpl->setValue('LAST_NAME',        htmlspecialchars(strtoupper($row['last_name']   ?? '')));
$tpl->setValue('FIRST_NAME',       htmlspecialchars(strtoupper($row['first_name']  ?? '')));
$tpl->setValue('MIDDLE_NAME',      htmlspecialchars(strtoupper($row['middle_name'] ?? '')));
$tpl->setValue('EXTENSION_NAME',   htmlspecialchars(strtoupper($row['extension_name'] ?? '')));
$tpl->setValue('SEX_MALE',         $sex_male);
$tpl->setValue('SEX_FEMALE',       $sex_female);
$tpl->setValue('BIRTHDATE',        htmlspecialchars($row['birthdate']         ?? ''));
$tpl->setValue('AGE',              htmlspecialchars($row['age']               ?? ''));
$tpl->setValue('PLACE_OF_BIRTH',   htmlspecialchars($row['place_of_birth']    ?? ''));
$tpl->setValue('MOTHER_TONGUE',    htmlspecialchars($row['mother_tongue']     ?? ''));
$tpl->setValue('RELIGION',         htmlspecialchars($row['religion']          ?? ''));
$tpl->setValue('IP_COMMUNITY',     cb($row['ip_community'] ?? ''));
$tpl->setValue('FAMILY_4PS',       cb($row['family_4ps']   ?? ''));

// Section 3 — Address
$tpl->setValue('STREET',           htmlspecialchars($row['street']            ?? ''));
$tpl->setValue('BARANGAY',         htmlspecialchars($row['barangay']          ?? ''));
$tpl->setValue('CITY',             htmlspecialchars($row['city']              ?? ''));
$tpl->setValue('PROVINCE',         htmlspecialchars($row['province']          ?? ''));
$tpl->setValue('ZIP_CODE',         htmlspecialchars($row['zip_code']          ?? ''));
$tpl->setValue('LIVING_WITH',      htmlspecialchars($row['living_with']       ?? ''));

// Section 4 — Parents/Guardian
$tpl->setValue('FATHER_LAST_NAME',    htmlspecialchars(strtoupper($row['father_last_name']    ?? '')));
$tpl->setValue('FATHER_FIRST_NAME',   htmlspecialchars(strtoupper($row['father_first_name']   ?? '')));
$tpl->setValue('FATHER_MIDDLE_NAME',  htmlspecialchars(strtoupper($row['father_middle_name']  ?? '')));
$tpl->setValue('FATHER_CONTACT',      htmlspecialchars($row['father_contact']  ?? ''));
$tpl->setValue('MOTHER_LAST_NAME',    htmlspecialchars(strtoupper($row['mother_last_name']    ?? '')));
$tpl->setValue('MOTHER_FIRST_NAME',   htmlspecialchars(strtoupper($row['mother_first_name']   ?? '')));
$tpl->setValue('MOTHER_MIDDLE_NAME',  htmlspecialchars(strtoupper($row['mother_middle_name']  ?? '')));
$tpl->setValue('MOTHER_CONTACT',      htmlspecialchars($row['mother_contact']  ?? ''));
$tpl->setValue('GUARDIAN_LAST_NAME',  htmlspecialchars(strtoupper($row['guardian_last_name']  ?? '')));
$tpl->setValue('GUARDIAN_FIRST_NAME', htmlspecialchars(strtoupper($row['guardian_first_name'] ?? '')));
$tpl->setValue('GUARDIAN_MIDDLE_NAME',htmlspecialchars(strtoupper($row['guardian_middle_name']?? '')));
$tpl->setValue('GUARDIAN_CONTACT',    htmlspecialchars($row['guardian_contact'] ?? ''));

// Section 5 — SPED/PWD
$tpl->setValue('SPED',             cb($row['sped']  ?? ''));
$tpl->setValue('SPED_DIAGNOSIS',   htmlspecialchars($row['sped_diagnosis'] ?? ''));
$tpl->setValue('PWD',              cb($row['pwd']   ?? ''));
$tpl->setValue('PWD_ID',           htmlspecialchars($row['pwd_id']         ?? ''));

// Section 6 — Academic
$tpl->setValue('SEM_1ST',          $sem_1st);
$tpl->setValue('SEM_2ND',          $sem_2nd);
$tpl->setValue('SEMESTER',         htmlspecialchars($sem));
$tpl->setValue('TRACK',            htmlspecialchars($track));
$tpl->setValue('PATHWAY_STRAND',   htmlspecialchars($pathway));

// Learner name + LRN line
$tpl->setValue('FULL_NAME',        htmlspecialchars($full_name));

// ── Images ────────────────────────────────────────────────────────────────────
// ID Photo (1×1 inch = ~96px at 96dpi)
$photo_abs = __DIR__ . '/' . $photo_path;
if ($photo_path && file_exists($photo_abs)) {
    try {
        $tpl->setImageValue('ID_PHOTO', [
            'path'   => $photo_abs,
            'width'  => 96,
            'height' => 96,
            'ratio'  => false,
        ]);
    } catch (Exception $e) {
        $tpl->setValue('ID_PHOTO', '[Photo]');
    }
} else {
    $tpl->setValue('ID_PHOTO', '');
}

// QR Code (~1.5 inch = ~144px)
if ($qr_ok && file_exists($qr_tmp)) {
    try {
        $tpl->setImageValue('QR_CODE', [
            'path'   => $qr_tmp,
            'width'  => 144,
            'height' => 144,
            'ratio'  => false,
        ]);
    } catch (Exception $e) {
        $tpl->setValue('QR_CODE', '[QR]');
    }
} else {
    $tpl->setValue('QR_CODE', '');
}

// ── Set page size to 8.5" × 13" (Legal/Folio) in the output ─────────────────
// PHPWord TemplateProcessor doesn't expose page size directly,
// but we can patch the document XML after saving to a temp file.
$out_tmp = sys_get_temp_dir() . '/BEEF_' . $student_id . '_' . time() . '.docx';
$tpl->saveAs($out_tmp);

// Patch page size: 8.5"×13" = 12240×18720 twips (1 inch = 1440 twips)
// w:w="12240" w:h="18720"
$zip = new ZipArchive();
if ($zip->open($out_tmp) === true) {
    $doc_xml = $zip->getFromName('word/document.xml');
    if ($doc_xml) {
        // Replace existing pgSz or inject after sectPr opening tag
        $doc_xml = preg_replace(
            '/<w:pgSz[^\/]*\/>/',
            '<w:pgSz w:w="12240" w:h="18720" w:orient="portrait"/>',
            $doc_xml
        );
        // If no pgSz found, inject before </w:sectPr>
        if (strpos($doc_xml, 'w:pgSz') === false) {
            $doc_xml = str_replace(
                '</w:sectPr>',
                '<w:pgSz w:w="12240" w:h="18720" w:orient="portrait"/></w:sectPr>',
                $doc_xml
            );
        }
        $zip->addFromString('word/document.xml', $doc_xml);
    }
    $zip->close();
}

// ── Stream to browser ─────────────────────────────────────────────────────────
$safe_name = preg_replace('/[^a-zA-Z0-9_\-]/', '_', $full_name);
$filename  = 'BEEF_' . $safe_name . '_' . date('Ymd') . '.docx';

header('Content-Type: application/vnd.openxmlformats-officedocument.wordprocessingml.document');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Content-Length: ' . filesize($out_tmp));
header('Cache-Control: no-cache, no-store, must-revalidate');
header('Pragma: no-cache');

readfile($out_tmp);

// ── Cleanup ───────────────────────────────────────────────────────────────────
@unlink($out_tmp);
if ($qr_ok) @unlink($qr_tmp);
exit;
