<?php
require_once __DIR__ . '/../pathway_strand_catalog.php';

$db_host = 'localhost'; $db_user = 'root'; $db_pass = ''; $db_name = 'dranhswin';
$conn = new mysqli($db_host, $db_user, $db_pass, $db_name);

$classrooms = [];
$advisers = [];
$db_error = '';
$toast_message = '';
$toast_type = 'success';
$isAdviser = (($_SESSION['role'] ?? '') === 'adviser');
$adviserSection = $_SESSION['adviser_section'] ?? null;

// Ensure classrooms table exists
if (!$conn->connect_error) {
    $conn->query("CREATE TABLE IF NOT EXISTS classrooms (
        id INT AUTO_INCREMENT PRIMARY KEY,
        grade_level VARCHAR(20) NOT NULL,
        track VARCHAR(50),
        pathway_strand VARCHAR(100),
        section_name VARCHAR(100) NOT NULL,
        adviser_id INT NULL,
        adviser_name VARCHAR(150) NULL,
        max_capacity INT NOT NULL DEFAULT 40,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )");
    $conn->query("ALTER TABLE classrooms ADD COLUMN IF NOT EXISTS adviser_id INT NULL AFTER section_name");
    $conn->query("ALTER TABLE classrooms ADD COLUMN IF NOT EXISTS adviser_name VARCHAR(150) NULL AFTER adviser_id");
    $conn->query("ALTER TABLE students ADD COLUMN IF NOT EXISTS assigned_section VARCHAR(100)");
    // Add group_chat_url safely — check first to support MySQL 5.7
    $col_check = $conn->query("SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA='dranhswin' AND TABLE_NAME='classrooms' AND COLUMN_NAME='group_chat_url'");
    if ($col_check && $col_check->num_rows === 0) {
        $conn->query("ALTER TABLE classrooms ADD COLUMN group_chat_url VARCHAR(500) NULL");
    }
}

$catalog_for_js = [
    'Grade 11' => get_pathway_strand_options('Grade 11'),
    'Grade 12' => get_pathway_strand_options('Grade 12'),
];

// Fetch sections from add_sections
$g11_sections = [];
$g12_sections = [];

