<?php
if (basename($_SERVER['PHP_SELF']) == basename(__FILE__)) {
    header('Location: admin.php?page=system_settings');
    exit;
}

// Ensure upload directory exists
$upload_dir = '../uploads/advisers/';
if (!is_dir($upload_dir)) {
    mkdir($upload_dir, 0755, true);
}

// Database Connection
require_once __DIR__ . '/../db.php';
$conn = db_connect();

// ── PRG: read flash message from session ──────────────────────────────────────
$toast_message = '';
$toast_type    = '';
if (!empty($_SESSION['_ss_toast'])) {
    $toast_message = $_SESSION['_ss_toast']['msg'];
    $toast_type    = $_SESSION['_ss_toast']['type'];
    unset($_SESSION['_ss_toast']);
}

// Helper: set flash and redirect (PRG pattern — prevents duplicate on refresh)
function ss_redirect($msg, $type = 'success', $tab = '') {
    $_SESSION['_ss_toast'] = ['msg' => $msg, 'type' => $type];
    $url = 'admin.php?page=system_settings' . ($tab ? '#tab-' . $tab : '');
    header('Location: ' . $url);
    exit;
}

function ss_tab_from_action($action) {
    switch ((string)$action) {
        case 'save_main_settings':
            return 'main-settings';
        case 'save_curriculum':
        case 'delete_curriculum':
            return 'curriculum';
        case 'add_registry':
        case 'delete_registry':
            return 'registries';
        case 'assign_room':
        case 'add_annex':
        case 'delete_annex':
        case 'add_facility':
        case 'assign_facility_room':
        case 'delete_facility':
            return 'room-assignment';
        case 'upload_theme_asset':
        case 'remove_theme_asset':
            return 'theme';
        case 'retrieve_student':
        case 'delete_student':
            return 'withdrawn';
        case 'upload_template':
            return 'print-templates';
        default:
            return 'main-settings';
    }
}

// advisers are now managed in the users table (via account.php)

$theme_dir = __DIR__ . '/../uploads/theme/';
if (!is_dir($theme_dir)) mkdir($theme_dir, 0755, true);

// Ensure annex/facilities tables exist
$conn->query("CREATE TABLE IF NOT EXISTS room_annex (    id INT AUTO_INCREMENT PRIMARY KEY,
    section_name VARCHAR(100) NOT NULL,
    building_number VARCHAR(50) NOT NULL,
    floor_number VARCHAR(20) NOT NULL,
    room_number VARCHAR(50) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)");
$conn->query("CREATE TABLE IF NOT EXISTS room_facilities (
    id INT AUTO_INCREMENT PRIMARY KEY,
    facility_name VARCHAR(150) NOT NULL,
    building_number VARCHAR(50) NOT NULL,
    floor_number VARCHAR(20) NOT NULL,
    room_number VARCHAR(50) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)");
$conn->query("CREATE TABLE IF NOT EXISTS pathway_strand (
    id INT AUTO_INCREMENT PRIMARY KEY,
    grade_level VARCHAR(20) NOT NULL,
    category VARCHAR(20) NOT NULL,
    track VARCHAR(50) NOT NULL,
    pathway_strand VARCHAR(150) NOT NULL,
    code VARCHAR(100) NOT NULL,
    description TEXT DEFAULT NULL,
    electives TEXT DEFAULT NULL,
    enabled TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY unique_pathway_code (code),
    UNIQUE KEY unique_grade_code (grade_level, code)
)");

// Handle POST Requests
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        if ($_POST['action'] === 'save_main_settings') {
            $year = $_POST['academic_year'];
            $sem = $_POST['active_semester'];
            $date = $_POST['phase_start_date'];
            $status = isset($_POST['enrollment_status']) ? 'unlocked' : 'locked';

            $stmt = $conn->prepare("UPDATE system_settings SET setting_value = ? WHERE setting_key = ?");
            
            $stmt->bind_param("ss", $year, $key);
            $key = 'academic_year'; $stmt->execute();
            
            $stmt->bind_param("ss", $sem, $key);
            $key = 'active_semester'; $stmt->execute();
            
            $stmt->bind_param("ss", $date, $key);
            $key = 'phase_start_date'; $stmt->execute();
            
            $stmt->bind_param("ss", $status, $key);
            $key = 'enrollment_status'; $stmt->execute();

            $stmt->close();
            ss_redirect('Main settings updated successfully!', 'success', 'main-settings');
        } 
        elseif ($_POST['action'] === 'assign_room') {
            $section_id = intval($_POST['section_id']);
            $room = $_POST['room'];

            // Get the grade level of the section being assigned
            $gl_stmt = $conn->prepare("SELECT grade_level FROM add_sections WHERE id = ?");
            $gl_stmt->bind_param("i", $section_id);
            $gl_stmt->execute();
            $gl_row = $gl_stmt->get_result()->fetch_assoc();
            $gl_stmt->close();
            $grade_level = $gl_row['grade_level'] ?? '';

            // Clear prior assignment of THIS grade level in this room (allow 1 per grade)
            $clear = $conn->prepare("UPDATE add_sections SET room = NULL WHERE room = ? AND grade_level = ? AND id != ?");
            $clear->bind_param("ssi", $room, $grade_level, $section_id);
            $clear->execute();
            $clear->close();

            // Assign room to section
            $stmt = $conn->prepare("UPDATE add_sections SET room = ? WHERE id = ?");
            $stmt->bind_param("si", $room, $section_id);
            $stmt->execute();
            $stmt->close();

            ss_redirect('Room successfully assigned!', 'success', 'room-assignment');
        }
        elseif ($_POST['action'] === 'save_curriculum') {
            $id           = intval($_POST['id'] ?? 0);
            $grade_level  = trim($_POST['grade_level'] ?? '');
            $category     = trim($_POST['category'] ?? '');
            $track        = trim($_POST['track'] ?? '');
            $pathway_name = trim($_POST['pathway_strand'] ?? '');
            $code         = trim($_POST['code'] ?? '');
            $description  = trim($_POST['description'] ?? '');
            $electives    = array_values(array_filter(array_map('trim', $_POST['electives'] ?? []), fn($value) => $value !== ''));
            $enabled      = isset($_POST['enabled']) ? 1 : 0;

            if ($grade_level === '' || $pathway_name === '' || $code === '' || $track === '' || $category === '') {
                $toast_message = 'Please fill in Grade Level, Category, Track, Pathway or Strand name, and Code.';
                $toast_type = 'error';
            } else {
                $electives_json = !empty($electives) ? json_encode(array_values($electives)) : null;
                try {
                    if ($id > 0) {
                        $update = $conn->prepare("UPDATE pathway_strand SET grade_level = ?, category = ?, track = ?, pathway_strand = ?, code = ?, description = ?, electives = ?, enabled = ? WHERE id = ?");
                        if (!$update) {
                            throw new RuntimeException('Unable to prepare curriculum update.');
                        }
                        $update->bind_param("sssssssii", $grade_level, $category, $track, $pathway_name, $code, $description, $electives_json, $enabled, $id);
                        $update->execute();
                        $update->close();
                        $toast_message = 'Curriculum entry updated successfully.';
                    } else {
                        $insert = $conn->prepare("INSERT INTO pathway_strand (grade_level, category, track, pathway_strand, code, description, electives, enabled) VALUES (?,?,?,?,?,?,?,?)");
                        if (!$insert) {
                            throw new RuntimeException('Unable to prepare curriculum insert.');
                        }
                        $insert->bind_param("sssssssi", $grade_level, $category, $track, $pathway_name, $code, $description, $electives_json, $enabled);
                        $insert->execute();
                        $insert->close();
                        $toast_message = 'Curriculum entry added successfully.';
                    }
                    $toast_type = 'success';
                } catch (mysqli_sql_exception $e) {
                    if ((int)$e->getCode() === 1062) {
                        $toast_message = 'That curriculum code is already in use. Please choose a unique code.';
                    } else {
                        $toast_message = 'Curriculum entry could not be saved: ' . $e->getMessage();
                    }
                    $toast_type = 'error';
                } catch (RuntimeException $e) {
                    $toast_message = $e->getMessage();
                    $toast_type = 'error';
                }
            }
            ss_redirect($toast_message, $toast_type, 'curriculum');
        }
        elseif ($_POST['action'] === 'delete_curriculum') {
            $id = intval($_POST['id'] ?? 0);
            if ($id > 0) {
                $stmt = $conn->prepare("DELETE FROM pathway_strand WHERE id = ?");
                if ($stmt) {
                    $stmt->bind_param("i", $id);
                    $stmt->execute();
                    $stmt->close();
                }
                ss_redirect('Curriculum entry deleted.', 'success', 'curriculum');
            } else {
                ss_redirect('Invalid curriculum entry.', 'error', 'curriculum');
            }
        }
        elseif ($_POST['action'] === 'add_registry') {
            $cat = $_POST['category'];
            $name = trim(strtoupper($_POST['name']));
            
            if (!empty($name)) {
                if ($cat === 'faculty_advisers') {
                    // Faculty advisers now managed on Account page
                    $toast_message = 'Advisers are managed from the Accounts page.';
                    $toast_type = 'info';
                } else {
                    // Handle sections (g10_sections, g11_sections or g12_sections)
                    $grade_level = ($cat === 'g10_sections') ? '10' : (($cat === 'g11_sections') ? '11' : '12');
                    
                    // Check for duplicate section name in the same grade level
                    $check_stmt = $conn->prepare("SELECT id FROM add_sections WHERE grade_level = ? AND name = ?");
                    $check_stmt->bind_param("ss", $grade_level, $name);
                    $check_stmt->execute();
                    $check_result = $check_stmt->get_result();
                    
                    if ($check_result->num_rows > 0) {
                        $toast_message = 'Section name "' . htmlspecialchars($name) . '" already exists for Grade ' . $grade_level . '!';
                        $toast_type = 'error';
                    } else {
                        $stmt = $conn->prepare("INSERT INTO add_sections (grade_level, name) VALUES (?, ?)");
                        $stmt->bind_param("ss", $grade_level, $name);
                        $stmt->execute();
                        $stmt->close();
                        $toast_message = 'Section added!';
                        $toast_type = 'success';
                    }
                    $check_stmt->close();
                }
            }
        }
        elseif ($_POST['action'] === 'upload_template') {
            $allowed  = ['docx'];
            $tpl_dir  = '../uploads/templates/';
            $tpl_type = $_POST['template_type'] ?? 'beef';
            if (!is_dir($tpl_dir)) mkdir($tpl_dir, 0755, true);

            $dest_name = ($tpl_type === 'id') ? 'ID-Temp.docx' : 'BEEF-Temp.docx';

            if (isset($_FILES['template_file']) && $_FILES['template_file']['error'] === UPLOAD_ERR_OK) {
                $fname = $_FILES['template_file']['name'];
                $ext   = strtolower(pathinfo($fname, PATHINFO_EXTENSION));
                if (in_array($ext, $allowed)) {
                    $dest = $tpl_dir . $dest_name;
                    if (file_exists($dest)) {
                        rename($dest, $tpl_dir . pathinfo($dest_name, PATHINFO_FILENAME) . '_backup_' . date('Ymd_His') . '.docx');
                    }
                    if (move_uploaded_file($_FILES['template_file']['tmp_name'], $dest)) {
                        $toast_message = $dest_name . ' uploaded successfully! Old template backed up.';
                        $toast_type    = 'success';
                    } else {
                        $toast_message = 'Upload failed. Check folder permissions.';
                        $toast_type    = 'error';
                    }
                } else {
                    $toast_message = 'Only .docx files are allowed.';
                    $toast_type    = 'error';
                }
            } else {
                $toast_message = 'No file received.';
                $toast_type    = 'error';
            }
        }
        elseif ($_POST['action'] === 'add_annex') {
            // Accept either dropdown selection or manual text input
            $sec  = trim($_POST['annex_section'] ?? '');
            if (empty($sec)) $sec = trim($_POST['annex_section_manual'] ?? '');
            $bldg = trim($_POST['annex_building']  ?? '');
            $flr  = trim($_POST['annex_floor']     ?? '');
            $rm   = trim($_POST['annex_room']      ?? '');
            if ($sec && $bldg && $flr && $rm) {
                $stmt = $conn->prepare("INSERT INTO room_annex (section_name, building_number, floor_number, room_number) VALUES (?,?,?,?)");
                if ($stmt) { $stmt->bind_param("ssss", $sec, $bldg, $flr, $rm); $stmt->execute(); $stmt->close(); }
                $toast_message = 'Annex entry added.';
                $toast_type = 'success';
            } else {
                $toast_message = 'Please fill in all fields.';
                $toast_type = 'error';
            }
        }
        elseif ($_POST['action'] === 'delete_annex') {
            $id = intval($_POST['annex_id'] ?? 0);
            $stmt = $conn->prepare("DELETE FROM room_annex WHERE id = ?");
            if ($stmt) { $stmt->bind_param("i", $id); $stmt->execute(); $stmt->close(); }
            $toast_message = 'Annex entry removed.';
            $toast_type = 'success';
        }
        elseif ($_POST['action'] === 'add_facility') {
            $name = trim($_POST['facility_name']     ?? '');
            $bldg = trim($_POST['facility_building'] ?? '');
            $flr  = trim($_POST['facility_floor']    ?? '');
            $rm   = trim($_POST['facility_room']     ?? '');
            if ($name) {
                $stmt = $conn->prepare("INSERT INTO room_facilities (facility_name, building_number, floor_number, room_number) VALUES (?,?,?,?)");
                if ($stmt) { $stmt->bind_param("ssss", $name, $bldg, $flr, $rm); $stmt->execute(); $stmt->close(); }
                $toast_message = 'Facility added.';
                $toast_type = 'success';
            } else {
                $toast_message = 'Please enter a facility name.';
                $toast_type = 'error';
            }
        }
        elseif ($_POST['action'] === 'assign_facility_room') {
            $fid  = intval($_POST['facility_id'] ?? 0);
            $room = trim($_POST['room'] ?? '');
            if ($fid > 0 && $room !== '') {
                // Derive building/floor from room number
                $bldg = ''; $flr = '';
                $rn = intval($room);
                if ($rn >= 41 && $rn <= 56) { $bldg = '14'; $flr = (string)ceil(($rn - 40) / 4); }
                elseif ($rn >= 21 && $rn <= 40) { $bldg = '15'; $flr = (string)ceil(($rn - 20) / 5); }
                $stmt = $conn->prepare("UPDATE room_facilities SET building_number=?, floor_number=?, room_number=? WHERE id=?");
                if ($stmt) { $stmt->bind_param("sssi", $bldg, $flr, $room, $fid); $stmt->execute(); $stmt->close(); }
                $toast_message = 'Facility room assigned.';
                $toast_type = 'success';
            }
        }
        elseif ($_POST['action'] === 'delete_facility') {
            $id = intval($_POST['facility_id'] ?? 0);
            $stmt = $conn->prepare("DELETE FROM room_facilities WHERE id = ?");
            if ($stmt) { $stmt->bind_param("i", $id); $stmt->execute(); $stmt->close(); }
            $toast_message = 'Facility removed.';
            $toast_type = 'success';
        }
        elseif ($_POST['action'] === 'upload_theme_asset') {
            $asset_key = trim($_POST['asset_key'] ?? '');
            $allowed_keys = ['school_logo', 'background', 'deped_logo', 'division_logo'];
            $upload_dir = __DIR__ . '/../uploads/theme/';

            if (!in_array($asset_key, $allowed_keys)) {
                $toast_message = 'Invalid asset type.';
                $toast_type = 'error';
            } elseif (!isset($_FILES['theme_file']) || $_FILES['theme_file']['error'] !== UPLOAD_ERR_OK) {
                $toast_message = 'No file received or upload error.';
                $toast_type = 'error';
            } else {
                $file = $_FILES['theme_file'];
                $mime = mime_content_type($file['tmp_name']);
                $allowed_mimes = ['image/png', 'image/jpeg', 'image/jpg', 'image/webp', 'image/svg+xml'];
                if (!in_array($mime, $allowed_mimes)) {
                    $toast_message = 'Only PNG, JPG, WebP, or SVG files are allowed.';
                    $toast_type = 'error';
                } elseif ($file['size'] > 2 * 1024 * 1024) {
                    $toast_message = 'File must be under 2MB.';
                    $toast_type = 'error';
                } else {
                    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
                    $dest = $upload_dir . $asset_key . '.' . $ext;
                    // Remove old files for this key
                    foreach (glob($upload_dir . $asset_key . '.*') as $old) @unlink($old);
                    if (move_uploaded_file($file['tmp_name'], $dest)) {
                        // Save path to system_settings
                        $rel = 'uploads/theme/' . $asset_key . '.' . $ext;
                        $chk = $conn->prepare("SELECT setting_key FROM system_settings WHERE setting_key = ?");
                        $chk->bind_param("s", $asset_key); $chk->execute();
                        $exists = $chk->get_result()->num_rows > 0; $chk->close();
                        if ($exists) {
                            $upd = $conn->prepare("UPDATE system_settings SET setting_value = ? WHERE setting_key = ?");
                            $upd->bind_param("ss", $rel, $asset_key); $upd->execute(); $upd->close();
                        } else {
                            $ins = $conn->prepare("INSERT INTO system_settings (setting_key, setting_value) VALUES (?, ?)");
                            $ins->bind_param("ss", $asset_key, $rel); $ins->execute(); $ins->close();
                        }
                        $toast_message = ucwords(str_replace('_', ' ', $asset_key)) . ' uploaded successfully.';
                        $toast_type = 'success';
                    } else {
                        $toast_message = 'Upload failed. Check folder permissions.';
                        $toast_type = 'error';
                    }
                }
            }
        }
        elseif ($_POST['action'] === 'remove_theme_asset') {
            $asset_key = trim($_POST['asset_key'] ?? '');
            $allowed_keys = ['school_logo', 'background', 'deped_logo', 'division_logo'];
            if (in_array($asset_key, $allowed_keys)) {
                $upload_dir = __DIR__ . '/../uploads/theme/';
                foreach (glob($upload_dir . $asset_key . '.*') as $old) @unlink($old);
                $del = $conn->prepare("DELETE FROM system_settings WHERE setting_key = ?");
                $del->bind_param("s", $asset_key); $del->execute(); $del->close();
                $toast_message = ucwords(str_replace('_', ' ', $asset_key)) . ' removed.';
                $toast_type = 'success';
            }
        }
        elseif ($_POST['action'] === 'retrieve_student') {
            if ($sid > 0) {
                $stmt = $conn->prepare("UPDATE students SET enrollment_status = 'for_evaluation' WHERE id = ? AND enrollment_status = 'withdrawn'");
                $stmt->bind_param("i", $sid);
                $stmt->execute();
                $stmt->close();
                $toast_message = 'Student retrieved and moved back to evaluation.';
                $toast_type = 'success';
            }
        }
        elseif ($_POST['action'] === 'delete_student') {
            $sid = intval($_POST['student_id'] ?? 0);
            $pwd = $_POST['confirm_password'] ?? '';
            $uid = $_SESSION['user_id'] ?? 0;
            $ok = false;
            if ($uid) {
                $s = $conn->prepare("SELECT password FROM users WHERE id=?");
                if ($s) { $s->bind_param("i",$uid); $s->execute(); $r=$s->get_result()->fetch_assoc(); $s->close(); $ok = $r && password_verify($pwd,$r['password']); }
            }
            if ($ok && $sid > 0) {
                $stmt = $conn->prepare("DELETE FROM students WHERE id = ? AND enrollment_status = 'withdrawn'");
                $stmt->bind_param("i", $sid);
                $stmt->execute();
                $stmt->close();
                $toast_message = 'Student record permanently deleted.';
                $toast_type = 'success';
            } else {
                $toast_message = 'Incorrect password. Student was not deleted.';
                $toast_type = 'error';
            }
        }
        elseif ($_POST['action'] === 'delete_registry') {
            $id = intval($_POST['id']);
            $cat = $_POST['category'] ?? '';
            
            if ($cat === 'faculty_advisers') {
                // Advisers managed on Account page now
                $toast_message = 'Advisers are managed from the Accounts page.';
                $toast_type = 'info';
            } else {
                // Delete section from add_sections
                $stmt = $conn->prepare("DELETE FROM add_sections WHERE id = ?");
                $stmt->bind_param("i", $id);
                $stmt->execute();
                $stmt->close();
                $toast_message = 'Section deleted.';
                $toast_type = 'success';
            }
        }
    }

    ss_redirect(
        $toast_message !== '' ? $toast_message : 'System settings saved successfully.',
        $toast_type !== '' ? $toast_type : 'success',
        ss_tab_from_action($_POST['action'] ?? '')
    );
}

