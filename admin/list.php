<?php
require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/../activity_log.php';
$list_conn = db_connect();

$toast_msg = ''; $toast_type = 'success';
$stem_rows = []; $g11_rows = []; $watch_rows = [];
$g11_sections = [];
$stem_qualifier_enabled = true;

// School year from settings
$school_year = date('Y') . ' - ' . (date('Y')+1);
if (!$list_conn->connect_error) {
    $sy = $list_conn->query("SELECT setting_value FROM system_settings WHERE setting_key='academic_year'");
    if ($sy && $r = $sy->fetch_assoc()) $school_year = $r['setting_value'];

    $stem_toggle_result = $list_conn->query("SELECT setting_value FROM system_settings WHERE setting_key='stem_qualifier_enabled' LIMIT 1");
    if ($stem_toggle_result && ($stem_toggle_row = $stem_toggle_result->fetch_assoc())) {
        $stem_qualifier_enabled = (($stem_toggle_row['setting_value'] ?? '1') === '1');
    }
}

$stem_clusters = [
    'med_allied'         => 'Medical & Allied Health',
    'eng_avi'            => 'Engineering & Aviation',
    'earth_space_weather'=> 'Earth, Space & Weather Science',
];

$g12_strands = [
    'GAS' => 'Academic - GAS',
    'STEM' => 'Academic - STEM',
    'HUMSS' => 'Academic - HUMSS',
    'ABM' => 'Academic - ABM',
    'CSS' => 'TVL - CSS',
    'EIM' => 'TVL - EIM',
    'Cookery' => 'TVL - Cookery',
    'Beauty Care' => 'TVL - Beauty Care',
];

$g11_completer_statuses = [
    'regular' => 'Grade 11 Completer - Regular',
    'irregular' => 'Grade 11 Completer - Irregular',
];

$stem_cluster_selections = [
    '1' => 'med_allied',
    '2' => 'eng_avi',
    '3' => 'earth_space_weather',
];

function ensure_table_columns(mysqli $conn, string $table_name, array $columns): void {
    $safe_table = $conn->real_escape_string($table_name);

    foreach ($columns as $column_name => $definition) {
        $safe_column = $conn->real_escape_string($column_name);
        $exists = $conn->query(
            "SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS
             WHERE TABLE_SCHEMA = '" . $conn->real_escape_string(DB_NAME) . "'
             AND TABLE_NAME = '{$safe_table}'
             AND COLUMN_NAME = '{$safe_column}'"
        );

        if ($exists && $exists->num_rows === 0) {
            $conn->query("ALTER TABLE `{$table_name}` ADD COLUMN `{$column_name}` {$definition}");
        }
    }
}

$csv_templates = [
    'stem' => "last_name,first_name,middle_name,lrn,general_average,pathway_cluster\nDela Cruz,Juan,S,123456789012,95.50,2\n",
    'g11' => "last_name,first_name,middle_name,sex,lrn,section,strand,completer_status\nSantos,Maria,L,Female,123456789012,HYDROGEN,STEM,regular\n",
    'watch' => "last_name,first_name,middle_name,lrn,issue_type,issue_details\nReyes,Ana,P,123456789012,Missing Requirement,Needs to submit report card\n",
];

function normalize_stem_cluster($cluster_value, $stem_clusters, $stem_cluster_selections) {
    $cluster_value = trim((string)$cluster_value);
    if ($cluster_value === '') return '';

    if (isset($stem_cluster_selections[$cluster_value])) {
        return $stem_cluster_selections[$cluster_value];
    }

    if (isset($stem_clusters[$cluster_value])) {
        return $cluster_value;
    }

    foreach ($stem_clusters as $code => $label) {
        if (strcasecmp($cluster_value, $label) === 0) {
            return $code;
        }
    }

    return $cluster_value;
}

function normalize_lrn($lrn_value) {
    $lrn_value = trim((string)$lrn_value);
    if ($lrn_value === '') return '';

    $lrn_value = preg_replace('/\.0+$/', '', $lrn_value);
    $lrn_value = preg_replace('/\s+/', '', $lrn_value);

    return $lrn_value;
}

function is_valid_lrn($lrn_value) {
    return (bool)preg_match('/^\d{12}$/', $lrn_value);
}

function record_exists_by_lrn($conn, $table_name, $lrn_value, $school_year) {
    if ($lrn_value === '') return false;

    $allowed_tables = ['stem_qualifiers', 'g11_completers', 'watchlist'];
    if (!in_array($table_name, $allowed_tables, true)) return false;

    $stmt = $conn->prepare("SELECT id FROM {$table_name} WHERE lrn = ? AND school_year = ? LIMIT 1");
    if (!$stmt) return false;

    $stmt->bind_param('ss', $lrn_value, $school_year);
    $stmt->execute();
    $stmt->store_result();
    $exists = $stmt->num_rows > 0;
    $stmt->close();

    return $exists;
}

function student_exists_by_lrn($conn, $lrn_value) {
    if ($lrn_value === '') return false;

    $stmt = $conn->prepare("SELECT id FROM students WHERE lrn = ? LIMIT 1");
    if (!$stmt) return false;

    $stmt->bind_param('s', $lrn_value);
    $stmt->execute();
    $stmt->store_result();
    $exists = $stmt->num_rows > 0;
    $stmt->close();

    return $exists;
}

function normalize_g11_completer_status($status_value, $allowed_statuses) {
    $status_value = strtolower(trim((string)$status_value));
    if ($status_value === '') return 'regular';
    return array_key_exists($status_value, $allowed_statuses) ? $status_value : 'regular';
}

function normalize_g12_strand($strand_value, $allowed_strands) {
    $strand_value = trim((string)$strand_value);
    if ($strand_value === '') return '';

    foreach ($allowed_strands as $code => $label) {
        if (strcasecmp($strand_value, $code) === 0 || strcasecmp($strand_value, $label) === 0) {
            return $code;
        }
    }

    return $strand_value;
}

function g12_track_from_strand($strand_value) {
    $strand_value = strtoupper(trim((string)$strand_value));
    return in_array($strand_value, ['GAS', 'STEM', 'HUMSS', 'ABM'], true) ? 'Academic' : 'TVL';
}

