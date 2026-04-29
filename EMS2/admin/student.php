<?php
require_once __DIR__ . '/../pathway_strand_catalog.php';

$db_host = 'localhost';
$db_user = 'root';
$db_pass = '';
$db_name = 'dranhswin';
$conn = new mysqli($db_host, $db_user, $db_pass, $db_name);

$student_rows = [];
$db_error = '';
$toast_message = '';
$toast_type = 'success';
$catalog_for_js = [
    'Grade 11' => get_pathway_strand_options('Grade 11'),
    'Grade 12' => get_pathway_strand_options('Grade 12')
];

function student_full_name($row) {
    $name = ($row['last_name'] ?? '') . ', ' . ($row['first_name'] ?? '');
    if (!empty($row['middle_name'])) $name .= ' ' . strtoupper(substr($row['middle_name'], 0, 1)) . '.';
    if (!empty($row['extension_name'])) $name .= ' ' . $row['extension_name'];
    return trim($name);
}

function student_status_badge($row) {
    $status = $row['enrollment_status'] ?? 'for_evaluation';
    switch ($status) {
        case 'for_encoding':
            return '<span class="px-2.5 py-1 bg-blue-100 text-blue-700 rounded-full text-xs font-bold">For Encoding</span>';
        case 'enrolled':
            return '<span class="px-2.5 py-1 bg-green-100 text-green-700 rounded-full text-xs font-bold">Enrolled</span>';
        case 'withdrawn':
            return '<span class="px-2.5 py-1 bg-red-100 text-red-700 rounded-full text-xs font-bold">For Withdrawal</span>';
        case 'for_evaluation':
        default:
            return '<span class="px-2.5 py-1 bg-amber-100 text-amber-700 rounded-full text-xs font-bold">For Evaluation</span>';
    }
}

function verify_current_user_password($conn, $user_id, $password) {
    if (!$user_id || $password === '') return false;
    $stmt = $conn->prepare("SELECT password FROM users WHERE id = ?");
    if (!$stmt) return false;
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result ? $result->fetch_assoc() : null;
    $stmt->close();
    return $row ? password_verify($password, $row['password']) : false;
}

if ($conn->connect_error) {
    $db_error = 'Database connection failed.';
} else {
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
        if ($_POST['action'] === 'update_student') {
            $student_id = (int) ($_POST['student_id'] ?? 0);
            $grade_level = trim($_POST['grade_level'] ?? '');
            $track = trim($_POST['track'] ?? '');
            $pathway_code = get_pathway_strand_code($grade_level, trim($_POST['pathway_strand'] ?? ''));

            // Handle ID photo upload — save as pic_(lrn).jpg
            if (isset($_FILES['id_photo_file']) && $_FILES['id_photo_file']['error'] === UPLOAD_ERR_OK) {
                $lrn_for_photo = trim($_POST['lrn'] ?? '');
                $ext = strtolower(pathinfo($_FILES['id_photo_file']['name'], PATHINFO_EXTENSION));
                if (!in_array($ext, ['jpg','jpeg','png','webp'])) $ext = 'jpg';
                $fname = 'pic_' . ($lrn_for_photo ?: $student_id) . '.' . $ext;
                $fpath = __DIR__ . '/../uploads/id_photos/' . $fname;
                if (move_uploaded_file($_FILES['id_photo_file']['tmp_name'], $fpath)) {
                    $photo_rel = 'uploads/id_photos/' . $fname;
                    // Upsert encodings photo
                    $chk2 = $conn->prepare("SELECT id FROM encodings WHERE student_id=?");
                    if ($chk2) { $chk2->bind_param("i",$student_id); $chk2->execute(); $ex2=$chk2->get_result()->num_rows>0; $chk2->close(); }
                    if (!empty($ex2)) {
                        $pu = $conn->prepare("UPDATE encodings SET id_photo_path=?, has_id_photo=1 WHERE student_id=?");
                        if ($pu) { $pu->bind_param("si",$photo_rel,$student_id); $pu->execute(); $pu->close(); }
                    } else {
                        // Create minimal encodings row with just the photo
                        $pi = $conn->prepare("INSERT INTO encodings (student_id,lrn,student_name,grade_level,track,pathway_strand,has_id_photo,id_photo_path) VALUES (?,?,?,?,?,?,1,?)");
                        if ($pi) {
                            $sn = trim(($_POST['last_name']??'').', '.($_POST['first_name']??''));
                            $pi->bind_param("issssss",$student_id,$lrn_for_photo,$sn,$grade_level,$track,$pathway_code,$photo_rel);
                            $pi->execute(); $pi->close();
                        }
                    }
                }
            }
            $stmt = $conn->prepare("UPDATE students SET
                lrn = ?, last_name = ?, first_name = ?, middle_name = ?, extension_name = ?,
                birthdate = ?, age = ?, sex = ?, place_of_birth = ?, mother_tongue = ?, religion = ?,
                school_year = ?, grade_level = ?, student_type = ?, semester = ?, track = ?, pathway_strand = ?,
                street = ?, province = ?, city = ?, barangay = ?, zip_code = ?, living_with = ?,
                father_contact = ?, mother_contact = ?, guardian_contact = ?,
                prev_school = ?, prev_school_year = ?, prev_section = ?
                WHERE id = ?");

            if ($stmt) {
                $age = ($_POST['age'] ?? '') !== '' ? (int) $_POST['age'] : null;
                $params = [
                    $_POST['lrn'], $_POST['last_name'], $_POST['first_name'], $_POST['middle_name'], $_POST['extension_name'],
                    $_POST['birthdate'], $age, $_POST['sex'], $_POST['place_of_birth'], $_POST['mother_tongue'], $_POST['religion'],
                    $_POST['school_year'], $grade_level, $_POST['student_type'], $_POST['semester'], $track, $pathway_code,
                    $_POST['street'], $_POST['province'], $_POST['city'], $_POST['barangay'], $_POST['zip_code'], $_POST['living_with'],
                    $_POST['father_contact'] ?? '', $_POST['mother_contact'] ?? '', $_POST['guardian_contact'] ?? '',
                    $_POST['prev_school'], $_POST['prev_school_year'], $_POST['prev_section'],
                    $student_id
                ];
                $types = str_repeat('s', count($params) - 1) . 'i';
                $stmt->bind_param($types, ...$params);
                if ($stmt->execute()) {
                    $toast_message = 'Student record updated successfully.';
                } else {
                    $toast_message = 'Failed to update student record.';
                    $toast_type = 'error';
                }
                $stmt->close();
            } else {
                $toast_message = 'Unable to prepare student update.';
                $toast_type = 'error';
            }
        } elseif ($_POST['action'] === 'verify_withdraw_password') {
            $user_id = $_SESSION['user_id'] ?? 0;
            $password = $_POST['admin_password'] ?? '';
            $sid = (int)($_POST['student_id'] ?? 0);
            if (!verify_current_user_password($conn, $user_id, $password)) {
                $toast_message = 'Incorrect password. Withdrawal was not authorized.';
                $toast_type = 'error';
            } elseif ($sid > 0) {
                $stmt = $conn->prepare("UPDATE students SET enrollment_status = 'withdrawn' WHERE id = ?");
                if ($stmt) { $stmt->bind_param("i", $sid); $stmt->execute(); $stmt->close(); }
                $toast_message = 'Student has been withdrawn successfully.';
            }
        }
    }

    $res = $conn->query("SELECT s.*, COALESCE(e.id_photo_path, '') AS _id_photo_path FROM students s LEFT JOIN encodings e ON e.student_id = s.id WHERE s.enrollment_status != 'withdrawn' OR s.enrollment_status IS NULL ORDER BY s.created_at DESC, s.id DESC");
    if (!$res) {
        $res = $conn->query("SELECT *, '' AS _id_photo_path FROM students WHERE enrollment_status != 'withdrawn' OR enrollment_status IS NULL ORDER BY created_at DESC, id DESC");
    }
    if ($res) {
        while ($row = $res->fetch_assoc()) $student_rows[] = $row;
        $res->close();
    } else {
        $db_error = 'Unable to load student records.';
    }
    $conn->close();
}
?>

<?php if ($toast_message): ?>
<div class="mb-6 rounded-xl border px-5 py-4 text-sm font-semibold <?php echo $toast_type === 'error' ? 'bg-red-50 border-red-200 text-red-700' : 'bg-emerald-50 border-emerald-200 text-emerald-700'; ?>">
    <?php echo htmlspecialchars($toast_message); ?>
</div>
<?php endif; ?>