// Fetch Settings
$settings = [];
$res = $conn->query("SELECT setting_key, setting_value FROM system_settings");
if ($res) {
    while($row = $res->fetch_assoc()) {
        $settings[$row['setting_key']] = $row['setting_value'];
    }
}

// Fetch sections without a room assignment (available for annex entry)
$annex_available_sections = [];
$avs = $conn->query("SELECT id, name, grade_level FROM add_sections ORDER BY grade_level ASC, name ASC");
if ($avs) { while ($r = $avs->fetch_assoc()) $annex_available_sections[] = $r; $avs->close(); }
$annex_entries = [];
$ar = $conn->query("SELECT * FROM room_annex ORDER BY building_number, floor_number, room_number");
if ($ar) { while ($r = $ar->fetch_assoc()) $annex_entries[] = $r; $ar->close(); }

$facilities = [];
$fr = $conn->query("SELECT * FROM room_facilities ORDER BY building_number, floor_number, room_number");
if ($fr) { while ($r = $fr->fetch_assoc()) $facilities[] = $r; $fr->close(); }

// Fetch withdrawn students
$withdrawn_students = [];
$wr = $conn->query("SELECT id, lrn, last_name, first_name, middle_name, extension_name, grade_level, track, pathway_strand, created_at FROM students WHERE enrollment_status = 'withdrawn' ORDER BY last_name, first_name");
if ($wr) { while ($r = $wr->fetch_assoc()) $withdrawn_students[] = $r; $wr->close(); }

$pathway_catalog = [];
$pc = $conn->query("SELECT * FROM pathway_strand ORDER BY FIELD(grade_level,'Grade 11','Grade 12'), category, track, pathway_strand");
if ($pc) { while ($row = $pc->fetch_assoc()) $pathway_catalog[] = $row; $pc->close(); }

// Fetch Advisers and Sections
$registries = [
    'faculty_advisers' => [],
    'g10_sections' => [],
    'g11_sections' => [],
    'g12_sections' => []
];

// Advisers now come from users table
$adv_res = $conn->query("SELECT id, full_name AS name, avatar, created_at FROM users WHERE status='active' ORDER BY full_name ASC");
if ($adv_res) {
    while($r = $adv_res->fetch_assoc()) {
        $r['category'] = 'faculty_advisers';
        $registries['faculty_advisers'][] = $r;
    }
}

// Fetch sections from add_sections table
$sec_res = $conn->query("SELECT id, grade_level, name, room, created_at FROM add_sections ORDER BY name ASC");
if ($sec_res) {
    while($r = $sec_res->fetch_assoc()) {
        $cat = ($r['grade_level'] === '10') ? 'g10_sections' : (($r['grade_level'] === '11') ? 'g11_sections' : 'g12_sections');
        $r['category'] = $cat;
        $r['avatar'] = NULL; // sections don't have avatars
        $registries[$cat][] = $r;
    }
}

function generateYearOptions($currentValue) {
    $currentYear = intval(date('Y'));
    $html = '';
    for($i=$currentYear; $i <= $currentYear + 10; $i++) {
        $val = $i . ' - ' . ($i + 1);
        $sel = ($val === $currentValue) ? 'selected' : '';
        $html .= "<option value='$val' $sel>$val</option>";
    }
    return $html;
}

// Helper to check if a room is assigned and return details
function getRoomAssignment($roomNumber, $registries) {
    // Check both arrays for the room
    foreach(['g10_sections', 'g11_sections', 'g12_sections'] as $cat) {
        foreach($registries[$cat] as $sec) {
            if ($sec['room'] == $roomNumber) return $sec;
        }
    }
    return null;
}
?>