if ($conn->connect_error) {
    $db_error = 'Database connection failed.';
} else {
    // Fetch sections
    $sec_res = $conn->query("SELECT grade_level, name FROM add_sections ORDER BY name ASC");
    if ($sec_res) {
        while ($s = $sec_res->fetch_assoc()) {
            if ($s['grade_level'] === '11') $g11_sections[] = $s['name'];
            elseif ($s['grade_level'] === '12') $g12_sections[] = $s['name'];
        }
        $sec_res->close();
    }

    $adv_res = $conn->query("SELECT id, name, avatar FROM advisers_accounts ORDER BY name ASC");
    if ($adv_res) {
        while ($adv = $adv_res->fetch_assoc()) $advisers[] = $adv;
        $adv_res->close();
    }

    // Add classroom
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['cr_action'])) {
        if ($_POST['cr_action'] === 'add') {
            $gl  = trim($_POST['grade_level'] ?? '');
            $tr  = trim($_POST['track'] ?? '');
            $ps  = trim($_POST['pathway_strand'] ?? '');
            $sec = trim($_POST['section_name'] ?? '');
            $adviser_id = (int)($_POST['adviser_id'] ?? 0);
            $cap = (int)($_POST['max_capacity'] ?? 40);
            $adviser_name = '';
            if ($adviser_id > 0) {
                $adv_stmt = $conn->prepare("SELECT name FROM advisers_accounts WHERE id = ?");
                if ($adv_stmt) {
                    $adv_stmt->bind_param("i", $adviser_id);
                    $adv_stmt->execute();
                    $adv_row = $adv_stmt->get_result()->fetch_assoc();
                    $adv_stmt->close();
                    $adviser_name = trim($adv_row['name'] ?? '');
                }
            }

            $section_locked = false;
            $adviser_locked = false;
            if ($gl && $sec && $adviser_id > 0 && $adviser_name !== '') {
                $chk_sec = $conn->prepare("SELECT id FROM classrooms WHERE LOWER(section_name) = LOWER(?) LIMIT 1");
                if ($chk_sec) {
                    $chk_sec->bind_param("s", $sec);
                    $chk_sec->execute();
                    $section_locked = $chk_sec->get_result()->num_rows > 0;
                    $chk_sec->close();
                }

                $chk_adv = $conn->prepare("SELECT id FROM classrooms WHERE adviser_id = ? LIMIT 1");
                if ($chk_adv) {
                    $chk_adv->bind_param("i", $adviser_id);
                    $chk_adv->execute();
                    $adviser_locked = $chk_adv->get_result()->num_rows > 0;
                    $chk_adv->close();
                }
            }

            if ($gl && $sec && $adviser_id > 0 && $adviser_name !== '' && !$section_locked && !$adviser_locked) {
                $stmt = $conn->prepare("INSERT INTO classrooms (grade_level, track, pathway_strand, section_name, adviser_id, adviser_name, max_capacity) VALUES (?,?,?,?,?,?,?)");
                if ($stmt) { $stmt->bind_param("ssssisi", $gl, $tr, $ps, $sec, $adviser_id, $adviser_name, $cap); $stmt->execute(); $stmt->close(); }
                $toast_message = 'Classroom added successfully.';
            } else {
                $toast_message = $section_locked
                    ? 'Selected section is already locked to another classroom.'
                    : ($adviser_locked
                        ? 'Selected adviser is already assigned to another classroom.'
                        : 'Please select a valid section and adviser.');
                $toast_type = 'error';
            }
        } elseif ($_POST['cr_action'] === 'update_classroom') {
            $cid = (int)($_POST['classroom_id'] ?? 0);
            $cap = (int)($_POST['max_capacity'] ?? 40);
            $adviser_id = (int)($_POST['adviser_id'] ?? 0);
            $pwd = $_POST['confirm_password'] ?? '';
            // Verify admin password
            $uid = $_SESSION['user_id'] ?? 0;
            $ok  = false;
            if ($uid) {
                $s = $conn->prepare("SELECT password FROM users WHERE id=?");
                if ($s) { $s->bind_param("i",$uid); $s->execute(); $r=$s->get_result()->fetch_assoc(); $s->close(); $ok = $r && password_verify($pwd,$r['password']); }
            }
            if ($ok && $cid > 0) {
                $adviser_name = null;
                if ($adviser_id > 0) {
                    $adv_stmt = $conn->prepare("SELECT name FROM advisers_accounts WHERE id = ? LIMIT 1");
                    if ($adv_stmt) {
                        $adv_stmt->bind_param("i", $adviser_id);
                        $adv_stmt->execute();
                        $adv_row = $adv_stmt->get_result()->fetch_assoc();
                        $adv_stmt->close();
                        $adviser_name = trim((string)($adv_row['name'] ?? ''));
                    }
                    if ($adviser_name === '') {
                        $toast_message = 'Selected adviser was not found.';
                        $toast_type = 'error';
                        $ok = false;
                    }
                }

                if ($ok && $adviser_id > 0) {
                    $clear_old = $conn->prepare("UPDATE classrooms SET adviser_id = NULL, adviser_name = NULL WHERE adviser_id = ? AND id <> ?");
                    if ($clear_old) {
                        $clear_old->bind_param("ii", $adviser_id, $cid);
                        $clear_old->execute();
                        $clear_old->close();
                    }
                }

                if ($ok) {
                    $stmt = $conn->prepare("UPDATE classrooms SET adviser_id = ?, adviser_name = ?, max_capacity = ? WHERE id = ?");
                    if ($stmt) { $stmt->bind_param("isii", $adviser_id, $adviser_name, $cap, $cid); $stmt->execute(); $stmt->close(); }
                    $toast_message = 'Classroom updated successfully.';
                }
            } else {
                $toast_message = 'Incorrect password. Classroom was not updated.';
                $toast_type = 'error';
            }
        } elseif ($_POST['cr_action'] === 'delete') {
            $cid = (int)($_POST['classroom_id'] ?? 0);
            $pwd = $_POST['confirm_password'] ?? '';
            $uid = $_SESSION['user_id'] ?? 0;
            $ok  = false;
            if ($uid) {
                $s = $conn->prepare("SELECT password FROM users WHERE id=?");
                if ($s) { $s->bind_param("i",$uid); $s->execute(); $r=$s->get_result()->fetch_assoc(); $s->close(); $ok = $r && password_verify($pwd,$r['password']); }
            }
            if ($ok && $cid > 0) {
                $stmt = $conn->prepare("DELETE FROM classrooms WHERE id=?");
                if ($stmt) { $stmt->bind_param("i",$cid); $stmt->execute(); $stmt->close(); }
                $toast_message = 'Classroom deleted.';
            } else {
                $toast_message = 'Incorrect password. Classroom not deleted.';
                $toast_type = 'error';
            }
        } elseif ($_POST['cr_action'] === 'reassign_student_complete') {
            $student_id = (int)($_POST['student_id'] ?? 0);
            $new_pathway = trim($_POST['new_pathway_strand'] ?? '');
            $new_section = trim($_POST['assigned_section'] ?? '');
            
            if ($student_id > 0 && $new_section !== '') {
                // Get student's current info and validate new section
                $stud_check = $conn->prepare("SELECT grade_level FROM students WHERE id = ?");
                if ($stud_check) {
                    $stud_check->bind_param("i", $student_id);
                    $stud_check->execute();
                    $stud_row = $stud_check->get_result()->fetch_assoc();
                    $stud_check->close();
                    
                    if ($stud_row) {
                        // Check if new section exists and get its capacity
                        $sec_check = $conn->prepare("SELECT id, max_capacity, grade_level FROM classrooms WHERE section_name = ? AND grade_level = ? LIMIT 1");
                        if ($sec_check) {
                            $sec_check->bind_param("ss", $new_section, $stud_row['grade_level']);
                            $sec_check->execute();
                            $sec_row = $sec_check->get_result()->fetch_assoc();
                            $sec_check->close();
                            
                            if ($sec_row) {
                                // Count current enrolled students in target section
                                $cap_check = $conn->prepare("SELECT COUNT(*) as cnt FROM students WHERE assigned_section = ? AND enrollment_status = 'enrolled'");
                                if ($cap_check) {
                                    $cap_check->bind_param("s", $new_section);
                                    $cap_check->execute();
                                    $cap_row = $cap_check->get_result()->fetch_assoc();
                                    $cap_check->close();
                                    
                                    $current_enrolled = (int)$cap_row['cnt'];
                                    $max_cap = (int)$sec_row['max_capacity'];
                                    
                                    // Check if target section is full
                                    if ($current_enrolled >= $max_cap) {
                                        $toast_message = "Cannot reassign: Section '{$new_section}' is at full capacity ({$current_enrolled}/{$max_cap}). Remove a student first.";
                                        $toast_type = 'error';
                                    } else {
                                        // Check if reassigning pathway/strand
                                        $update_stmt = $conn->prepare("UPDATE students SET assigned_section = ? WHERE id = ?");
                                        if ($new_pathway !== '') {
                                            $update_stmt = $conn->prepare("UPDATE students SET assigned_section = ?, pathway_strand = ? WHERE id = ?");
                                            $update_stmt->bind_param("ssi", $new_section, $new_pathway, $student_id);
                                        } else {
                                            $update_stmt->bind_param("si", $new_section, $student_id);
                                        }
                                        $update_stmt->execute();
                                        $update_stmt->close();
                                        
                                        // Update encodings table as well
                                        $enc_stmt = $conn->prepare("UPDATE encodings SET assigned_section = ? WHERE student_id = ?");
                                        if ($enc_stmt) {
                                            $enc_stmt->bind_param("si", $new_section, $student_id);
                                            $enc_stmt->execute();
                                            $enc_stmt->close();
                                        }
                                        
                                        if ($new_pathway !== '') {
                                            $enc_ps_stmt = $conn->prepare("UPDATE encodings SET pathway_strand = ? WHERE student_id = ?");
                                            if ($enc_ps_stmt) {
                                                $enc_ps_stmt->bind_param("si", $new_pathway, $student_id);
                                                $enc_ps_stmt->execute();
                                                $enc_ps_stmt->close();
                                            }
                                        }
                                        
                                        $toast_message = 'Student reassigned successfully to ' . htmlspecialchars($new_section) . ($new_pathway !== '' ? ' and pathway/strand updated' : '') . '.';
                                    }
                                }
                            } else {
                                $toast_message = 'Selected section not found.';
                                $toast_type = 'error';
                            }
                        }
                    } else {
                        $toast_message = 'Student not found.';
                        $toast_type = 'error';
                    }
                }
            } else {
                $toast_message = 'Please choose a valid section for reassignment.';
                $toast_type = 'error';
            }
        } elseif ($_POST['cr_action'] === 'save_group_link') {
            $cid  = (int)($_POST['classroom_id'] ?? 0);
            $url  = trim($_POST['group_chat_url'] ?? '');
            // Basic URL validation — allow empty to clear
            if ($url !== '' && !filter_var($url, FILTER_VALIDATE_URL)) {
                $toast_message = 'Invalid URL. Please enter a valid link.';
                $toast_type = 'error';
            } elseif ($cid > 0) {
                $stmt = $conn->prepare("UPDATE classrooms SET group_chat_url = ? WHERE id = ?");
                if ($stmt) { $stmt->bind_param("si", $url, $cid); $stmt->execute(); $stmt->close(); }
                $toast_message = $url ? 'Group chat link saved.' : 'Group chat link removed.';
            }
        } elseif ($_POST['cr_action'] === 'verify_withdraw_password') {
            $student_id = (int)($_POST['student_id'] ?? 0);
            $pwd = $_POST['admin_password'] ?? '';
            $uid = $_SESSION['user_id'] ?? 0;
            $ok  = false;
            if ($uid) {
                $s = $conn->prepare("SELECT password FROM users WHERE id = ?");
                if ($s) {
                    $s->bind_param("i", $uid);
                    $s->execute();
                    $r = $s->get_result()->fetch_assoc();
                    $s->close();
                    $ok = $r && password_verify($pwd, $r['password']);
                }
            }
            if ($ok && $student_id > 0) {
                $stmt = $conn->prepare("UPDATE students SET enrollment_status = 'withdrawn' WHERE id = ?");
                if ($stmt) {
                    $stmt->bind_param("i", $student_id);
                    $stmt->execute();
                    $stmt->close();
                }
                $toast_message = 'Student has been withdrawn successfully.';
            } else {
                $toast_message = 'Incorrect password. Withdrawal was not authorized.';
                $toast_type = 'error';
            }
        }
    }

    if ($isAdviser && $adviserSection) {
        $res = $conn->prepare("SELECT c.*, a.avatar AS adviser_avatar FROM classrooms c LEFT JOIN advisers_accounts a ON a.id = c.adviser_id WHERE c.section_name = ? ORDER BY c.section_name ASC");
        $res->bind_param("s", $adviserSection);
        $res->execute();
        $res = $res->get_result();
    } else {
        $res = $conn->query("SELECT c.*, a.avatar AS adviser_avatar FROM classrooms c LEFT JOIN advisers_accounts a ON a.id = c.adviser_id ORDER BY c.grade_level DESC, c.section_name ASC");
    }
    if ($res) { while ($row = $res->fetch_assoc()) $classrooms[] = $row; $res->close(); }
    // Enrolled count per classroom section
    $enrolled_counts = [];
    $ec = $conn->query("SELECT assigned_section, COUNT(*) as cnt FROM students WHERE enrollment_status='enrolled' AND assigned_section IS NOT NULL AND assigned_section <> '' GROUP BY assigned_section");
    if ($ec) { while ($r = $ec->fetch_assoc()) $enrolled_counts[$r['assigned_section']] = (int)$r['cnt']; $ec->close(); }

    $conn->close();
}

// Helper: default capacity
function default_capacity($grade_level, $track) {
    if ($grade_level === 'Grade 11') return ($track === 'Tech-Pro' || $track === 'ALS') ? 30 : 40;
    return 45; // Grade 12 academic + TVL
}
?>

<?php if ($toast_message): ?>
<div class="mb-4 rounded-xl border px-5 py-4 text-sm font-semibold <?php echo $toast_type==='error'?'bg-red-50 border-red-200 text-red-700':'bg-emerald-50 border-emerald-200 text-emerald-700';?>">
    <?php echo htmlspecialchars($toast_message); ?>
</div>
<?php endif; ?>