if (!$list_conn->connect_error) {
    ensure_table_columns($list_conn, 'stem_qualifiers', [
        'last_name' => "VARCHAR(100) NULL AFTER `id`",
        'first_name' => "VARCHAR(100) NULL AFTER `last_name`",
        'middle_name' => "VARCHAR(100) NULL AFTER `first_name`",
        'general_average' => "DECIMAL(5,2) NULL AFTER `lrn`",
        'added_by' => "INT NULL AFTER `school_year`",
    ]);

    ensure_table_columns($list_conn, 'g11_completers', [
        'sex' => "ENUM('Male', 'Female') NULL AFTER `middle_name`",
        'added_by' => "INT NULL AFTER `school_year`",
    ]);

    ensure_table_columns($list_conn, 'watchlist', [
        'last_name' => "VARCHAR(100) NULL AFTER `id`",
        'first_name' => "VARCHAR(100) NULL AFTER `last_name`",
        'middle_name' => "VARCHAR(100) NULL AFTER `first_name`",
        'added_by' => "INT NULL AFTER `school_year`",
    ]);

    if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && isset($_POST['list_action'])) {
        $uid = (int)($_SESSION['user_id'] ?? 0);
        $action = $_POST['list_action'];

        // ---- STEM ----
        if ($action === 'toggle_stem_qualifier') {
            $stem_qualifier_enabled = isset($_POST['stem_qualifier_enabled']);
            $setting_value = $stem_qualifier_enabled ? '1' : '0';
            $update = $list_conn->prepare("UPDATE system_settings SET setting_value = ? WHERE setting_key = 'stem_qualifier_enabled'");
            if ($update) {
                $update->bind_param("s", $setting_value);
                $update->execute();
                $needs_insert = $update->affected_rows === 0;
                $update->close();

                if ($needs_insert) {
                    $check = $list_conn->query("SELECT setting_key FROM system_settings WHERE setting_key = 'stem_qualifier_enabled'");
                    if ($check && $check->num_rows === 0) {
                        $insert = $list_conn->prepare("INSERT INTO system_settings (setting_key, setting_value) VALUES ('stem_qualifier_enabled', ?)");
                        if ($insert) {
                            $insert->bind_param("s", $setting_value);
                            $insert->execute();
                            $insert->close();
                        }
                    }
                }
            }
            $toast_msg = $stem_qualifier_enabled
                ? 'STEM qualifier restriction is now ON. Only listed qualifiers can choose restricted STEM clusters.'
                : 'STEM qualifier restriction is now OFF. Students may freely choose any STEM cluster.';
            log_activity($list_conn, 'settings_updated', 'STEM qualifier restriction toggled ' . ($stem_qualifier_enabled ? 'ON' : 'OFF'));
        } elseif ($action === 'add_stem') {
            $ln = trim($_POST['last_name']??''); $fn = trim($_POST['first_name']??'');
            $mn = trim($_POST['middle_name']??''); $lrn = normalize_lrn($_POST['lrn']??'');
            $ga_input = $_POST['general_average'] ?? '';
            $ga = $ga_input !== '' ? (float)$ga_input : null;
            $cl = normalize_stem_cluster($_POST['pathway_cluster']??'', $stem_clusters, $stem_cluster_selections);
            if ($ln && $fn) {
                if ($lrn !== '' && !is_valid_lrn($lrn)) {
                    $toast_msg = 'LRN must be exactly 12 digits.';
                    $toast_type = 'error';
                } elseif ($lrn !== '' && student_exists_by_lrn($list_conn, $lrn)) {
                    $toast_msg = 'LRN already exists in the student table. STEM qualifier was not added.';
                    $toast_type = 'error';
                } elseif ($lrn !== '' && record_exists_by_lrn($list_conn, 'stem_qualifiers', $lrn, $school_year)) {
                    $toast_msg = 'Duplicate LRN found. STEM qualifier was not added again.';
                    $toast_type = 'error';
                } else {
                    $s = $list_conn->prepare("INSERT INTO stem_qualifiers (last_name,first_name,middle_name,lrn,general_average,pathway_cluster,school_year,added_by) VALUES (?,?,?,?,?,?,?,?)");
                    if ($s) { $s->bind_param("ssssdssi",$ln,$fn,$mn,$lrn,$ga,$cl,$school_year,$uid); $s->execute(); $s->close(); }
                    $toast_msg = 'STEM qualifier added.';
                    log_activity($list_conn, 'list_entry_added', 'Added STEM qualifier: ' . $ln . ', ' . $fn . ' (LRN: ' . $lrn . ')');
                }
            }
        } elseif ($action === 'delete_stem') {
            $id = (int)($_POST['row_id']??0);
            $s = $list_conn->prepare("DELETE FROM stem_qualifiers WHERE id=?");
            if ($s) { $s->bind_param("i",$id); $s->execute(); $s->close(); }
            $toast_msg = 'Record deleted.'; $toast_type = 'error';
            log_activity($list_conn, 'list_entry_deleted', 'Deleted STEM qualifier ID#' . $id);
        } elseif ($action === 'csv_stem') {
            if (isset($_FILES['csv_file']) && $_FILES['csv_file']['error']===0) {
                $handle = fopen($_FILES['csv_file']['tmp_name'],'r');
                fgetcsv($handle); // skip header
                $count = 0; $duplicate_count = 0; $invalid_lrn_count = 0; $student_duplicate_count = 0;
                while (($row = fgetcsv($handle)) !== false) {
                    if (count($row) < 2) continue;
                    [$ln,$fn,$mn,$lrn,$ga,$cl] = array_pad($row,6,'');
                    $lrn = normalize_lrn($lrn);
                    if ($lrn === '' || !is_valid_lrn($lrn)) { $invalid_lrn_count++; continue; }
                    if (student_exists_by_lrn($list_conn, $lrn)) { $student_duplicate_count++; continue; }
                    if (record_exists_by_lrn($list_conn, 'stem_qualifiers', $lrn, $school_year)) { $duplicate_count++; continue; }
                    $ln = trim($ln);
                    $fn = trim($fn);
                    $mn = trim($mn);
                    $ga = $ga!=='' ? (float)$ga : null;
                    $cl = normalize_stem_cluster($cl, $stem_clusters, $stem_cluster_selections);
                    $s = $list_conn->prepare("INSERT INTO stem_qualifiers (last_name,first_name,middle_name,lrn,general_average,pathway_cluster,school_year,added_by) VALUES (?,?,?,?,?,?,?,?)");
                    if ($s) { $s->bind_param("ssssdssi",$ln,$fn,$mn,$lrn,$ga,$cl,$school_year,$uid); $s->execute(); $s->close(); $count++; }
                }
                fclose($handle);
                $toast_msg = "$count STEM qualifier(s) imported.";
                log_activity($list_conn, 'list_csv_imported', 'Imported ' . $count . ' STEM qualifier(s) from CSV');
                if ($duplicate_count > 0 || $invalid_lrn_count > 0 || $student_duplicate_count > 0) {
                    $toast_msg .= " Skipped $duplicate_count duplicate(s), $student_duplicate_count existing student LRN row(s), and $invalid_lrn_count invalid LRN row(s).";
                }
                if ($invalid_lrn_count > 0 && $count === 0) {
                    $toast_type = 'error';
                }
            }
        }

        // ---- G11 COMPLETERS ----
        elseif ($action === 'add_g11') {
            $ln = trim($_POST['last_name']??''); $fn = trim($_POST['first_name']??'');
            $mn = trim($_POST['middle_name']??''); $lrn = normalize_lrn($_POST['lrn']??'');
            $sex_val = trim($_POST['sex']??'');
            $sex_val = in_array($sex_val, ['Male','Female'], true) ? $sex_val : null;
            $sec = trim($_POST['section']??'');
            $strand = normalize_g12_strand($_POST['strand']??'', $g12_strands);
            $completer_status = normalize_g11_completer_status($_POST['completer_status']??'', $g11_completer_statuses);
            if ($ln && $fn) {
                if ($lrn !== '' && !is_valid_lrn($lrn)) {
                    $toast_msg = 'LRN must be exactly 12 digits.';
                    $toast_type = 'error';
                } elseif ($lrn !== '' && student_exists_by_lrn($list_conn, $lrn)) {
                    $toast_msg = 'LRN already exists in the student table. Grade 11 completer was not added.';
                    $toast_type = 'error';
                } elseif ($lrn !== '' && record_exists_by_lrn($list_conn, 'g11_completers', $lrn, $school_year)) {
                    $toast_msg = 'Duplicate LRN found. Grade 11 completer was not added again.';
                    $toast_type = 'error';
                } else {
                    $s = $list_conn->prepare("INSERT INTO g11_completers (last_name,first_name,middle_name,sex,lrn,section,strand,completer_status,school_year,added_by) VALUES (?,?,?,?,?,?,?,?,?,?)");
                    if ($s) { $s->bind_param("sssssssssi",$ln,$fn,$mn,$sex_val,$lrn,$sec,$strand,$completer_status,$school_year,$uid); $s->execute(); $s->close(); }
                    $toast_msg = 'Grade 11 completer added.';
                    log_activity($list_conn, 'list_entry_added', 'Added G11 completer: ' . $ln . ', ' . $fn . ' (LRN: ' . $lrn . ')');
                }
            }
        } elseif ($action === 'delete_g11') {
            $id = (int)($_POST['row_id']??0);
            $s = $list_conn->prepare("DELETE FROM g11_completers WHERE id=?");
            if ($s) { $s->bind_param("i",$id); $s->execute(); $s->close(); }
            $toast_msg = 'Record deleted.'; $toast_type = 'error';
            log_activity($list_conn, 'list_entry_deleted', 'Deleted G11 completer ID#' . $id);
        } elseif ($action === 'csv_g11') {
            if (isset($_FILES['csv_file']) && $_FILES['csv_file']['error']===0) {
                $handle = fopen($_FILES['csv_file']['tmp_name'],'r');
                fgetcsv($handle);
                $count = 0; $duplicate_count = 0; $invalid_lrn_count = 0; $student_duplicate_count = 0;
                while (($row = fgetcsv($handle)) !== false) {
                    if (count($row) < 2) continue;
                    [$ln,$fn,$mn,$sex_csv,$lrn,$sec,$strand,$completer_status] = array_pad($row,8,'');
                    $lrn = normalize_lrn($lrn);
                    if ($lrn === '' || !is_valid_lrn($lrn)) { $invalid_lrn_count++; continue; }
                    if (student_exists_by_lrn($list_conn, $lrn)) { $student_duplicate_count++; continue; }
                    if (record_exists_by_lrn($list_conn, 'g11_completers', $lrn, $school_year)) { $duplicate_count++; continue; }
                    $ln = trim($ln);
                    $fn = trim($fn);
                    $mn = trim($mn);
                    $sex_csv = trim($sex_csv);
                    $sex_csv = in_array($sex_csv, ['Male','Female'], true) ? $sex_csv : null;
                    $sec = trim($sec);
                    $strand = normalize_g12_strand($strand, $g12_strands);
                    $completer_status = normalize_g11_completer_status($completer_status, $g11_completer_statuses);
                    $s = $list_conn->prepare("INSERT INTO g11_completers (last_name,first_name,middle_name,sex,lrn,section,strand,completer_status,school_year,added_by) VALUES (?,?,?,?,?,?,?,?,?,?)");
                    if ($s) { $s->bind_param("sssssssssi",$ln,$fn,$mn,$sex_csv,$lrn,$sec,$strand,$completer_status,$school_year,$uid); $s->execute(); $s->close(); $count++; }
                }
                fclose($handle);
                $toast_msg = "$count completer(s) imported.";
                log_activity($list_conn, 'list_csv_imported', 'Imported ' . $count . ' G11 completer(s) from CSV');
                if ($duplicate_count > 0 || $invalid_lrn_count > 0 || $student_duplicate_count > 0) {
                    $toast_msg .= " Skipped $duplicate_count duplicate(s), $student_duplicate_count existing student LRN row(s), and $invalid_lrn_count invalid LRN row(s).";
                }
                if ($invalid_lrn_count > 0 && $count === 0) {
                    $toast_type = 'error';
                }
            }
        }

        // ---- WATCHLIST ----
        elseif ($action === 'add_watch') {
            $ln = trim($_POST['last_name']??''); $fn = trim($_POST['first_name']??'');
            $mn = trim($_POST['middle_name']??''); $lrn = normalize_lrn($_POST['lrn']??'');
            $it = trim($_POST['issue_type']??''); $id2 = trim($_POST['issue_details']??'');
            if ($ln && $fn) {
                if ($lrn !== '' && !is_valid_lrn($lrn)) {
                    $toast_msg = 'LRN must be exactly 12 digits.';
                    $toast_type = 'error';
                } elseif ($lrn !== '' && student_exists_by_lrn($list_conn, $lrn)) {
                    $toast_msg = 'LRN already exists in the student table. Focus List entry was not added.';
                    $toast_type = 'error';
                } elseif ($lrn !== '' && record_exists_by_lrn($list_conn, 'watchlist', $lrn, $school_year)) {
                    $toast_msg = 'Duplicate LRN found. Focus List entry was not added again.';
                    $toast_type = 'error';
                } else {
                    $s = $list_conn->prepare("INSERT INTO watchlist (last_name,first_name,middle_name,lrn,issue_type,issue_details,school_year,added_by) VALUES (?,?,?,?,?,?,?,?)");
                    if ($s) { $s->bind_param("sssssssi",$ln,$fn,$mn,$lrn,$it,$id2,$school_year,$uid); $s->execute(); $s->close(); }
                    $toast_msg = 'Focus List entry added.';
                    log_activity($list_conn, 'list_entry_added', 'Added Focus List entry: ' . $ln . ', ' . $fn . ' (LRN: ' . $lrn . ')');
                }
            }
        } elseif ($action === 'delete_watch') {
            $id = (int)($_POST['row_id']??0);
            $s = $list_conn->prepare("DELETE FROM watchlist WHERE id=?");
            if ($s) { $s->bind_param("i",$id); $s->execute(); $s->close(); }
            $toast_msg = 'Record deleted.'; $toast_type = 'error';
            log_activity($list_conn, 'list_entry_deleted', 'Deleted Focus List entry ID#' . $id);
        } elseif ($action === 'csv_watch') {
            if (isset($_FILES['csv_file']) && $_FILES['csv_file']['error']===0) {
                $handle = fopen($_FILES['csv_file']['tmp_name'],'r');
                fgetcsv($handle);
                $count = 0; $duplicate_count = 0; $invalid_lrn_count = 0; $student_duplicate_count = 0;
                while (($row = fgetcsv($handle)) !== false) {
                    if (count($row) < 2) continue;
                    [$ln,$fn,$mn,$lrn,$it,$id2] = array_pad($row,6,'');
                    $lrn = normalize_lrn($lrn);
                    if ($lrn === '' || !is_valid_lrn($lrn)) { $invalid_lrn_count++; continue; }
                    if (student_exists_by_lrn($list_conn, $lrn)) { $student_duplicate_count++; continue; }
                    if (record_exists_by_lrn($list_conn, 'watchlist', $lrn, $school_year)) { $duplicate_count++; continue; }
                    $ln = trim($ln);
                    $fn = trim($fn);
                    $mn = trim($mn);
                    $it = trim($it);
                    $id2 = trim($id2);
                    $s = $list_conn->prepare("INSERT INTO watchlist (last_name,first_name,middle_name,lrn,issue_type,issue_details,school_year,added_by) VALUES (?,?,?,?,?,?,?,?)");
                    if ($s) { $s->bind_param("sssssssi",$ln,$fn,$mn,$lrn,$it,$id2,$school_year,$uid); $s->execute(); $s->close(); $count++; }
                }
                fclose($handle);
                $toast_msg = "$count Focus List entr" . ($count === 1 ? 'y' : 'ies') . " imported.";
                log_activity($list_conn, 'list_csv_imported', 'Imported ' . $count . ' Focus List entries from CSV');
                if ($duplicate_count > 0 || $invalid_lrn_count > 0 || $student_duplicate_count > 0) {
                    $toast_msg .= " Skipped $duplicate_count duplicate(s), $student_duplicate_count existing student LRN row(s), and $invalid_lrn_count invalid LRN row(s).";
                }
                if ($invalid_lrn_count > 0 && $count === 0) {
                    $toast_type = 'error';
                }
            }
        }
    }

    $r = $list_conn->query("SELECT name FROM add_sections WHERE grade_level = '11' ORDER BY name ASC");
    if ($r) { while ($row=$r->fetch_assoc()) $g11_sections[] = $row['name']; $r->close(); }

    // Fetch all
    $stem_stmt = $list_conn->prepare("SELECT * FROM stem_qualifiers WHERE school_year = ? ORDER BY pathway_cluster, last_name, first_name");
    if ($stem_stmt) {
        $stem_stmt->bind_param("s", $school_year);
        $stem_stmt->execute();
        $r = $stem_stmt->get_result();
        if ($r) { while ($row = $r->fetch_assoc()) $stem_rows[] = $row; $r->close(); }
        $stem_stmt->close();
    }

    $g11_stmt = $list_conn->prepare("SELECT * FROM g11_completers WHERE school_year = ? ORDER BY last_name, first_name");
    if ($g11_stmt) {
        $g11_stmt->bind_param("s", $school_year);
        $g11_stmt->execute();
        $r = $g11_stmt->get_result();
        if ($r) { while ($row = $r->fetch_assoc()) $g11_rows[] = $row; $r->close(); }
        $g11_stmt->close();
    }

    $watch_stmt = $list_conn->prepare("SELECT * FROM watchlist WHERE school_year = ? ORDER BY last_name, first_name");
    if ($watch_stmt) {
        $watch_stmt->bind_param("s", $school_year);
        $watch_stmt->execute();
        $r = $watch_stmt->get_result();
        if ($r) { while ($row = $r->fetch_assoc()) $watch_rows[] = $row; $r->close(); }
        $watch_stmt->close();
    }
    $list_conn->close();
}