<div class="bg-white rounded-2xl shadow-sm border border-slate-200 overflow-hidden min-h-[600px] flex flex-col relative w-full">
    
    <!-- Toast Notification -->
    <?php if ($toast_message): ?>
    <div id="toast" class="absolute top-4 right-4 z-50 px-4 py-3 rounded-lg shadow-lg flex items-center gap-3 <?php echo $toast_type === 'error' ? 'bg-rose-100 border border-rose-300 text-rose-800' : 'bg-emerald-100 border border-emerald-400 text-emerald-800'; ?>">
        <?php if ($toast_type === 'error'): ?>
        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z"></path></svg>
        <?php else: ?>
        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>
        <?php endif; ?>
        <span class="font-bold text-sm"><?php echo htmlspecialchars($toast_message); ?></span>
    </div>
    <script>
        setTimeout(() => document.getElementById('toast').style.display='none', 3000);
    </script>
    <?php endif; ?>

    <!-- Tabs Header -->
    <div class="flex overflow-x-auto border-b border-slate-100 bg-slate-50/50 sidebar-scroll shrink-0">
        <button onclick="switchTab('main-settings')" id="tab-btn-main-settings" class="tab-btn px-6 py-4 font-bold text-sm text-dranhs-green border-b-2 border-dranhs-green bg-white shrink-0">Main Settings</button>
        <button onclick="switchTab('registries')" id="tab-btn-registries" class="tab-btn px-6 py-4 font-bold text-sm text-slate-500 hover:text-slate-800 hover:bg-slate-50 transition-colors shrink-0">Section</button>
        <button onclick="switchTab('room-assignment')" id="tab-btn-room-assignment" class="tab-btn px-6 py-4 font-bold text-sm text-slate-500 hover:text-slate-800 hover:bg-slate-50 transition-colors shrink-0 flex items-center gap-2">Room Assignment</button>
        <button onclick="switchTab('withdrawn')" id="tab-btn-withdrawn" class="tab-btn px-6 py-4 font-bold text-sm text-slate-500 hover:text-red-600 hover:bg-slate-50 transition-colors shrink-0 flex items-center gap-2">
            Withdrawn Students
        </button>
        <button onclick="switchTab('theme')" id="tab-btn-theme" class="tab-btn px-6 py-4 font-bold text-sm text-slate-500 hover:text-slate-800 hover:bg-slate-50 transition-colors shrink-0 flex items-center gap-2">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 21a4 4 0 01-4-4V5a2 2 0 012-2h4a2 2 0 012 2v12a4 4 0 01-4 4zm0 0h12a2 2 0 002-2v-4a2 2 0 00-2-2h-2.343M11 7.343l1.657-1.657a2 2 0 012.828 0l2.829 2.829a2 2 0 010 2.828l-8.486 8.485M7 17h.01"/></svg>
            Theme
        </button>
        <button onclick="switchTab('curriculum')" id="tab-btn-curriculum" class="tab-btn px-6 py-4 font-bold text-sm text-slate-500 hover:text-slate-800 hover:bg-slate-50 transition-colors shrink-0 flex items-center gap-2">Curriculum</button>
        <button onclick="switchTab('datahub')" id="tab-btn-datahub" class="tab-btn px-6 py-4 font-bold text-sm text-slate-500 hover:text-slate-800 hover:bg-slate-50 transition-colors shrink-0 flex items-center gap-2">Data Hub</button>
        <button onclick="switchTab('print-templates')" id="tab-btn-print-templates" class="tab-btn px-6 py-4 font-bold text-sm text-slate-500 hover:text-slate-800 hover:bg-slate-50 transition-colors shrink-0 flex items-center gap-2">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
            Print Templates
        </button>
    </div>

    <div class="flex-1 p-6 lg:p-8">
        
        <!-- MAIN SETTINGS TAB -->
        <div id="tab-main-settings" class="tab-content block">
            <!-- (Retained your exact design for Main Settings) -->
            <h2 class="text-2xl font-heading font-black text-dranhs-dark mb-6">Core Configuration</h2>
            
            <form action="?page=system_settings" method="POST" class="max-w-2xl space-y-8">
                <input type="hidden" name="action" value="save_main_settings">

                <div class="grid grid-cols-1 md:grid-cols-2 gap-6 p-6 bg-slate-50 border border-slate-200 rounded-xl">
                    <div class="flex flex-col gap-2">
                        <label class="text-sm font-bold text-slate-700 uppercase tracking-widest">Academic School Year</label>
                        <select name="academic_year" class="w-full bg-white border border-slate-300 px-4 py-3 rounded-lg text-slate-800 font-bold focus:border-dranhs-green focus:ring-2 focus:ring-dranhs-green/20 outline-none">
                            <?php echo generateYearOptions($settings['academic_year'] ?? ''); ?>
                        </select>
                    </div>
                    
                    <div class="flex flex-col gap-2">
                        <label class="text-sm font-bold text-slate-700 uppercase tracking-widest">Active Term</label>
                        <select name="active_semester" class="w-full bg-white border border-slate-300 px-4 py-3 rounded-lg text-slate-800 font-bold focus:border-dranhs-green focus:ring-2 focus:ring-dranhs-green/20 outline-none">
                            <option value="term_1" <?php echo (($settings['active_semester'] ?? '') === 'term_1' || ($settings['active_semester'] ?? '') === '1st') ? 'selected' : ''; ?>>Term 1</option>
                            <option value="term_2" <?php echo (($settings['active_semester'] ?? '') === 'term_2' || ($settings['active_semester'] ?? '') === '2nd') ? 'selected' : ''; ?>>Term 2</option>
                            <option value="term_3" <?php echo (($settings['active_semester'] ?? '') === 'term_3') ? 'selected' : ''; ?>>Term 3</option>
                        </select>
                    </div>
                </div>

                <div class="p-6 bg-blue-50/50 border border-blue-100 rounded-xl">
                    <div class="flex flex-col gap-2 mb-2">
                        <label class="text-sm font-bold text-slate-700 uppercase tracking-widest flex items-center gap-2">
                            <svg class="w-5 h-5 text-blue-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path></svg>
                            Phase Start Date (Age Cutoff)
                        </label>
                        <p class="text-xs text-slate-500 mb-2 font-medium">This date dictates the age calculation logic on enrollment forms. DepEd bases this on the First Friday of the School Year.</p>
                        <input type="date" name="phase_start_date" value="<?php echo htmlspecialchars($settings['phase_start_date'] ?? ''); ?>" class="w-full max-w-sm bg-white border border-slate-300 px-4 py-2.5 rounded-lg text-slate-800 font-bold focus:border-blue-500 focus:ring-2 focus:ring-blue-500/20 outline-none">
                    </div>
                </div>

                <div class="p-6 bg-rose-50/50 border border-rose-100 rounded-xl flex items-center justify-between">
                    <div>
                        <h3 class="text-sm font-black text-rose-800 uppercase tracking-widest mb-1">Enrollment Portal Access</h3>
                        <p class="text-xs text-rose-600 font-medium">Turn this off to lock the system. Enrollment buttons on the landing page will be disabled.</p>
                    </div>
                    <label class="relative inline-flex items-center cursor-pointer">
                        <input type="checkbox" name="enrollment_status" value="unlocked" class="sr-only peer" <?php echo (($settings['enrollment_status']??'')=='unlocked')?'checked':''; ?>>
                        <div class="w-14 h-7 bg-rose-300 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-slate-300 after:border after:rounded-full after:h-6 after:w-6 after:transition-all peer-checked:bg-emerald-500"></div>
                        <span class="ml-3 text-sm font-bold text-slate-700 hidden sm:block peer-checked:text-emerald-600 uppercase tracking-wider">
                            <?php echo (($settings['enrollment_status']??'')=='unlocked')?'Unlocked':'Locked'; ?>
                        </span>
                    </label>
                </div>

                <button type="submit" class="bg-dranhs-dark hover:bg-slate-800 text-white font-bold py-3 px-8 rounded-lg shadow-md transition-transform hover:-translate-y-0.5 tracking-wider uppercase text-sm">Save Configuration</button>
            </form>
        </div>

        <!-- SECTIONS TAB -->
        <div id="tab-registries" class="tab-content hidden">
            <h2 class="text-2xl font-heading font-black text-dranhs-dark mb-2">Core Database Sections</h2>
            <p class="text-sm text-slate-500 mb-8">Manage the selectable dictionaries available on different forms across the application.</p>

            <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 items-start">
                
                <?php 
                $columns = [
                    'g10_sections' => 'Grade 10 Sections',
                    'g11_sections' => 'Grade 11 Sections',
                    'g12_sections' => 'Grade 12 Sections'
                ];
                
                foreach ($columns as $cat_key => $cat_title): 
                ?>
                <!-- Column: <?php echo $cat_title; ?> -->
                <div class="bg-white border text-center border-slate-200 rounded-2xl p-5 shadow-sm max-h-[500px] flex flex-col">
                    <h3 class="font-black text-slate-800 uppercase tracking-widest text-left text-xs mb-4"><?php echo $cat_title; ?></h3>
                    
                    <form action="?page=system_settings" method="POST" enctype="multipart/form-data" class="mb-4 flex flex-col gap-2 w-full">
                        <input type="hidden" name="action" value="add_registry">
                        <input type="hidden" name="category" value="<?php echo $cat_key; ?>">
                        
                        <div class="flex gap-2">
                            <!-- Photo upload for advisers only -->
                            <?php if ($cat_key === 'faculty_advisers'): ?>
                            <label class="cursor-pointer bg-slate-100 hover:bg-slate-200 text-slate-500 p-2.5 rounded-lg border border-dashed border-slate-300 transition-colors" title="Upload Display Photo">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 9a2 2 0 012-2h.93a2 2 0 001.664-.89l.812-1.22A2 2 0 0110.07 4h3.86a2 2 0 011.664.89l.812 1.22A2 2 0 0018.07 7H19a2 2 0 012 2v9a2 2 0 01-2 2H5a2 2 0 01-2-2V9z"></path><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 13a3 3 0 11-6 0 3 3 0 016 0z"></path></svg>
                                <input type="file" name="avatar" class="hidden" accept="image/png, image/jpeg, image/webp">
                            </label>
                            <?php endif; ?>

                            <input type="text" name="name" placeholder="Name or Section..." required class="flex-1 min-w-0 bg-slate-50 border border-slate-200 px-3 py-2 rounded-lg text-sm font-semibold text-slate-700 focus:border-dranhs-green outline-none">
                            <button type="submit" class="bg-dranhs-green text-white px-3 py-2 rounded-lg hover:bg-emerald-600 transition-colors flex items-center justify-center shrink-0" title="Add Item">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="3" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"></path></svg>
                            </button>
                        </div>
                    </form>

                    <div class="flex-1 overflow-y-auto sidebar-scroll pr-1 space-y-2">
                        <?php if (empty($registries[$cat_key])): ?>
                            <p class="text-xs text-slate-400 font-bold p-4">No data added yet.</p>
                        <?php else: ?>
                            <?php foreach($registries[$cat_key] as $item): ?>
                            <div class="flex items-center justify-between bg-white border border-slate-100 p-3 rounded-lg group hover:border-slate-300 transition-colors shadow-sm gap-3">
                                
                                <div class="flex items-center gap-3 min-w-0">
                                    <?php if ($cat_key === 'faculty_advisers'): ?>
                                        <?php if (!empty($item['avatar'])): ?>
                                            <img src="../<?php echo htmlspecialchars($item['avatar']); ?>" class="w-8 h-8 rounded-full object-cover shrink-0">
                                        <?php else: ?>
                                            <img src="https://ui-avatars.com/api/?name=<?php echo urlencode($item['name']); ?>&background=009b5a&color=fff&size=64&bold=true" class="w-8 h-8 rounded-full shrink-0">
                                        <?php endif; ?>
                                    <?php endif; ?>
                                    
                                    <span class="text-xs font-bold text-slate-700 tracking-wide truncate"><?php echo htmlspecialchars($item['name']); ?></span>
                                </div>
                                
                                <form action="?page=system_settings" method="POST" class="shrink-0 confirm-action-form" data-confirm-message="Are you sure you want to delete this item?">
                                    <input type="hidden" name="action" value="delete_registry">
                                    <input type="hidden" name="id" value="<?php echo $item['id']; ?>">
                                    <input type="hidden" name="category" value="<?php echo htmlspecialchars($cat_key); ?>">
                                    <button type="submit" class="text-slate-300 hover:text-red-500 transition-colors p-1" title="Delete">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path></svg>
                                    </button>
                                </form>
                            </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endforeach; ?>

            </div>
        </div>

        <!-- ROOM ASSIGNMENT TAB -->
        <div id="tab-room-assignment" class="tab-content hidden">
            <div class="flex items-center justify-between mb-8">
                <div class="flex flex-col gap-1">
                    <div class="flex items-center gap-2">
                        <svg class="w-6 h-6 text-dranhs-green" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"></path></svg>
                        <h2 class="text-2xl font-heading font-black text-dranhs-dark uppercase tracking-tight">Infrastructure Node Mapping</h2>
                    </div>
                    <p class="text-sm font-bold text-slate-400 tracking-widest uppercase flex items-center gap-1">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"></path><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"></path></svg>
                        Link Sections to Physical Locations within Bldg 14 and 15
                    </p>
                </div>
                <!-- Manual Annex Entry + Faculty/Lab buttons -->
                <div class="flex gap-2">
                    <button type="button" id="open-annex-modal" class="bg-indigo-600 hover:bg-indigo-700 text-white font-bold text-xs px-5 py-3 rounded-xl uppercase tracking-widest shadow-lg shadow-indigo-600/30 transition-transform hover:-translate-y-0.5 flex items-center gap-2">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16m-7 6h7"></path></svg>
                        Manual Annex Entry
                    </button>
                    <button type="button" id="open-facility-modal" class="bg-amber-500 hover:bg-amber-600 text-white font-bold text-xs px-5 py-3 rounded-xl uppercase tracking-widest shadow-lg shadow-amber-500/30 transition-transform hover:-translate-y-0.5 flex items-center gap-2">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/></svg>
                        Faculty / Laboratory
                    </button>
                </div>
            </div>

            <div class="grid grid-cols-1 lg:grid-cols-3 gap-x-12 gap-y-8">
                <?php 
                $dirs = [
                    'g11_sections' => ['title' => 'Grade 11 Section', 'color' => 'blue'],
                    'g12_sections' => ['title' => 'Grade 12 Section', 'color' => 'pink'],
                ];

                foreach ($dirs as $dk => $dv): 
                ?>
                <div>
                    <div class="border-b-2 <?php echo 'border-'.$dv['color'].'-200'; ?> mb-4 pb-2">
                        <h3 class="text-xs font-black uppercase tracking-[0.2em] <?php echo 'text-'.$dv['color'].'-600'; ?> flex items-center gap-2">
                            <span class="w-2 h-2 rounded-full <?php echo 'bg-'.$dv['color'].'-500'; ?> shrink-0"></span>
                            <?php echo $dv['title']; ?>
                        </h3>
                    </div>
                    <div class="space-y-4">
                        <?php if (empty($registries[$dk])): ?>
                            <p class="text-sm text-slate-400 font-medium">No sections added yet.</p>
                        <?php endif; ?>
                        
                        <?php foreach($registries[$dk] as $sec): ?>
                        <div class="bg-white border text-center border-slate-100 p-4 rounded-2xl flex items-center justify-between shadow-sm hover:shadow-md hover:border-slate-200 transition-all group">
                            <div class="flex items-center gap-4">
                                <div class="w-12 h-12 rounded-xl bg-slate-50 border border-slate-100 flex items-center justify-center text-slate-400 group-hover:bg-slate-100 transition-colors">
                                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"></path></svg>
                                </div>
                                <div class="text-left">
                                    <h4 class="font-bold text-slate-800 tracking-wide uppercase"><?php echo htmlspecialchars($sec['name']); ?></h4>
                                    <?php if ($sec['room']): ?>
                                        <p class="text-[10px] font-bold uppercase tracking-widest text-emerald-600 flex items-center gap-1 mt-0.5">
                                            <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"></path></svg>
                                            ROOM <?php echo htmlspecialchars($sec['room']); ?>
                                        </p>
                                    <?php else: ?>
                                        <p class="text-[10px] font-bold uppercase tracking-widest text-slate-400 flex items-center gap-1 mt-0.5">
                                            <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"></path></svg>
                                            Infrastructure Pending
                                        </p>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <button onclick="openMapModal(<?php echo $sec['id']; ?>, '<?php echo addslashes($sec['name']); ?>')" class="bg-dranhs-green hover:bg-emerald-600 text-white font-bold text-[10px] uppercase tracking-widest px-4 py-2.5 rounded-full shadow-lg shadow-emerald-600/20 transition-transform hover:-translate-y-0.5 flex items-center gap-1.5 focus:outline-none">
                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"></path><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"></path></svg>
                                Map
                            </button>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endforeach; ?>

                <!-- 3rd column: Faculty / Laboratory -->
                <div>
                    <div class="border-b-2 border-amber-200 mb-4 pb-2">
                        <h3 class="text-xs font-black uppercase tracking-[0.2em] text-amber-600 flex items-center gap-2">
                            <span class="w-2 h-2 rounded-full bg-amber-500 shrink-0"></span>
                            Faculty / Laboratory
                        </h3>
                    </div>
                    <div class="space-y-4">
                        <?php if (empty($facilities)): ?>
                            <p class="text-sm text-slate-400 font-medium">No facilities added yet.</p>
                        <?php endif; ?>
                        <?php foreach ($facilities as $fc): ?>
                        <div class="bg-white border border-slate-100 p-4 rounded-2xl flex items-center justify-between shadow-sm hover:shadow-md hover:border-slate-200 transition-all group">
                            <div class="flex items-center gap-4">
                                <div class="w-12 h-12 rounded-xl bg-amber-50 border border-amber-100 flex items-center justify-center text-amber-400 group-hover:bg-amber-100 transition-colors">
                                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/></svg>
                                </div>
                                <div class="text-left">
                                    <h4 class="font-bold text-slate-800 tracking-wide uppercase"><?php echo htmlspecialchars($fc['facility_name']); ?></h4>
                                    <?php if ($fc['room_number']): ?>
                                        <p class="text-[10px] font-bold uppercase tracking-widest text-amber-600 flex items-center gap-1 mt-0.5">
                                            <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/></svg>
                                            Bldg <?php echo htmlspecialchars($fc['building_number']); ?> · Floor <?php echo htmlspecialchars($fc['floor_number']); ?> · Room <?php echo htmlspecialchars($fc['room_number']); ?>
                                        </p>
                                    <?php else: ?>
                                        <p class="text-[10px] font-bold uppercase tracking-widest text-slate-400 flex items-center gap-1 mt-0.5">
                                            <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/></svg>
                                            Location Pending
                                        </p>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <div class="flex items-center gap-2">
                                <button onclick="openFacilityMapModal(<?php echo (int)$fc['id']; ?>, '<?php echo addslashes($fc['facility_name']); ?>')" class="bg-amber-500 hover:bg-amber-600 text-white font-bold text-[10px] uppercase tracking-widest px-4 py-2.5 rounded-full shadow-lg shadow-amber-500/20 transition-transform hover:-translate-y-0.5 flex items-center gap-1.5 focus:outline-none">
                                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                                    Map
                                </button>
                                <form method="POST" action="?page=system_settings" class="inline confirm-action-form" data-confirm-message="Remove this facility?">
                                    <input type="hidden" name="action" value="delete_facility">
                                    <input type="hidden" name="facility_id" value="<?php echo (int)$fc['id']; ?>">
                                    <button type="submit" class="text-slate-300 hover:text-red-500 transition-colors p-1">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6M9 7V4a1 1 0 011-1h4a1 1 0 011 1v3m-7 0h8"/></svg>
                                    </button>
                                </form>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>

            </div>

            <?php
            // Annex entries — only non-Bldg14/15
            $other_annex = array_filter($annex_entries, function($ae) {
                $b = trim($ae['building_number'] ?? '');
                return $b !== '14' && $b !== '15';
            });
            ?>
            <?php if (!empty($other_annex)): ?>
            <div class="mt-8">
                <h3 class="text-xs font-black uppercase tracking-widest text-indigo-600 mb-3 flex items-center gap-2">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16m-7 6h7"/></svg>
                    Manual Annex Entries (Other Buildings)
                </h3>
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-3">
                    <?php foreach ($other_annex as $ae): ?>
                    <div class="flex items-center justify-between bg-indigo-50 border border-indigo-100 rounded-xl px-4 py-3">
                        <div>
                            <div class="text-sm font-black text-indigo-800"><?php echo htmlspecialchars($ae['section_name']); ?></div>
                            <div class="text-[10px] font-bold text-indigo-500 uppercase tracking-widest mt-0.5">
                                Bldg <?php echo htmlspecialchars($ae['building_number']); ?> · Floor <?php echo htmlspecialchars($ae['floor_number']); ?> · Room <?php echo htmlspecialchars($ae['room_number']); ?>
                            </div>
                        </div>
                        <form method="POST" action="?page=system_settings" class="confirm-action-form" data-confirm-message="Remove this annex entry?">
                            <input type="hidden" name="action" value="delete_annex">
                            <input type="hidden" name="annex_id" value="<?php echo (int)$ae['id']; ?>">
                            <button type="submit" class="text-indigo-300 hover:text-red-500 transition-colors">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6M9 7V4a1 1 0 011-1h4a1 1 0 011 1v3m-7 0h8"/></svg>
                            </button>
                        </form>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>

        </div> <!-- END TAB ROOM ASSIGNMENT -->

        <!-- THEME TAB -->
        <div id="tab-theme" class="tab-content hidden">
            <h2 class="text-2xl font-heading font-black text-dranhs-dark mb-1">Theme & Branding</h2>
            <p class="text-sm text-slate-500 mb-8">Upload PNG images to customize the school logo, background, and footer logos. Recommended: PNG with transparency, max 2MB.</p>

            <?php
            // Helper: get current theme asset path
            function theme_asset($key, $settings) {
                return !empty($settings[$key]) ? '../' . $settings[$key] : null;
            }
            $assets = [
                'school_logo'   => ['label' => 'School Logo',    'desc' => 'Replaces the logo icon in the navbar (admin & landing page). Recommended: 128×128px PNG.',  'icon' => 'M12 14l9-5-9-5-9 5 9 5z'],
                'background'    => ['label' => 'Background Image','desc' => 'Used as the landing page background at 80% opacity. Recommended: 1920×1080px PNG/JPG.', 'icon' => 'M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z'],
                'deped_logo'    => ['label' => 'DepEd Logo',      'desc' => 'Small logo shown in the footer. Recommended: 64×64px PNG.',  'icon' => 'M9 12l2 2 4-4M7.835 4.697a3.42 3.42 0 001.946-.806 3.42 3.42 0 014.438 0 3.42 3.42 0 001.946.806 3.42 3.42 0 013.138 3.138 3.42 3.42 0 00.806 1.946 3.42 3.42 0 010 4.438 3.42 3.42 0 00-.806 1.946 3.42 3.42 0 01-3.138 3.138 3.42 3.42 0 00-1.946.806 3.42 3.42 0 01-4.438 0 3.42 3.42 0 00-1.946-.806 3.42 3.42 0 01-3.138-3.138 3.42 3.42 0 00-.806-1.946 3.42 3.42 0 010-4.438 3.42 3.42 0 00.806-1.946 3.42 3.42 0 013.138-3.138z'],
                'division_logo' => ['label' => 'Division Logo',   'desc' => 'Small logo shown in the footer beside DepEd logo. Recommended: 64×64px PNG.', 'icon' => 'M9 12l2 2 4-4M7.835 4.697a3.42 3.42 0 001.946-.806 3.42 3.42 0 014.438 0 3.42 3.42 0 001.946.806 3.42 3.42 0 013.138 3.138 3.42 3.42 0 00.806 1.946 3.42 3.42 0 010 4.438 3.42 3.42 0 00-.806 1.946 3.42 3.42 0 01-3.138 3.138 3.42 3.42 0 00-1.946.806 3.42 3.42 0 01-4.438 0 3.42 3.42 0 00-1.946-.806 3.42 3.42 0 01-3.138-3.138 3.42 3.42 0 00-.806-1.946 3.42 3.42 0 010-4.438 3.42 3.42 0 00.806-1.946 3.42 3.42 0 013.138-3.138z'],
            ];
            ?>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-6">
                <?php foreach ($assets as $key => $info):
                    $current = theme_asset($key, $settings);
                    $has = $current !== null;
                ?>
                <div class="bg-white border border-slate-200 rounded-2xl overflow-hidden shadow-sm">
                    <div class="px-5 py-4 border-b border-slate-100 flex items-center gap-3">
                        <div class="w-9 h-9 rounded-xl bg-dranhs-green/10 flex items-center justify-center shrink-0">
                            <svg class="w-5 h-5 text-dranhs-green" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="<?php echo $info['icon']; ?>"/></svg>
                        </div>
                        <div>
                            <h3 class="text-sm font-black text-dranhs-dark"><?php echo $info['label']; ?></h3>
                            <p class="text-[10px] text-slate-400 mt-0.5"><?php echo $info['desc']; ?></p>
                        </div>
                    </div>
                    <div class="p-5 flex items-center gap-4">
                        <!-- Preview -->
                        <div class="w-16 h-16 rounded-xl border-2 <?php echo $has ? 'border-dranhs-green/30' : 'border-dashed border-slate-200'; ?> overflow-hidden bg-slate-50 flex items-center justify-center shrink-0">
                            <?php if ($has): ?>
                                <img src="<?php echo htmlspecialchars($current); ?>" alt="<?php echo $info['label']; ?>" class="w-full h-full object-contain">
                            <?php else: ?>
                                <svg class="w-7 h-7 text-slate-300" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                            <?php endif; ?>
                        </div>
                        <!-- Upload / Remove -->
                        <div class="flex-1 space-y-2">
                            <form method="POST" action="?page=system_settings" enctype="multipart/form-data" class="flex gap-2">
                                <input type="hidden" name="action" value="upload_theme_asset">
                                <input type="hidden" name="asset_key" value="<?php echo $key; ?>">
                                <label class="flex-1 cursor-pointer">
                                    <span class="inline-flex items-center gap-1.5 w-full justify-center px-3 py-2 rounded-xl bg-dranhs-green text-white text-xs font-bold hover:bg-emerald-700 transition-colors">
                                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12"/></svg>
                                        <?php echo $has ? 'Replace' : 'Upload'; ?>
                                    </span>
                                    <input type="file" name="theme_file" accept="image/png,image/jpeg,image/jpg,image/webp,image/svg+xml" class="hidden"
                                        onchange="this.closest('form').submit()">
                                </label>
                            </form>
                            <?php if ($has): ?>
                            <form method="POST" action="?page=system_settings" class="confirm-action-form" data-confirm-message="Remove this image?">
                                <input type="hidden" name="action" value="remove_theme_asset">
                                <input type="hidden" name="asset_key" value="<?php echo $key; ?>">
                                <button type="submit" class="w-full px-3 py-2 rounded-xl bg-red-50 text-red-600 text-xs font-bold hover:bg-red-100 transition-colors">Remove</button>
                            </form>
                            <?php endif; ?>
                        </div>
                    </div>
                    <?php if ($has): ?>
                    <div class="px-5 pb-4">
                        <p class="text-[9px] text-slate-400 font-semibold truncate">📁 <?php echo htmlspecialchars($settings[$key]); ?></p>
                    </div>
                    <?php endif; ?>
                </div>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- CURRICULUM TAB -->
        <div id="tab-curriculum" class="tab-content hidden">
            <div class="flex items-center justify-between mb-8">
                <div class="flex flex-col gap-1">
                    <div class="flex items-center gap-2">
                        <svg class="w-6 h-6 text-violet-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"></path></svg>
                        <h2 class="text-2xl font-heading font-black text-dranhs-dark uppercase tracking-tight">Curriculum Catalog</h2>
                    </div>
                    <p class="text-sm font-bold text-slate-400 tracking-widest uppercase">Manage Grade 11 career pathways and Grade 12 strands with code, description, and electives.</p>
                </div>
                <button type="button" onclick="openCurriculumModal()" class="bg-violet-600 hover:bg-violet-700 text-white font-bold uppercase tracking-widest text-xs px-5 py-3 rounded-xl shadow-lg shadow-violet-500/20 transition-transform hover:-translate-y-0.5 flex items-center gap-2">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path></svg>
                    Add Pathway / Strand
                </button>
            </div>

            <div class="overflow-x-auto rounded-2xl border border-slate-200 bg-white shadow-sm">
                <table class="min-w-full text-left text-sm border-collapse">
                    <thead class="bg-slate-50 text-slate-500 uppercase text-xs tracking-wider font-bold">
                        <tr>
                            <th class="px-5 py-3">Grade</th>
                            <th class="px-5 py-3">Category</th>
                            <th class="px-5 py-3">Track</th>
                            <th class="px-5 py-3">Pathway / Strand</th>
                            <th class="px-5 py-3">Code</th>
                            <th class="px-5 py-3">Status</th>
                            <th class="px-5 py-3">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        <?php if (empty($pathway_catalog)): ?>
                        <tr>
                            <td colspan="7" class="px-5 py-10 text-center text-slate-400 font-bold">No curriculum catalog entries found. Add one to begin.</td>
                        </tr>
                        <?php else: ?>
                            <?php foreach ($pathway_catalog as $entry): ?>
                            <tr class="hover:bg-slate-50 transition-colors">
                                <td class="px-5 py-4 font-semibold text-slate-700"><?php echo htmlspecialchars($entry['grade_level']); ?></td>
                                <td class="px-5 py-4 text-slate-600"><?php echo htmlspecialchars($entry['category']); ?></td>
                                <td class="px-5 py-4 text-slate-600"><?php echo htmlspecialchars($entry['track']); ?></td>
                                <td class="px-5 py-4 text-slate-600"><?php echo htmlspecialchars($entry['pathway_strand']); ?></td>
                                <td class="px-5 py-4 text-slate-600"><?php echo htmlspecialchars($entry['code']); ?></td>
                                <td class="px-5 py-4 text-slate-600"><?php echo $entry['enabled'] ? '<span class="inline-flex px-2 py-1 rounded-full bg-emerald-100 text-emerald-700 text-[11px] uppercase tracking-[0.18em] font-bold">Active</span>' : '<span class="inline-flex px-2 py-1 rounded-full bg-slate-100 text-slate-600 text-[11px] uppercase tracking-[0.18em] font-bold">Inactive</span>'; ?></td>
                                <td class="px-5 py-4">
                                    <div class="flex items-center gap-2">
                                        <button type="button" onclick="editCurriculumEntry(<?php echo (int)$entry['id']; ?>)" class="px-3 py-2 rounded-xl bg-slate-100 text-slate-700 text-xs font-bold hover:bg-slate-200 transition-colors">Edit</button>
                                        <form method="POST" action="?page=system_settings" class="inline confirm-action-form" data-confirm-message="Delete this curriculum entry?">
                                            <input type="hidden" name="action" value="delete_curriculum">
                                            <input type="hidden" name="id" value="<?php echo (int)$entry['id']; ?>">
                                            <button type="submit" class="px-3 py-2 rounded-xl bg-rose-50 text-rose-600 text-xs font-bold hover:bg-rose-100 transition-colors">Delete</button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <div id="curriculum-modal" class="hidden fixed inset-0 z-50 flex items-center justify-center bg-slate-900/70 p-4">
                <div class="w-full max-w-3xl rounded-3xl bg-white shadow-2xl overflow-hidden">
                    <div class="flex items-center justify-between p-5 border-b border-slate-200">
                        <div>
                            <h3 class="text-xl font-black text-slate-900 uppercase tracking-tight">Pathway / Strand Entry</h3>
                            <p class="text-sm text-slate-500">Add or edit a Grade 11 career pathway or Grade 12 strand.</p>
                        </div>
                        <button type="button" onclick="closeCurriculumModal()" class="text-slate-400 hover:text-slate-700 transition-colors">✕</button>
                    </div>
                    <form method="POST" action="?page=system_settings" class="space-y-6 p-6" id="curriculum-entry-form">
                        <input type="hidden" name="action" value="save_curriculum">
                        <input type="hidden" name="id" id="curriculum-id" value="0">

                        <div class="grid grid-cols-1 lg:grid-cols-2 gap-4">
                            <div class="space-y-2">
                                <label class="text-xs font-bold uppercase tracking-widest text-slate-600">Grade Level</label>
                                <select name="grade_level" id="curriculum-grade-level" onchange="syncCurriculumTrackOptions()" class="w-full border border-slate-300 rounded-xl px-4 py-3 text-sm focus:border-violet-500 outline-none">
                                    <option value="">Select Grade</option>
                                    <option value="Grade 11">Grade 11</option>
                                    <option value="Grade 12">Grade 12</option>
                                </select>
                            </div>
                            <div class="space-y-2">
                                <label class="text-xs font-bold uppercase tracking-widest text-slate-600">Category</label>
                                <select name="category" id="curriculum-category" onchange="onCurriculumCategoryChange()" class="w-full border border-slate-300 rounded-xl px-4 py-3 text-sm focus:border-violet-500 outline-none">
                                    <option value="Career Pathway">Career Pathway</option>
                                    <option value="Strand">Strand</option>
                                </select>
                            </div>
                        </div>

                        <div class="grid grid-cols-1 lg:grid-cols-2 gap-4">
                            <div class="space-y-2">
                                <label id="curriculum-track-label" class="text-xs font-bold uppercase tracking-widest text-slate-600">Track</label>
                                <select name="track" id="curriculum-track" class="w-full border border-slate-300 rounded-xl px-4 py-3 text-sm focus:border-violet-500 outline-none">
                                    <option value="">Select Track</option>
                                </select>
                            </div>
                            <div class="space-y-2">
                                <label class="text-xs font-bold uppercase tracking-widest text-slate-600">Code</label>
                                <input type="text" name="code" id="curriculum-code" class="w-full border border-slate-300 rounded-xl px-4 py-3 text-sm focus:border-violet-500 outline-none" placeholder="example: stem" required>
                            </div>
                        </div>

                        <div class="grid grid-cols-1 lg:grid-cols-2 gap-4">
                            <div class="space-y-2 lg:col-span-2">
                                <label class="text-xs font-bold uppercase tracking-widest text-slate-600">Pathway / Strand Name</label>
                                <input type="text" name="pathway_strand" id="curriculum-name" class="w-full border border-slate-300 rounded-xl px-4 py-3 text-sm focus:border-violet-500 outline-none" placeholder="Enter career pathway or strand name" required>
                            </div>
                        </div>

                        <div class="space-y-2">
                            <label class="text-xs font-bold uppercase tracking-widest text-slate-600">Description</label>
                            <textarea name="description" id="curriculum-description" rows="4" class="w-full border border-slate-300 rounded-xl px-4 py-3 text-sm focus:border-violet-500 outline-none" placeholder="Optional description"></textarea>
                        </div>

                        <div id="curriculum-electives-section" class="space-y-3">
                            <div class="flex items-center justify-between">
                                <div>
                                    <p class="text-xs font-bold uppercase tracking-widest text-slate-600">Electives</p>
                                    <p class="text-xs text-slate-500">Add optional subject electives for this pathway or strand.</p>
                                </div>
                                <button type="button" onclick="addCurriculumElective('')" class="bg-slate-100 hover:bg-slate-200 text-slate-700 px-3 py-2 rounded-xl text-xs font-bold uppercase tracking-widest transition-colors">Add Elective</button>
                            </div>
                            <div id="curriculum-electives" class="space-y-2">
                                <div class="text-xs uppercase tracking-[0.2em] text-slate-300 font-bold">No electives added yet.</div>
                            </div>
                        </div>

                        <div class="flex items-center gap-3">
                            <label class="relative inline-flex items-center cursor-pointer">
                                <input type="checkbox" name="enabled" id="curriculum-enabled" class="sr-only peer" checked>
                                <div class="w-12 h-6 rounded-full bg-slate-200 peer-checked:bg-emerald-500 relative after:content-[''] after:absolute after:top-[3px] after:left-[3px] after:w-5 after:h-5 after:rounded-full after:bg-white after:transition-all peer-checked:after:translate-x-full"></div>
                            </label>
                            <span class="text-sm font-semibold text-slate-700 uppercase tracking-widest">Active</span>
                        </div>

                        <div class="flex justify-end gap-3 pt-4 border-t border-slate-200">
                            <button type="button" onclick="closeCurriculumModal()" class="text-slate-500 hover:text-slate-900 font-bold uppercase tracking-widest text-xs">Cancel</button>
                            <button type="submit" class="bg-violet-600 hover:bg-violet-700 text-white font-bold uppercase tracking-widest text-xs px-6 py-3 rounded-xl transition-transform hover:-translate-y-0.5">Save Entry</button>
                        </div>
                    </form>
                </div>
            </div>

            <script>
            const curriculumTrackOptions = {
                'Grade 11': {
                    'Career Pathway': ['Academic', 'Tech-Pro', 'ALS'],
                    'Strand': ['Academic', 'TVL']
                },
                'Grade 12': {
                    'Career Pathway': ['Academic', 'Tech-Pro', 'TVL'],
                    'Strand': ['Academic', 'TVL']
                }
            };

            const gradeToCategory = {
                'Grade 11': 'Career Pathway',
                'Grade 12': 'Strand'
            };

            function openCurriculumModal(entry = null) {
                const modal = document.getElementById('curriculum-modal');
                const form = document.getElementById('curriculum-entry-form');
                const gradeInput = document.getElementById('curriculum-grade-level');
                const categoryInput = document.getElementById('curriculum-category');
                const trackInput = document.getElementById('curriculum-track');
                const nameInput = document.getElementById('curriculum-name');
                const codeInput = document.getElementById('curriculum-code');
                const descInput = document.getElementById('curriculum-description');
                const enabledInput = document.getElementById('curriculum-enabled');
                const idInput = document.getElementById('curriculum-id');
                const electivesContainer = document.getElementById('curriculum-electives');

                form.reset();
                idInput.value = '0';
                electivesContainer.innerHTML = '<div class="text-xs uppercase tracking-[0.2em] text-slate-300 font-bold">No electives added yet.</div>';
                gradeInput.value = '';
                categoryInput.value = 'Career Pathway';
                fillTrackOptions('');
                toggleCurriculumElectivesVisibility(categoryInput.value);
                enabledInput.checked = true;

                if (entry) {
                    idInput.value = entry.id;
                    gradeInput.value = entry.grade_level;
                    categoryInput.value = entry.category;
                    fillTrackOptions(entry.grade_level, entry.track, entry.category);
                    toggleCurriculumElectivesVisibility(entry.category);
                    nameInput.value = entry.pathway_strand;
                    codeInput.value = entry.code;
                    descInput.value = entry.description || '';
                    enabledInput.checked = entry.enabled == 1 || entry.enabled === '1';
                    if (entry.category !== 'Strand') {
                        populateElectives(entry.electives);
                    }
                }

                modal.classList.remove('hidden');
            }

            function closeCurriculumModal() {
                document.getElementById('curriculum-modal').classList.add('hidden');
            }

            function setCurriculumTrackLabel(category) {
                const label = document.getElementById('curriculum-track-label');
                label.textContent = 'Track';
            }

            function toggleCurriculumElectivesVisibility(category) {
                const electivesSection = document.getElementById('curriculum-electives-section');
                const electivesContainer = document.getElementById('curriculum-electives');
                if (!electivesSection || !electivesContainer) return;

                if (category === 'Strand') {
                    electivesSection.classList.add('hidden');
                    electivesContainer.innerHTML = '<div class="text-xs uppercase tracking-[0.2em] text-slate-400 font-bold">Electives are not applicable for Strand entries.</div>';
                } else {
                    electivesSection.classList.remove('hidden');
                    if (!electivesContainer.querySelector('input[name="electives[]"]')) {
                        electivesContainer.innerHTML = '<div class="text-xs uppercase tracking-[0.2em] text-slate-300 font-bold">No electives added yet.</div>';
                    }
                }
            }

            function fillTrackOptions(gradeLevel, selected = '', category = 'Career Pathway') {
                const track = document.getElementById('curriculum-track');
                track.innerHTML = '<option value="">Select Track</option>';
                const gradeOptions = curriculumTrackOptions[gradeLevel] || {};
                let options = gradeOptions[category] || [];
                if (selected && !options.includes(selected)) {
                    options = [...options, selected];
                }
                options.forEach(opt => {
                    const option = document.createElement('option');
                    option.value = opt;
                    option.textContent = opt;
                    if (opt === selected) option.selected = true;
                    track.appendChild(option);
                });
                setCurriculumTrackLabel(category);
            }

            function syncCurriculumTrackOptions() {
                const gradeValue = document.getElementById('curriculum-grade-level').value;
                const categoryInput = document.getElementById('curriculum-category');
                categoryInput.value = gradeToCategory[gradeValue] || categoryInput.value || 'Career Pathway';
                fillTrackOptions(gradeValue, '', categoryInput.value);
                toggleCurriculumElectivesVisibility(categoryInput.value);
            }

            function onCurriculumCategoryChange() {
                const gradeValue = document.getElementById('curriculum-grade-level').value;
                const categoryValue = document.getElementById('curriculum-category').value;
                fillTrackOptions(gradeValue, '', categoryValue);
                toggleCurriculumElectivesVisibility(categoryValue);
            }

            function addCurriculumElective(value = '') {
                const container = document.getElementById('curriculum-electives');
                const noElectiveMsg = container.querySelector('.text-xs');
                if (noElectiveMsg) noElectiveMsg.remove();

                const row = document.createElement('div');
                row.className = 'flex gap-3 items-center';
                row.innerHTML = `
                    <input type="text" name="electives[]" value="${value.replace(/"/g, '&quot;')}" placeholder="Elective subject" class="flex-1 border border-slate-300 rounded-xl px-4 py-3 text-sm focus:border-violet-500 outline-none">
                    <button type="button" onclick="removeCurriculumElective(this)" class="bg-rose-50 text-rose-600 px-3 py-2 rounded-xl text-xs font-bold uppercase tracking-widest hover:bg-rose-100 transition-colors">Remove</button>
                `;
                container.appendChild(row);
            }

            function removeCurriculumElective(button) {
                const item = button.closest('div');
                if (item) item.remove();
                const container = document.getElementById('curriculum-electives');
                if (!container.querySelector('input[name="electives[]"]')) {
                    container.innerHTML = '<div class="text-xs uppercase tracking-[0.2em] text-slate-300 font-bold">No electives added yet.</div>';
                }
            }

            function populateElectives(raw) {
                const container = document.getElementById('curriculum-electives');
                container.innerHTML = '';
                let values = [];
                try {
                    values = typeof raw === 'string' ? JSON.parse(raw || '[]') : raw || [];
                } catch (err) {
                    values = [];
                }
                if (!Array.isArray(values) || values.length === 0) {
                    container.innerHTML = '<div class="text-xs uppercase tracking-[0.2em] text-slate-300 font-bold">No electives added yet.</div>';
                    return;
                }
                values.forEach(val => addCurriculumElective(val));
            }

            function editCurriculumEntry(id) {
                const entry = <?php echo json_encode($pathway_catalog); ?>.find(item => parseInt(item.id, 10) === id);
                if (!entry) return;
                openCurriculumModal(entry);
            }
            </script>
        </div>

        <!-- WITHDRAWN STUDENTS TAB -->
        <div id="tab-withdrawn" class="tab-content hidden">
            <div class="flex items-center justify-between mb-6">
                <div>
                    <h2 class="text-2xl font-heading font-black text-dranhs-dark">Withdrawn Students</h2>
                    <p class="text-sm text-slate-500 mt-1">These students have been withdrawn. Retrieve to send back to evaluation, or permanently delete.</p>
                </div>
                <span class="px-3 py-1.5 bg-red-100 text-red-700 rounded-full text-xs font-black uppercase tracking-wider"><?php echo count($withdrawn_students); ?> withdrawn</span>
            </div>

            <?php if (empty($withdrawn_students)): ?>
                <div class="text-center py-16 bg-slate-50 rounded-2xl border border-slate-200">
                    <svg class="w-12 h-12 text-slate-300 mx-auto mb-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                    <p class="text-sm font-semibold text-slate-500">No withdrawn students.</p>
                </div>
            <?php else: ?>
            <div class="overflow-x-auto rounded-2xl border border-slate-200">
                <table class="w-full text-left border-collapse text-sm">
                    <thead class="bg-slate-50 text-xs uppercase text-slate-500 font-bold">
                        <tr>
                            <th class="px-5 py-3">Name</th>
                            <th class="px-5 py-3">LRN</th>
                            <th class="px-5 py-3">Grade Level</th>
                            <th class="px-5 py-3">Track</th>
                            <th class="px-5 py-3">Action</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        <?php foreach ($withdrawn_students as $ws):
                            $wname = ($ws['last_name']??'').', '.($ws['first_name']??'');
                            if (!empty($ws['middle_name'])) $wname .= ' '.strtoupper(substr($ws['middle_name'],0,1)).'.';
                            if (!empty($ws['extension_name'])) $wname .= ' '.$ws['extension_name'];
                        ?>
                        <tr class="hover:bg-slate-50 transition-colors">
                            <td class="px-5 py-4 font-semibold text-slate-700"><?php echo htmlspecialchars($wname); ?></td>
                            <td class="px-5 py-4 text-slate-500"><?php echo htmlspecialchars($ws['lrn'] ?: '--'); ?></td>
                            <td class="px-5 py-4 text-slate-500"><?php echo htmlspecialchars($ws['grade_level'] ?: '--'); ?></td>
                            <td class="px-5 py-4 text-slate-500"><?php echo htmlspecialchars($ws['track'] ?: '--'); ?></td>
                            <td class="px-5 py-4">
                                <div class="flex items-center gap-2">
                                    <!-- Retrieve -->
                                    <form method="POST" action="?page=system_settings" class="inline">
                                        <input type="hidden" name="action" value="retrieve_student">
                                        <input type="hidden" name="student_id" value="<?php echo (int)$ws['id']; ?>">
                                        <button type="submit" class="inline-flex items-center gap-1.5 px-3 py-2 rounded-lg bg-emerald-50 text-emerald-700 text-xs font-bold hover:bg-emerald-100 transition-colors border border-emerald-200">
                                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/></svg>
                                            Retrieve
                                        </button>
                                    </form>
                                    <!-- Delete -->
                                    <button type="button"
                                        class="wd-delete-btn inline-flex items-center gap-1.5 px-3 py-2 rounded-lg bg-red-50 text-red-600 text-xs font-bold hover:bg-red-100 transition-colors border border-red-200"
                                        data-id="<?php echo (int)$ws['id']; ?>"
                                        data-name="<?php echo htmlspecialchars($wname, ENT_QUOTES); ?>">
                                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6M9 7V4a1 1 0 011-1h4a1 1 0 011 1v3m-7 0h8"/></svg>
                                        Delete
                                    </button>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php endif; ?>
        </div>

        <!-- Delete confirm modal -->
        <div id="wd-delete-modal" class="fixed inset-0 z-50 hidden">
            <div class="absolute inset-0 bg-slate-900/60" id="wd-del-backdrop"></div>
            <div class="relative z-10 min-h-screen flex items-center justify-center p-4">
                <div class="w-full max-w-sm bg-white rounded-2xl shadow-2xl border border-slate-200 overflow-hidden">
                    <div class="bg-red-600 px-6 py-4 flex items-center justify-between">
                        <h3 class="font-heading font-black text-lg text-white">Permanently Delete</h3>
                        <button type="button" id="wd-del-close" class="w-9 h-9 rounded-full bg-white/10 text-white hover:bg-white/20 text-xl flex items-center justify-center">&times;</button>
                    </div>
                    <form method="POST" action="?page=system_settings" class="p-6 space-y-4">
                        <input type="hidden" name="action" value="delete_student">
                        <input type="hidden" name="student_id" id="wd-del-student-id">
                        <p class="text-sm text-slate-600">Permanently delete <span id="wd-del-student-name" class="font-bold text-red-600"></span>? This cannot be undone.</p>
                        <div>
                            <label class="block text-xs font-bold uppercase tracking-wider text-slate-500 mb-2">Confirm Password</label>
                            <input type="password" name="confirm_password" class="w-full bg-white border-2 border-slate-300 px-4 py-3 rounded-xl text-sm font-medium focus:border-red-500 outline-none" required placeholder="Enter your password">
                        </div>
                        <div class="flex justify-end gap-3">
                            <button type="button" id="wd-del-cancel" class="px-5 py-2.5 rounded-xl border-2 border-slate-300 text-slate-600 font-bold text-sm hover:bg-slate-50">Cancel</button>
                            <button type="submit" class="px-5 py-2.5 rounded-xl bg-red-600 text-white font-bold text-sm hover:bg-red-700 shadow-md">Delete Forever</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
        <script>
        document.querySelectorAll('.wd-delete-btn').forEach(btn => btn.addEventListener('click', function () {
            document.getElementById('wd-del-student-id').value = this.dataset.id;
            document.getElementById('wd-del-student-name').textContent = this.dataset.name;
            document.getElementById('wd-delete-modal').classList.remove('hidden');
        }));
        document.getElementById('wd-del-close').addEventListener('click', function () { document.getElementById('wd-delete-modal').classList.add('hidden'); });
        document.getElementById('wd-del-cancel').addEventListener('click', function () { document.getElementById('wd-delete-modal').classList.add('hidden'); });
        document.getElementById('wd-del-backdrop').addEventListener('click', function () { document.getElementById('wd-delete-modal').classList.add('hidden'); });
        </script>
        
        <!-- DATA HUB TAB -->
        <div id="tab-datahub" class="tab-content hidden">
            <div class="flex items-center gap-2 mb-2">
                <svg class="w-6 h-6 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path></svg>
                <h2 class="text-2xl font-heading font-black text-dranhs-dark uppercase tracking-tight">Data Hub — Export Reports</h2>
            </div>
            <p class="text-sm text-slate-500 mb-8">Download enrollment reports as Excel (.xlsx) files. All data is filtered by the current school year.</p>

            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-5">
                <!-- 1. Enrollment Summary -->
                <a href="export_report.php?report=enrollment_summary" class="group bg-white border-2 border-slate-200 hover:border-emerald-400 rounded-2xl p-6 transition-all hover:shadow-lg hover:-translate-y-0.5">
                    <div class="w-12 h-12 rounded-xl bg-emerald-50 text-emerald-600 flex items-center justify-center mb-4 group-hover:bg-emerald-100 transition-colors">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"></path></svg>
                    </div>
                    <h3 class="font-black text-sm text-dranhs-dark uppercase tracking-wider mb-1">Enrollment Summary</h3>
                    <p class="text-xs text-slate-400">Dashboard stats, G11 pathways, G12 strands, student types, and daily enrollment — all with gender breakdown.</p>
                    <span class="inline-flex items-center gap-1 mt-3 text-[10px] font-bold text-emerald-600 uppercase tracking-widest"><svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"></path></svg> 5 Sheets</span>
                </a>

                <!-- 2. Student Master List -->
                <a href="export_report.php?report=student_masterlist" class="group bg-white border-2 border-slate-200 hover:border-blue-400 rounded-2xl p-6 transition-all hover:shadow-lg hover:-translate-y-0.5">
                    <div class="w-12 h-12 rounded-xl bg-blue-50 text-blue-600 flex items-center justify-center mb-4 group-hover:bg-blue-100 transition-colors">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z"></path></svg>
                    </div>
                    <h3 class="font-black text-sm text-dranhs-dark uppercase tracking-wider mb-1">Student Master List</h3>
                    <p class="text-xs text-slate-400">Complete list of all active students with LRN, name, grade, track, pathway/strand, contact info, and enrollment date.</p>
                    <span class="inline-flex items-center gap-1 mt-3 text-[10px] font-bold text-blue-600 uppercase tracking-widest"><svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"></path></svg> 1 Sheet</span>
                </a>

                <!-- 3. Classroom Master List -->
                <a href="export_report.php?report=classroom_masterlist" class="group bg-white border-2 border-slate-200 hover:border-violet-400 rounded-2xl p-6 transition-all hover:shadow-lg hover:-translate-y-0.5">
                    <div class="w-12 h-12 rounded-xl bg-violet-50 text-violet-600 flex items-center justify-center mb-4 group-hover:bg-violet-100 transition-colors">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 14v3m4-3v3m4-3v3M3 21h18M3 10h18M3 7l9-4 9 4M4 10h16v11H4V10z"></path></svg>
                    </div>
                    <h3 class="font-black text-sm text-dranhs-dark uppercase tracking-wider mb-1">Classroom Master List</h3>
                    <p class="text-xs text-slate-400">One sheet per section with numbered student roster, sorted by sex then last name.</p>
                    <span class="inline-flex items-center gap-1 mt-3 text-[10px] font-bold text-violet-600 uppercase tracking-widest"><svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"></path></svg> Multi-Sheet</span>
                </a>

                <!-- 4. Withdrawn Students -->
                <a href="export_report.php?report=withdrawn_list" class="group bg-white border-2 border-slate-200 hover:border-red-400 rounded-2xl p-6 transition-all hover:shadow-lg hover:-translate-y-0.5">
                    <div class="w-12 h-12 rounded-xl bg-red-50 text-red-600 flex items-center justify-center mb-4 group-hover:bg-red-100 transition-colors">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6M9 7V4a1 1 0 011-1h4a1 1 0 011 1v3m-7 0h8"></path></svg>
                    </div>
                    <h3 class="font-black text-sm text-dranhs-dark uppercase tracking-wider mb-1">Withdrawn Students</h3>
                    <p class="text-xs text-slate-400">List of all withdrawn students with their original enrollment details.</p>
                    <span class="inline-flex items-center gap-1 mt-3 text-[10px] font-bold text-red-600 uppercase tracking-widest"><svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"></path></svg> 1 Sheet</span>
                </a>

                <!-- 5. Gender Analysis -->
                <a href="export_report.php?report=gender_report" class="group bg-white border-2 border-slate-200 hover:border-pink-400 rounded-2xl p-6 transition-all hover:shadow-lg hover:-translate-y-0.5">
                    <div class="w-12 h-12 rounded-xl bg-pink-50 text-pink-600 flex items-center justify-center mb-4 group-hover:bg-pink-100 transition-colors">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"></path></svg>
                    </div>
                    <h3 class="font-black text-sm text-dranhs-dark uppercase tracking-wider mb-1">Gender Analysis</h3>
                    <p class="text-xs text-slate-400">Gender breakdown by grade level, G11 pathway, G12 strand, and student type.</p>
                    <span class="inline-flex items-center gap-1 mt-3 text-[10px] font-bold text-pink-600 uppercase tracking-widest"><svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"></path></svg> 4 Sheets</span>
                </a>
            </div>
        </div>

    </div>