<div class="bg-white rounded-xl shadow-sm border border-slate-100 overflow-hidden">
    <div class="px-6 py-4 border-b border-slate-100 flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4">
        <div>
            <h3 class="font-heading font-bold text-lg text-dranhs-dark">Classroom List</h3>
            <p class="text-sm text-slate-500">Manage sections per grade level and career pathway / strand.</p>
        </div>
        <div class="flex items-center gap-3 w-full sm:w-auto">
            <div class="relative flex-1 sm:w-64">
                <input type="text" id="cr-search-input" placeholder="Search sections..." class="w-full bg-slate-50 border border-slate-200 px-4 py-2 pl-10 rounded-lg text-sm focus:border-dranhs-green outline-none transition-colors">
                <svg class="w-4 h-4 text-slate-400 absolute left-3 top-1/2 -translate-y-1/2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path></svg>
            </div>
            <button type="button" id="cr-add-btn" class="shrink-0 inline-flex items-center gap-2 px-4 py-2 rounded-lg bg-dranhs-green text-white text-sm font-bold hover:bg-emerald-700 transition-colors <?php echo $isAdviser ? 'hidden' : ''; ?>">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                Add Classroom
            </button>
        </div>
    </div>

    <div class="p-6">
        <?php if ($db_error): ?>
            <p class="text-sm text-red-600 font-semibold"><?php echo htmlspecialchars($db_error); ?></p>
        <?php elseif (empty($classrooms)): ?>
            <div class="text-center py-16">
                <svg class="w-12 h-12 text-slate-300 mx-auto mb-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M8 14v3m4-3v3m4-3v3M3 21h18M3 10h18M3 7l9-4 9 4M4 10h16v11H4V10z"/></svg>
                <p class="text-sm font-semibold text-slate-500">No classrooms yet.</p>
                <p class="text-xs text-slate-400 mt-1">Click "Add Classroom" to create the first section.</p>
            </div>
        <?php else: ?>
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-5">
            <?php foreach ($classrooms as $cr):
                $enrolled = $enrolled_counts[$cr['section_name']] ?? 0;
                $cap = (int)$cr['max_capacity'];
                $pct = $cap > 0 ? min(100, round($enrolled / $cap * 100)) : 0;
                $barColor = $pct >= 90 ? 'bg-red-500' : ($pct >= 70 ? 'bg-amber-400' : 'bg-dranhs-green');
                $isG11 = $cr['grade_level'] === 'Grade 11';
                $accentColor = $isG11 ? '#6d28d9' : '#e11d48';
                $psLabel = get_pathway_strand_label($cr['grade_level'], $cr['pathway_strand']);
            ?>
            <div class="cr-card bg-white rounded-2xl border-2 border-slate-200 overflow-hidden flex flex-col shadow-sm hover:shadow-md transition-shadow" data-search="<?php echo htmlspecialchars(strtolower($cr['grade_level'] . ' ' . $cr['section_name'] . ' ' . $cr['track'] . ' ' . $psLabel . ' ' . $cr['adviser_name'])); ?>">
                <!-- Card top accent -->
                <div class="h-2 w-full" style="background:<?php echo $accentColor;?>"></div>
                <div class="p-5 flex-1 flex flex-col gap-3">
                    <div class="flex items-start justify-between gap-2">
                        <div>
                            <span class="text-xs font-black uppercase tracking-widest" style="color:<?php echo $accentColor;?>"><?php echo htmlspecialchars($cr['grade_level']); ?></span>
                            <h4 class="font-heading font-black text-lg text-dranhs-dark leading-tight"><?php echo htmlspecialchars($cr['section_name']); ?></h4>
                        </div>
                    </div>

                    <div class="text-xs text-slate-500 font-semibold">
                        <?php echo $isG11 ? 'Career Pathway' : 'Strand'; ?>:
                        <span class="text-slate-700"><?php echo htmlspecialchars($psLabel ?: '--'); ?></span>
                    </div>
                    <?php if ($cr['track']): ?>
                    <div class="text-xs text-slate-400">Track: <span class="text-slate-600 font-semibold"><?php echo htmlspecialchars($cr['track']); ?></span></div>
                    <?php endif; ?>
                    <div class="text-xs text-slate-400">Adviser: <span class="text-slate-600 font-semibold"><?php echo htmlspecialchars($cr['adviser_name'] ?: '--'); ?></span></div>

                    <!-- Capacity bar -->
                    <div>
                        <div class="flex justify-between text-xs font-bold text-slate-500 mb-1">
                            <span>Enrolled</span>
                            <span><?php echo $enrolled; ?> / <?php echo $cap; ?></span>
                        </div>
                        <div class="w-full bg-slate-100 rounded-full h-2">
                            <div class="<?php echo $barColor; ?> h-2 rounded-full transition-all" style="width:<?php echo $pct; ?>%"></div>
                        </div>
                    </div>

                    <!-- Actions -->
                    <div class="flex items-center gap-2 pt-1 mt-auto">
                        <!-- Masterlist -->
                        <button type="button"
                            class="cr-masterlist-btn flex-1 inline-flex items-center justify-center gap-1.5 px-3 py-2 rounded-lg bg-blue-50 text-blue-600 text-xs font-bold hover:bg-blue-100 transition-colors"
                            data-id="<?php echo (int)$cr['id'];?>"
                            data-name="<?php echo htmlspecialchars($cr['section_name'],ENT_QUOTES);?>"
                            data-pathway="<?php echo htmlspecialchars($cr['pathway_strand'],ENT_QUOTES);?>"
                            data-grade="<?php echo htmlspecialchars($cr['grade_level'],ENT_QUOTES);?>">
                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/></svg>
                            Masterlist
                        </button>

                        <!-- Group Chat Link -->
                        <?php $hasLink = !empty($cr['group_chat_url']); ?>
                        <?php if ($hasLink): ?>
                            <!-- Has URL: clicking opens the link -->
                            <a href="<?php echo htmlspecialchars($cr['group_chat_url']); ?>" target="_blank" rel="noopener noreferrer"
                                class="cr-link-btn inline-flex items-center justify-center w-8 h-8 rounded-lg bg-emerald-500 text-white hover:bg-emerald-600 transition-colors relative"
                                title="Open Group Chat Link"
                                data-id="<?php echo (int)$cr['id']; ?>"
                                data-url="<?php echo htmlspecialchars($cr['group_chat_url'], ENT_QUOTES); ?>">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.828 10.172a4 4 0 00-5.656 0l-4 4a4 4 0 105.656 5.656l1.102-1.101m-.758-4.899a4 4 0 005.656 0l4-4a4 4 0 00-5.656-5.656l-1.1 1.1"/></svg>
                            </a>
                            <!-- Edit link button (long press or right-click feel — separate small edit icon) -->
                            <button type="button"
                                class="cr-link-edit-btn inline-flex items-center justify-center w-5 h-5 rounded-full bg-emerald-100 text-emerald-600 hover:bg-emerald-200 transition-colors -ml-1"
                                title="Edit Group Chat Link"
                                data-id="<?php echo (int)$cr['id']; ?>"
                                data-url="<?php echo htmlspecialchars($cr['group_chat_url'], ENT_QUOTES); ?>">
                                <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536M9 11l6 6L5 23l-2-2 6-6z"/></svg>
                            </button>
                        <?php else: ?>
                            <!-- No URL: clicking opens set-link modal -->
                            <button type="button"
                                class="cr-link-edit-btn inline-flex items-center justify-center w-8 h-8 rounded-lg bg-slate-100 text-slate-400 hover:bg-slate-200 transition-colors"
                                title="Set Group Chat Link"
                                data-id="<?php echo (int)$cr['id']; ?>"
                                data-url="">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.828 10.172a4 4 0 00-5.656 0l-4 4a4 4 0 105.656 5.656l1.102-1.101m-.758-4.899a4 4 0 005.656 0l4-4a4 4 0 00-5.656-5.656l-1.1 1.1"/></svg>
                            </button>
                        <?php endif; ?>
                        <!-- Edit capacity -->
                        <button type="button"
                            class="cr-capacity-btn inline-flex items-center justify-center w-8 h-8 rounded-lg bg-amber-50 text-amber-600 hover:bg-amber-100 transition-colors <?php echo $isAdviser ? 'hidden' : ''; ?>"
                            data-id="<?php echo (int)$cr['id'];?>"
                            data-name="<?php echo htmlspecialchars($cr['section_name'],ENT_QUOTES);?>"
                            data-cap="<?php echo $cap;?>"
                            data-adviser-id="<?php echo (int)($cr['adviser_id'] ?? 0);?>"
                            title="Edit Classroom">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                        </button>
                        <!-- Delete -->
                        <button type="button"
                            class="cr-delete-btn inline-flex items-center justify-center w-8 h-8 rounded-lg bg-red-50 text-red-500 hover:bg-red-100 transition-colors <?php echo $isAdviser ? 'hidden' : ''; ?>"
                            data-id="<?php echo (int)$cr['id'];?>"
                            data-name="<?php echo htmlspecialchars($cr['section_name'],ENT_QUOTES);?>" title="Delete">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6M9 7V4a1 1 0 011-1h4a1 1 0 011 1v3m-7 0h8"/></svg>
                        </button>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </div>
</div>