<div class="bg-white rounded-xl shadow-sm border border-slate-100 overflow-hidden">
    <div class="px-6 py-4 border-b border-slate-100">
        <h3 class="font-heading font-bold text-lg text-dranhs-dark">Student List</h3>
        <p class="text-sm text-slate-500">View enrollment details, edit records, open print choices, and require password before withdrawal.</p>
    </div>
    <div class="px-6 py-3 border-b border-slate-100 bg-slate-50">
        <div class="relative max-w-sm">
            <svg class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-4.35-4.35M17 11A6 6 0 1 1 5 11a6 6 0 0 1 12 0z"/></svg>
            <input type="text" id="student-search" placeholder="Search name, LRN, track, pathway..." class="w-full pl-9 pr-4 py-2 text-sm border border-slate-300 rounded-lg focus:outline-none focus:border-dranhs-green focus:ring-2 focus:ring-dranhs-green/20 bg-white">
        </div>
    </div>

    <?php if ($db_error): ?>
        <div class="px-6 py-5 text-sm font-semibold text-red-600 bg-red-50 border-t border-red-100"><?php echo htmlspecialchars($db_error); ?></div>
    <?php elseif (empty($student_rows)): ?>
        <div class="px-6 py-10 text-center">
            <p class="text-sm font-semibold text-slate-600">No students found yet.</p>
            <p class="text-sm text-slate-400 mt-1">Submitted enrollments will appear here automatically.</p>
        </div>
    <?php else: ?>
    <div class="overflow-x-auto">
        <table class="w-full text-left border-collapse">
            <thead class="bg-slate-50 text-xs uppercase text-slate-500 font-bold">
                <tr>
                    <th class="px-6 py-3 tracking-wider">Name</th>
                    <th class="px-6 py-3 tracking-wider">Grade Level / Section</th>
                    <th class="px-6 py-3 tracking-wider">Track</th>
                    <th class="px-6 py-3 tracking-wider">Pathway / Strand</th>
                    <th class="px-6 py-3 tracking-wider">Status</th>
                    <th class="px-6 py-3 tracking-wider">Action</th>
                </tr>
            </thead>
            <tbody id="student-tbody" class="divide-y divide-slate-100 text-sm">
                <?php foreach ($student_rows as $row): ?>
                <tr class="hover:bg-slate-50 transition-colors">
                    <td class="px-6 py-4">
                        <div class="flex items-center gap-3">
                            <?php
                                $photo = $row['_id_photo_path'] ?? '';
                                $initials = strtoupper(substr($row['first_name'] ?? 'S', 0, 1) . substr($row['last_name'] ?? '', 0, 1));
                            ?>
                            <?php if ($photo): ?>
                                <img src="../<?php echo htmlspecialchars($photo); ?>" alt="Photo" class="w-9 h-9 rounded-full object-cover border border-slate-200 shrink-0">
                            <?php else: ?>
                                <img src="https://ui-avatars.com/api/?name=<?php echo urlencode($initials); ?>&background=009b5a&color=fff&size=64&bold=true" alt="<?php echo htmlspecialchars($initials); ?>" class="w-9 h-9 rounded-full shrink-0">
                            <?php endif; ?>
                            <div>
                                <div class="font-semibold text-slate-700"><?php echo htmlspecialchars(student_full_name($row)); ?></div>
                                <div class="text-xs text-slate-400 mt-0.5"><?php echo htmlspecialchars($row['lrn'] ?: '--'); ?></div>
                            </div>
                        </div>
                    </td>
                    <td class="px-6 py-4 text-slate-600">
                        <div class="font-semibold"><?php echo htmlspecialchars($row['grade_level'] ?: '--'); ?></div>
                        <?php if (!empty($row['assigned_section'])): ?>
                            <div class="text-xs font-bold mt-0.5 <?php echo ($row['grade_level'] === 'Grade 11') ? 'text-violet-600' : 'text-pink-600'; ?>">
                                <?php echo htmlspecialchars($row['assigned_section']); ?>
                            </div>
                        <?php endif; ?>
                    </td>
                    <td class="px-6 py-4 text-slate-600"><?php echo htmlspecialchars($row['track'] ?: '--'); ?></td>
                    <td class="px-6 py-4 text-slate-600"><?php echo htmlspecialchars(get_pathway_strand_label($row['grade_level'] ?? '', $row['pathway_strand'] ?? '')); ?></td>
                    <td class="px-6 py-4"><?php echo student_status_badge($row); ?></td>
                    <td class="px-6 py-4">
                        <div class="flex items-center gap-2">
                            <button type="button" class="view-student-btn inline-flex items-center justify-center w-9 h-9 rounded-lg bg-blue-50 text-blue-600 hover:bg-blue-100 transition-colors" data-student-id="<?php echo (int) $row['id']; ?>" title="View">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"></path></svg>
                            </button>
                            <button type="button" class="print-student-btn inline-flex items-center justify-center w-9 h-9 rounded-lg bg-slate-100 text-slate-700 hover:bg-slate-200 transition-colors <?php echo ($row['enrollment_status'] !== 'enrolled') ? 'hidden' : ''; ?>" data-student-id="<?php echo (int) $row['id']; ?>" title="Print">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-3a2 2 0 00-2-2H5a2 2 0 00-2 2v3a2 2 0 002 2h2m10 0H7m10 0v4H7v-4m10-8V3H7v6h10z"></path></svg>
                            </button>
                            <button type="button" class="withdraw-student-btn inline-flex items-center justify-center w-9 h-9 rounded-lg bg-red-50 text-red-600 hover:bg-red-100 transition-colors" data-student-id="<?php echo (int) $row['id']; ?>" title="Withdraw">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6M9 7V4a1 1 0 011-1h4a1 1 0 011 1v3m-7 0h8"></path></svg>
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

<div id="student-view-modal" class="fixed inset-0 z-40 hidden">
    <div class="absolute inset-0 bg-slate-900/60 modal-close"></div>
    <div class="relative z-10 min-h-screen flex items-start justify-center p-4 py-8">
        <div class="w-full max-w-4xl bg-slate-50 rounded-2xl shadow-2xl border border-slate-200 overflow-hidden">
            <!-- Modal Header -->
            <div class="bg-dranhs-dark px-6 py-5 flex items-center justify-between">
                <div>
                    <p class="text-xs font-bold uppercase tracking-[0.2em] text-dranhs-green mb-1">Enrollment Record</p>
                    <h3 id="view-student-name" class="font-heading font-black text-2xl text-white">Student Name</h3>
                </div>
                <div class="flex items-center gap-2">
                    <button type="button" id="view-to-edit-btn" class="inline-flex items-center gap-2 px-4 py-2 rounded-lg bg-amber-500 text-white text-sm font-bold hover:bg-amber-600 transition-colors">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path></svg>
                        Edit
                    </button>
                    <button type="button" class="modal-close inline-flex items-center justify-center w-10 h-10 rounded-full bg-white/10 text-white hover:bg-white/20 transition-colors text-xl">&times;</button>
                </div>
            </div>
            <!-- Scrollable body -->
            <div class="max-h-[80vh] overflow-y-auto p-6 space-y-5" id="student-view-grid"></div>
        </div>
    </div>
</div>