</div>

<!-- ===================================== -->
<!-- MAP BUILDING MODAL -->
<!-- ===================================== -->
<!-- ANNEX ENTRY MODAL -->
<div id="annex-modal" class="fixed inset-0 z-[110] hidden items-center justify-center p-4" style="background:rgba(15,23,42,0.6);">
    <div class="bg-white rounded-2xl shadow-2xl w-full max-w-md overflow-hidden">        <div class="bg-indigo-600 px-6 py-5 flex items-center justify-between">
            <div>
                <p class="text-xs font-bold uppercase tracking-widest text-indigo-200 mb-0.5">Room Assignment</p>
                <h3 class="font-heading font-black text-xl text-white">Manual Annex Entry</h3>
            </div>
            <button type="button" id="annex-modal-close" class="w-9 h-9 rounded-full bg-white/10 text-white hover:bg-white/20 text-xl flex items-center justify-center">&times;</button>
        </div>
        <form method="POST" action="?page=system_settings" class="p-6 space-y-4">
            <input type="hidden" name="action" value="add_annex">
            <div>
                <label class="block text-xs font-bold uppercase tracking-wider text-slate-500 mb-2">Section Name</label>
                <!-- Dropdown mode (default) -->
                <select id="annex-section-select" name="annex_section"
                    class="w-full border-2 border-slate-200 rounded-xl px-4 py-3 text-sm font-semibold focus:border-indigo-500 outline-none bg-white">
                    <option value="">Select a section...</option>
                    <?php
                    $last_grade = '';
                    foreach ($annex_available_sections as $s):
                        $grade_label = 'Grade ' . $s['grade_level'];
                        if ($grade_label !== $last_grade):
                            if ($last_grade !== '') echo '</optgroup>';
                            echo '<optgroup label="' . htmlspecialchars($grade_label) . '">';
                            $last_grade = $grade_label;
                        endif;
                    ?>
                    <option value="<?php echo htmlspecialchars($s['name']); ?>"><?php echo htmlspecialchars($s['name']); ?></option>
                    <?php endforeach; ?>
                    <?php if ($last_grade !== '') echo '</optgroup>'; ?>
                </select>
                <!-- Manual text input (hidden by default) -->
                <input type="text" id="annex-section-manual"
                    placeholder="Type section name..."
                    class="hidden w-full border-2 border-indigo-300 rounded-xl px-4 py-3 text-sm font-semibold focus:border-indigo-500 outline-none mt-0">
                <!-- Toggle links -->
                <button type="button" id="annex-toggle-manual"
                    class="mt-1.5 text-[10px] font-bold text-indigo-400 hover:text-indigo-600 uppercase tracking-widest">
                    ✏ Not in list? Type it manually
                </button>
                <button type="button" id="annex-toggle-dropdown"
                    class="hidden mt-1.5 text-[10px] font-bold text-indigo-400 hover:text-indigo-600 uppercase tracking-widest">
                    ← Back to dropdown
                </button>
            </div>
            <div class="grid grid-cols-3 gap-3">
                <div>
                    <label class="block text-xs font-bold uppercase tracking-wider text-slate-500 mb-2">Building No.</label>
                    <input type="text" name="annex_building" required placeholder="e.g. 16"
                        class="w-full border-2 border-slate-200 rounded-xl px-3 py-3 text-sm font-semibold focus:border-indigo-500 outline-none">
                </div>
                <div>
                    <label class="block text-xs font-bold uppercase tracking-wider text-slate-500 mb-2">Floor</label>
                    <input type="text" name="annex_floor" required placeholder="e.g. 2"
                        class="w-full border-2 border-slate-200 rounded-xl px-3 py-3 text-sm font-semibold focus:border-indigo-500 outline-none">
                </div>
                <div>
                    <label class="block text-xs font-bold uppercase tracking-wider text-slate-500 mb-2">Room No.</label>
                    <input type="text" name="annex_room" required placeholder="e.g. 201"
                        class="w-full border-2 border-slate-200 rounded-xl px-3 py-3 text-sm font-semibold focus:border-indigo-500 outline-none">
                </div>
            </div>
            <div class="flex gap-3 pt-1">
                <button type="button" id="annex-modal-cancel" class="flex-1 px-4 py-2.5 rounded-xl border-2 border-slate-200 text-slate-600 text-sm font-bold hover:bg-slate-50">Cancel</button>
                <button type="submit" class="flex-1 px-4 py-2.5 rounded-xl bg-indigo-600 text-white text-sm font-bold hover:bg-indigo-700">Save Entry</button>
            </div>
        </form>
    </div>
