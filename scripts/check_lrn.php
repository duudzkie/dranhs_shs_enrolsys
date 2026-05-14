<?php
header('Content-Type: application/json');
require_once __DIR__ . '/../pathway_strand_catalog.php';
require_once __DIR__ . '/../db.php';

try {
    $conn = db_connect();
} catch (Exception $e) {
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

$school_year = '';
$stem_qualifier_enabled = true;
$settings_stmt = $conn->prepare("SELECT setting_value FROM system_settings WHERE setting_key = 'academic_year' LIMIT 1");
if ($settings_stmt) {
    $settings_stmt->execute();
    $settings_result = $settings_stmt->get_result();
    if ($settings_result && ($settings_row = $settings_result->fetch_assoc())) {
        $school_year = trim((string)($settings_row['setting_value'] ?? ''));
    }
    $settings_stmt->close();
}

$stem_toggle_stmt = $conn->prepare("SELECT setting_value FROM system_settings WHERE setting_key = 'stem_qualifier_enabled' LIMIT 1");
if ($stem_toggle_stmt) {
    $stem_toggle_stmt->execute();
    $stem_toggle_result = $stem_toggle_stmt->get_result();
    if ($stem_toggle_result && ($stem_toggle_row = $stem_toggle_result->fetch_assoc())) {
        $stem_qualifier_enabled = (($stem_toggle_row['setting_value'] ?? '1') === '1');
    }
    $stem_toggle_stmt->close();
}

$stem_qualifier = null;
$g11_completer = null;
$watchlist_entry = null;

$stem_stmt = $conn->prepare("SELECT pathway_cluster FROM stem_qualifiers WHERE lrn = ? AND school_year = ? LIMIT 1");
if ($stem_stmt && $school_year !== '' && $stem_qualifier_enabled) {
    $stem_stmt->bind_param("ss", $lrn, $school_year);
    $stem_stmt->execute();
    $stem_result = $stem_stmt->get_result();
    if ($stem_result) {
        $stem_qualifier = $stem_result->fetch_assoc() ?: null;
    }
    $stem_stmt->close();
}

$watch_stmt = $conn->prepare("SELECT issue_type, issue_details FROM watchlist WHERE lrn = ? AND school_year = ? LIMIT 1");
if ($watch_stmt && $school_year !== '') {
    $watch_stmt->bind_param("ss", $lrn, $school_year);
    $watch_stmt->execute();
    $watch_result = $watch_stmt->get_result();
    if ($watch_result) {
        $watchlist_entry = $watch_result->fetch_assoc() ?: null;
    }
    $watch_stmt->close();
}

$g11_stmt = $conn->prepare("SELECT last_name, first_name, middle_name, section, strand, completer_status FROM g11_completers WHERE lrn = ? AND school_year = ? LIMIT 1");
if ($g11_stmt && $school_year !== '') {
    $g11_stmt->bind_param("ss", $lrn, $school_year);
    $g11_stmt->execute();
    $g11_result = $g11_stmt->get_result();
    if ($g11_result) {
        $g11_completer = $g11_result->fetch_assoc() ?: null;
    }
    $g11_stmt->close();
}

$stem_pathway_code = '';
$stem_pathway_label = '';
if (!empty($stem_qualifier['pathway_cluster'])) {
    $stem_pathway_label = trim((string)$stem_qualifier['pathway_cluster']);
    $stem_pathway_code = get_pathway_strand_code('Grade 11', $stem_pathway_label);
    $stem_pathway_label = get_pathway_strand_label('Grade 11', $stem_pathway_code ?: $stem_pathway_label);
}

$conn->close();

echo json_encode([
    'ok' => true,
    'exists' => $exists,
    'message' => $exists ? 'This LRN already exists in the database.' : 'LRN is available.',
    'stem_qualifier_enabled' => $stem_qualifier_enabled,
    'stem_qualified' => !empty($stem_qualifier),
    'stem_pathway_code' => $stem_pathway_code,
    'stem_pathway_label' => $stem_pathway_label,
    'g11_completer' => !empty($g11_completer),
    'g11_completer_last_name' => trim((string)($g11_completer['last_name'] ?? '')),
    'g11_completer_first_name' => trim((string)($g11_completer['first_name'] ?? '')),
    'g11_completer_middle_name' => trim((string)($g11_completer['middle_name'] ?? '')),
    'g11_completer_section' => trim((string)($g11_completer['section'] ?? '')),
    'g11_completer_strand' => trim((string)($g11_completer['strand'] ?? '')),
    'g11_completer_status' => trim((string)($g11_completer['completer_status'] ?? '')),
    'watchlisted' => !empty($watchlist_entry),
    'watch_issue_type' => trim((string)($watchlist_entry['issue_type'] ?? '')),
    'watch_issue_details' => trim((string)($watchlist_entry['issue_details'] ?? '')),
]);
?>