<div id="student-edit-modal" class="fixed inset-0 z-50 hidden">
    <div class="absolute inset-0 bg-slate-900/60 modal-close"></div>
    <div class="relative z-10 min-h-screen flex items-start justify-center p-4 py-8">
        <div class="w-full max-w-4xl bg-slate-50 rounded-2xl shadow-2xl border border-slate-200 overflow-hidden">
            <div class="bg-dranhs-dark px-6 py-5 flex items-center justify-between">
                <div>
                    <p class="text-xs font-bold uppercase tracking-[0.2em] text-amber-400 mb-1">Edit Enrollment Record</p>
                    <h3 id="edit-student-title" class="font-heading font-black text-2xl text-white">Edit Student</h3>
                </div>
                <button type="button" class="modal-close inline-flex items-center justify-center w-10 h-10 rounded-full bg-white/10 text-white hover:bg-white/20 transition-colors text-xl">&times;</button>
            </div>
            <form method="POST" id="edit-student-form" enctype="multipart/form-data" class="max-h-[80vh] overflow-y-auto p-6 space-y-5">
                <input type="hidden" name="action" value="update_student">
                <input type="hidden" name="student_id" id="edit-student-id">

                <!-- ID Photo -->
                <div class="bg-white rounded-2xl shadow-sm border border-slate-200 overflow-hidden relative">
                    <div class="absolute top-0 left-0 w-2 h-full rounded-l-2xl bg-dranhs-green"></div>
                    <div class="px-6 pt-5 pb-3 border-b border-slate-100 ml-2">
                        <h4 class="text-lg font-heading font-black uppercase tracking-tight text-dranhs-green">ID Photo</h4>
                    </div>
                    <div class="p-6 ml-2 flex flex-col sm:flex-row items-start gap-6">
                        <!-- Preview -->
                        <div class="shrink-0 w-28 h-36 rounded-xl border-2 border-dashed border-slate-300 overflow-hidden bg-slate-50 flex flex-col items-center justify-center" id="edit-photo-preview-wrap">
                            <img id="edit-photo-preview" src="" class="w-full h-full object-cover hidden" alt="ID Photo">
                            <svg id="edit-photo-placeholder" class="w-10 h-10 text-slate-300" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/></svg>
                            <span id="edit-photo-label" class="text-xs text-slate-400 font-semibold mt-1">No ID Photo</span>
                        </div>
                        <!-- Controls -->
                        <div class="flex-1 space-y-3">
                            <p class="text-xs text-slate-500">Upload a new photo or capture from webcam. File will be saved as <code class="bg-slate-100 px-1 rounded text-xs">pic_(LRN).jpg</code></p>
                            <video id="edit-webcam" class="hidden w-full max-w-xs rounded-xl border-2 border-dranhs-green" autoplay playsinline></video>
                            <canvas id="edit-canvas" class="hidden"></canvas>
                            <div class="flex flex-wrap gap-2">
                                <button type="button" id="edit-cam-start" class="px-3 py-2 rounded-lg bg-dranhs-green text-white text-xs font-bold hover:bg-emerald-700 transition-colors">Open Webcam</button>
                                <button type="button" id="edit-cam-capture" class="hidden px-3 py-2 rounded-lg bg-blue-600 text-white text-xs font-bold hover:bg-blue-700 transition-colors">Capture</button>
                                <button type="button" id="edit-cam-stop" class="hidden px-3 py-2 rounded-lg bg-slate-200 text-slate-700 text-xs font-bold hover:bg-slate-300 transition-colors">Stop</button>
                                <label class="px-3 py-2 rounded-lg bg-amber-50 text-amber-700 border border-amber-200 text-xs font-bold cursor-pointer hover:bg-amber-100 transition-colors">
                                    Upload Photo
                                    <input type="file" name="id_photo_file" id="edit-photo-upload" accept="image/*" class="hidden">
                                </label>
                                <button type="button" id="edit-photo-clear" class="hidden px-3 py-2 rounded-lg bg-red-50 text-red-600 text-xs font-bold hover:bg-red-100 transition-colors">Clear</button>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- General Info -->
                <div class="bg-white rounded-2xl shadow-sm border border-slate-200 overflow-hidden relative">
                    <div class="absolute top-0 left-0 w-2 h-full rounded-l-2xl bg-dranhs-green"></div>
                    <div class="px-6 pt-5 pb-3 border-b border-slate-100 ml-2">
                        <h4 class="text-lg font-heading font-black uppercase tracking-tight text-dranhs-green">General Information</h4>
                    </div>
                    <div class="p-6 ml-2 grid grid-cols-1 md:grid-cols-3 gap-5">
                        <div>
                            <label class="block text-xs font-bold uppercase tracking-wider text-slate-500 mb-2">Learner Category</label>
                            <select name="student_type" id="edit-student-type" class="w-full bg-white border-2 border-slate-300 px-4 py-3 rounded-xl text-slate-800 text-sm font-medium focus:border-dranhs-green focus:ring-2 focus:ring-dranhs-green/20 outline-none">
                                <option value="">Select Category...</option>
                                <option value="Grade 10 DRANHS Student">Grade 10 DRANHS Student</option>
                                <option value="Transferee">Transferee</option>
                                <option value="Balik-Aral(Returnee)">Balik-Aral (Returnee)</option>
                                <option value="Repeater">Repeater</option>
                                <option value="Old Student (Repeater)">Old Student (Repeater)</option>
                                <option value="ALS">ALS</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-xs font-bold uppercase tracking-wider text-slate-500 mb-2">School Year</label>
                            <input type="text" name="school_year" id="edit-school-year" class="w-full bg-white border-2 border-slate-300 px-4 py-3 rounded-xl text-slate-800 text-sm font-medium focus:border-dranhs-green focus:ring-2 focus:ring-dranhs-green/20 outline-none">
                        </div>
                        <div>
                            <label class="block text-xs font-bold uppercase tracking-wider text-slate-500 mb-2">Grade Level</label>
                            <select name="grade_level" id="edit-grade-level" class="w-full bg-white border-2 border-slate-300 px-4 py-3 rounded-xl text-slate-800 text-sm font-medium focus:border-dranhs-green focus:ring-2 focus:ring-dranhs-green/20 outline-none">
                                <option value="Grade 11">Grade 11</option>
                                <option value="Grade 12">Grade 12</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-xs font-bold uppercase tracking-wider text-slate-500 mb-2">Semester</label>
                            <select name="semester" id="edit-semester" class="w-full bg-white border-2 border-slate-300 px-4 py-3 rounded-xl text-slate-800 text-sm font-medium focus:border-dranhs-green focus:ring-2 focus:ring-dranhs-green/20 outline-none">
                                <option value="1st">1st Semester</option>
                                <option value="2nd">2nd Semester</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-xs font-bold uppercase tracking-wider text-slate-500 mb-2">LRN</label>
                            <input type="text" name="lrn" id="edit-lrn" maxlength="12" class="w-full bg-white border-2 border-slate-300 px-4 py-3 rounded-xl text-slate-800 text-sm font-medium focus:border-dranhs-green focus:ring-2 focus:ring-dranhs-green/20 outline-none">
                        </div>
                        <div>
                            <label class="block text-xs font-bold uppercase tracking-wider text-slate-500 mb-2">Track</label>
                            <select name="track" id="edit-track" class="w-full bg-white border-2 border-slate-300 px-4 py-3 rounded-xl text-slate-800 text-sm font-medium focus:border-dranhs-green focus:ring-2 focus:ring-dranhs-green/20 outline-none">
                                <option value="">Select Track...</option>
                                <option value="Academic">Academic</option>
                                <option value="Tech-Pro">Tech-Pro</option>
                                <option value="TVL">TVL</option>
                            </select>
                        </div>
                        <div class="md:col-span-2">
                            <label class="block text-xs font-bold uppercase tracking-wider text-slate-500 mb-2">Pathway / Strand</label>
                            <select name="pathway_strand" id="edit-pathway-strand" class="w-full bg-white border-2 border-slate-300 px-4 py-3 rounded-xl text-slate-800 text-sm font-medium focus:border-dranhs-green focus:ring-2 focus:ring-dranhs-green/20 outline-none">
                                <option value="">Select Track first...</option>
                            </select>
                        </div>
                    </div>
                </div>

                <!-- Personal Info -->
                <div class="bg-white rounded-2xl shadow-sm border border-slate-200 overflow-hidden relative">
                    <div class="absolute top-0 left-0 w-2 h-full rounded-l-2xl" style="background:#6d28d9"></div>
                    <div class="px-6 pt-5 pb-3 border-b border-slate-100 ml-2">
                        <h4 class="text-lg font-heading font-black uppercase tracking-tight" style="color:#6d28d9">Learner's Personal Information</h4>
                    </div>
                    <div class="p-6 ml-2 grid grid-cols-1 md:grid-cols-3 gap-5">
                        <div>
                            <label class="block text-xs font-bold uppercase tracking-wider text-slate-500 mb-2">Last Name</label>
                            <input type="text" name="last_name" id="edit-last-name" class="w-full bg-white border-2 border-slate-300 px-4 py-3 rounded-xl text-slate-800 text-sm font-medium focus:border-violet-600 focus:ring-2 focus:ring-violet-600/20 outline-none">
                        </div>
                        <div>
                            <label class="block text-xs font-bold uppercase tracking-wider text-slate-500 mb-2">First Name</label>
                            <input type="text" name="first_name" id="edit-first-name" class="w-full bg-white border-2 border-slate-300 px-4 py-3 rounded-xl text-slate-800 text-sm font-medium focus:border-violet-600 focus:ring-2 focus:ring-violet-600/20 outline-none">
                        </div>
                        <div>
                            <label class="block text-xs font-bold uppercase tracking-wider text-slate-500 mb-2">Middle Name</label>
                            <input type="text" name="middle_name" id="edit-middle-name" class="w-full bg-white border-2 border-slate-300 px-4 py-3 rounded-xl text-slate-800 text-sm font-medium focus:border-violet-600 focus:ring-2 focus:ring-violet-600/20 outline-none">
                        </div>
                        <div>
                            <label class="block text-xs font-bold uppercase tracking-wider text-slate-500 mb-2">Ext. Name</label>
                            <input type="text" name="extension_name" id="edit-extension-name" placeholder="Jr., III" class="w-full bg-white border-2 border-slate-300 px-4 py-3 rounded-xl text-slate-800 text-sm font-medium focus:border-violet-600 focus:ring-2 focus:ring-violet-600/20 outline-none">
                        </div>
                        <div>
                            <label class="block text-xs font-bold uppercase tracking-wider text-slate-500 mb-2">Birthdate</label>
                            <input type="date" name="birthdate" id="edit-birthdate" class="w-full bg-white border-2 border-slate-300 px-4 py-3 rounded-xl text-slate-800 text-sm font-medium focus:border-violet-600 focus:ring-2 focus:ring-violet-600/20 outline-none">
                        </div>
                        <div>
                            <label class="block text-xs font-bold uppercase tracking-wider text-slate-500 mb-2">Age <span class="text-slate-400 font-normal normal-case">(auto-calculated)</span></label>
                            <input type="number" name="age" id="edit-age" readonly class="w-full bg-slate-100 border-2 border-slate-200 px-4 py-3 rounded-xl text-slate-600 text-sm font-medium outline-none cursor-not-allowed">
                        </div>
                        <div>
                            <label class="block text-xs font-bold uppercase tracking-wider text-slate-500 mb-2">Sex</label>
                            <select name="sex" id="edit-sex" class="w-full bg-white border-2 border-slate-300 px-4 py-3 rounded-xl text-slate-800 text-sm font-medium focus:border-violet-600 focus:ring-2 focus:ring-violet-600/20 outline-none">
                                <option value="">Select...</option>
                                <option value="Male">Male</option>
                                <option value="Female">Female</option>
                            </select>
                        </div>
                        <div class="md:col-span-2">
                            <label class="block text-xs font-bold uppercase tracking-wider text-slate-500 mb-2">Place of Birth</label>
                            <input type="text" name="place_of_birth" id="edit-place-of-birth" placeholder="Municipality / City" class="w-full bg-white border-2 border-slate-300 px-4 py-3 rounded-xl text-slate-800 text-sm font-medium focus:border-violet-600 focus:ring-2 focus:ring-violet-600/20 outline-none">
                        </div>
                        <div>
                            <label class="block text-xs font-bold uppercase tracking-wider text-slate-500 mb-2">Mother Tongue</label>
                            <input type="text" name="mother_tongue" id="edit-mother-tongue" class="w-full bg-white border-2 border-slate-300 px-4 py-3 rounded-xl text-slate-800 text-sm font-medium focus:border-violet-600 focus:ring-2 focus:ring-violet-600/20 outline-none">
                        </div>
                        <div>
                            <label class="block text-xs font-bold uppercase tracking-wider text-slate-500 mb-2">Religion</label>
                            <input type="text" name="religion" id="edit-religion" class="w-full bg-white border-2 border-slate-300 px-4 py-3 rounded-xl text-slate-800 text-sm font-medium focus:border-violet-600 focus:ring-2 focus:ring-violet-600/20 outline-none">
                        </div>
                    </div>
                </div>

                <!-- Address -->
                <div class="bg-white rounded-2xl shadow-sm border border-slate-200 overflow-hidden relative">
                    <div class="absolute top-0 left-0 w-2 h-full rounded-l-2xl" style="background:#0ea5e9"></div>
                    <div class="px-6 pt-5 pb-3 border-b border-slate-100 ml-2">
                        <h4 class="text-lg font-heading font-black uppercase tracking-tight" style="color:#0ea5e9">Current Address</h4>
                    </div>
                    <div class="p-6 ml-2 grid grid-cols-1 md:grid-cols-3 gap-5">
                        <div class="md:col-span-3">
                            <label class="block text-xs font-bold uppercase tracking-wider text-slate-500 mb-2">Street / Purok / Sitio</label>
                            <input type="text" name="street" id="edit-street" class="w-full bg-white border-2 border-slate-300 px-4 py-3 rounded-xl text-slate-800 text-sm font-medium focus:border-sky-500 focus:ring-2 focus:ring-sky-500/20 outline-none">
                        </div>
                        <div>
                            <label class="block text-xs font-bold uppercase tracking-wider text-slate-500 mb-2">Barangay</label>
                            <input type="text" name="barangay" id="edit-barangay" class="w-full bg-white border-2 border-slate-300 px-4 py-3 rounded-xl text-slate-800 text-sm font-medium focus:border-sky-500 focus:ring-2 focus:ring-sky-500/20 outline-none">
                        </div>
                        <div>
                            <label class="block text-xs font-bold uppercase tracking-wider text-slate-500 mb-2">Municipality / City</label>
                            <input type="text" name="city" id="edit-city" class="w-full bg-white border-2 border-slate-300 px-4 py-3 rounded-xl text-slate-800 text-sm font-medium focus:border-sky-500 focus:ring-2 focus:ring-sky-500/20 outline-none">
                        </div>
                        <div>
                            <label class="block text-xs font-bold uppercase tracking-wider text-slate-500 mb-2">Province</label>
                            <input type="text" name="province" id="edit-province" class="w-full bg-white border-2 border-slate-300 px-4 py-3 rounded-xl text-slate-800 text-sm font-medium focus:border-sky-500 focus:ring-2 focus:ring-sky-500/20 outline-none">
                        </div>
                        <div>
                            <label class="block text-xs font-bold uppercase tracking-wider text-slate-500 mb-2">ZIP Code</label>
                            <input type="text" name="zip_code" id="edit-zip-code" class="w-full bg-white border-2 border-slate-300 px-4 py-3 rounded-xl text-slate-800 text-sm font-medium focus:border-sky-500 focus:ring-2 focus:ring-sky-500/20 outline-none">
                        </div>
                        <div>
                            <label class="block text-xs font-bold uppercase tracking-wider text-slate-500 mb-2">Living With</label>
                            <select name="living_with" id="edit-living-with" class="w-full bg-white border-2 border-slate-300 px-4 py-3 rounded-xl text-slate-800 text-sm font-medium focus:border-sky-500 focus:ring-2 focus:ring-sky-500/20 outline-none">
                                <option value="">Select...</option>
                                <option value="Parents">Parents</option>
                                <option value="Relatives">Relatives</option>
                                <option value="Guardian">Guardian</option>
                                <option value="Boarding House / Dorm">Boarding House / Dorm</option>
                                <option value="Others">Others</option>
                            </select>
                        </div>
                    </div>
                </div>

                <!-- Parents -->
                <div class="bg-white rounded-2xl shadow-sm border border-slate-200 overflow-hidden relative">
                    <div class="absolute top-0 left-0 w-2 h-full rounded-l-2xl" style="background:#f59e0b"></div>
                    <div class="px-6 pt-5 pb-3 border-b border-slate-100 ml-2">
                        <h4 class="text-lg font-heading font-black uppercase tracking-tight" style="color:#f59e0b">Parent's / Guardian's Information</h4>
                    </div>
                    <div class="p-6 ml-2 grid grid-cols-1 md:grid-cols-3 gap-5">
                        <div>
                            <label class="block text-xs font-bold uppercase tracking-wider text-slate-500 mb-2">Father Contact</label>
                            <input type="text" name="father_contact" id="edit-father-contact" class="w-full bg-white border-2 border-slate-300 px-4 py-3 rounded-xl text-slate-800 text-sm font-medium focus:border-amber-500 focus:ring-2 focus:ring-amber-500/20 outline-none">
                        </div>
                        <div>
                            <label class="block text-xs font-bold uppercase tracking-wider text-slate-500 mb-2">Mother Contact</label>
                            <input type="text" name="mother_contact" id="edit-mother-contact" class="w-full bg-white border-2 border-slate-300 px-4 py-3 rounded-xl text-slate-800 text-sm font-medium focus:border-amber-500 focus:ring-2 focus:ring-amber-500/20 outline-none">
                        </div>
                        <div>
                            <label class="block text-xs font-bold uppercase tracking-wider text-slate-500 mb-2">Guardian Contact</label>
                            <input type="text" name="guardian_contact" id="edit-guardian-contact" class="w-full bg-white border-2 border-slate-300 px-4 py-3 rounded-xl text-slate-800 text-sm font-medium focus:border-amber-500 focus:ring-2 focus:ring-amber-500/20 outline-none">
                        </div>
                    </div>
                </div>

                <!-- Previous School -->
                <div class="bg-white rounded-2xl shadow-sm border border-slate-200 overflow-hidden relative">
                    <div class="absolute top-0 left-0 w-2 h-full rounded-l-2xl bg-slate-400"></div>
                    <div class="px-6 pt-5 pb-3 border-b border-slate-100 ml-2">
                        <h4 class="text-lg font-heading font-black uppercase tracking-tight text-slate-500">Previous School Information</h4>
                    </div>
                    <div class="p-6 ml-2 grid grid-cols-1 md:grid-cols-3 gap-5">
                        <div>
                            <label class="block text-xs font-bold uppercase tracking-wider text-slate-500 mb-2">Previous School</label>
                            <input type="text" name="prev_school" id="edit-prev-school" class="w-full bg-white border-2 border-slate-300 px-4 py-3 rounded-xl text-slate-800 text-sm font-medium focus:border-slate-500 focus:ring-2 focus:ring-slate-500/20 outline-none">
                        </div>
                        <div>
                            <label class="block text-xs font-bold uppercase tracking-wider text-slate-500 mb-2">Last School Year</label>
                            <input type="text" name="prev_school_year" id="edit-prev-school-year" placeholder="e.g. 2024-2025" class="w-full bg-white border-2 border-slate-300 px-4 py-3 rounded-xl text-slate-800 text-sm font-medium focus:border-slate-500 focus:ring-2 focus:ring-slate-500/20 outline-none">
                        </div>
                        <div>
                            <label class="block text-xs font-bold uppercase tracking-wider text-slate-500 mb-2">Previous Section</label>
                            <input type="text" name="prev_section" id="edit-prev-section" class="w-full bg-white border-2 border-slate-300 px-4 py-3 rounded-xl text-slate-800 text-sm font-medium focus:border-slate-500 focus:ring-2 focus:ring-slate-500/20 outline-none">
                        </div>
                    </div>
                </div>

                <!-- Actions -->
                <div class="flex justify-end gap-3 pt-2 pb-2">
                    <button type="button" class="modal-close px-6 py-3 rounded-xl border-2 border-slate-300 text-slate-600 font-bold text-sm hover:bg-slate-100 transition-colors">Cancel</button>
                    <button type="submit" class="px-6 py-3 rounded-xl bg-dranhs-green text-white font-bold text-sm hover:bg-emerald-700 transition-colors shadow-md">Save Changes</button>
                </div>
            </form>
        </div>
    </div>