</div>

<!-- FACILITY MODAL — name only, MAP button handles location -->
<div id="facility-modal" class="fixed inset-0 z-[110] hidden items-center justify-center p-4" style="background:rgba(15,23,42,0.6);">
    <div class="bg-white rounded-2xl shadow-2xl w-full max-w-md overflow-hidden">
        <div class="bg-amber-500 px-6 py-5 flex items-center justify-between">
            <div>
                <p class="text-xs font-bold uppercase tracking-widest text-amber-100 mb-0.5">Room Assignment</p>
                <h3 class="font-heading font-black text-xl text-white">Faculty / Laboratory</h3>
            </div>
            <button type="button" id="facility-modal-close" class="w-9 h-9 rounded-full bg-white/10 text-white hover:bg-white/20 text-xl flex items-center justify-center">&times;</button>
        </div>
        <form method="POST" action="?page=system_settings" class="p-6 space-y-4">
            <input type="hidden" name="action" value="add_facility">
            <div>
                <label class="block text-xs font-bold uppercase tracking-wider text-slate-500 mb-2">Facility Name</label>
                <input type="text" name="facility_name" required placeholder="e.g. SHS Faculty, Cookery Laboratory, ICT Lab"
                    class="w-full border-2 border-slate-200 rounded-xl px-4 py-3 text-sm font-semibold focus:border-amber-500 outline-none">
                <p class="text-xs text-slate-400 mt-1.5">After saving, use the Map button to assign a room location.</p>
            </div>
            <div class="flex gap-3 pt-1">
                <button type="button" id="facility-modal-cancel" class="flex-1 px-4 py-2.5 rounded-xl border-2 border-slate-200 text-slate-600 text-sm font-bold hover:bg-slate-50">Cancel</button>
                <button type="submit" class="flex-1 px-4 py-2.5 rounded-xl bg-amber-500 text-white text-sm font-bold hover:bg-amber-600">Save Facility</button>
            </div>
        </form>
    </div>