function list_name($row) {
    $n = ($row['last_name']??'').', '.($row['first_name']??'');
    if (!empty($row['middle_name'])) $n .= ' '.strtoupper(substr($row['middle_name'],0,1)).'.';
    return $n;
}

$g11_card_sections = $g11_sections;
foreach ($g11_rows as $row) {
    $section_name = trim((string)($row['section'] ?? ''));
    if ($section_name !== '' && !in_array($section_name, $g11_card_sections, true)) {
        $g11_card_sections[] = $section_name;
    }
}
foreach ($g11_rows as $row) {
    if (trim((string)($row['section'] ?? '')) === '' && !in_array('Unassigned', $g11_card_sections, true)) {
        $g11_card_sections[] = 'Unassigned';
    }
}

$g11_section_counts = [];
foreach ($g11_card_sections as $section_name) {
    $g11_section_counts[$section_name] = 0;
}
foreach ($g11_rows as $row) {
    $section_name = trim((string)($row['section'] ?? ''));
    if ($section_name === '') $section_name = 'Unassigned';
    if (!isset($g11_section_counts[$section_name])) $g11_section_counts[$section_name] = 0;
    $g11_section_counts[$section_name]++;
}
?>

<?php if ($toast_msg): ?>
<div class="mb-6 rounded-xl border px-5 py-4 text-sm font-semibold <?php echo $toast_type === 'error' ? 'bg-red-50 border-red-200 text-red-700' : 'bg-emerald-50 border-emerald-200 text-emerald-700'; ?>">
    <?php echo htmlspecialchars($toast_msg); ?>