</div>

<div id="student-print-modal" class="fixed inset-0 z-40 hidden">
    <div class="absolute inset-0 bg-slate-900/60 modal-close"></div>
    <div class="relative z-10 min-h-screen flex items-center justify-center p-4">
        <div class="w-full max-w-lg bg-white rounded-2xl shadow-2xl border border-slate-200 overflow-hidden">
            <div class="px-6 py-4 border-b border-slate-100 flex items-center justify-between">
                <div>
                    <p class="text-xs font-bold uppercase tracking-[0.2em] text-slate-500 mb-1">Print Options</p>
                    <h3 id="print-student-title" class="font-heading font-black text-2xl text-dranhs-dark">Print Student</h3>
                </div>
                <button type="button" class="modal-close inline-flex items-center justify-center w-10 h-10 rounded-full bg-slate-100 text-slate-500 hover:bg-slate-200 transition-colors">&times;</button>
            </div>
            <div class="p-6 space-y-4">
                <button type="button" id="print-id-btn" class="w-full px-5 py-5 rounded-2xl border border-slate-200 bg-slate-50 text-left hover:bg-emerald-50 hover:border-dranhs-green transition-colors group">
                    <div class="flex items-center gap-3">
                        <div class="w-10 h-10 rounded-xl bg-emerald-100 flex items-center justify-center shrink-0 group-hover:bg-dranhs-green transition-colors">
                            <svg class="w-5 h-5 text-dranhs-green group-hover:text-white transition-colors" fill="none" stroke="currentColor" viewBox="0 0 24 24"><rect x="2" y="5" width="20" height="14" rx="2" stroke-width="2"/><path stroke-linecap="round" stroke-width="2" d="M7 9h10M7 13h6"/></svg>
                        </div>
                        <div>
                            <span class="block text-sm font-black text-dranhs-dark uppercase tracking-wide">Print for ID</span>
                            <span class="block text-xs text-slate-500 mt-0.5">Opens QR ID card layout — CR80 size, ready to print.</span>
                        </div>
                    </div>
                </button>
                <button type="button" id="print-doc-btn" class="w-full px-5 py-5 rounded-2xl border border-slate-200 bg-slate-50 text-left hover:bg-blue-50 hover:border-blue-400 transition-colors group">
                    <div class="flex items-center gap-3">
                        <div class="w-10 h-10 rounded-xl bg-blue-100 flex items-center justify-center shrink-0">
                            <svg class="w-5 h-5 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                        </div>
                        <div>
                            <span class="block text-sm font-black text-dranhs-dark uppercase tracking-wide">Print for Document</span>
                            <span class="block text-xs text-slate-500 mt-0.5">Prepare enrollment document printout for this student.</span>
                        </div>
                    </div>
                </button>
            </div>
        </div>
    </div>