<!-- ===== ADD CLASSROOM MODAL ===== -->
<div id="cr-add-modal" class="fixed inset-0 z-50 hidden">
    <div class="absolute inset-0 bg-slate-900/60" id="cr-add-backdrop"></div>
    <div class="relative z-10 min-h-screen flex items-center justify-center p-4">
        <div class="w-full max-w-lg bg-white rounded-2xl shadow-2xl border border-slate-200 overflow-hidden">
            <div class="bg-dranhs-dark px-6 py-5 flex items-center justify-between">
                <div>
                    <p class="text-xs font-bold uppercase tracking-widest text-dranhs-green mb-1">New Classroom</p>
                    <h3 class="font-heading font-black text-xl text-white">Add Classroom</h3>
                </div>
                <button type="button" id="cr-add-close" class="w-9 h-9 rounded-full bg-white/10 text-white hover:bg-white/20 transition-colors text-xl flex items-center justify-center">&times;</button>
            </div>
            <form method="POST" class="p-6 space-y-5">
                <input type="hidden" name="cr_action" value="add">

                <div>
                    <label class="block text-xs font-bold uppercase tracking-wider text-slate-500 mb-2">Grade Level</label>
                    <select name="grade_level" id="cr-grade-level" class="w-full bg-white border-2 border-slate-300 px-4 py-3 rounded-xl text-sm font-medium focus:border-dranhs-green outline-none" required>
                        <option value="">Select Grade Level...</option>
                        <option value="Grade 11">Grade 11</option>
                        <option value="Grade 12">Grade 12</option>
                    </select>
                </div>

                <div id="cr-track-wrap" class="hidden">
                    <label class="block text-xs font-bold uppercase tracking-wider text-slate-500 mb-2">Track</label>
                    <select name="track" id="cr-track" class="w-full bg-white border-2 border-slate-300 px-4 py-3 rounded-xl text-sm font-medium focus:border-dranhs-green outline-none">
                        <option value="">Select Track...</option>
                        <option value="Academic">Academic</option>
                        <option value="Tech-Pro">Tech-Pro</option>
                        <option value="ALS">ALS</option>
                        <option value="TVL">TVL</option>
                    </select>
                </div>

                <div id="cr-pathway-wrap" class="hidden">
                    <label class="block text-xs font-bold uppercase tracking-wider text-slate-500 mb-2" id="cr-pathway-label">Career Pathway</label>
                    <select name="pathway_strand" id="cr-pathway" class="w-full bg-white border-2 border-slate-300 px-4 py-3 rounded-xl text-sm font-medium focus:border-dranhs-green outline-none">
                        <option value="">Select...</option>
                    </select>
                </div>

                <div id="cr-section-wrap" class="hidden">
                    <label class="block text-xs font-bold uppercase tracking-wider text-slate-500 mb-2">Section Name</label>
                    <select name="section_name" id="cr-section-name" class="w-full bg-white border-2 border-slate-300 px-4 py-3 rounded-xl text-sm font-medium focus:border-dranhs-green outline-none" required>
                        <option value="">Select Section...</option>
                    </select>
                </div>

                <div id="cr-adviser-wrap" class="hidden">
                    <label class="block text-xs font-bold uppercase tracking-wider text-slate-500 mb-2">Adviser</label>
                    <select name="adviser_id" id="cr-adviser-id" class="w-full bg-white border-2 border-slate-300 px-4 py-3 rounded-xl text-sm font-medium focus:border-dranhs-green outline-none" required>
                        <option value="">Select Adviser...</option>
                    </select>
                </div>

                <div id="cr-cap-wrap" class="hidden">
                    <label class="block text-xs font-bold uppercase tracking-wider text-slate-500 mb-2">Max Capacity <span class="text-slate-400 font-normal normal-case" id="cr-cap-hint"></span></label>
                    <input type="number" name="max_capacity" id="cr-capacity" min="1" max="100" class="w-full bg-white border-2 border-slate-300 px-4 py-3 rounded-xl text-sm font-medium focus:border-dranhs-green outline-none">
                </div>

                <div id="cr-submit-wrap" class="hidden flex justify-end gap-3 pt-2">
                    <button type="button" id="cr-add-cancel" class="px-5 py-3 rounded-xl border-2 border-slate-300 text-slate-600 font-bold text-sm hover:bg-slate-50 transition-colors">Cancel</button>
                    <button type="submit" class="px-5 py-3 rounded-xl bg-dranhs-green text-white font-bold text-sm hover:bg-emerald-700 transition-colors shadow-md">Add Classroom</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- ===== CLASSROOM EDIT MODAL ===== -->
<div id="cr-cap-modal" class="fixed inset-0 z-50 hidden">
    <div class="absolute inset-0 bg-slate-900/60" id="cr-cap-backdrop"></div>
    <div class="relative z-10 min-h-screen flex items-center justify-center p-4">
        <div class="w-full max-w-sm bg-white rounded-2xl shadow-2xl border border-slate-200 overflow-hidden">
            <div class="bg-dranhs-dark px-6 py-4 flex items-center justify-between">
                <h3 class="font-heading font-black text-lg text-white">Edit Classroom</h3>
                <button type="button" id="cr-cap-close" class="w-9 h-9 rounded-full bg-white/10 text-white hover:bg-white/20 text-xl flex items-center justify-center">&times;</button>
            </div>
            <form method="POST" class="p-6 space-y-4">
                <input type="hidden" name="cr_action" value="update_classroom">
                <input type="hidden" name="classroom_id" id="cr-cap-id">
                <p class="text-sm text-slate-600">Editing classroom for: <span id="cr-cap-section-name" class="font-bold text-dranhs-dark"></span></p>
                <div>
                    <label class="block text-xs font-bold uppercase tracking-wider text-slate-500 mb-2">Adviser</label>
                    <select name="adviser_id" id="cr-cap-adviser-id" class="w-full bg-white border-2 border-slate-300 px-4 py-3 rounded-xl text-sm font-medium focus:border-dranhs-green outline-none" required>
                        <option value="">Select Adviser...</option>
                    </select>
                </div>
                <div class="flex items-center gap-3">
                    <button type="button" id="cr-cap-minus" class="w-10 h-10 rounded-xl bg-slate-100 text-slate-700 font-black text-xl hover:bg-slate-200 transition-colors flex items-center justify-center">−</button>
                    <input type="number" name="max_capacity" id="cr-cap-value" min="1" max="100" class="flex-1 text-center bg-white border-2 border-slate-300 px-4 py-3 rounded-xl text-lg font-black focus:border-dranhs-green outline-none">
                    <button type="button" id="cr-cap-plus" class="w-10 h-10 rounded-xl bg-slate-100 text-slate-700 font-black text-xl hover:bg-slate-200 transition-colors flex items-center justify-center">+</button>
                </div>
                <div>
                    <label class="block text-xs font-bold uppercase tracking-wider text-slate-500 mb-2">Confirm Password</label>
                    <input type="password" name="confirm_password" class="w-full bg-white border-2 border-slate-300 px-4 py-3 rounded-xl text-sm font-medium focus:border-dranhs-green outline-none" required placeholder="Enter your password">
                </div>
                <div class="flex justify-end gap-3 pt-1">
                    <button type="button" id="cr-cap-cancel" class="px-5 py-2.5 rounded-xl border-2 border-slate-300 text-slate-600 font-bold text-sm hover:bg-slate-50">Cancel</button>
                    <button type="submit" class="px-5 py-2.5 rounded-xl bg-amber-500 text-white font-bold text-sm hover:bg-amber-600 shadow-md">Save</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- ===== DELETE MODAL ===== -->
<div id="cr-del-modal" class="fixed inset-0 z-50 hidden">
    <div class="absolute inset-0 bg-slate-900/60" id="cr-del-backdrop"></div>
    <div class="relative z-10 min-h-screen flex items-center justify-center p-4">
        <div class="w-full max-w-sm bg-white rounded-2xl shadow-2xl border border-slate-200 overflow-hidden">
            <div class="bg-red-600 px-6 py-4 flex items-center justify-between">
                <h3 class="font-heading font-black text-lg text-white">Delete Classroom</h3>
                <button type="button" id="cr-del-close" class="w-9 h-9 rounded-full bg-white/10 text-white hover:bg-white/20 text-xl flex items-center justify-center">&times;</button>
            </div>
            <form method="POST" class="p-6 space-y-4">
                <input type="hidden" name="cr_action" value="delete">
                <input type="hidden" name="classroom_id" id="cr-del-id">
                <p class="text-sm text-slate-600">Delete classroom: <span id="cr-del-name" class="font-bold text-red-600"></span>? This cannot be undone.</p>
                <div>
                    <label class="block text-xs font-bold uppercase tracking-wider text-slate-500 mb-2">Confirm Password</label>
                    <input type="password" name="confirm_password" class="w-full bg-white border-2 border-slate-300 px-4 py-3 rounded-xl text-sm font-medium focus:border-red-500 outline-none" required placeholder="Enter your password">
                </div>
                <div class="flex justify-end gap-3 pt-1">
                    <button type="button" id="cr-del-cancel" class="px-5 py-2.5 rounded-xl border-2 border-slate-300 text-slate-600 font-bold text-sm hover:bg-slate-50">Cancel</button>
                    <button type="submit" class="px-5 py-2.5 rounded-xl bg-red-600 text-white font-bold text-sm hover:bg-red-700 shadow-md">Delete</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- ===== GROUP CHAT LINK MODAL ===== -->
