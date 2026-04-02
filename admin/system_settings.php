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
            
            // Clear prior assignments to this room
            $clear = $conn->prepare("UPDATE add_sections SET room = NULL WHERE room = ?");
            $clear->bind_param("s", $room);
            $clear->execute();
            
            // Assign room to section
            $stmt = $conn->prepare("UPDATE add_sections SET room = ? WHERE id = ?");
            $stmt->bind_param("si", $room, $section_id);
            $stmt->execute();
            $stmt->close();
            
            $toast_message = 'Room successfully assigned!';
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
        <button class="px-6 py-4 font-bold text-sm text-slate-400 cursor-not-allowed shrink-0 flex items-center gap-2">Theme <span class="bg-slate-200 text-slate-500 text-[10px] px-1.5 py-0.5 rounded-md uppercase tracking-wide">Soon</span></button>
        <button class="px-6 py-4 font-bold text-sm text-slate-400 cursor-not-allowed shrink-0 flex items-center gap-2">Curriculum <span class="bg-slate-200 text-slate-500 text-[10px] px-1.5 py-0.5 rounded-md uppercase tracking-wide">Soon</span></button>
        <button class="px-6 py-4 font-bold text-sm text-slate-400 cursor-not-allowed shrink-0 flex items-center gap-2">Data Hub <span class="bg-slate-200 text-slate-500 text-[10px] px-1.5 py-0.5 rounded-md uppercase tracking-wide">Soon</span></button>
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
                    'g10_sections' => ['title' => 'Grade 10 Directory', 'color' => 'emerald'],
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