</div>

<!-- ID Assets Modal -->
<div id="id-assets-modal" class="fixed inset-0 z-50 hidden">
    <div class="absolute inset-0 bg-slate-900/70 modal-close"></div>
    <div class="relative z-10 min-h-screen flex items-center justify-center p-4">
        <div class="w-full max-w-2xl bg-white rounded-2xl shadow-2xl border border-slate-200 overflow-hidden">
            <!-- Header -->
            <div class="bg-dranhs-dark px-6 py-4 flex items-center justify-between">
                <div>
                    <p class="text-xs font-bold uppercase tracking-[0.2em] text-dranhs-green mb-0.5">ID Assets</p>
                    <h3 id="id-assets-student-name" class="font-heading font-black text-xl text-white"></h3>
                </div>
                <button type="button" class="modal-close inline-flex items-center justify-center w-10 h-10 rounded-full bg-white/10 text-white hover:bg-white/20 transition-colors text-xl">&times;</button>
            </div>

            <!-- Two boxes -->
            <div class="p-6 grid grid-cols-2 gap-5">

                <!-- ID Photo box -->
                <div class="flex flex-col items-center gap-3 p-4 bg-slate-50 border border-slate-200 rounded-2xl">
                    <p class="text-xs font-black uppercase tracking-widest text-slate-500">ID Photo</p>
                    <div class="w-full aspect-square rounded-xl overflow-hidden border border-slate-200 bg-white flex items-center justify-center">
                        <img id="id-assets-photo" src="" alt="ID Photo" class="w-full h-full object-cover">
                    </div>
                    <a id="id-assets-photo-dl" href="#" class="w-full inline-flex items-center justify-center gap-2 px-4 py-2.5 rounded-xl bg-dranhs-green text-white text-xs font-bold hover:bg-emerald-700 transition-colors">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/></svg>
                        Download Photo
                    </a>
                </div>

                <!-- QR Code box -->
                <div class="flex flex-col items-center gap-3 p-4 bg-slate-50 border border-slate-200 rounded-2xl">
                    <p class="text-xs font-black uppercase tracking-widest text-slate-500">QR Code</p>
                    <div class="w-full aspect-square rounded-xl overflow-hidden border border-slate-200 bg-white flex items-center justify-center p-2">
                        <img id="id-assets-qr" src="" alt="QR Code" class="w-full h-full object-contain">
                    </div>
                    <a id="id-assets-qr-dl" href="#" class="w-full inline-flex items-center justify-center gap-2 px-4 py-2.5 rounded-xl bg-blue-600 text-white text-xs font-bold hover:bg-blue-700 transition-colors">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/></svg>
                        Download QR
                    </a>
                </div>

            </div>

            <!-- Bottom: Print ID + Download ZIP -->
            <div class="px-6 pb-6 flex flex-col sm:flex-row gap-3">
                <button type="button" id="id-assets-print-btn" class="flex-1 inline-flex items-center justify-center gap-2 px-4 py-3 rounded-xl bg-slate-800 text-white text-sm font-bold hover:bg-slate-700 transition-colors">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-3a2 2 0 00-2-2H5a2 2 0 00-2 2v3a2 2 0 002 2h2m10 0H7m10 0v4H7v-4m10-8V3H7v6h10z"/></svg>
                    Print ID Card
                </button>
                <a id="id-assets-zip-dl" href="#" class="flex-1 inline-flex items-center justify-center gap-2 px-4 py-3 rounded-xl bg-violet-600 text-white text-sm font-bold hover:bg-violet-700 transition-colors">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/></svg>
                    Download Both (ZIP)
                </a>
            </div>
        </div>
    </div>