</div>

<!-- MAP BUILDING MODAL -->
<div id="map-modal" class="fixed inset-0 z-[100] bg-slate-900/40 backdrop-blur-sm hidden flex items-center justify-center p-4 lg:p-10 opacity-0 transition-opacity duration-300">
    <!-- Close wrapper -->
    <div class="absolute inset-0 cursor-pointer" onclick="closeMapModal()"></div>
    
    <div id="map-modal-content" class="bg-white rounded-3xl w-full max-w-5xl shadow-2xl relative z-10 transform scale-95 transition-transform duration-300 flex flex-col max-h-[90vh]">
        <!-- Header -->
        <div class="p-6 border-b border-slate-100 flex items-center justify-between shrink-0 bg-slate-50/50 rounded-t-3xl">
            <div>
                <h3 class="text-xl font-heading font-black text-dranhs-dark tracking-tight">Assign Room</h3>
                <p class="text-sm font-bold text-slate-500 uppercase tracking-widest" id="mapping-section-name">SECTION NAME</p>
            </div>
            
            <div class="flex gap-2 bg-slate-100 p-1 rounded-xl">
                <button onclick="switchBldg('14')" id="bldg-btn-14" class="bldg-btn active px-4 py-2 rounded-lg text-xs font-bold uppercase tracking-wider bg-white shadow-sm text-dranhs-dark transition-all">Bldg 14</button>
                <button onclick="switchBldg('15')" id="bldg-btn-15" class="bldg-btn px-4 py-2 rounded-lg text-xs font-bold uppercase tracking-wider text-slate-500 hover:text-slate-800 transition-all">Bldg 15</button>
            </div>
        </div>

        <!-- Scrollable Content -->
        <div class="p-6 lg:p-8 overflow-y-auto sidebar-scroll">
            <form id="assignRoomForm" method="POST" action="?page=system_settings">
                <input type="hidden" name="action" id="assign_action" value="assign_room">
                <input type="hidden" name="section_id" id="assign_section_id" value="">
                <input type="hidden" name="facility_id" id="assign_facility_id" value="">
                <input type="hidden" name="room" id="assign_room_number" value="">

                <!-- BLDG 14 VIEW (4 Floors, 4 Rooms = 41 to 56) -->
                <div id="bldg-view-14" class="bldg-view block">
                    <div class="flex items-center gap-2 mb-6 border-b-2 border-blue-200 pb-2 inline-flex">
                        <svg class="w-6 h-6 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"></path></svg>
                        <div>
                            <h2 class="text-xl font-black text-dranhs-dark tracking-widest uppercase">Senior High Complex (Bldg 14)</h2>
                            <p class="text-[10px] font-bold text-slate-400 uppercase tracking-[0.2em]">4 Levels &bull; Type: SHS Standard</p>
                        </div>
                    </div>

                    <div class="bg-blue-50/50 border border-blue-100 rounded-[2rem] p-6 lg:p-8 space-y-8 relative overflow-hidden">
                        <!-- Floor loops -->
                        <?php 
                        $floors_14 = [
                            4 => [53, 54, 55, 56],
                            3 => [49, 50, 51, 52],
                            2 => [45, 46, 47, 48],
                            1 => [41, 42, 43, 44]
                        ];
                        foreach($floors_14 as $f_num => $rooms):
                        ?>
                        <div class="flex flex-col lg:flex-row gap-4 items-start lg:items-center relative z-10 w-full">
                            <div class="w-full lg:w-24 shrink-0 text-xs font-black text-slate-400 uppercase tracking-[0.3em]">Floor <?php echo $f_num; ?></div>
                            <div class="flex-1 grid grid-cols-2 md:grid-cols-4 gap-4 lg:gap-6 w-full">
                                <?php foreach($rooms as $r): 
                                    $assignment = getRoomAssignment($r, $registries);
                                    $is_assigned = $assignment !== null;
                                ?>
                                <button type="button" onclick="assignRoom('<?php echo $r; ?>')" class="relative flex flex-col items-center justify-center p-4 rounded-xl border-2 transition-all <?php echo $is_assigned ? 'bg-emerald-50/50 border-emerald-200 hover:border-emerald-400 shadow-[0_0_15px_rgba(16,185,129,0.1)]' : 'bg-white border-white hover:border-blue-200 shadow-sm hover:shadow-md'; ?>">
                                    <span class="text-2xl font-black <?php echo $is_assigned ? 'text-emerald-700' : 'text-slate-200'; ?> tracking-tighter"><?php echo $r; ?></span>
                                    <?php if ($is_assigned): ?>
                                        <span class="text-[8px] font-black text-emerald-600 uppercase tracking-widest mt-1 truncate w-full text-center px-1"><?php echo htmlspecialchars($assignment['name']); ?></span>
                                    <?php endif; ?>
                                </button>
                                <?php endforeach; ?>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>

                <!-- BLDG 15 VIEW (4 Floors, 5 Rooms = 21 to 40) -->
                <div id="bldg-view-15" class="bldg-view hidden">
                    <div class="flex items-center justify-between mb-6 border-b-2 border-orange-200 pb-2">
                        <div class="flex items-center gap-2">
                            <svg class="w-6 h-6 text-orange-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"></path></svg>
                            <div>
                                <h2 class="text-xl font-black text-dranhs-dark tracking-widest uppercase">Senior High Complex (Bldg 15)</h2>
                                <p class="text-[10px] font-bold text-slate-400 uppercase tracking-[0.2em]">4 Levels &bull; Type: SHS Standard</p>
                            </div>
                        </div>
                        <div class="border border-orange-200 text-orange-600 bg-orange-50 px-3 py-1.5 rounded-lg text-[9px] font-bold flex items-center gap-1 uppercase tracking-widest hidden sm:flex">
                            <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                            Right-to-Left Sequence
                        </div>
                    </div>

                    <div class="bg-[#FFF8F1] border border-orange-100 rounded-[2rem] p-6 lg:p-8 space-y-8 relative overflow-hidden">
                        <?php 
                        $floors_15 = [
                            4 => [40, 39, 38, 37, 36],
                            3 => [35, 34, 33, 32, 31],
                            2 => [30, 29, 28, 27, 26],
                            1 => [25, 24, 23, 22, 21]
                        ];
                        foreach($floors_15 as $f_num => $rooms):
                        ?>
                        <div class="flex flex-col lg:flex-row gap-4 items-start lg:items-center relative w-full">
                            <div class="w-full lg:w-24 shrink-0 text-xs font-black text-slate-400 uppercase tracking-[0.3em]">Floor <?php echo $f_num; ?></div>
                            <div class="flex-1 grid grid-cols-2 md:grid-cols-5 gap-3 lg:gap-4 w-full">
                                <?php foreach($rooms as $r): 
                                    $assignment = getRoomAssignment($r, $registries);
                                    $is_assigned = $assignment !== null;
                                ?>
                                <button type="button" onclick="assignRoom('<?php echo $r; ?>')" class="relative flex flex-col items-center justify-center p-3 rounded-xl border-2 transition-all <?php echo $is_assigned ? 'bg-emerald-50/50 border-emerald-200 hover:border-emerald-400 shadow-[0_0_15px_rgba(16,185,129,0.1)]' : 'bg-white border-white hover:border-orange-200 shadow-sm hover:shadow-md'; ?>">
                                    <span class="text-2xl font-black <?php echo $is_assigned ? 'text-emerald-700' : 'text-slate-200'; ?> tracking-tighter"><?php echo $r; ?></span>
                                    <?php if ($is_assigned): ?>
                                        <span class="text-[8px] font-black text-emerald-600 uppercase tracking-widest mt-1 truncate w-full text-center px-1"><?php echo htmlspecialchars($assignment['name']); ?></span>
                                    <?php endif; ?>
                                </button>
                                <?php endforeach; ?>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>

            </form>
        </div>
        
        <!-- Footer -->
        <div class="p-6 border-t border-slate-100 flex justify-end shrink-0">
            <button type="button" onclick="closeMapModal()" class="text-slate-500 font-bold hover:text-slate-800 uppercase tracking-widest text-xs px-6 py-3 transition-colors">Cancel</button>
        </div>
    </div>