</div>
<?php endif; ?>

<div class="bg-white rounded-2xl shadow-sm border border-slate-200 overflow-hidden">
    <div class="px-6 py-5 border-b border-slate-100">
        <h2 class="text-2xl font-heading font-black text-dranhs-dark">Academic Lists</h2>
        <p class="text-sm text-slate-500 mt-1">Manage STEM qualifiers, Grade 11 completers, and Focus List entries for <?php echo htmlspecialchars($school_year); ?>.</p>
    </div>

    <div class="border-b border-slate-100 bg-slate-50 px-4">
        <div class="flex flex-wrap gap-2 py-3">
            <button type="button" class="list-tab-btn px-4 py-2 rounded-lg text-sm font-bold bg-dranhs-green text-white" data-tab="stem">STEM Qualifiers</button>
            <button type="button" class="list-tab-btn px-4 py-2 rounded-lg text-sm font-bold text-slate-600 hover:bg-slate-200" data-tab="g11">Grade 11 Completers</button>
            <button type="button" class="list-tab-btn px-4 py-2 rounded-lg text-sm font-bold text-slate-600 hover:bg-slate-200" data-tab="watch">Focus List</button>
        </div>
    </div>

    <div class="p-6">
        <section id="list-tab-stem" class="list-tab-panel space-y-6">
            <div class="grid grid-cols-1 xl:grid-cols-3 gap-6">
                <div class="xl:col-span-1">
                    <div class="rounded-2xl border border-slate-200 bg-slate-50 p-5">
                        <div class="rounded-2xl border <?php echo $stem_qualifier_enabled ? 'border-emerald-200 bg-emerald-50' : 'border-amber-200 bg-amber-50'; ?> p-4 mb-5">
                            <div class="flex items-start justify-between gap-4">
                                <div>
                                    <p class="text-xs font-black uppercase tracking-widest <?php echo $stem_qualifier_enabled ? 'text-emerald-700' : 'text-amber-700'; ?>">STEM Restriction</p>
                                    <h3 class="text-base font-heading font-black text-dranhs-dark mt-1"><?php echo $stem_qualifier_enabled ? 'Restriction Enabled' : 'Restriction Disabled'; ?></h3>
                                    <p class="text-xs text-slate-600 mt-2">
                                        <?php echo $stem_qualifier_enabled
                                            ? 'Only students listed here can choose the restricted STEM clusters.'
                                            : 'All students may choose any STEM cluster. Qualifier records stay saved, but they are not enforced.'; ?>
                                    </p>
                                </div>
                                <form method="POST" class="shrink-0">
                                    <input type="hidden" name="list_action" value="toggle_stem_qualifier">
                                    <label class="inline-flex items-center cursor-pointer select-none">
                                        <input type="checkbox" name="stem_qualifier_enabled" value="1" class="sr-only peer" <?php echo $stem_qualifier_enabled ? 'checked' : ''; ?> onchange="this.form.submit()">
                                        <span class="relative w-14 h-8 rounded-full transition-colors <?php echo $stem_qualifier_enabled ? 'bg-emerald-500' : 'bg-slate-300'; ?> peer-focus:ring-4 peer-focus:ring-emerald-200">
                                            <span class="absolute top-1 left-1 h-6 w-6 rounded-full bg-white shadow transition-transform <?php echo $stem_qualifier_enabled ? 'translate-x-6' : 'translate-x-0'; ?>"></span>
                                        </span>
                                    </label>
                                </form>
                            </div>
                        </div>
                        <h3 class="text-lg font-heading font-black text-dranhs-dark">Add STEM Qualifier</h3>
                        <form method="POST" class="mt-4 space-y-3">
                            <input type="hidden" name="list_action" value="add_stem">
                            <input name="last_name" class="w-full rounded-xl border border-slate-300 px-4 py-3 text-sm" placeholder="Last Name" required>
                            <input name="first_name" class="w-full rounded-xl border border-slate-300 px-4 py-3 text-sm" placeholder="First Name" required>
                            <input name="middle_name" class="w-full rounded-xl border border-slate-300 px-4 py-3 text-sm" placeholder="Middle Name">
                            <input name="lrn" class="w-full rounded-xl border border-slate-300 px-4 py-3 text-sm" placeholder="LRN">
                            <input name="general_average" type="number" step="0.01" class="w-full rounded-xl border border-slate-300 px-4 py-3 text-sm" placeholder="General Average">
                            <select name="pathway_cluster" class="w-full rounded-xl border border-slate-300 px-4 py-3 text-sm">
                                <option value="">Select STEM Cluster...</option>
                                <?php foreach ($stem_clusters as $code => $label): ?>
                                    <option value="<?php echo htmlspecialchars($code); ?>"><?php echo htmlspecialchars($label); ?></option>
                                <?php endforeach; ?>
                            </select>
                            <button type="submit" class="w-full rounded-xl bg-dranhs-green text-white py-3 text-sm font-bold hover:bg-emerald-700 transition-colors">Save Qualifier</button>
                        </form>
                        <div class="mt-6 pt-5 border-t border-slate-200">
                            <div class="flex items-center justify-between gap-3">
                                <h4 class="text-sm font-black uppercase tracking-wider text-slate-600">Import CSV</h4>
                                <a
                                    href="data:text/csv;charset=utf-8,<?php echo rawurlencode($csv_templates['stem']); ?>"
                                    download="stem_qualifiers_template.csv"
                                    class="inline-flex items-center rounded-lg border border-slate-300 px-3 py-2 text-xs font-bold text-slate-600 hover:bg-white transition-colors"
                                >
                                    Download Template
                                </a>
                            </div>
                            <p class="text-xs text-slate-500 mt-1">Columns: `last_name, first_name, middle_name, lrn, general_average, pathway_cluster`</p>
                            <p class="text-xs text-slate-500 mt-1">Use `1 = Medical &amp; Allied Health`, `2 = Engineering &amp; Aviation`, `3 = Earth, Space &amp; Weather Science`. The upload also accepts the original code or full cluster name.</p>
                            <form method="POST" enctype="multipart/form-data" class="mt-3 space-y-3">
                                <input type="hidden" name="list_action" value="csv_stem">
                                <input type="file" name="csv_file" accept=".csv" class="w-full rounded-xl border border-slate-300 px-4 py-3 text-sm bg-white" required>
                                <button type="submit" class="w-full rounded-xl border border-dranhs-green text-dranhs-green py-3 text-sm font-bold hover:bg-emerald-50 transition-colors">Upload STEM CSV</button>
                            </form>
                        </div>
                    </div>
                </div>
                <div class="xl:col-span-2">
                    <div class="rounded-2xl border border-slate-200 overflow-hidden">
                        <div class="px-5 py-4 border-b border-slate-100 bg-white flex flex-col sm:flex-row justify-between items-start sm:items-center gap-3">
                            <h3 class="text-lg font-heading font-black text-dranhs-dark">STEM Qualifier List</h3>
                            <div class="relative w-full sm:w-64">
                                <input type="text" id="stem-search" placeholder="Search qualifiers..." class="w-full bg-slate-50 border border-slate-200 px-3 py-1.5 pl-9 rounded-lg text-sm focus:border-dranhs-green outline-none transition-colors">
                                <svg class="w-4 h-4 text-slate-400 absolute left-3 top-1/2 -translate-y-1/2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path></svg>
                            </div>
                        </div>
                        <div class="overflow-x-auto">
                            <table class="min-w-full text-sm">
                                <thead class="bg-slate-50 text-slate-500 uppercase text-xs font-bold">
                                    <tr>
                                        <th class="px-4 py-3 text-left">#</th>
                                        <th class="px-4 py-3 text-left">Name</th>
                                        <th class="px-4 py-3 text-left">LRN</th>
                                        <th class="px-4 py-3 text-left">Average</th>
                                        <th class="px-4 py-3 text-left">Cluster</th>
                                        <th class="px-4 py-3 text-right">Action</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-slate-100 bg-white">
                                    <?php if (empty($stem_rows)): ?>
                                        <tr><td colspan="6" class="px-4 py-6 text-center text-slate-500">No STEM qualifiers yet.</td></tr>
                                    <?php else: ?>
                                        <?php foreach ($stem_rows as $index => $row): ?>
                                            <tr class="stem-row">
                                                <td class="px-4 py-3 font-semibold text-slate-500 stem-search-text"><?php echo $index + 1; ?></td>
                                                <td class="px-4 py-3 font-semibold text-slate-700"><?php echo htmlspecialchars(list_name($row)); ?></td>
                                                <td class="px-4 py-3 text-slate-600"><?php echo htmlspecialchars($row['lrn'] ?: '--'); ?></td>
                                                <td class="px-4 py-3 text-slate-600"><?php echo $row['general_average'] !== null ? htmlspecialchars(number_format((float)$row['general_average'], 2)) : '--'; ?></td>
                                                <td class="px-4 py-3 text-slate-600"><?php echo htmlspecialchars($stem_clusters[$row['pathway_cluster']] ?? ($row['pathway_cluster'] ?: '--')); ?></td>
                                                <td class="px-4 py-3 text-right">
                                                    <form method="POST" class="inline">
                                                        <input type="hidden" name="list_action" value="delete_stem">
                                                        <input type="hidden" name="row_id" value="<?php echo (int)$row['id']; ?>">
                                                        <button type="submit" class="text-red-600 hover:text-red-700 font-bold text-xs uppercase">Delete</button>
                                                    </form>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <section id="list-tab-g11" class="list-tab-panel hidden space-y-6">
            <div class="grid grid-cols-1 xl:grid-cols-3 gap-6">
                <div class="xl:col-span-1">
                    <div class="rounded-2xl border border-slate-200 bg-slate-50 p-5">
                        <h3 class="text-lg font-heading font-black text-dranhs-dark">Add Grade 11 Completer</h3>
                        <form method="POST" class="mt-4 space-y-3">
                            <input type="hidden" name="list_action" value="add_g11">
                            <input name="last_name" class="w-full rounded-xl border border-slate-300 px-4 py-3 text-sm" placeholder="Last Name" required>
                            <input name="first_name" class="w-full rounded-xl border border-slate-300 px-4 py-3 text-sm" placeholder="First Name" required>
                            <input name="middle_name" class="w-full rounded-xl border border-slate-300 px-4 py-3 text-sm" placeholder="Middle Name">
                            <select name="sex" class="w-full rounded-xl border border-slate-300 px-4 py-3 text-sm">
                                <option value="">Select Sex...</option>
                                <option value="Male">Male</option>
                                <option value="Female">Female</option>
                            </select>
                            <input name="lrn" class="w-full rounded-xl border border-slate-300 px-4 py-3 text-sm" placeholder="LRN">
                            <select name="section" class="w-full rounded-xl border border-slate-300 px-4 py-3 text-sm">
                                <option value="">Select Grade 11 Section...</option>
                                <?php foreach ($g11_sections as $section_name): ?>
                                    <option value="<?php echo htmlspecialchars($section_name); ?>"><?php echo htmlspecialchars($section_name); ?></option>
                                <?php endforeach; ?>
                            </select>
                            <select name="strand" class="w-full rounded-xl border border-slate-300 px-4 py-3 text-sm">
                                <option value="">Select Strand...</option>
                                <?php foreach ($g12_strands as $strand_code => $strand_label): ?>
                                    <option value="<?php echo htmlspecialchars($strand_code); ?>"><?php echo htmlspecialchars($strand_label); ?></option>
                                <?php endforeach; ?>
                            </select>
                            <select name="completer_status" class="w-full rounded-xl border border-slate-300 px-4 py-3 text-sm">
                                <option value="regular">Grade 11 Completer - Regular</option>
                                <option value="irregular">Grade 11 Completer - Irregular</option>
                            </select>
                            <button type="submit" class="w-full rounded-xl bg-dranhs-green text-white py-3 text-sm font-bold hover:bg-emerald-700 transition-colors">Add Completer</button>
                        </form>
                        <div class="mt-6 pt-5 border-t border-slate-200">
                            <div class="flex items-center justify-between gap-3">
                                <h4 class="text-sm font-black uppercase tracking-wider text-slate-600">Import CSV</h4>
                                <a
                                    href="data:text/csv;charset=utf-8,<?php echo rawurlencode($csv_templates['g11']); ?>"
                                    download="g11_completers_template.csv"
                                    class="inline-flex items-center rounded-lg border border-slate-300 px-3 py-2 text-xs font-bold text-slate-600 hover:bg-white transition-colors"
                                >
                                    Download Template
                                </a>
                            </div>
                            <p class="text-xs text-slate-500 mt-1">Columns: <code>last_name, first_name, middle_name, sex, lrn, section, strand, completer_status</code> &mdash; sex must be <code>Male</code> or <code>Female</code>.</p>
                            <p class="text-xs text-slate-500 mt-1">LRN must be exactly 12 digits. If the CSV value ends in `.0`, the upload will ignore that decimal.</p>
                            <form method="POST" enctype="multipart/form-data" class="mt-3 space-y-3">
                                <input type="hidden" name="list_action" value="csv_g11">
                                <input type="file" name="csv_file" accept=".csv" class="w-full rounded-xl border border-slate-300 px-4 py-3 text-sm bg-white" required>
                                <button type="submit" class="w-full rounded-xl border border-dranhs-green text-dranhs-green py-3 text-sm font-bold hover:bg-emerald-50 transition-colors">Upload G11 CSV</button>
                            </form>
                        </div>
                    </div>
                </div>
                <div class="xl:col-span-2">
                    <div class="rounded-2xl border border-slate-200 bg-white p-5">
                        <div class="flex items-center justify-between gap-3 mb-5">
                            <div>
                                <p class="text-xs font-black uppercase tracking-widest text-blue-600">Completer Sections</p>
                                <h3 class="text-lg font-heading font-black text-dranhs-dark mt-1">Grade 11 Section Cards</h3>
                            </div>
                            <span class="inline-flex items-center rounded-full bg-blue-50 px-3 py-1 text-xs font-bold text-blue-700">
                                <?php echo count($g11_rows); ?> Students
                            </span>
                        </div>
                        <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-4">
                            <?php foreach ($g11_card_sections as $section_name): ?>
                                <?php $count = (int)($g11_section_counts[$section_name] ?? 0); ?>
                                <?php $active = $count > 0; ?>
                                <button
                                    type="button"
                                    class="g11-section-card group rounded-3xl border-2 border-dashed p-6 text-center transition-all <?php echo $active ? 'border-emerald-200 bg-emerald-50/60 hover:bg-emerald-50 hover:border-emerald-300' : 'border-slate-200 bg-slate-50 hover:bg-white hover:border-slate-300'; ?>"
                                    data-section="<?php echo htmlspecialchars($section_name, ENT_QUOTES); ?>"
                                >
                                    <div class="mx-auto w-12 h-12 rounded-2xl flex items-center justify-center <?php echo $active ? 'text-emerald-600' : 'text-slate-300'; ?>">
                                        <svg class="w-9 h-9" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M4 21h16M7 18V6a1 1 0 011-1h8a1 1 0 011 1v12M9 9h1m4 0h1M9 13h1m4 0h1M9 17h6"></path></svg>
                                    </div>
                                    <p class="mt-4 text-2xl font-heading font-black <?php echo $active ? 'text-emerald-800' : 'text-slate-500'; ?>"><?php echo htmlspecialchars($section_name); ?></p>
                                    <span class="mt-3 inline-flex items-center rounded-full px-4 py-1.5 text-sm font-bold <?php echo $active ? 'bg-white text-emerald-700' : 'bg-slate-200 text-slate-600'; ?>">
                                        <?php echo $count; ?> Student<?php echo $count === 1 ? '' : 's'; ?>
                                    </span>
                                </button>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <div id="g11-completer-modal" class="fixed inset-0 z-50 hidden">
            <div id="g11-completer-backdrop" class="absolute inset-0 bg-slate-900/60"></div>
            <div class="relative z-10 min-h-screen flex items-center justify-center p-4">
                <div class="w-full max-w-5xl bg-white rounded-2xl shadow-2xl border border-slate-200 overflow-hidden">
                    <div class="px-6 py-5 bg-dranhs-dark flex items-center justify-between">
                        <div>
                            <p class="text-xs font-black uppercase tracking-widest text-dranhs-green">Grade 11 Completers</p>
                            <h3 id="g11-completer-modal-title" class="text-xl font-heading font-black text-white mt-1">Completer Table</h3>
                        </div>
                        <div class="relative flex-1 max-w-sm mr-4">
                            <input type="text" id="g11-search" placeholder="Search..." class="w-full bg-white/10 border border-white/20 text-white placeholder:text-white/50 px-3 py-1.5 pl-9 rounded-lg text-sm focus:border-white focus:bg-white/20 outline-none transition-colors">
                            <svg class="w-4 h-4 text-white/50 absolute left-3 top-1/2 -translate-y-1/2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path></svg>
                        </div>
                        <button type="button" id="close-g11-completer-modal" class="w-10 h-10 rounded-full bg-white/10 text-white hover:bg-white/20 text-xl flex items-center justify-center">&times;</button>
                    </div>
                    <div id="g11-completer-modal-body" class="max-h-[75vh] overflow-auto">
                        <div class="px-4 py-6 text-center text-slate-500">Select a section card to view its completer table.</div>
                    </div>
                </div>
            </div>
        </div>

        <section id="list-tab-watch" class="list-tab-panel hidden space-y-6">
            <div class="grid grid-cols-1 xl:grid-cols-3 gap-6">
                <div class="xl:col-span-1">
                    <div class="rounded-2xl border border-slate-200 bg-slate-50 p-5">
                        <h3 class="text-lg font-heading font-black text-dranhs-dark">Add Focus List Entry</h3>
                        <form method="POST" class="mt-4 space-y-3">
                            <input type="hidden" name="list_action" value="add_watch">
                            <input name="last_name" class="w-full rounded-xl border border-slate-300 px-4 py-3 text-sm" placeholder="Last Name" required>
                            <input name="first_name" class="w-full rounded-xl border border-slate-300 px-4 py-3 text-sm" placeholder="First Name" required>
                            <input name="middle_name" class="w-full rounded-xl border border-slate-300 px-4 py-3 text-sm" placeholder="Middle Name">
                            <input name="lrn" class="w-full rounded-xl border border-slate-300 px-4 py-3 text-sm" placeholder="LRN">
                            <input name="issue_type" class="w-full rounded-xl border border-slate-300 px-4 py-3 text-sm" placeholder="Flag Reason / Issue Type">
                            <textarea name="issue_details" class="w-full rounded-xl border border-slate-300 px-4 py-3 text-sm min-h-[120px]" placeholder="Flag Description / Issue Details"></textarea>
                            <button type="submit" class="w-full rounded-xl bg-dranhs-green text-white py-3 text-sm font-bold hover:bg-emerald-700 transition-colors">Save Focus List Entry</button>
                        </form>
                        <div class="mt-6 pt-5 border-t border-slate-200">
                            <div class="flex items-center justify-between gap-3">
                                <h4 class="text-sm font-black uppercase tracking-wider text-slate-600">Import CSV</h4>
                                <a
                                    href="data:text/csv;charset=utf-8,<?php echo rawurlencode($csv_templates['watch']); ?>"
                                    download="focus_list_template.csv"
                                    class="inline-flex items-center rounded-lg border border-slate-300 px-3 py-2 text-xs font-bold text-slate-600 hover:bg-white transition-colors"
                                >
                                    Download Template
                                </a>
                            </div>
                            <p class="text-xs text-slate-500 mt-1">Columns: `last_name, first_name, middle_name, lrn, issue_type, issue_details`</p>
                            <p class="text-xs text-slate-500 mt-1">LRN must be exactly 12 digits. If the CSV value ends in `.0`, the upload will ignore that decimal.</p>
                            <form method="POST" enctype="multipart/form-data" class="mt-3 space-y-3">
                                <input type="hidden" name="list_action" value="csv_watch">
                                <input type="file" name="csv_file" accept=".csv" class="w-full rounded-xl border border-slate-300 px-4 py-3 text-sm bg-white" required>
                                <button type="submit" class="w-full rounded-xl border border-dranhs-green text-dranhs-green py-3 text-sm font-bold hover:bg-emerald-50 transition-colors">Upload Focus List CSV</button>
                            </form>
                        </div>
                    </div>
                </div>
                <div class="xl:col-span-2">
                    <div class="rounded-2xl border border-slate-200 overflow-hidden">
                        <div class="px-5 py-4 border-b border-slate-100 bg-white flex flex-col sm:flex-row justify-between items-start sm:items-center gap-3">
                            <h3 class="text-lg font-heading font-black text-dranhs-dark">Focus List</h3>
                            <div class="relative w-full sm:w-64">
                                <input type="text" id="watch-search" placeholder="Search focus list..." class="w-full bg-slate-50 border border-slate-200 px-3 py-1.5 pl-9 rounded-lg text-sm focus:border-dranhs-green outline-none transition-colors">
                                <svg class="w-4 h-4 text-slate-400 absolute left-3 top-1/2 -translate-y-1/2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path></svg>
                            </div>
                        </div>
                        <div class="overflow-x-auto">
                            <table class="min-w-full text-sm">
                                <thead class="bg-slate-50 text-slate-500 uppercase text-xs font-bold">
                                    <tr>
                                        <th class="px-4 py-3 text-left">#</th>
                                        <th class="px-4 py-3 text-left">Name</th>
                                        <th class="px-4 py-3 text-left">LRN</th>
                                        <th class="px-4 py-3 text-left">Issue Type</th>
                                        <th class="px-4 py-3 text-left">Details</th>
                                        <th class="px-4 py-3 text-right">Action</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-slate-100 bg-white">
                                    <?php if (empty($watch_rows)): ?>
                                        <tr><td colspan="6" class="px-4 py-6 text-center text-slate-500">No Focus List entries yet.</td></tr>
                                    <?php else: ?>
                                        <?php foreach ($watch_rows as $index => $row): ?>
                                            <tr class="stem-row">
                                                <td class="px-4 py-3 font-semibold text-slate-500 stem-search-text"><?php echo $index + 1; ?></td>
                                                <td class="px-4 py-3 font-semibold text-slate-700"><?php echo htmlspecialchars(list_name($row)); ?></td>
                                                <td class="px-4 py-3 text-slate-600"><?php echo htmlspecialchars($row['lrn'] ?: '--'); ?></td>
                                                <td class="px-4 py-3 text-slate-600"><?php echo htmlspecialchars($row['issue_type'] ?: '--'); ?></td>
                                                <td class="px-4 py-3 text-slate-600"><?php echo htmlspecialchars($row['issue_details'] ?: '--'); ?></td>
                                                <td class="px-4 py-3 text-right">
                                                    <form method="POST" class="inline">
                                                        <input type="hidden" name="list_action" value="delete_watch">
                                                        <input type="hidden" name="row_id" value="<?php echo (int)$row['id']; ?>">
                                                        <button type="submit" class="text-red-600 hover:text-red-700 font-bold text-xs uppercase">Delete</button>
                                                    </form>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </section>
    </div>