</div>

<!-- Document Preview Modal -->
<div id="doc-preview-modal" class="fixed inset-0 z-50 hidden">
    <div class="absolute inset-0 bg-slate-900/70 modal-close"></div>
    <div class="relative z-10 min-h-screen flex items-start justify-center p-4 py-6">
        <div class="w-full max-w-5xl bg-white rounded-2xl shadow-2xl border border-slate-200 overflow-hidden flex flex-col" style="height:90vh">
            <!-- Header -->
            <div class="bg-dranhs-dark px-6 py-4 flex items-center justify-between shrink-0">
                <div>
                    <p class="text-xs font-bold uppercase tracking-[0.2em] text-dranhs-green mb-0.5">Enrollment Document Preview</p>
                    <h3 id="doc-preview-student" class="font-heading font-black text-xl text-white"></h3>
                </div>
                <div class="flex items-center gap-2">
                    <a id="doc-download-link" href="#" class="inline-flex items-center gap-2 px-4 py-2 rounded-lg bg-dranhs-green text-white text-sm font-bold hover:bg-emerald-700 transition-colors">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/></svg>
                        Download .docx
                    </a>
                    <button type="button" class="modal-close inline-flex items-center justify-center w-10 h-10 rounded-full bg-white/10 text-white hover:bg-white/20 transition-colors text-xl">&times;</button>
                </div>
            </div>
            <!-- Preview note -->
            <div class="bg-amber-50 border-b border-amber-200 px-6 py-2 shrink-0">
                <p class="text-xs text-amber-700 font-semibold">
                    ⚠ Preview may not render perfectly in all browsers. Download the .docx for the exact formatted output.
                </p>
            </div>
            <!-- iframe -->
            <div class="flex-1 overflow-hidden bg-slate-100">
                <iframe id="doc-preview-frame" src="" class="w-full h-full border-0" title="Document Preview"></iframe>
            </div>
        </div>
    </div>
</div>

<div id="student-withdraw-modal" class="fixed inset-0 z-40 hidden">
    <div class="absolute inset-0 bg-slate-900/60 modal-close"></div>
    <div class="relative z-10 min-h-screen flex items-center justify-center p-4">
        <div class="w-full max-w-lg bg-white rounded-2xl shadow-2xl border border-slate-200 overflow-hidden">
            <div class="px-6 py-4 border-b border-slate-100 flex items-center justify-between">
                <div>
                    <p class="text-xs font-bold uppercase tracking-[0.2em] text-red-500 mb-1">Withdrawal Security</p>
                    <h3 id="withdraw-student-title" class="font-heading font-black text-2xl text-dranhs-dark">Authorize Withdrawal</h3>
                </div>
                <button type="button" class="modal-close inline-flex items-center justify-center w-10 h-10 rounded-full bg-slate-100 text-slate-500 hover:bg-slate-200 transition-colors">&times;</button>
            </div>
            <form method="POST" class="p-6">
                <input type="hidden" name="action" value="verify_withdraw_password">
                <input type="hidden" name="student_id" id="withdraw-student-id">
                <p class="text-sm text-slate-500 mb-4">Enter your account password before withdrawal. This currently verifies authorization only and will not delete any database record.</p>
                <div class="mb-4 rounded-xl bg-red-50 border border-red-100 px-4 py-3 text-sm text-red-700">
                    Student selected: <span id="withdraw-student-name" class="font-bold"></span>
                </div>
                <label class="block text-xs font-bold uppercase tracking-wider text-slate-500 mb-2">Admin / Evaluator Password</label>
                <input name="admin_password" type="password" class="w-full rounded-xl border border-slate-300 px-4 py-3 text-sm" required>
                <div class="flex justify-end gap-3 pt-5">
                    <button type="button" class="modal-close px-5 py-3 rounded-xl border border-slate-300 text-slate-600 font-bold text-sm hover:bg-slate-50 transition-colors">Cancel</button>
                    <button type="submit" class="px-5 py-3 rounded-xl bg-red-600 text-white font-bold text-sm hover:bg-red-700 transition-colors">Verify Password</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
const STUDENT_DATA = <?php echo json_encode($student_rows, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP); ?>;
const PATHWAY_CATALOG = <?php echo json_encode($catalog_for_js, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP); ?>;

const studentMap = new Map(STUDENT_DATA.map(student => [String(student.id), student]));
const viewGrid = document.getElementById('student-view-grid');
const editGrid = document.getElementById('student-edit-grid');

function textOrDash(value) {
    return value && String(value).trim() !== '' ? String(value) : '--';
}

function fullName(student) {
    return `${textOrDash(student.last_name)}, ${textOrDash(student.first_name)}${student.middle_name ? ' ' + String(student.middle_name).charAt(0).toUpperCase() + '.' : ''}${student.extension_name ? ' ' + student.extension_name : ''}`;
}

function pathwayLabel(gradeLevel, code) {
    const options = PATHWAY_CATALOG[gradeLevel] || [];
    const match = options.find(item => String(item.code).toLowerCase() === String(code || '').toLowerCase());
    return match ? `${match.label}` : textOrDash(code);
}

function fieldBlock(label, value) {
    return `
        <div class="rounded-xl border border-slate-100 bg-slate-50 p-4">
            <p class="text-xs font-bold uppercase tracking-wider text-slate-400 mb-1">${label}</p>
            <p class="text-sm font-semibold text-slate-700 break-words">${textOrDash(value)}</p>
        </div>
    `;
}

function openModal(id) {
    document.getElementById(id).classList.remove('hidden');
    document.body.classList.add('overflow-hidden');
}

function closeModal(el) {
    const modal = el.closest('.fixed.inset-0');
    if (!modal) return;
    modal.classList.add('hidden');
    if (!document.querySelector('.fixed.inset-0:not(.hidden)')) {
        document.body.classList.remove('overflow-hidden');
    }
}

function renderPathwayOptions(gradeLevel, track, selectedCode) {
    const options = (PATHWAY_CATALOG[gradeLevel] || []).filter(item => !track || item.track === track);
    const html = ['<option value="">Select Pathway / Strand</option>'];
    options.forEach(item => {
        const selected = String(item.code).toLowerCase() === String(selectedCode || '').toLowerCase() ? ' selected' : '';
        html.push(`<option value="${item.code}"${selected}>${item.label} (${item.code})</option>`);
    });
    return html.join('');
}

function inputField(id, name, label, value, type = 'text', extra = '') {
    return `
        <div>
            <label class="block text-xs font-bold uppercase tracking-wider text-slate-500 mb-2">${label}</label>
            <input id="${id}" name="${name}" type="${type}" value="${String(value || '').replace(/"/g, '&quot;')}" class="w-full rounded-xl border border-slate-300 px-4 py-3 text-sm" ${extra}>
        </div>
    `;
}

function selectField(id, name, label, optionsHtml) {
    return `
        <div>
            <label class="block text-xs font-bold uppercase tracking-wider text-slate-500 mb-2">${label}</label>
            <select id="${id}" name="${name}" class="w-full rounded-xl border border-slate-300 px-4 py-3 text-sm">${optionsHtml}</select>
        </div>
    `;
}

function calcAge(birthdateStr) {
    if (!birthdateStr) return '';
    const today = new Date();
    const bd = new Date(birthdateStr);
    let age = today.getFullYear() - bd.getFullYear();
    const m = today.getMonth() - bd.getMonth();
    if (m < 0 || (m === 0 && today.getDate() < bd.getDate())) age--;
    return age >= 0 ? age : '';
}

function setSelectValue(id, value) {
    const el = document.getElementById(id);
    if (!el) return;
    el.value = value || '';
    if (el.value === '' && value) {
        // value not in options, add it
        const opt = document.createElement('option');
        opt.value = value;
        opt.textContent = value;
        el.appendChild(opt);
        el.value = value;
    }
}