<div id="cr-link-modal" class="fixed inset-0 z-50 hidden">
    <div class="absolute inset-0 bg-slate-900/60" id="cr-link-backdrop"></div>
    <div class="relative z-10 min-h-screen flex items-center justify-center p-4">
        <div class="w-full max-w-md bg-white rounded-2xl shadow-2xl border border-slate-200 overflow-hidden">
            <div class="bg-dranhs-dark px-6 py-5 flex items-center justify-between">
                <div>
                    <p class="text-xs font-bold uppercase tracking-widest text-emerald-400 mb-1">Group Chat</p>
                    <h3 class="font-heading font-black text-xl text-white">Set Group Chat Link</h3>
                </div>
                <button type="button" id="cr-link-close" class="w-9 h-9 rounded-full bg-white/10 text-white hover:bg-white/20 transition-colors text-xl flex items-center justify-center">&times;</button>
            </div>
            <form method="POST" class="p-6 space-y-4">
                <input type="hidden" name="cr_action" value="save_group_link">
                <input type="hidden" name="classroom_id" id="cr-link-classroom-id" value="">
                <div>
                    <label class="block text-xs font-bold uppercase tracking-wider text-slate-500 mb-2">Group Chat URL</label>
                    <input type="url" name="group_chat_url" id="cr-link-url-input"
                        placeholder="https://chat.google.com/... or https://t.me/..."
                        class="w-full bg-white border-2 border-slate-300 px-4 py-3 rounded-xl text-sm font-medium focus:border-dranhs-green outline-none">
                    <p class="text-xs text-slate-400 mt-1.5">Supports any URL — Google Chat, Telegram, Messenger, etc. Leave blank to remove.</p>
                </div>
                <div class="flex gap-3 pt-1">
                    <button type="button" id="cr-link-cancel" class="flex-1 px-4 py-2.5 rounded-xl border-2 border-slate-200 text-slate-600 text-sm font-bold hover:bg-slate-50">Cancel</button>
                    <button type="submit" class="flex-1 px-4 py-2.5 rounded-xl bg-dranhs-green text-white text-sm font-bold hover:bg-emerald-700">Save Link</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- ===== MASTERLIST MODAL ===== -->
<div id="cr-masterlist-modal" class="fixed inset-0 z-50 hidden">
    <div class="absolute inset-0 bg-slate-900/60" id="cr-ml-backdrop"></div>
    <div class="relative z-10 min-h-screen flex items-start justify-center p-4 py-8">
        <div class="w-full max-w-3xl bg-white rounded-2xl shadow-2xl border border-slate-200 overflow-hidden">
            <div class="bg-dranhs-dark px-6 py-5 flex items-center justify-between">
                <div>
                    <p class="text-xs font-bold uppercase tracking-widest text-dranhs-green mb-1">Section Masterlist</p>
                    <h3 id="cr-ml-title" class="font-heading font-black text-xl text-white">Section Name</h3>
                </div>
                <div class="flex items-center gap-2">
                    <button type="button" id="cr-ml-print" class="inline-flex items-center gap-2 px-4 py-2 rounded-lg bg-white/10 text-white text-sm font-bold hover:bg-white/20 transition-colors">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-3a2 2 0 00-2-2H5a2 2 0 00-2 2v3a2 2 0 002 2h2m10 0H7m10 0v4H7v-4m10-8V3H7v6h10z"/></svg>
                        Print
                    </button>
                    <button type="button" id="cr-ml-close" class="w-9 h-9 rounded-full bg-white/10 text-white hover:bg-white/20 text-xl flex items-center justify-center">&times;</button>
                </div>
            </div>
            <div class="max-h-[70vh] overflow-y-auto">
                <div id="cr-ml-body" class="p-6">
                    <p class="text-sm text-slate-400 text-center py-8">Loading...</p>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- ===== REASSIGN MODAL ===== -->
<div id="cr-reassign-modal" class="fixed inset-0 z-50 hidden">
    <div class="absolute inset-0 bg-slate-900/60" id="cr-reassign-backdrop"></div>
    <div class="relative z-10 min-h-screen flex items-center justify-center p-4">
        <div class="w-full max-w-lg bg-white rounded-2xl shadow-2xl border border-slate-200 overflow-hidden">
            <div class="bg-dranhs-dark px-6 py-5 flex items-center justify-between">
                <div>
                    <p class="text-xs font-bold uppercase tracking-widest text-amber-400 mb-1">Student Reassignment</p>
                    <h3 id="cr-reassign-title" class="font-heading font-black text-xl text-white">Reassign Student</h3>
                </div>
                <button type="button" id="cr-reassign-close" class="w-9 h-9 rounded-full bg-white/10 text-white hover:bg-white/20 text-xl flex items-center justify-center">&times;</button>
            </div>
            <form method="POST" class="p-6 space-y-4">
                <input type="hidden" name="cr_action" value="reassign_student_complete">
                <input type="hidden" name="student_id" id="cr-reassign-student-id">
                <div class="rounded-xl bg-amber-50 border border-amber-100 px-4 py-3">
                    <p class="text-xs font-bold uppercase tracking-wider text-amber-600">Current Student</p>
                    <p id="cr-reassign-student-name" class="text-sm font-bold text-slate-700 mt-1">Student Name</p>
                    <p id="cr-reassign-current-info" class="text-xs text-amber-700 mt-2">Current Section & Pathway</p>
                </div>
                
                <div id="cr-reassign-pathway-wrap">
                    <label class="block text-xs font-bold uppercase tracking-wider text-slate-500 mb-2">Career Pathway / Strand</label>
                    <select name="new_pathway_strand" id="cr-reassign-pathway" class="w-full bg-white border-2 border-slate-300 px-4 py-3 rounded-xl text-sm font-medium focus:border-dranhs-green outline-none">
                        <option value="">Keep Current...</option>
                    </select>
                </div>
                
                <div>
                    <label class="block text-xs font-bold uppercase tracking-wider text-slate-500 mb-2">New Section</label>
                    <select name="assigned_section" id="cr-reassign-section" class="w-full bg-white border-2 border-slate-300 px-4 py-3 rounded-xl text-sm font-medium focus:border-dranhs-green outline-none" required>
                        <option value="">Select Section...</option>
                    </select>
                </div>
                
                <div id="cr-reassign-capacity-info" class="rounded-xl bg-blue-50 border border-blue-100 px-4 py-3 text-sm hidden">
                    <p class="text-xs font-bold uppercase text-blue-600">Section Capacity Status</p>
                    <p id="cr-reassign-capacity-text" class="text-blue-700 font-semibold mt-1">--</p>
                </div>
                
                <div class="flex justify-end gap-3 pt-1">
                    <button type="button" id="cr-reassign-cancel" class="px-5 py-2.5 rounded-xl border-2 border-slate-300 text-slate-600 font-bold text-sm hover:bg-slate-50">Cancel</button>
                    <button type="submit" class="px-5 py-2.5 rounded-xl bg-amber-500 text-white font-bold text-sm hover:bg-amber-600 shadow-md">Save Reassignment</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- ===== WITHDRAW MODAL ===== -->
<div id="cr-withdraw-modal" class="fixed inset-0 z-50 hidden">
    <div class="absolute inset-0 bg-slate-900/60" id="cr-withdraw-backdrop"></div>
    <div class="relative z-10 min-h-screen flex items-center justify-center p-4">
        <div class="w-full max-w-lg bg-white rounded-2xl shadow-2xl border border-slate-200 overflow-hidden">
            <div class="bg-red-600 px-6 py-5 flex items-center justify-between">
                <div>
                    <p class="text-xs font-bold uppercase tracking-widest text-red-100 mb-1">Withdrawal Security</p>
                    <h3 id="cr-withdraw-title" class="font-heading font-black text-xl text-white">Authorize Withdrawal</h3>
                </div>
                <button type="button" id="cr-withdraw-close" class="w-9 h-9 rounded-full bg-white/10 text-white hover:bg-white/20 text-xl flex items-center justify-center">&times;</button>
            </div>
            <form method="POST" class="p-6 space-y-4">
                <input type="hidden" name="cr_action" value="verify_withdraw_password">
                <input type="hidden" name="student_id" id="cr-withdraw-student-id">
                <div class="rounded-xl bg-red-50 border border-red-100 px-4 py-3 text-sm text-red-700">
                    Student selected: <span id="cr-withdraw-student-name" class="font-bold"></span>
                </div>
                <div>
                    <label class="block text-xs font-bold uppercase tracking-wider text-slate-500 mb-2">Admin / Evaluator Password</label>
                    <input name="admin_password" type="password" class="w-full rounded-xl border border-slate-300 px-4 py-3 text-sm" required>
                </div>
                <div class="flex justify-end gap-3 pt-1">
                    <button type="button" id="cr-withdraw-cancel" class="px-5 py-2.5 rounded-xl border-2 border-slate-300 text-slate-600 font-bold text-sm hover:bg-slate-50">Cancel</button>
                    <button type="submit" class="px-5 py-2.5 rounded-xl bg-red-600 text-white font-bold text-sm hover:bg-red-700 shadow-md">Verify Password</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