</div>

<script>
const LIST_G11_ROWS = <?php echo json_encode($g11_rows, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP); ?>;

document.querySelectorAll('.list-tab-btn').forEach(btn => {
    btn.addEventListener('click', function () {
        document.querySelectorAll('.list-tab-btn').forEach(item => {
            item.classList.remove('bg-dranhs-green', 'text-white');
            item.classList.add('text-slate-600', 'hover:bg-slate-200');
        });
        document.querySelectorAll('.list-tab-panel').forEach(panel => panel.classList.add('hidden'));
        this.classList.add('bg-dranhs-green', 'text-white');
        this.classList.remove('text-slate-600', 'hover:bg-slate-200');
        document.getElementById(`list-tab-${this.dataset.tab}`).classList.remove('hidden');
    });
});

const g11CompleterModal = document.getElementById('g11-completer-modal');
const closeG11CompleterModal = document.getElementById('close-g11-completer-modal');
const g11CompleterBackdrop = document.getElementById('g11-completer-backdrop');
const g11CompleterModalTitle = document.getElementById('g11-completer-modal-title');
const g11CompleterModalBody = document.getElementById('g11-completer-modal-body');

function showG11CompleterModal() {
    if (!g11CompleterModal) return;
    g11CompleterModal.classList.remove('hidden');
    document.body.classList.add('overflow-hidden');
}

