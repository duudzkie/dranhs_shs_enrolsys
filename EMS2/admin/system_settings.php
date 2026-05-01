<?php
if (basename($_SERVER['PHP_SELF']) == basename(__FILE__)) {
    header('Location: admin.php');
    exit;
}

// Ensure upload directory exists
$upload_dir = '../uploads/advisers/';
if (!is_dir($upload_dir)) {
    mkdir($upload_dir, 0755, true);
}

// Database Connection
$db_host = 'localhost';
$db_user = 'root';
$db_pass = '';
$db_name = 'dranhswin';
$conn = new mysqli($db_host, $db_user, $db_pass, $db_name);

$toast_message = '';
$toast_type = '';

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
            $toast_message = 'Main settings updated successfully!';
            $toast_type = 'success';
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

            $toast_message = 'Room successfully assigned!';
            $toast_type = 'success';
        }
        elseif ($_POST['action'] === 'save_curriculum') {
            $vis = isset($_POST['cv']) ? $_POST['cv'] : [];
            
            // Process cv payload from complex array structure
            foreach ($vis as $track => &$pathways) {
                foreach ($pathways as $id => &$p) {
                    $p['enabled'] = isset($p['enabled']) ? true : false;
                }
            }
            // Re-index arrays to ensure JSON encodes them as arrays instead of objects
            $final_structure = [
                'Academic' => array_values($vis['Academic'] ?? []),
                'Tech-Pro' => array_values($vis['Tech-Pro'] ?? []),
                'ALS'      => array_values($vis['ALS'] ?? [])
            ];
            
            $vis_json = json_encode($final_structure);
            
            $stmt = $conn->prepare("UPDATE system_settings SET setting_value = ? WHERE setting_key = 'curriculum_structure'");
            $stmt->bind_param("s", $vis_json);
            $stmt->execute();
            if ($stmt->affected_rows === 0) {
                $check = $conn->query("SELECT setting_key FROM system_settings WHERE setting_key = 'curriculum_structure'");
                if ($check->num_rows === 0) {
                    $insert = $conn->prepare("INSERT INTO system_settings (setting_key, setting_value) VALUES ('curriculum_structure', ?)");
                    $insert->bind_param("s", $vis_json);
                    $insert->execute();
                    $insert->close();
                }
            }
            $stmt->close();
            
            $toast_message = 'Curriculum Matrix updated!';
            $toast_type = 'success';
        }
        elseif ($_POST['action'] === 'add_registry') {
            $cat = $_POST['category'];
            $name = trim(strtoupper($_POST['name']));
            
            if (!empty($name)) {
                if ($cat === 'faculty_advisers') {
                    $avatarVal = NULL;
                    
                    // Handle file upload for adviser
                    if (isset($_FILES['avatar']) && $_FILES['avatar']['error'] === UPLOAD_ERR_OK) {
                        $tmp_name = $_FILES['avatar']['tmp_name'];
                        $fname = basename($_FILES['avatar']['name']);
                        $ext = strtolower(pathinfo($fname, PATHINFO_EXTENSION));
                        if (in_array($ext, ['jpg', 'jpeg', 'png', 'webp'])) {
                            $new_fname = uniqid('adv_') . '.' . $ext;
                            if (move_uploaded_file($tmp_name, $upload_dir . $new_fname)) {
                                $avatarVal = 'uploads/advisers/' . $new_fname;
                            }
                        }
                    }

                    $stmt = $conn->prepare("INSERT INTO advisers_accounts (name, avatar) VALUES (?, ?)");
                    $stmt->bind_param("ss", $name, $avatarVal);
                    $stmt->execute();
                    $stmt->close();
                    $toast_message = 'Adviser added!';
                    $toast_type = 'success';
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
        elseif ($_POST['action'] === 'retrieve_student') {
            $sid = intval($_POST['student_id'] ?? 0);
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
                // Delete adviser from advisers_accounts
                $q = $conn->prepare("SELECT avatar FROM advisers_accounts WHERE id = ?");
                $q->bind_param("i", $id);
                $q->execute();
                $r = $q->get_result();
                if ($r && $row = $r->fetch_assoc()) {
                    if ($row['avatar'] && file_exists('../' . $row['avatar'])) {
                        unlink('../' . $row['avatar']);
                    }
                }
                $q->close();

                $stmt = $conn->prepare("DELETE FROM advisers_accounts WHERE id = ?");
                $stmt->bind_param("i", $id);
                $stmt->execute();
                $stmt->close();
                $toast_message = 'Adviser deleted.';
                $toast_type = 'success';
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
}

// Fetch Settings
$settings = [];
$res = $conn->query("SELECT setting_key, setting_value FROM system_settings");
if ($res) {
    while($row = $res->fetch_assoc()) {
        $settings[$row['setting_key']] = $row['setting_value'];
    }
}

// Fetch withdrawn students
$withdrawn_students = [];
$wr = $conn->query("SELECT id, lrn, last_name, first_name, middle_name, extension_name, grade_level, track, pathway_strand, created_at FROM students WHERE enrollment_status = 'withdrawn' ORDER BY last_name, first_name");
if ($wr) { while ($r = $wr->fetch_assoc()) $withdrawn_students[] = $r; $wr->close(); }

// Fetch Advisers and Sections
$registries = [
    'faculty_advisers' => [],
    'g10_sections' => [],
    'g11_sections' => [],
    'g12_sections' => []
];

// Fetch advisers from advisers_accounts table
$adv_res = $conn->query("SELECT id, name, avatar, created_at FROM advisers_accounts ORDER BY name ASC");
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
    <div id="toast" class="absolute top-4 right-4 z-50 bg-emerald-100 border border-emerald-400 text-emerald-800 px-4 py-3 rounded-lg shadow-lg flex items-center gap-3 animate-bounce">
        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>
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
        <button class="px-6 py-4 font-bold text-sm text-slate-400 cursor-not-allowed shrink-0 flex items-center gap-2">Theme <span class="bg-slate-200 text-slate-500 text-[10px] px-1.5 py-0.5 rounded-md uppercase tracking-wide">Soon</span></button>
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
                        <label class="text-sm font-bold text-slate-700 uppercase tracking-widest">Active Semester</label>
                        <select name="active_semester" class="w-full bg-white border border-slate-300 px-4 py-3 rounded-lg text-slate-800 font-bold focus:border-dranhs-green focus:ring-2 focus:ring-dranhs-green/20 outline-none">
                            <option value="1st" <?php echo (($settings['active_semester']??'')=='1st')?'selected':''; ?>>1st Semester</option>
                            <option value="2nd" <?php echo (($settings['active_semester']??'')=='2nd')?'selected':''; ?>>2nd Semester</option>
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
                                
                                <form action="?page=system_settings" method="POST" class="shrink-0" onsubmit="return confirm('Are you sure you want to delete this item?');">
                                    <input type="hidden" name="action" value="delete_registry">
                                    <input type="hidden" name="id" value="<?php echo $item['id']; ?>">
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
                <!-- Manual entry is dummy for now as per design -->
                <button class="bg-indigo-600 hover:bg-indigo-700 text-white font-bold text-xs px-6 py-3 rounded-xl uppercase tracking-widest shadow-lg shadow-indigo-600/30 transition-transform hover:-translate-y-0.5 flex items-center gap-2">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16m-7 6h7"></path></svg>
                    Manual Annex Entry
                </button>
            </div>

            <div class="grid grid-cols-1 lg:grid-cols-2 gap-x-12 gap-y-8">
                <?php 
                $dirs = [
                    'g11_sections' => ['title' => 'Grade 11 Directory', 'color' => 'blue'],
                    'g12_sections' => ['title' => 'Grade 12 Directory', 'color' => 'pink']
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
            </div>

        </div> <!-- END TAB ROOM ASSIGNMENT -->

        <!-- CURRICULUM TAB -->
        <div id="tab-curriculum" class="tab-content hidden">
            <div class="flex items-center justify-between mb-8">
                <div class="flex flex-col gap-1">
                    <div class="flex items-center gap-2">
                        <svg class="w-6 h-6 text-violet-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"></path></svg>
                        <h2 class="text-2xl font-heading font-black text-dranhs-dark uppercase tracking-tight">Curriculum Visibility Mapping</h2>
                    </div>
                    <p class="text-sm font-bold text-slate-400 tracking-widest uppercase">Select which career pathways are active for Grade 11</p>
                </div>
            </div>

            <?php
            $curr_vis_saved = $settings['curriculum_structure'] ?? null;
            $curriculum_configured = ($curr_vis_saved !== null);
            $curr_vis = $curriculum_configured ? json_decode($curr_vis_saved, true) : [];
            
            $all_pathways = [
                'Academic' => $curr_vis['Academic'] ?? [],
                'Tech-Pro' => $curr_vis['Tech-Pro'] ?? [],
                'ALS' => $curr_vis['ALS'] ?? []
            ];
            
            $track_colors = [
                'Academic' => 'emerald',
                'Tech-Pro' => 'orange',
                'ALS' => 'rose'
            ];
            ?>

            <form action="?page=system_settings" method="POST" class="space-y-8" id="curriculumForm">
                <input type="hidden" name="action" value="save_curriculum">
                
                <div class="grid grid-cols-1 xl:grid-cols-3 gap-6">
                    <?php foreach ($all_pathways as $track => $path): $color = $track_colors[$track]; ?>
                    <div class="bg-white border border-slate-200 rounded-2xl p-6 shadow-sm flex flex-col h-full" id="track-container-<?php echo $track; ?>">
                        <div class="border-b-2 <?php echo 'border-'.$color.'-200'; ?> mb-4 pb-2 flex justify-between items-center shrink-0">
                            <h3 class="text-sm font-black uppercase tracking-[0.1em] <?php echo 'text-'.$color.'-600'; ?> flex items-center gap-2">
                                <span class="w-2 h-2 rounded-full <?php echo 'bg-'.$color.'-500'; ?> shrink-0"></span>
                                <?php echo htmlspecialchars($track); ?> Track
                            </h3>
                            <button type="button" onclick="addPathwayRow('<?php echo htmlspecialchars($track, ENT_QUOTES); ?>', '<?php echo $color; ?>')" class="text-[10px] font-bold uppercase tracking-widest text-slate-400 hover:text-<?php echo $color; ?>-600 transition-colors flex items-center gap-1 bg-slate-50 hover:bg-<?php echo $color; ?>-50 px-2 py-1 rounded-md">
                                <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M12 4v16m8-8H4"></path></svg> Add
                            </button>
                        </div>
                        <div class="space-y-3 lg:max-h-[300px] overflow-y-auto sidebar-scroll pr-2 flex-grow" id="list-<?php echo htmlspecialchars($track, ENT_QUOTES); ?>">
                            <?php foreach ($path as $idx => $pathway): 
                                $uid = uniqid(); 
                                $escaped_track = htmlspecialchars($track, ENT_QUOTES);
                                $checked = (!empty($pathway['enabled'])) ? 'checked' : '';
                            ?>
                            <div class="flex items-center group bg-white border border-slate-100 p-2 rounded-xl transition-all hover:border-slate-300 gap-2 relative">
                                <!-- Hidden input for icon -->
                                <input type="hidden" name="cv[<?php echo $escaped_track; ?>][<?php echo $uid; ?>][icon]" value="<?php echo htmlspecialchars($pathway['icon'] ?? '', ENT_QUOTES); ?>">
                                <!-- Name Input -->
                                <input type="text" name="cv[<?php echo $escaped_track; ?>][<?php echo $uid; ?>][name]" value="<?php echo htmlspecialchars($pathway['name'] ?? '', ENT_QUOTES); ?>" class="flex-1 text-xs font-bold text-slate-700 uppercase tracking-wide bg-transparent border-none outline-none focus:ring-2 focus:ring-<?php echo $color; ?>-200 rounded px-1 w-full truncate" required>
                                
                                <div class="flex items-center gap-3 shrink-0">
                                    <label class="relative inline-flex items-center cursor-pointer" title="Toggle Visibility">
                                        <input type="checkbox" name="cv[<?php echo $escaped_track; ?>][<?php echo $uid; ?>][enabled]" value="1" class="sr-only peer" <?php echo $checked; ?>>
                                        <div class="w-8 h-4 bg-slate-200 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-slate-300 after:border after:rounded-full after:h-3 after:w-3 after:transition-all <?php echo 'peer-checked:bg-'.$color.'-500'; ?>"></div>
                                    </label>
                                    <button type="button" onclick="this.closest('.group').remove()" class="text-slate-300 hover:text-red-500 transition-colors p-1 rounded-md hover:bg-red-50" title="Delete Pathway">
                                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path></svg>
                                    </button>
                                </div>
                            </div>
                            <?php endforeach; ?>
                            <?php if(empty($path)): ?>
                            <div class="text-[10px] uppercase tracking-widest text-slate-400 font-bold text-center py-4 border-2 border-dashed border-slate-200 rounded-xl empty-msg">No pathways added</div>
                            <?php endif; ?>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>

                <div class="flex justify-end pt-4 border-t border-slate-100 mt-6">
                    <button type="submit" class="bg-dranhs-dark hover:bg-slate-800 text-white font-bold py-3 px-8 rounded-lg shadow-md transition-transform hover:-translate-y-0.5 tracking-wider uppercase text-sm">Save Curriculum Matrix</button>
                </div>
            </form>

            <script>
            function addPathwayRow(track, color) {
                const list = document.getElementById('list-' + track);
                const uid = 'new_' + Math.random().toString(36).substr(2, 9);
                // Default generic book icon
                const defaultIcon = '<path d="M4 19.5A2.5 2.5 0 0 1 6.5 17H20"></path><path d="M6.5 2H20v20H6.5A2.5 2.5 0 0 1 4 19.5v-15A2.5 2.5 0 0 1 6.5 2z"></path>';
                
                // Clear the "No pathways added" message
                const emptyMsg = list.querySelector('.empty-msg');
                if(emptyMsg) emptyMsg.remove();

                const rowHtml = `
                    <div class="flex items-center group bg-blue-50 border border-blue-200 p-2 rounded-xl transition-all hover:border-blue-400 gap-2 relative animate-pulse" style="animation-iteration-count: 2;">
                        <input type="hidden" name="cv[${track}][${uid}][icon]" value='${defaultIcon}'>
                        <input type="text" name="cv[${track}][${uid}][name]" placeholder="Enter Pathway Name..." class="flex-1 text-xs font-black text-slate-800 uppercase tracking-wide bg-transparent border-none outline-none focus:ring-2 focus:ring-${color}-300 rounded px-1 w-full" required autofocus>
                        
                        <div class="flex items-center gap-3 shrink-0">
                            <label class="relative inline-flex items-center cursor-pointer" title="Toggle Visibility">
                                <input type="checkbox" name="cv[${track}][${uid}][enabled]" value="1" class="sr-only peer" checked>
                                <div class="w-8 h-4 bg-slate-200 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-slate-300 after:border after:rounded-full after:h-3 after:w-3 after:transition-all peer-checked:bg-${color}-500"></div>
                            </label>
                            <button type="button" onclick="this.closest('.group').remove()" class="text-slate-400 hover:text-red-500 transition-colors p-1 rounded-md hover:bg-red-50">
                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path></svg>
                            </button>
                        </div>
                    </div>
                `;
                list.insertAdjacentHTML('beforeend', rowHtml);
                const input = list.lastElementChild.querySelector('input[type="text"]');
                if (input) input.focus();
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
                <input type="hidden" name="action" value="assign_room">
                <input type="hidden" name="section_id" id="assign_section_id" value="">
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
    
    function openMapModal(sectionId, sectionName) {
        document.getElementById('assign_section_id').value = sectionId;
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