const CR_CATALOG = <?php echo json_encode($catalog_for_js, JSON_HEX_TAG|JSON_HEX_APOS|JSON_HEX_QUOT|JSON_HEX_AMP); ?>;
const CR_SECTIONS = {
    'Grade 11': <?php echo json_encode($g11_sections, JSON_HEX_TAG|JSON_HEX_APOS|JSON_HEX_QUOT|JSON_HEX_AMP); ?>,
    'Grade 12': <?php echo json_encode($g12_sections, JSON_HEX_TAG|JSON_HEX_APOS|JSON_HEX_QUOT|JSON_HEX_AMP); ?>,
};
const CR_ADVISERS = <?php echo json_encode($advisers, JSON_HEX_TAG|JSON_HEX_APOS|JSON_HEX_QUOT|JSON_HEX_AMP); ?>;
const CR_CLASSROOMS = <?php echo json_encode($classrooms, JSON_HEX_TAG|JSON_HEX_APOS|JSON_HEX_QUOT|JSON_HEX_AMP); ?>;
const ENROLLED_DATA = <?php
    $conn2 = new mysqli($db_host, $db_user, $db_pass, $db_name);
    $all_enrolled = [];
    if (!$conn2->connect_error) {
        $er = $conn2->query("SELECT students.id, students.last_name, students.first_name, students.middle_name, students.extension_name, students.lrn, students.sex, students.grade_level, students.track, students.pathway_strand, students.assigned_section, students.enrollment_status,
            watchlist.issue_type AS watch_issue_type, watchlist.issue_details AS watch_issue_details
            FROM students
            LEFT JOIN watchlist
                ON watchlist.lrn = students.lrn
               AND watchlist.school_year = students.school_year
            WHERE students.enrollment_status IN ('for_encoding','enrolled')
            ORDER BY students.last_name, students.first_name");
        if ($er) { while ($row = $er->fetch_assoc()) $all_enrolled[] = $row; $er->close(); }
        $conn2->close();
    }
    echo json_encode($all_enrolled, JSON_HEX_TAG|JSON_HEX_APOS|JSON_HEX_QUOT|JSON_HEX_AMP);
?>;

function defaultCap(gl, track) {
    if (gl === 'Grade 11') return (track === 'Tech-Pro' || track === 'ALS') ? 30 : 40;
    return 45;
}

function pathwayLabel(gradeLevel, code) {
    const options = CR_CATALOG[gradeLevel] || [];
    const match = options.find(item => String(item.code || '').toLowerCase() === String(code || '').toLowerCase());
    return match ? match.label : (code || '--');
}

function masterlistName(student) {
    const middleInitial = student.middle_name ? ` ${String(student.middle_name).trim().charAt(0).toUpperCase()}.` : '';
    const extension = student.extension_name ? ` ${student.extension_name}` : '';
    return `${student.last_name}, ${student.first_name}${middleInitial}${extension}`;
}

function genderRank(sex) {
    const value = String(sex || '').trim().toLowerCase();
    if (value === 'male') return 0;
    if (value === 'female') return 1;
    return 2;
}

function compareStudents(a, b) {
    const byGender = genderRank(a.sex) - genderRank(b.sex);
    if (byGender !== 0) return byGender;
    const byLast = String(a.last_name || '').localeCompare(String(b.last_name || ''), undefined, { sensitivity: 'base' });
    if (byLast !== 0) return byLast;
    const byFirst = String(a.first_name || '').localeCompare(String(b.first_name || ''), undefined, { sensitivity: 'base' });
    if (byFirst !== 0) return byFirst;
    return String(a.middle_name || '').localeCompare(String(b.middle_name || ''), undefined, { sensitivity: 'base' });
}

function lockedSectionNames() {
    return new Set(CR_CLASSROOMS.map(c => String(c.section_name || '').toLowerCase()).filter(Boolean));
}

function lockedAdviserIds() {
    return new Set(CR_CLASSROOMS.map(c => String(c.adviser_id || '')).filter(Boolean));
}

function sectionChoices(gradeLevel) {
    const locked = lockedSectionNames();
    return (CR_SECTIONS[gradeLevel] || []).map(name => ({ name, locked: locked.has(String(name || '').toLowerCase()) }));
}

function adviserChoices() {
    const locked = lockedAdviserIds();
    return CR_ADVISERS.map(adviser => ({ ...adviser, locked: locked.has(String(adviser.id)) }));
}

function populateEditAdviserOptions(selectedAdviserId, currentClassroomId) {
    const advSel = document.getElementById('cr-cap-adviser-id');
    if (!advSel) return;
    advSel.innerHTML = '<option value="">Select Adviser...</option>';
    if (!Array.isArray(CR_ADVISERS) || CR_ADVISERS.length === 0) {
        const option = document.createElement('option');
        option.disabled = true;
        option.textContent = 'No advisers found - add them in Account page';
        advSel.appendChild(option);
        return;
    }

    CR_ADVISERS.forEach(adviser => {
        const assigned = CR_CLASSROOMS.find(c => String(c.adviser_id || '') === String(adviser.id) && String(c.id || '') !== String(currentClassroomId || ''));
        const option = document.createElement('option');
        option.value = adviser.id;
        option.textContent = assigned ? `${adviser.name} (${assigned.section_name})` : adviser.name;
        if (String(adviser.id) === String(selectedAdviserId || '')) option.selected = true;
        advSel.appendChild(option);
    });
}

function openModal(modal) {
    modal.classList.remove('hidden');
    document.body.classList.add('overflow-hidden');
}

function closeModal(modal) {
    modal.classList.add('hidden');
    if (!document.querySelector('.fixed.inset-0.z-50:not(.hidden)')) {
        document.body.classList.remove('overflow-hidden');
    }
}

const addModal = document.getElementById('cr-add-modal');
const capModal = document.getElementById('cr-cap-modal');
const delModal = document.getElementById('cr-del-modal');
const mlModal  = document.getElementById('cr-masterlist-modal');
const linkModal = document.getElementById('cr-link-modal');
const reassignModal = document.getElementById('cr-reassign-modal');
const withdrawModal = document.getElementById('cr-withdraw-modal');

document.getElementById('cr-add-btn').addEventListener('click', function () { openModal(addModal); });
document.getElementById('cr-add-close').addEventListener('click', function () { closeModal(addModal); });
document.getElementById('cr-add-cancel').addEventListener('click', function () { closeModal(addModal); });
document.getElementById('cr-add-backdrop').addEventListener('click', function () { closeModal(addModal); });

function refreshPathwayOptions() {
    const gl = document.getElementById('cr-grade-level').value;
    const tr = document.getElementById('cr-track').value;
    const sel = document.getElementById('cr-pathway');
    const label = document.getElementById('cr-pathway-label');
    label.textContent = gl === 'Grade 11' ? 'Career Pathway' : 'Strand';
    const opts = (CR_CATALOG[gl] || []).filter(item => !tr || item.track === tr);
    sel.innerHTML = '<option value="">Select...</option>';
    opts.forEach(item => {
        const option = document.createElement('option');
        option.value = item.code;
        option.textContent = item.label;
        sel.appendChild(option);
    });
}

function populateSectionOptions(gradeLevel) {
    const secSel = document.getElementById('cr-section-name');
    const sections = sectionChoices(gradeLevel);
    secSel.innerHTML = '<option value="">Select Section...</option>';
    if (sections.length === 0) {
        const option = document.createElement('option');
        option.disabled = true;
        option.textContent = 'No sections found - add them in System Settings';
        secSel.appendChild(option);
        return;
    }
    sections.forEach(section => {
        const option = document.createElement('option');
        option.value = section.name;
        option.textContent = section.locked ? `${section.name} (Locked)` : section.name;
        option.disabled = section.locked;
        secSel.appendChild(option);
    });
}

function populateAdviserOptions() {
    const advSel = document.getElementById('cr-adviser-id');
    const advisers = adviserChoices();
    advSel.innerHTML = '<option value="">Select Adviser...</option>';
    if (advisers.length === 0) {
        const option = document.createElement('option');
        option.disabled = true;
        option.textContent = 'No advisers found - add them in Account page';
        advSel.appendChild(option);
        return;
    }
    advisers.forEach(adviser => {
        const option = document.createElement('option');
        option.value = adviser.id;
        option.textContent = adviser.locked ? `${adviser.name} (Locked)` : adviser.name;
        option.disabled = adviser.locked;
        advSel.appendChild(option);
    });
}

document.getElementById('cr-grade-level').addEventListener('change', function () {
    const gl = this.value;
    const trackWrap = document.getElementById('cr-track-wrap');
    const pathWrap = document.getElementById('cr-pathway-wrap');
    const secWrap = document.getElementById('cr-section-wrap');
    const advWrap = document.getElementById('cr-adviser-wrap');
    const capWrap = document.getElementById('cr-cap-wrap');
    const subWrap = document.getElementById('cr-submit-wrap');
    const trackSel = document.getElementById('cr-track');

    if (!gl) {
        [trackWrap, pathWrap, secWrap, advWrap, capWrap, subWrap].forEach(el => el.classList.add('hidden'));
        return;
    }

    trackSel.innerHTML = '<option value="">Select Track...</option>';
    (gl === 'Grade 11' ? ['Academic', 'Tech-Pro', 'ALS'] : ['Academic', 'TVL']).forEach(track => {
        const option = document.createElement('option');
        option.value = track;
        option.textContent = track;
        trackSel.appendChild(option);
    });

    trackWrap.classList.remove('hidden');
    pathWrap.classList.add('hidden');
    secWrap.classList.add('hidden');
    advWrap.classList.add('hidden');
    capWrap.classList.add('hidden');
    subWrap.classList.add('hidden');
});

document.getElementById('cr-track').addEventListener('change', function () {
    const gl = document.getElementById('cr-grade-level').value;
    const tr = this.value;
    if (!tr) return;

    refreshPathwayOptions();
    populateSectionOptions(gl);
    populateAdviserOptions();

    document.getElementById('cr-pathway-wrap').classList.remove('hidden');
    document.getElementById('cr-section-wrap').classList.remove('hidden');
    document.getElementById('cr-adviser-wrap').classList.remove('hidden');

    const cap = defaultCap(gl, tr);
    document.getElementById('cr-capacity').value = cap;
    document.getElementById('cr-cap-hint').textContent = `(default: ${cap})`;
    document.getElementById('cr-cap-wrap').classList.remove('hidden');
    document.getElementById('cr-submit-wrap').classList.remove('hidden');
});

document.querySelectorAll('.cr-capacity-btn').forEach(btn => btn.addEventListener('click', function () {
    document.getElementById('cr-cap-id').value = this.dataset.id;
    document.getElementById('cr-cap-section-name').textContent = this.dataset.name;
    document.getElementById('cr-cap-value').value = this.dataset.cap;
    populateEditAdviserOptions(this.dataset.adviserId || '', this.dataset.id || '');
    openModal(capModal);
}));
document.getElementById('cr-cap-close').addEventListener('click', function () { closeModal(capModal); });
document.getElementById('cr-cap-cancel').addEventListener('click', function () { closeModal(capModal); });
document.getElementById('cr-cap-backdrop').addEventListener('click', function () { closeModal(capModal); });
document.getElementById('cr-cap-minus').addEventListener('click', function () {
    const inp = document.getElementById('cr-cap-value');
    if (parseInt(inp.value, 10) > 1) inp.value = parseInt(inp.value, 10) - 1;
});
document.getElementById('cr-cap-plus').addEventListener('click', function () {
    const inp = document.getElementById('cr-cap-value');
    inp.value = parseInt(inp.value || '0', 10) + 1;
});

document.querySelectorAll('.cr-delete-btn').forEach(btn => btn.addEventListener('click', function () {
    document.getElementById('cr-del-id').value = this.dataset.id;
    document.getElementById('cr-del-name').textContent = this.dataset.name;
    openModal(delModal);
}));
document.getElementById('cr-del-close').addEventListener('click', function () { closeModal(delModal); });
document.getElementById('cr-del-cancel').addEventListener('click', function () { closeModal(delModal); });
document.getElementById('cr-del-backdrop').addEventListener('click', function () { closeModal(delModal); });

function renderReassignSectionOptions(student) {
    const select = document.getElementById('cr-reassign-section');
    const capacityInfo = document.getElementById('cr-reassign-capacity-info');
    
    // Get all sections for the student's grade level and current pathway
    const sectionsByPathway = {};
    CR_CLASSROOMS
        .filter(c => String(c.grade_level || '').toLowerCase() === String(student.grade_level || '').toLowerCase())
        .forEach(classroom => {
            const pathway = String(classroom.pathway_strand || '').toLowerCase();
            if (!sectionsByPathway[pathway]) sectionsByPathway[pathway] = [];
            sectionsByPathway[pathway].push(classroom);
        });
    
    // Get sections for current pathway first
    const currentPathway = String(student.pathway_strand || '').toLowerCase();
    const sections = sectionsByPathway[currentPathway] || [];
    
    // Store sections globally for use in change event listener
    window.CR_CURRENT_SECTIONS = sections;
    
    select.innerHTML = '<option value="">Select Section...</option>';
    
    if (sections.length === 0) {
        const option = document.createElement('option');
        option.disabled = true;
        option.textContent = 'No sections available for this pathway/grade level.';
        select.appendChild(option);
        return;
    }
    
    sections.forEach(section => {
        const enrolled = ENROLLED_DATA.filter(s => 
            String(s.assigned_section || '').toLowerCase() === String(section.section_name || '').toLowerCase() &&
            s.enrollment_status === 'enrolled'
        ).length;
        
        const capacity = parseInt(section.max_capacity || 40, 10);
        const isFull = enrolled >= capacity;
        const isCurrentSection = String(student.assigned_section || '').toLowerCase() === String(section.section_name || '').toLowerCase();
        
        const option = document.createElement('option');
        option.value = section.section_name;
        
        let displayText = section.section_name;
        if (isCurrentSection) {
            displayText += ' (Current)';
        } else if (isFull) {
            displayText += ` (Full: ${enrolled}/${capacity})`;
            option.disabled = true;
            option.style.color = '#dc2626';
        } else {
            displayText += ` (${enrolled}/${capacity} slots)`;
        }
        
        option.textContent = displayText;
        select.appendChild(option);
    });
}

function updateCapacityInfo(sectionName, sections) {
    const capacityInfo = document.getElementById('cr-reassign-capacity-info');
    const capacityText = document.getElementById('cr-reassign-capacity-text');
    
    if (!sectionName) {
        capacityInfo.classList.add('hidden');
        return;
    }
    
    const section = sections.find(s => String(s.section_name || '').toLowerCase() === String(sectionName || '').toLowerCase());
    if (!section) {
        capacityInfo.classList.add('hidden');
        return;
    }
    
    const enrolled = ENROLLED_DATA.filter(s => 
        String(s.assigned_section || '').toLowerCase() === String(sectionName || '').toLowerCase() &&
        s.enrollment_status === 'enrolled'
    ).length;
    
    const capacity = parseInt(section.max_capacity || 40, 10);
    const available = Math.max(0, capacity - enrolled);
    const isFull = enrolled >= capacity;
    
    capacityInfo.classList.remove('hidden');
    
    if (isFull) {
        capacityInfo.classList.remove('bg-blue-50', 'border-blue-100');
        capacityInfo.classList.add('bg-red-50', 'border-red-100');
        capacityText.classList.remove('text-blue-700');
        capacityText.classList.add('text-red-700');
        capacityText.innerHTML = `<span class="font-black">SECTION IS FULL</span> - ${enrolled}/${capacity} students enrolled. Cannot reassign until a slot opens.`;
    } else {
        capacityInfo.classList.remove('bg-red-50', 'border-red-100');
        capacityInfo.classList.add('bg-blue-50', 'border-blue-100');
        capacityText.classList.remove('text-red-700');
        capacityText.classList.add('text-blue-700');
        capacityText.innerHTML = `<span class="font-semibold">${available} slot(s) available</span> - ${enrolled}/${capacity} enrolled`;
    }
}

function bindMasterlistActions() {
    document.querySelectorAll('.cr-reassign-student-btn').forEach(btn => {
        btn.addEventListener('click', function () {
            const studentId = this.dataset.studentId;
            const student = ENROLLED_DATA.find(s => s.id == studentId);
            
            if (!student) {
                alert('Student data not found. Please try again.');
                return;
            }
            
            document.getElementById('cr-reassign-student-id').value = student.id || '';
            document.getElementById('cr-reassign-title').textContent = `Reassign ${masterlistName(student)}`;
            document.getElementById('cr-reassign-student-name').textContent = masterlistName(student);
            
            // Show current assignment info
            const currentInfo = document.getElementById('cr-reassign-current-info');
            const psLabel = pathwayLabel(student.grade_level, student.pathway_strand);
            currentInfo.textContent = `Currently in: ${student.assigned_section || '--'} | Pathway/Strand: ${psLabel}`;
            
            // Populate pathway options
            const pathwaySelect = document.getElementById('cr-reassign-pathway');
            const pathwayOptions = (CR_CATALOG[student.grade_level] || []);
            pathwaySelect.innerHTML = '<option value="">Keep Current...</option>';
            pathwayOptions.forEach(option => {
                const opt = document.createElement('option');
                opt.value = option.code;
                opt.textContent = option.label;
                if (String(option.code || '').toLowerCase() === String(student.pathway_strand || '').toLowerCase()) {
                    opt.selected = true;
                }
                pathwaySelect.appendChild(opt);
            });
            
            // Render section options based on current pathway
            renderReassignSectionOptions(student);
            openModal(reassignModal);
        });
    });

    document.querySelectorAll('.cr-withdraw-student-btn').forEach(btn => {
        btn.addEventListener('click', function () {
            const studentId = this.dataset.studentId;
            const student = ENROLLED_DATA.find(s => s.id == studentId);
            
            if (!student) {
                alert('Student data not found. Please try again.');
                return;
            }
            
            document.getElementById('cr-withdraw-student-id').value = student.id || '';
            document.getElementById('cr-withdraw-title').textContent = `Authorize Withdrawal for ${masterlistName(student)}`;
            document.getElementById('cr-withdraw-student-name').textContent = masterlistName(student);
            openModal(withdrawModal);
        });
    });
}

document.querySelectorAll('.cr-masterlist-btn').forEach(btn => btn.addEventListener('click', function () {
    const pathway = this.dataset.pathway;
    const grade = this.dataset.grade;
    const name = this.dataset.name;
    const classroomId = this.dataset.id;
    document.getElementById('cr-ml-title').textContent = name;
    // Store for print button
    document.getElementById('cr-ml-print').dataset.section = name;
    document.getElementById('cr-ml-print').dataset.classroomId = classroomId;

    const students = ENROLLED_DATA
        .filter(student => String(student.assigned_section || '').toLowerCase() === String(name || '').toLowerCase())
        .filter(student => !grade || String(student.grade_level || '').toLowerCase() === String(grade || '').toLowerCase())
        .filter(student => !pathway || String(student.pathway_strand || '').toLowerCase() === String(pathway || '').toLowerCase())
        .sort(compareStudents);

    const body = document.getElementById('cr-ml-body');
    if (students.length === 0) {
        body.innerHTML = `<div class="text-center py-10">
            <p class="text-sm font-semibold text-slate-500">No enrolled students in this section yet.</p>
            <p class="text-xs text-slate-400 mt-1">Students will appear here once they are encoded and assigned.</p>
        </div>`;
    } else {
        const totals = students.reduce((acc, student) => {
            const sex = String(student.sex || '').trim().toLowerCase();
            if (sex === 'male') acc.male += 1;
            else if (sex === 'female') acc.female += 1;
            acc.total += 1;
            return acc;
        }, { male: 0, female: 0, total: 0 });

        let rowNumber = 0;
        let lastRank = null;

        body.innerHTML = `<div class="mb-5 flex flex-col gap-3 md:flex-row md:items-start md:justify-between">
            <div class="flex flex-wrap gap-3">
                <div class="rounded-xl bg-blue-50 border border-blue-100 px-4 py-3 min-w-[140px]">
                    <p class="text-xs font-bold uppercase tracking-widest text-blue-500">Male</p>
                    <p class="text-2xl font-black text-blue-700">${totals.male}</p>
                </div>
                <div class="rounded-xl bg-pink-50 border border-pink-100 px-4 py-3 min-w-[140px]">
                    <p class="text-xs font-bold uppercase tracking-widest text-pink-500">Female</p>
                    <p class="text-2xl font-black text-pink-700">${totals.female}</p>
                </div>
                <div class="rounded-xl bg-emerald-50 border border-emerald-100 px-4 py-3 min-w-[160px]">
                    <p class="text-xs font-bold uppercase tracking-widest text-emerald-600">Total Students</p>
                    <p class="text-2xl font-black text-emerald-700">${totals.total}</p>
                </div>
            </div>
            <div class="text-right">
                <p class="text-xs font-semibold text-slate-400 uppercase tracking-widest">${grade} - ${name}</p>
                <p class="text-sm font-bold text-slate-600 mt-1">${pathwayLabel(grade, pathway)}</p>
            </div>
        </div>
        <table class="w-full text-left border-collapse text-sm">
            <thead class="bg-slate-50 text-xs uppercase text-slate-500 font-bold">
                <tr>
                    <th class="px-4 py-3">#</th>
                    <th class="px-4 py-3">Name</th>
                    <th class="px-4 py-3">LRN</th>
                    <th class="px-4 py-3">Gender</th>
                    <th class="px-4 py-3">Remarks</th>
                    <th class="px-4 py-3">Status</th>
                    <th class="px-4 py-3 text-center">Action</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                ${students.map(student => {
                    const currentRank = genderRank(student.sex);
                    if (lastRank !== currentRank) {
                        rowNumber = 1;
                        lastRank = currentRank;
                    } else {
                        rowNumber += 1;
                    }
                    const watchIssueType = String(student.watch_issue_type || '').trim();
                    const watchIssueDetails = String(student.watch_issue_details || '').trim();
                    const badge = student.enrollment_status === 'enrolled'
                        ? '<span class="px-2 py-0.5 bg-green-100 text-green-700 rounded-full text-xs font-bold">Enrolled</span>'
                        : '<span class="px-2 py-0.5 bg-blue-100 text-blue-700 rounded-full text-xs font-bold">For Encoding</span>';
                    const redFlagBadge = watchIssueType
                        ? `<span class="text-red-600 font-bold uppercase tracking-wide" title="${watchIssueDetails ? watchIssueDetails.replace(/"/g, '&quot;') : 'No additional notes provided in the watchlist.'}">FLAGGED</span>`
                        : '<span class="text-slate-300 font-semibold">--</span>';
                    return `<tr class="hover:bg-slate-50">
                        <td class="px-4 py-3 text-slate-400 font-semibold">${rowNumber}</td>
                        <td class="px-4 py-3 font-semibold text-slate-700">${masterlistName(student)}</td>
                        <td class="px-4 py-3 text-slate-500">${student.lrn || '--'}</td>
                        <td class="px-4 py-3 text-slate-500 font-semibold">${student.sex || '--'}</td>
                        <td class="px-4 py-3">${redFlagBadge}</td>
                        <td class="px-4 py-3">${badge}</td>
                        <td class="px-4 py-3">
                            <div class="flex items-center justify-center gap-2">
                                <button type="button" class="cr-reassign-student-btn inline-flex items-center justify-center w-9 h-9 rounded-lg bg-amber-50 text-amber-600 hover:bg-amber-100 transition-colors" data-student-id="${student.id || ''}" title="Reassign Section">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 3h4a2 2 0 012 2v4m-6 12H9a2 2 0 01-2-2v-4m10-4H8m0 0l3-3m-3 3l3 3"/></svg>
                                </button>
                                <button type="button" class="cr-withdraw-student-btn inline-flex items-center justify-center w-9 h-9 rounded-lg bg-red-50 text-red-600 hover:bg-red-100 transition-colors" data-student-id="${student.id || ''}" title="Withdraw">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6M9 7V4a1 1 0 011-1h4a1 1 0 011 1v3m-7 0h8"/></svg>
                                </button>
                            </div>
                        </td>
                    </tr>`;
                }).join('')}
            </tbody>
        </table>`;
        bindMasterlistActions();
    }

    openModal(mlModal);
}));

document.getElementById('cr-ml-close').addEventListener('click', function () { closeModal(mlModal); });
document.getElementById('cr-ml-backdrop').addEventListener('click', function () { closeModal(mlModal); });

// Group chat link modal
document.getElementById('cr-link-close').addEventListener('click', function () { closeModal(linkModal); });
document.getElementById('cr-link-cancel').addEventListener('click', function () { closeModal(linkModal); });
document.getElementById('cr-link-backdrop').addEventListener('click', function () { closeModal(linkModal); });

document.querySelectorAll('.cr-link-edit-btn').forEach(btn => btn.addEventListener('click', function () {
    document.getElementById('cr-link-classroom-id').value = this.dataset.id;
    document.getElementById('cr-link-url-input').value = this.dataset.url || '';
    openModal(linkModal);
}));
document.getElementById('cr-ml-print').addEventListener('click', function () {
    const section = this.dataset.section || '';
    const classroomId = this.dataset.classroomId || '';
    if (section) {
        window.open('../print_masterlist.php?section=' + encodeURIComponent(section) + '&classroom_id=' + classroomId, '_blank');
    }
});
document.getElementById('cr-reassign-close').addEventListener('click', function () { closeModal(reassignModal); });
document.getElementById('cr-reassign-cancel').addEventListener('click', function () { closeModal(reassignModal); });
document.getElementById('cr-reassign-backdrop').addEventListener('click', function () { closeModal(reassignModal); });

// Section select change listener for capacity info
document.getElementById('cr-reassign-section').addEventListener('change', function() {
    const sections = window.CR_CURRENT_SECTIONS || [];
    updateCapacityInfo(this.value, sections);
});

// Pathway change listener for reassign modal
document.getElementById('cr-reassign-pathway').addEventListener('change', function() {
    const studentIdInput = document.getElementById('cr-reassign-student-id');
    const student = ENROLLED_DATA.find(s => s.id == studentIdInput.value);
    
    if (student && this.value !== '') {
        // Update student object with new pathway
        const updatedStudent = { ...student, pathway_strand: this.value };
        renderReassignSectionOptions(updatedStudent);
    } else {
        // Reset to current pathway
        renderReassignSectionOptions(student);
    }
});

document.getElementById('cr-withdraw-close').addEventListener('click', function () { closeModal(withdrawModal); });
document.getElementById('cr-withdraw-cancel').addEventListener('click', function () { closeModal(withdrawModal); });
document.getElementById('cr-withdraw-backdrop').addEventListener('click', function () { closeModal(withdrawModal); });

// Classroom live search
const crSearchInput = document.getElementById('cr-search-input');
if (crSearchInput) {
    crSearchInput.addEventListener('input', function() {
        const term = this.value.toLowerCase().trim();
        document.querySelectorAll('.cr-card').forEach(card => {
            const textToSearch = card.getAttribute('data-search') || '';
            if (textToSearch.includes(term)) {
                card.classList.remove('hidden');
            } else {
                card.classList.add('hidden');
            }
        });
    });
}
</script>