function hideG11CompleterModal() {
    if (!g11CompleterModal) return;
    g11CompleterModal.classList.add('hidden');
    document.body.classList.remove('overflow-hidden');
}

function renderG11CompleterTable(sectionName) {
    const rows = LIST_G11_ROWS.filter(row => {
        const rowSection = String(row.section || '').trim();
        if (String(sectionName || '') === 'Unassigned') {
            return rowSection === '';
        }
        return rowSection === String(sectionName || '');
    });
    g11CompleterModalTitle.textContent = `${sectionName} Completer Table`;

    if (rows.length === 0) {
        g11CompleterModalBody.innerHTML = '<div class="px-4 py-6 text-center text-slate-500">No Grade 11 completers in this section yet.</div>';
        return;
    }

    g11CompleterModalBody.innerHTML = `
        <table class="min-w-full text-sm">
            <thead class="bg-slate-50 text-slate-500 uppercase text-xs font-bold sticky top-0">
                <tr>
                    <th class="px-4 py-3 text-left">#</th>
                    <th class="px-4 py-3 text-left">Name</th>
                    <th class="px-4 py-3 text-left">LRN</th>
                    <th class="px-4 py-3 text-left">Sex</th>
                    <th class="px-4 py-3 text-left">Section</th>
                    <th class="px-4 py-3 text-left">Strand</th>
                    <th class="px-4 py-3 text-left">Status</th>
                    <th class="px-4 py-3 text-right">Action</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100 bg-white">
                ${rows.map((row, index) => `
                    <tr>
                        <td class="px-4 py-3 font-semibold text-slate-500">${index + 1}</td>
                        <td class="px-4 py-3 font-semibold text-slate-700">${row.last_name}, ${row.first_name}${row.middle_name ? ' ' + String(row.middle_name).charAt(0).toUpperCase() + '.' : ''}</td>
                        <td class="px-4 py-3 text-slate-600">${row.lrn || '--'}</td>
                        <td class="px-4 py-3 text-slate-600">${row.sex || '--'}</td>
                        <td class="px-4 py-3 text-slate-600">${row.section || '--'}</td>
                        <td class="px-4 py-3 text-slate-600">${row.strand || '--'}</td>
                        <td class="px-4 py-3 text-slate-600">${row.completer_status ? String(row.completer_status).replace(/(^.)/, c => c.toUpperCase()) : '--'}</td>
                        <td class="px-4 py-3 text-right">
                            <form method="POST" class="inline">
                                <input type="hidden" name="list_action" value="delete_g11">
                                <input type="hidden" name="row_id" value="${row.id}">
                                <button type="submit" class="text-red-600 hover:text-red-700 font-bold text-xs uppercase">Delete</button>
                            </form>
                        </td>
                    </tr>
                `).join('')}
            </tbody>
        </table>
    `;
}