</div>

<!-- PRINT TEMPLATES TAB -->
<div id="tab-print-templates" class="tab-content hidden">
    <div class="max-w-2xl">
        <h2 class="text-2xl font-heading font-black text-dranhs-dark mb-1">Print Templates</h2>
        <p class="text-sm text-slate-500 mb-8">Manage the document templates used when printing enrollment forms. Upload a new <code class="bg-slate-100 px-1.5 py-0.5 rounded text-xs font-mono">.docx</code> file to replace the current template.</p>

        <!-- Current Template Status -->
        <div class="mb-6 p-5 rounded-2xl border <?php echo file_exists('../uploads/templates/BEEF-Temp.docx') ? 'bg-emerald-50 border-emerald-200' : 'bg-amber-50 border-amber-200'; ?>">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 rounded-xl flex items-center justify-center shrink-0 <?php echo file_exists('../uploads/templates/BEEF-Temp.docx') ? 'bg-emerald-100' : 'bg-amber-100'; ?>">
                    <svg class="w-5 h-5 <?php echo file_exists('../uploads/templates/BEEF-Temp.docx') ? 'text-emerald-600' : 'text-amber-600'; ?>" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                </div>
                <div>
                    <div class="text-sm font-black <?php echo file_exists('../uploads/templates/BEEF-Temp.docx') ? 'text-emerald-800' : 'text-amber-800'; ?>">
                        <?php echo file_exists('../uploads/templates/BEEF-Temp.docx') ? '✓ Enrollment Form — BEEF-Temp.docx' : '⚠ No enrollment template uploaded yet'; ?>
                    </div>
                    <?php if (file_exists('../uploads/templates/BEEF-Temp.docx')): ?>
                    <div class="text-xs text-emerald-600 mt-0.5">
                        Last modified: <?php echo date('F d, Y h:i A', filemtime('../uploads/templates/BEEF-Temp.docx')); ?>
                        &nbsp;·&nbsp;
                        Size: <?php echo round(filesize('../uploads/templates/BEEF-Temp.docx') / 1024, 1); ?> KB
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <div class="mb-6 p-5 rounded-2xl border <?php echo file_exists('../uploads/templates/ID-Temp.docx') ? 'bg-emerald-50 border-emerald-200' : 'bg-amber-50 border-amber-200'; ?>">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 rounded-xl flex items-center justify-center shrink-0 <?php echo file_exists('../uploads/templates/ID-Temp.docx') ? 'bg-emerald-100' : 'bg-amber-100'; ?>">
                    <svg class="w-5 h-5 <?php echo file_exists('../uploads/templates/ID-Temp.docx') ? 'text-emerald-600' : 'text-amber-600'; ?>" fill="none" stroke="currentColor" viewBox="0 0 24 24"><rect x="2" y="5" width="20" height="14" rx="2" stroke-width="2"/><path stroke-linecap="round" stroke-width="2" d="M7 9h10M7 13h6"/></svg>
                </div>
                <div>
                    <div class="text-sm font-black <?php echo file_exists('../uploads/templates/ID-Temp.docx') ? 'text-emerald-800' : 'text-amber-800'; ?>">
                        <?php echo file_exists('../uploads/templates/ID-Temp.docx') ? '✓ ID Card Template — ID-Temp.docx' : '⚠ No ID template uploaded yet'; ?>
                    </div>
                    <?php if (file_exists('../uploads/templates/ID-Temp.docx')): ?>
                    <div class="text-xs text-emerald-600 mt-0.5">
                        Last modified: <?php echo date('F d, Y h:i A', filemtime('../uploads/templates/ID-Temp.docx')); ?>
                        &nbsp;·&nbsp;
                        Size: <?php echo round(filesize('../uploads/templates/ID-Temp.docx') / 1024, 1); ?> KB
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Upload Forms — side by side -->
        <div class="grid grid-cols-1 md:grid-cols-2 gap-5 mb-8">
            <!-- BEEF Template Upload -->
            <form action="?page=system_settings" method="POST" enctype="multipart/form-data"
                  class="p-5 bg-white border-2 border-dashed border-slate-300 rounded-2xl hover:border-dranhs-green transition-colors">
                <input type="hidden" name="action" value="upload_template">
                <input type="hidden" name="template_type" value="beef">
                <p class="text-xs font-black uppercase tracking-widest text-slate-500 mb-3">Enrollment Form Template</p>
                <div class="flex flex-col gap-3">
                    <label class="cursor-pointer">
                        <span class="inline-flex items-center gap-2 px-4 py-2 rounded-xl bg-dranhs-green text-white text-xs font-bold hover:bg-emerald-700 transition-colors">
                            Choose BEEF-Temp.docx
                        </span>
                        <input type="file" name="template_file" accept=".docx" class="hidden" required onchange="this.nextElementSibling.textContent='📄 '+this.files[0].name; this.closest('form').querySelector('button').disabled=false;">
                    </label>
                    <span class="text-xs text-slate-400">No file chosen</span>
                    <button type="submit" disabled class="px-4 py-2 rounded-xl bg-slate-200 text-slate-400 text-xs font-bold cursor-not-allowed">Upload</button>
                </div>
            </form>

            <!-- ID Template Upload -->
            <form action="?page=system_settings" method="POST" enctype="multipart/form-data"
                  class="p-5 bg-white border-2 border-dashed border-slate-300 rounded-2xl hover:border-blue-400 transition-colors">
                <input type="hidden" name="action" value="upload_template">
                <input type="hidden" name="template_type" value="id">
                <p class="text-xs font-black uppercase tracking-widest text-slate-500 mb-3">ID Card Template</p>
                <div class="flex flex-col gap-3">
                    <label class="cursor-pointer">
                        <span class="inline-flex items-center gap-2 px-4 py-2 rounded-xl bg-blue-600 text-white text-xs font-bold hover:bg-blue-700 transition-colors">
                            Choose ID-Temp.docx
                        </span>
                        <input type="file" name="template_file" accept=".docx" class="hidden" required onchange="this.nextElementSibling.textContent='📄 '+this.files[0].name; this.closest('form').querySelector('button').disabled=false;">
                    </label>
                    <span class="text-xs text-slate-400">No file chosen</span>
                    <button type="submit" disabled class="px-4 py-2 rounded-xl bg-slate-200 text-slate-400 text-xs font-bold cursor-not-allowed">Upload</button>
                </div>
            </form>
        </div>

        <!-- Backups list -->
        <?php
        $backups = glob('../uploads/templates/BEEF-Temp_backup_*.docx') ?: [];
        rsort($backups); // newest first
        ?>
        <?php if (!empty($backups)): ?>
        <div class="mt-6">
            <h3 class="text-xs font-black uppercase tracking-widest text-slate-500 mb-3">Previous Backups</h3>
            <div class="space-y-2">
                <?php foreach (array_slice($backups, 0, 5) as $bk): ?>
                <div class="flex items-center justify-between px-4 py-3 bg-slate-50 border border-slate-200 rounded-xl">
                    <div class="flex items-center gap-3">
                        <svg class="w-4 h-4 text-slate-400 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                        <span class="text-xs font-semibold text-slate-600"><?php echo htmlspecialchars(basename($bk)); ?></span>
                    </div>
                    <span class="text-xs text-slate-400"><?php echo date('M d, Y h:i A', filemtime($bk)); ?></span>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>

        <!-- Placeholder reference -->
        <div class="mt-8 p-5 bg-slate-50 border border-slate-200 rounded-2xl">
            <h3 class="text-xs font-black uppercase tracking-widest text-slate-500 mb-3">Placeholder Reference</h3>
            <p class="text-xs text-slate-500 mb-3">Use these exact strings in your Word template. PHPWord will replace them with student data on download.</p>
            <div class="grid grid-cols-2 gap-x-6 gap-y-1 text-xs font-mono">
                <?php
                $placeholders = [
                    '${STUDENT_TYPE}' => 'Learner Category',
                    '${LRN}' => 'LRN',
                    '${GRADE_LEVEL}' => 'Grade Level',
                    '${SCHOOL_YEAR}' => 'School Year',
                    '${LAST_NAME}' => 'Last Name',
                    '${FIRST_NAME}' => 'First Name',
                    '${MIDDLE_NAME}' => 'Middle Name',
                    '${EXTENSION_NAME}' => 'Ext. Name',
                    '${SEX}' => 'Sex (Male / Female text)',
                    '${BIRTHDATE}' => 'Birthdate',
                    '${AGE}' => 'Age',
                    '${PLACE_OF_BIRTH}' => 'Place of Birth',
                    '${MOTHER_TONGUE}' => 'Mother Tongue',
                    '${RELIGION}' => 'Religion',
                    '${IP_COMMUNITY}' => '☑/☐ IP Community',
                    '${IP_SPECIFY}' => 'IP Community (specify)',
                    '${FAMILY_4PS}' => '☑/☐ 4Ps',
                    '${FPS_ID}' => '4Ps Household ID',
                    '${STREET}' => 'Street',
                    '${BARANGAY}' => 'Barangay',
                    '${CITY}' => 'City',
                    '${PROVINCE}' => 'Province',
                    '${ZIP_CODE}' => 'Zip Code',
                    '${LIVING_WITH}' => 'Living With',
                    '${FATHER_LAST_NAME}' => 'Father Last Name',
                    '${FATHER_FIRST_NAME}' => 'Father First Name',
                    '${FATHER_CONTACT}' => 'Father Contact',
                    '${MOTHER_LAST_NAME}' => 'Mother Last Name',
                    '${MOTHER_FIRST_NAME}' => 'Mother First Name',
                    '${MOTHER_CONTACT}' => 'Mother Contact',
                    '${GUARDIAN_LAST_NAME}' => 'Guardian Last Name',
                    '${GUARDIAN_CONTACT}' => 'Guardian Contact',
                    '${SPED}' => '☑/☐ SPED',
                    '${PWD}' => '☑/☐ PWD',
                    '${SEMESTER}' => 'Semester',
                    '${TRACK}' => 'Track',
                    '${PATHWAY_STRAND}' => 'Pathway/Strand',
                    '${FULL_NAME}' => 'Full Name',
                    '${ID_PHOTO}' => 'ID Photo (image)',
                    '${QR_CODE}' => 'QR Code (image)',
                ];
                foreach ($placeholders as $ph => $label):
                ?>
                <div class="flex items-center gap-2 py-0.5">
                    <code class="text-blue-600 bg-blue-50 px-1 rounded"><?php echo htmlspecialchars($ph); ?></code>
                    <span class="text-slate-500"><?php echo htmlspecialchars($label); ?></span>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