function refreshPathwayOptions() {
    const gradeLevel = document.getElementById('edit-grade-level').value;
    const track = document.getElementById('edit-track').value;
    const select = document.getElementById('edit-pathway-strand');
    const current = select.value;
    const options = (PATHWAY_CATALOG[gradeLevel] || []).filter(item => !track || item.track === track);
    select.innerHTML = '<option value="">Select Pathway / Strand</option>';
    options.forEach(item => {
        const opt = document.createElement('option');
        opt.value = item.code;
        opt.textContent = `${item.label} (${item.code})`;
        if (String(item.code).toLowerCase() === String(current).toLowerCase()) opt.selected = true;
        select.appendChild(opt);
    });
}

function populateEditModal(student) {
    document.getElementById('edit-student-title').textContent = 'Edit: ' + fullName(student);
    document.getElementById('edit-student-id').value = student.id || '';

    // Show existing photo if available
    const photoPath = student._id_photo_path || '';
    const preview = document.getElementById('edit-photo-preview');
    const placeholder = document.getElementById('edit-photo-placeholder');
    const label = document.getElementById('edit-photo-label');
    const wrap = document.getElementById('edit-photo-preview-wrap');
    if (photoPath) {
        preview.src = '../' + photoPath;
        preview.classList.remove('hidden');
        placeholder.classList.add('hidden');
        label.classList.add('hidden');
        wrap.classList.remove('border-dashed','border-slate-300');
        wrap.classList.add('border-dranhs-green');
    } else {
        preview.src = '';
        preview.classList.add('hidden');
        placeholder.classList.remove('hidden');
        label.classList.remove('hidden');
        wrap.classList.add('border-dashed','border-slate-300');
        wrap.classList.remove('border-dranhs-green');
    }
    document.getElementById('edit-photo-upload').value = '';
    document.getElementById('edit-photo-clear').classList.add('hidden');
    stopEditCamera();

    // General
    setSelectValue('edit-student-type', student.student_type);
    document.getElementById('edit-school-year').value = student.school_year || '';
    setSelectValue('edit-grade-level', student.grade_level || 'Grade 11');
    setSelectValue('edit-semester', student.semester || '1st');
    document.getElementById('edit-lrn').value = student.lrn || '';
    setSelectValue('edit-track', student.track || '');
    refreshPathwayOptions();
    setSelectValue('edit-pathway-strand', student.pathway_strand || '');

    // Personal
    document.getElementById('edit-last-name').value = student.last_name || '';
    document.getElementById('edit-first-name').value = student.first_name || '';
    document.getElementById('edit-middle-name').value = student.middle_name || '';
    document.getElementById('edit-extension-name').value = student.extension_name || '';
    document.getElementById('edit-birthdate').value = student.birthdate || '';
    document.getElementById('edit-age').value = student.age || calcAge(student.birthdate);
    setSelectValue('edit-sex', student.sex || '');
    document.getElementById('edit-place-of-birth').value = student.place_of_birth || '';
    document.getElementById('edit-mother-tongue').value = student.mother_tongue || '';
    document.getElementById('edit-religion').value = student.religion || '';

    // Address
    document.getElementById('edit-street').value = student.street || '';
    document.getElementById('edit-barangay').value = student.barangay || '';
    document.getElementById('edit-city').value = student.city || '';
    document.getElementById('edit-province').value = student.province || '';
    document.getElementById('edit-zip-code').value = student.zip_code || '';
    setSelectValue('edit-living-with', student.living_with || '');

    // Parents
    document.getElementById('edit-father-contact').value = student.father_contact || '';
    document.getElementById('edit-mother-contact').value = student.mother_contact || '';
    document.getElementById('edit-guardian-contact').value = student.guardian_contact || '';

    // Previous school
    document.getElementById('edit-prev-school').value = student.prev_school || '';
    document.getElementById('edit-prev-school-year').value = student.prev_school_year || '';
    document.getElementById('edit-prev-section').value = student.prev_section || '';
}

// Birthdate → auto-calc age
document.getElementById('edit-birthdate').addEventListener('change', function () {
    document.getElementById('edit-age').value = calcAge(this.value);
});

// Grade level / track → refresh pathway options
document.getElementById('edit-grade-level').addEventListener('change', refreshPathwayOptions);
document.getElementById('edit-track').addEventListener('change', refreshPathwayOptions);

function section(title, color, fields) {
    const rows = fields.map(f => `
        <div class="form-view-group">
            <p class="text-xs font-bold text-slate-500 uppercase tracking-wider mb-1">${f.label}</p>
            <p class="w-full bg-white border-2 border-slate-300 px-4 py-3 rounded-xl text-slate-800 text-sm font-medium">${textOrDash(f.value)}</p>
        </div>`).join('');
    return `
        <div class="bg-white rounded-2xl shadow-sm border border-slate-200 overflow-hidden relative">
            <div class="absolute top-0 left-0 w-2 h-full rounded-l-2xl" style="background:${color}"></div>
            <div class="px-6 pt-5 pb-2 border-b border-slate-100 ml-2">
                <h4 class="text-lg font-heading font-black uppercase tracking-tight" style="color:${color}">${title}</h4>
            </div>
            <div class="p-6 ml-2 grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-5">${rows}</div>
        </div>`;
}

function populateViewModal(student) {
    document.getElementById('view-student-name').textContent = fullName(student);
    document.getElementById('view-to-edit-btn').dataset.studentId = String(student.id);

    const photoPath = student._id_photo_path || '';
    const photoBlock = photoPath
        ? '<img src="../' + photoPath + '" alt="ID Photo" class="w-full h-full object-cover" onerror="this.style.display=\'none\';this.nextElementSibling.style.display=\'flex\'">' +
          '<div class="w-full h-full flex-col items-center justify-center gap-1 bg-slate-50 hidden"><svg class="w-8 h-8 text-slate-300" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/></svg><span class="text-xs text-slate-400">No Photo</span></div>'
        : '<div class="w-full h-full flex flex-col items-center justify-center gap-2 bg-slate-50"><svg class="w-10 h-10 text-slate-300" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/></svg><span class="text-xs text-slate-400 font-semibold text-center px-2">No ID Photo</span></div>';

    const borderClass = photoPath ? 'border-dranhs-green' : 'border-dashed border-slate-300';
    const psLabel = student.grade_level === 'Grade 11' ? 'Career Pathway' : 'Strand';

    function infoBox(label, value) {
        return '<div class="rounded-xl bg-slate-50 border border-slate-100 p-3"><p class="text-xs font-bold uppercase tracking-wider text-slate-400 mb-1">' + label + '</p><p class="text-sm font-semibold text-slate-700">' + textOrDash(value) + '</p></div>';
    }

    const genInfoSection =
        '<div class="bg-white rounded-2xl shadow-sm border border-slate-200 overflow-hidden relative">' +
            '<div class="absolute top-0 left-0 w-2 h-full rounded-l-2xl bg-dranhs-green"></div>' +
            '<div class="px-6 pt-5 pb-2 border-b border-slate-100 ml-2"><h4 class="text-lg font-heading font-black uppercase tracking-tight text-dranhs-green">General Information</h4></div>' +
            '<div class="p-5 ml-2">' +
                '<div class="flex gap-5 mb-4">' +
                    '<div class="shrink-0 w-28 h-36 rounded-xl border-2 ' + borderClass + ' overflow-hidden shadow-sm">' + photoBlock + '</div>' +
                    '<div class="flex-1 grid grid-cols-2 gap-3 content-start">' +
                        infoBox('Learner Category', student.student_type) +
                        infoBox('School Year', student.school_year) +
                        infoBox('Semester', student.semester) +
                        infoBox('LRN', student.lrn) +
                    '</div>' +
                '</div>' +
                '<div class="grid grid-cols-1 md:grid-cols-3 gap-3 pt-3 border-t border-slate-100">' +
                    infoBox('Grade Level', student.grade_level) +
                    infoBox('Track', student.track) +
                    infoBox(psLabel, pathwayLabel(student.grade_level, student.pathway_strand)) +
                '</div>' +
            '</div>' +
        '</div>';

    viewGrid.innerHTML = genInfoSection + [
        section("Learner's Personal Information", '#6d28d9', [
            { label: 'Last Name',      value: student.last_name },
            { label: 'First Name',     value: student.first_name },
            { label: 'Middle Name',    value: student.middle_name },
            { label: 'Ext. Name',      value: student.extension_name },
            { label: 'Birthdate',      value: student.birthdate },
            { label: 'Age',            value: student.age },
            { label: 'Sex',            value: student.sex },
            { label: 'Place of Birth', value: student.place_of_birth },
            { label: 'Mother Tongue',  value: student.mother_tongue },
            { label: 'Religion',       value: student.religion },
        ]),
        section('Current Address', '#0ea5e9', [
            { label: 'Street / Purok / Sitio', value: student.street },
            { label: 'Barangay',               value: student.barangay },
            { label: 'Municipality / City',    value: student.city },
            { label: 'Province',               value: student.province },
            { label: 'ZIP Code',               value: student.zip_code },
            { label: 'Living With',            value: student.living_with },
        ]),
        section("Parent's / Guardian's Information", '#f59e0b', [
            { label: 'Father Contact',   value: student.father_contact },
            { label: 'Mother Contact',   value: student.mother_contact },
            { label: 'Guardian Contact', value: student.guardian_contact },
        ]),
        section('Previous School Information', '#64748b', [
            { label: 'Previous School',  value: student.prev_school },
            { label: 'Last School Year', value: student.prev_school_year },
            { label: 'Previous Section', value: student.prev_section },
        ]),
    ].join('');
}