document.querySelectorAll('.g11-section-card').forEach(card => {
    card.addEventListener('click', function () {
        renderG11CompleterTable(this.dataset.section || '');
        showG11CompleterModal();
    });
});

if (closeG11CompleterModal) closeG11CompleterModal.addEventListener('click', hideG11CompleterModal);
if (g11CompleterBackdrop) g11CompleterBackdrop.addEventListener('click', hideG11CompleterModal);

const stemSearch = document.getElementById('stem-search');
if (stemSearch) {
    stemSearch.addEventListener('input', function () {
        const query = this.value.toLowerCase().trim();
        document.querySelectorAll('#list-tab-stem tbody tr.stem-row').forEach(row => {
            row.style.display = row.textContent.toLowerCase().includes(query) ? '' : 'none';
        });
    });
}

const watchSearch = document.getElementById('watch-search');
if (watchSearch) {
    watchSearch.addEventListener('input', function () {
        const query = this.value.toLowerCase().trim();
        document.querySelectorAll('#list-tab-watch tbody tr.stem-row').forEach(row => {
            row.style.display = row.textContent.toLowerCase().includes(query) ? '' : 'none';
        });
    });
}

const g11Search = document.getElementById('g11-search');
if (g11Search) {
    g11Search.addEventListener('input', function () {
        const query = this.value.toLowerCase().trim();
        document.querySelectorAll('#g11-completer-modal-body tbody tr').forEach(row => {
            row.style.display = row.textContent.toLowerCase().includes(query) ? '' : 'none';
        });
    });
}
</script>