</div>


<div id="confirm-action-modal" class="fixed inset-0 z-[120] hidden items-center justify-center p-4" style="background:rgba(15,23,42,0.7);backdrop-filter:blur(6px);">
    <div id="confirm-action-modal-panel" class="w-full max-w-md rounded-[2rem] bg-white shadow-2xl border border-slate-200 overflow-hidden transform scale-95 opacity-0 transition-all duration-200">
        <div class="px-6 py-5 bg-gradient-to-r from-rose-500 to-orange-400 text-white">
            <div class="flex items-start gap-4">
                <div class="w-11 h-11 rounded-2xl bg-white/15 flex items-center justify-center shrink-0">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.2" d="M12 8v4m0 4h.01M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z"></path></svg>
                </div>
                <div>
                    <p class="text-[11px] font-black uppercase tracking-[0.25em] text-white/80">Confirm Action</p>
                    <h3 id="confirm-action-title" class="text-xl font-heading font-black mt-1">Please Confirm</h3>
                </div>
            </div>
        </div>
        <div class="px-6 py-6">
            <p id="confirm-action-message" class="text-sm leading-relaxed text-slate-600 font-semibold">Are you sure you want to continue?</p>
        </div>
        <div class="px-6 pb-6 flex items-center justify-end gap-3">
            <button type="button" id="confirm-action-cancel" class="px-4 py-2.5 rounded-xl border border-slate-200 text-slate-600 text-sm font-bold hover:bg-slate-50 transition-colors">Cancel</button>
            <button type="button" id="confirm-action-submit" class="px-5 py-2.5 rounded-xl bg-rose-500 text-white text-sm font-black uppercase tracking-widest hover:bg-rose-600 shadow-lg shadow-rose-500/20 transition-colors">Confirm</button>
        </div>
    </div>
</div>

<script>
    // Tab switching
    function switchTab(tabId) {
        document.querySelectorAll('.tab-content').forEach(el => {
            el.classList.add('hidden');
            el.classList.remove('block');
        });
        document.querySelectorAll('.tab-btn').forEach(el => {
            el.classList.remove('text-dranhs-green', 'border-b-2', 'border-dranhs-green', 'bg-white');
            el.classList.add('text-slate-500', 'bg-transparent');
        });

        document.getElementById('tab-' + tabId).classList.remove('hidden');
        document.getElementById('tab-' + tabId).classList.add('block');
        
        const activeBtn = document.getElementById('tab-btn-' + tabId);
        activeBtn.classList.add('text-dranhs-green', 'border-b-2', 'border-dranhs-green', 'bg-white');
        activeBtn.classList.remove('text-slate-500', 'bg-transparent');
        
        localStorage.setItem('activeSettingsTab', tabId);
    }
    
    // File Input Behavior for Advisers
    const avatarInput = document.querySelector('input[name="avatar"]');
    if (avatarInput) {
        avatarInput.addEventListener('change', function() {
            if(this.files && this.files.length > 0) {
                // Change UI to reflect file selected
                this.parentElement.classList.add('bg-dranhs-green', 'text-white', 'border-dranhs-green');
                this.parentElement.classList.remove('bg-slate-100', 'text-slate-500', 'border-slate-300');
            }
        });
    }

    // Modal behavior
    const mapModal = document.getElementById('map-modal');
    const mapContent = document.getElementById('map-modal-content');
    const confirmActionModal = document.getElementById('confirm-action-modal');
    const confirmActionPanel = document.getElementById('confirm-action-modal-panel');
    const confirmActionMessage = document.getElementById('confirm-action-message');
    const confirmActionCancel = document.getElementById('confirm-action-cancel');
    const confirmActionSubmit = document.getElementById('confirm-action-submit');
    let pendingConfirmForm = null;

    function openConfirmActionModal(form) {
        pendingConfirmForm = form;
        if (confirmActionMessage) {
            confirmActionMessage.textContent = form.dataset.confirmMessage || 'Are you sure you want to continue?';
        }
        confirmActionModal.classList.remove('hidden');
        confirmActionModal.classList.add('flex');
        requestAnimationFrame(() => {
            confirmActionPanel.classList.remove('scale-95', 'opacity-0');
        });
    }

    function closeConfirmActionModal() {
        pendingConfirmForm = null;
        confirmActionPanel.classList.add('scale-95', 'opacity-0');
        setTimeout(() => {
            confirmActionModal.classList.add('hidden');
            confirmActionModal.classList.remove('flex');
        }, 200);
    }
    
    function openFacilityMapModal(facilityId, facilityName) {
        // Reuse the same building map modal, switch to facility mode
        document.getElementById('assign_action').value = 'assign_facility_room';
        document.getElementById('assign_facility_id').value = facilityId;
        document.getElementById('assign_section_id').value = '';
        document.getElementById('mapping-section-name').textContent = facilityName;
        mapModal.classList.remove('hidden');
        mapModal.classList.add('flex');
        mapModal.classList.remove('opacity-0');
        mapContent.classList.remove('scale-95');
        setTimeout(() => {
            mapModal.classList.remove('opacity-0');
            mapContent.classList.remove('scale-95');
        }, 10);
    }

    function openMapModal(sectionId, sectionName) {
        // Section mode
        document.getElementById('assign_action').value = 'assign_room';
        document.getElementById('assign_section_id').value = sectionId;
        document.getElementById('assign_facility_id').value = '';
        document.getElementById('mapping-section-name').textContent = sectionName;        
        mapModal.classList.remove('hidden');
        // trigger reflow
        void mapModal.offsetWidth;
        mapModal.classList.remove('opacity-0');
        mapContent.classList.remove('scale-95');
    }

    function closeMapModal() {
        mapModal.classList.add('opacity-0');
        mapContent.classList.add('scale-95');
        setTimeout(() => { mapModal.classList.add('hidden'); }, 300);
    }

    // Bldg Switching inside Modal
    function switchBldg(b_id) {
        document.querySelectorAll('.bldg-view').forEach(el => { el.classList.add('hidden'); el.classList.remove('block'); });
        document.querySelectorAll('.bldg-btn').forEach(el => { 
            el.classList.remove('bg-white', 'shadow-sm', 'text-dranhs-dark');
            el.classList.add('text-slate-500');
        });

        document.getElementById('bldg-view-' + b_id).classList.remove('hidden');
        document.getElementById('bldg-view-' + b_id).classList.add('block');
        
        const activeBtn = document.getElementById('bldg-btn-' + b_id);
        activeBtn.classList.add('bg-white', 'shadow-sm', 'text-dranhs-dark');
        activeBtn.classList.remove('text-slate-500');
    }

    // Assigning Room
    function assignRoom(roomNumber) {
        document.getElementById('assign_room_number').value = roomNumber;
        document.getElementById('assignRoomForm').submit();
    }

    document.addEventListener('DOMContentLoaded', () => {
        const savedTab = localStorage.getItem('activeSettingsTab');
        if (savedTab) {
            switchTab(savedTab);
        }

        document.querySelectorAll('.confirm-action-form').forEach((form) => {
            form.addEventListener('submit', (event) => {
                if (form.dataset.confirmed === 'true') {
                    form.dataset.confirmed = 'false';
                    return;
                }
                event.preventDefault();
                openConfirmActionModal(form);
            });
        });

        // Annex modal
        const annexModal   = document.getElementById('annex-modal');
        const facilityModal = document.getElementById('facility-modal');

        if (confirmActionCancel) {
            confirmActionCancel.addEventListener('click', closeConfirmActionModal);
        }

        if (confirmActionModal) {
            confirmActionModal.addEventListener('click', (event) => {
                if (event.target === confirmActionModal) {
                    closeConfirmActionModal();
                }
            });
        }

        if (confirmActionSubmit) {
            confirmActionSubmit.addEventListener('click', () => {
                if (!pendingConfirmForm) return;
                pendingConfirmForm.dataset.confirmed = 'true';
                const formToSubmit = pendingConfirmForm;
                closeConfirmActionModal();
                formToSubmit.requestSubmit();
            });
        }

        document.addEventListener('keydown', (event) => {
            if (event.key === 'Escape' && confirmActionModal && !confirmActionModal.classList.contains('hidden')) {
                closeConfirmActionModal();
            }
        });

        document.getElementById('open-annex-modal').addEventListener('click', () => {
            annexModal.classList.remove('hidden');
            annexModal.classList.add('flex');
        });
        document.getElementById('annex-modal-close').addEventListener('click', () => {
            annexModal.classList.add('hidden');
            annexModal.classList.remove('flex');
        });
        document.getElementById('annex-modal-cancel').addEventListener('click', () => {
            annexModal.classList.add('hidden');
            annexModal.classList.remove('flex');
        });

        // Annex section toggle: dropdown ↔ manual text
        const annexSelect  = document.getElementById('annex-section-select');
        const annexManual  = document.getElementById('annex-section-manual');
        const toggleManual = document.getElementById('annex-toggle-manual');
        const toggleDrop   = document.getElementById('annex-toggle-dropdown');

        toggleManual.addEventListener('click', () => {
            annexSelect.removeAttribute('required');
            annexSelect.classList.add('hidden');
            annexManual.setAttribute('required', 'required');
            annexManual.classList.remove('hidden');
            toggleManual.classList.add('hidden');
            toggleDrop.classList.remove('hidden');
            // Sync value: manual input drives annex_section on submit
            annexManual.name = 'annex_section';
            annexSelect.name = '';
        });
        toggleDrop.addEventListener('click', () => {
            annexManual.removeAttribute('required');
            annexManual.classList.add('hidden');
            annexSelect.setAttribute('required', 'required');
            annexSelect.classList.remove('hidden');
            toggleDrop.classList.add('hidden');
            toggleManual.classList.remove('hidden');
            annexSelect.name = 'annex_section';
            annexManual.name = '';
        });

        document.getElementById('open-facility-modal').addEventListener('click', () => {
            facilityModal.classList.remove('hidden');
            facilityModal.classList.add('flex');
        });
        document.getElementById('facility-modal-close').addEventListener('click', () => {
            facilityModal.classList.add('hidden');
            facilityModal.classList.remove('flex');
        });
        document.getElementById('facility-modal-cancel').addEventListener('click', () => {
            facilityModal.classList.add('hidden');
            facilityModal.classList.remove('flex');
        });

        // Template file input — show filename + enable submit
        const tplInput = document.getElementById('template-file-input');
        const tplSubmit = document.getElementById('upload-submit-btn');
        const fileSelected = document.getElementById('file-selected');
        if (tplInput) {
            tplInput.addEventListener('change', function () {
                if (this.files && this.files.length > 0) {
                    const fname = this.files[0].name;
                    fileSelected.textContent = '📄 ' + fname;
                    fileSelected.classList.remove('hidden');
                    tplSubmit.disabled = false;
                    tplSubmit.classList.remove('bg-slate-200', 'text-slate-400', 'cursor-not-allowed');
                    tplSubmit.classList.add('bg-dranhs-green', 'text-white', 'hover:bg-emerald-700', 'cursor-pointer');
                }
            });
        }

        const toggleCheck = document.querySelector('input[name="enrollment_status"]');
        if (toggleCheck) {
            toggleCheck.addEventListener('change', function() {
                const label = this.parentElement.querySelector('span');
                label.textContent = this.checked ? 'UNLOCKED' : 'LOCKED';
                if(this.checked) {
                    label.classList.add('text-emerald-600');
                    label.classList.remove('text-slate-700');
                } else {
                    label.classList.remove('text-emerald-600');
                    label.classList.add('text-slate-700');
                }
            });
        }
    });
</script>