document.querySelectorAll('.modal-close').forEach(btn => btn.addEventListener('click', function () { closeModal(this); }));
document.querySelectorAll('.view-student-btn').forEach(btn => btn.addEventListener('click', function () {
    const student = studentMap.get(this.dataset.studentId);
    if (!student) return;
    populateViewModal(student);
    openModal('student-view-modal');
}));
document.querySelectorAll('.edit-student-btn').forEach(btn => btn.addEventListener('click', function () {
    const student = studentMap.get(this.dataset.studentId);
    if (!student) return;
    populateEditModal(student);
    openModal('student-edit-modal');
}));
document.getElementById('view-to-edit-btn').addEventListener('click', function () {
    const student = studentMap.get(this.dataset.studentId);
    if (!student) return;
    document.getElementById('student-view-modal').classList.add('hidden');
    populateEditModal(student);
    openModal('student-edit-modal');
});
let _printStudentId = null;
document.querySelectorAll('.print-student-btn').forEach(btn => btn.addEventListener('click', function () {
    const student = studentMap.get(this.dataset.studentId);
    if (!student) return;
    _printStudentId = student.id;
    document.getElementById('print-student-title').textContent = `Print Options for ${fullName(student)}`;
    openModal('student-print-modal');
}));
document.getElementById('print-id-btn').addEventListener('click', function () {
    if (!_printStudentId) return;
    const student = studentMap.get(String(_printStudentId));
    if (!student) return;

    // Build QR data URL for preview
    const lrn  = student.lrn  || '';
    const name = ((student.last_name || '') + ', ' + (student.first_name || '')).toUpperCase();
    const qrData = encodeURIComponent('LRN:' + lrn + '|NAME:' + name);
    const qrPreviewUrl = 'https://api.qrserver.com/v1/create-qr-code/?size=300x300&ecc=M&data=' + qrData;

    // Photo preview — use existing photo or avatar fallback
    const photoSrc = student._id_photo_path
        ? '../' + student._id_photo_path
        : 'https://ui-avatars.com/api/?name=' + encodeURIComponent((student.first_name||'S').charAt(0) + (student.last_name||'').charAt(0)) + '&background=009b5a&color=fff&size=200&bold=true';

    document.getElementById('id-assets-student-name').textContent = fullName(student);
    document.getElementById('id-assets-photo').src    = photoSrc;
    document.getElementById('id-assets-qr').src       = qrPreviewUrl;

    // Download links
    const base = '../download_id_assets.php?id=' + _printStudentId;
    document.getElementById('id-assets-photo-dl').href = base + '&type=photo';
    document.getElementById('id-assets-qr-dl').href    = base + '&type=qr';
    document.getElementById('id-assets-zip-dl').href   = base + '&type=zip';

    // Print ID card button
    document.getElementById('id-assets-print-btn').onclick = function () {
        window.open('../print_id.php?id=' + _printStudentId, '_blank');
    };

    document.getElementById('student-print-modal').classList.add('hidden');
    openModal('id-assets-modal');
});
document.getElementById('print-doc-btn').addEventListener('click', function () {
    if (!_printStudentId) return;
    // Open preview modal
    const previewUrl = '../print_document.php?id=' + _printStudentId + '&preview=1';
    const downloadUrl = '../print_document.php?id=' + _printStudentId;
    document.getElementById('doc-preview-frame').src = previewUrl;
    document.getElementById('doc-download-link').href = downloadUrl;
    document.getElementById('doc-preview-student').textContent = document.getElementById('print-student-title').textContent.replace('Print Options for ', '');
    document.getElementById('student-print-modal').classList.add('hidden');
    openModal('doc-preview-modal');
});
document.querySelectorAll('.withdraw-student-btn').forEach(btn => btn.addEventListener('click', function () {
    const student = studentMap.get(this.dataset.studentId);
    if (!student) return;
    document.getElementById('withdraw-student-id').value = student.id || '';
    document.getElementById('withdraw-student-title').textContent = `Authorize Withdrawal for ${fullName(student)}`;
    document.getElementById('withdraw-student-name').textContent = fullName(student);
    openModal('student-withdraw-modal');
}));

// Live search
document.getElementById('student-search').addEventListener('input', function () {
    const q = this.value.toLowerCase().trim();
    document.querySelectorAll('#student-tbody tr').forEach(row => {
        row.style.display = row.textContent.toLowerCase().includes(q) ? '' : 'none';
    });
});

// ---- Edit modal webcam ----
let editStream = null;
function stopEditCamera() {
    if (editStream) { editStream.getTracks().forEach(t => t.stop()); editStream = null; }
    document.getElementById('edit-webcam').classList.add('hidden');
    document.getElementById('edit-cam-capture').classList.add('hidden');
    document.getElementById('edit-cam-stop').classList.add('hidden');
    document.getElementById('edit-cam-start').classList.remove('hidden');
}
function setEditPhoto(dataUrl) {
    const preview = document.getElementById('edit-photo-preview');
    preview.src = dataUrl;
    preview.classList.remove('hidden');
    document.getElementById('edit-photo-placeholder').classList.add('hidden');
    document.getElementById('edit-photo-label').classList.add('hidden');
    document.getElementById('edit-photo-clear').classList.remove('hidden');
    // Inject as file
    fetch(dataUrl).then(r => r.blob()).then(blob => {
        const file = new File([blob], 'id_photo.jpg', { type: 'image/jpeg' });
        const dt = new DataTransfer();
        dt.items.add(file);
        document.getElementById('edit-photo-upload').files = dt.files;
    });
}
document.getElementById('edit-cam-start').addEventListener('click', async function () {
    try {
        editStream = await navigator.mediaDevices.getUserMedia({ video: true });
        const video = document.getElementById('edit-webcam');
        video.srcObject = editStream;
        video.classList.remove('hidden');
        this.classList.add('hidden');
        document.getElementById('edit-cam-capture').classList.remove('hidden');
        document.getElementById('edit-cam-stop').classList.remove('hidden');
    } catch(e) { alert('Camera not accessible: ' + e.message); }
});
document.getElementById('edit-cam-stop').addEventListener('click', stopEditCamera);
document.getElementById('edit-cam-capture').addEventListener('click', function () {
    const video = document.getElementById('edit-webcam');
    const canvas = document.getElementById('edit-canvas');
    canvas.width = video.videoWidth; canvas.height = video.videoHeight;
    canvas.getContext('2d').drawImage(video, 0, 0);
    setEditPhoto(canvas.toDataURL('image/jpeg', 0.85));
    stopEditCamera();
});
document.getElementById('edit-photo-upload').addEventListener('change', function () {
    const file = this.files[0];
    if (!file) return;
    const reader = new FileReader();
    reader.onload = e => {
        const preview = document.getElementById('edit-photo-preview');
        preview.src = e.target.result;
        preview.classList.remove('hidden');
        document.getElementById('edit-photo-placeholder').classList.add('hidden');
        document.getElementById('edit-photo-label').classList.add('hidden');
        document.getElementById('edit-photo-clear').classList.remove('hidden');
    };
    reader.readAsDataURL(file);
});
document.getElementById('edit-photo-clear').addEventListener('click', function () {
    document.getElementById('edit-photo-upload').value = '';
    document.getElementById('edit-photo-preview').src = '';
    document.getElementById('edit-photo-preview').classList.add('hidden');
    document.getElementById('edit-photo-placeholder').classList.remove('hidden');
    document.getElementById('edit-photo-label').classList.remove('hidden');
    this.classList.add('hidden');
});
</script>
