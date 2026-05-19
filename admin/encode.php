<?php
require_once __DIR__ . '/../pathway_strand_catalog.php';
require_once __DIR__ . '/../db.php';

$conn = db_connect();

$encode_rows = [];
$classrooms  = [];
$db_error    = '';
$toast_msg   = '';

$catalog_for_js = [
    'Grade 11' => get_pathway_strand_options('Grade 11'),
    'Grade 12' => get_pathway_strand_options('Grade 12'),
];

if ($conn->connect_error) {
    $db_error = 'Database connection failed.';
} else {
    // Ensure assigned_section column exists on students without MySQL 8-only syntax.
    $assigned_section_check = $conn->query(
        "SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS
         WHERE TABLE_SCHEMA='" . $conn->real_escape_string(DB_NAME) . "'
           AND TABLE_NAME='students'
           AND COLUMN_NAME='assigned_section'"
    );
    if ($assigned_section_check && $assigned_section_check->num_rows === 0) {
        $conn->query("ALTER TABLE students ADD COLUMN assigned_section VARCHAR(100)");
    }
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['enc_action'] ?? '') === 'enroll') {
        $sid     = (int)($_POST['student_id'] ?? 0);
        $section = trim($_POST['assigned_section'] ?? '');
        $encoded_by = (int)($_SESSION['user_id'] ?? 0);

        if ($sid > 0) {
            // Save photo from file upload
            $photo_path = null;
            if (isset($_FILES['id_photo_file']) && $_FILES['id_photo_file']['error'] === UPLOAD_ERR_OK) {
                $ext  = strtolower(pathinfo($_FILES['id_photo_file']['name'], PATHINFO_EXTENSION));
                if (!in_array($ext, ['jpg','jpeg','png','webp'])) $ext = 'jpg';
                $lrn_val = $stu['lrn'] ?? $sid;
                $fname = 'pic_' . $lrn_val . '.' . $ext;
                $fpath = __DIR__ . '/../uploads/id_photos/' . $fname;
                // Delete old photo with different extension
                foreach (glob(__DIR__ . '/../uploads/id_photos/pic_' . $lrn_val . '.*') as $old) {
                    if ($old !== $fpath) @unlink($old);
                }
                if (move_uploaded_file($_FILES['id_photo_file']['tmp_name'], $fpath)) {
                    $photo_path = 'uploads/id_photos/' . $fname;
                }
            }

            // Fetch student info for encodings record
            $stu = null;
            $sq = $conn->prepare("SELECT lrn, last_name, first_name, middle_name, extension_name, grade_level, track, pathway_strand FROM students WHERE id=?");
            if ($sq) { $sq->bind_param("i",$sid); $sq->execute(); $stu=$sq->get_result()->fetch_assoc(); $sq->close(); }

            if ($stu) {
                $lrn  = $stu['lrn'] ?? '';
                $sname = trim(($stu['last_name']??'').', '.($stu['first_name']??'').((!empty($stu['middle_name']))?' '.strtoupper(substr($stu['middle_name'],0,1)).'.':'').((!empty($stu['extension_name']))?' '.$stu['extension_name']:''));
                $gl   = $stu['grade_level'] ?? '';
                $tr   = $stu['track'] ?? '';
                $ps   = $stu['pathway_strand'] ?? '';

                // Upsert encodings — use has_id_photo flag + id_photo_path
                $has_photo = $photo_path ? 1 : 0;
                $chk = $conn->prepare("SELECT id FROM encodings WHERE student_id=?");
                if ($chk) { $chk->bind_param("i",$sid); $chk->execute(); $exists=$chk->get_result()->num_rows>0; $chk->close(); }
                if (!empty($exists)) {
                    $upd = $conn->prepare("UPDATE encodings SET lrn=?,student_name=?,grade_level=?,track=?,pathway_strand=?,assigned_section=?,has_id_photo=?,id_photo_path=?,encoded_by=?,encoded_at=NOW() WHERE student_id=?");
                    if ($upd) { $upd->bind_param("ssssssisii",$lrn,$sname,$gl,$tr,$ps,$section,$has_photo,$photo_path,$encoded_by,$sid); $upd->execute(); $upd->close(); }
                } else {
                    $ins = $conn->prepare("INSERT INTO encodings (student_id,lrn,student_name,grade_level,track,pathway_strand,assigned_section,has_id_photo,id_photo_path,encoded_by) VALUES (?,?,?,?,?,?,?,?,?,?)");
                    if ($ins) { $ins->bind_param("issssssiis",$sid,$lrn,$sname,$gl,$tr,$ps,$section,$has_photo,$photo_path,$encoded_by); $ins->execute(); $ins->close(); }
                }
            }

            // Update student status + assigned_section
            $stmt = $conn->prepare("UPDATE students SET enrollment_status='enrolled', assigned_section=? WHERE id=?");
            if ($stmt) { $stmt->bind_param("si",$section,$sid); $stmt->execute(); $stmt->close(); }
            $toast_msg = 'enrolled';
        }
    }

    $res = $conn->query("SELECT students.*, watchlist.issue_type AS watch_issue_type, watchlist.issue_details AS watch_issue_details
        FROM students
        LEFT JOIN watchlist
            ON watchlist.lrn = students.lrn
           AND watchlist.school_year = students.school_year
        WHERE students.enrollment_status='for_encoding'
        ORDER BY students.created_at DESC, students.id DESC");
    if ($res) { while ($r = $res->fetch_assoc()) $encode_rows[] = $r; $res->close(); }

    // Fetch classrooms with enrolled count
    $cr = $conn->query("SELECT c.*, (SELECT COUNT(*) FROM students s WHERE s.assigned_section=c.section_name AND s.enrollment_status='enrolled') as enrolled FROM classrooms c ORDER BY grade_level DESC, section_name ASC");
    if ($cr) { while ($r = $cr->fetch_assoc()) $classrooms[] = $r; $cr->close(); }

    $conn->close();
}

function enc_name($r) {
    $n = ($r['last_name']??'').', '.($r['first_name']??'');
    if (!empty($r['middle_name'])) $n .= ' '.strtoupper(substr($r['middle_name'],0,1)).'.';
    if (!empty($r['extension_name'])) $n .= ' '.$r['extension_name'];
    return trim($n);
}
?>

<?php if ($toast_msg): ?>
<script>window.location.replace('?page=encode&msg=<?php echo $toast_msg; ?>');</script>
<?php endif; ?>

<?php if (isset($_GET['msg']) && $_GET['msg'] === 'enrolled'): ?>
<div class="mb-4 rounded-xl border px-5 py-4 text-sm font-semibold bg-emerald-50 border-emerald-200 text-emerald-700">
    Student enrolled successfully.
</div>
<?php endif; ?>

<div class="bg-white rounded-xl shadow-sm border border-slate-100 overflow-hidden">
    <div class="px-6 py-4 border-b border-slate-100 flex justify-between items-center">
        <div>
            <h3 class="font-heading font-bold text-lg text-dranhs-dark">Encode</h3>
            <p class="text-sm text-slate-500">Students verified and ready for encoding, ID photo, and section assignment.</p>
        </div>
    </div>
    <div class="px-6 py-3 border-b border-slate-100 bg-slate-50">
        <div class="relative max-w-sm">
            <svg class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-4.35-4.35M17 11A6 6 0 1 1 5 11a6 6 0 0 1 12 0z"/></svg>
            <input type="text" id="encode-search" placeholder="Search name, LRN, track, pathway..." class="w-full pl-9 pr-4 py-2 text-sm border border-slate-300 rounded-lg focus:outline-none focus:border-dranhs-green focus:ring-2 focus:ring-dranhs-green/20 bg-white">
        </div>
    </div>

    <?php if ($db_error): ?>
        <div class="px-6 py-5 text-sm font-semibold text-red-600 bg-red-50"><?php echo htmlspecialchars($db_error); ?></div>
    <?php elseif (empty($encode_rows)): ?>
        <div class="px-6 py-10 text-center">
            <p class="text-sm font-semibold text-slate-600">No students ready for encoding yet.</p>
            <p class="text-sm text-slate-400 mt-1">Students will appear here once verified through evaluation.</p>
        </div>
    <?php else: ?>
    <div class="overflow-x-auto">
        <table class="w-full text-left border-collapse">
            <thead class="bg-slate-50 text-xs uppercase text-slate-500 font-bold">
                <tr>
                    <th class="px-6 py-3">Name</th>
                    <th class="px-6 py-3">LRN</th>
                    <th class="px-6 py-3">Grade Level</th>
                    <th class="px-6 py-3">Track</th>
                    <th class="px-6 py-3">Pathway / Strand</th>
                    <th class="px-6 py-3">Action</th>
                </tr>
            </thead>
            <tbody id="encode-tbody" class="divide-y divide-slate-100 text-sm">
                <?php foreach ($encode_rows as $row): ?>
                <tr class="hover:bg-slate-50 transition-colors">
                    <td class="px-6 py-4">
                        <div class="font-semibold text-slate-700"><?php echo htmlspecialchars(enc_name($row)); ?></div>
                        <div class="text-xs text-slate-400 mt-1">Verified <?php echo htmlspecialchars(date('M d, Y', strtotime($row['created_at']))); ?></div>
                        <?php if (!empty($row['watch_issue_type'])): ?>
                            <div class="mt-2 inline-flex items-center gap-1 rounded-full bg-red-50 px-2.5 py-1 text-[11px] font-bold uppercase tracking-wide text-red-700 border border-red-200" title="<?php echo htmlspecialchars($row['watch_issue_details'] ?: 'No additional notes provided in the Focus List.'); ?>">
                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M7 6v14l5-5 5 5V6"/></svg>
                                Flagged: <?php echo htmlspecialchars($row['watch_issue_type']); ?>
                            </div>
                        <?php endif; ?>
                    </td>
                    <td class="px-6 py-4 text-slate-600"><?php echo htmlspecialchars($row['lrn'] ?: '--'); ?></td>
                    <td class="px-6 py-4 text-slate-600"><?php echo htmlspecialchars($row['grade_level'] ?: '--'); ?></td>
                    <td class="px-6 py-4 text-slate-600"><?php echo htmlspecialchars($row['track'] ?: '--'); ?></td>
                    <td class="px-6 py-4 text-slate-600"><?php echo htmlspecialchars(get_pathway_strand_label($row['grade_level'] ?? '', $row['pathway_strand'] ?? '')); ?></td>
                    <td class="px-6 py-4">
                        <button type="button"
                            class="enc-open-btn inline-flex items-center gap-1.5 px-3 py-2 rounded-lg bg-dranhs-green text-white text-xs font-bold uppercase tracking-wide hover:bg-emerald-700 transition-colors"
                            data-student='<?php echo htmlspecialchars(json_encode($row, JSON_HEX_TAG|JSON_HEX_APOS|JSON_HEX_QUOT|JSON_HEX_AMP), ENT_QUOTES); ?>'>
                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                            Encode
                        </button>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>
</div>

<!-- ===== ENCODE MODAL ===== -->
<div id="enc-modal" class="fixed inset-0 z-50 hidden">
    <div class="absolute inset-0 bg-slate-900/60" id="enc-backdrop"></div>
    <div class="relative z-10 min-h-screen flex items-start justify-center p-4 py-6">
        <div class="w-full max-w-3xl bg-slate-50 rounded-2xl shadow-2xl border border-slate-200 overflow-hidden">

            <!-- Header -->
            <div class="bg-dranhs-dark px-6 py-5 flex items-center justify-between">
                <div>
                    <p class="text-xs font-bold uppercase tracking-widest text-dranhs-green mb-1">Encoding</p>
                    <h3 id="enc-modal-name" class="font-heading font-black text-xl text-white">Student Name</h3>
                </div>
                <button type="button" id="enc-close" class="w-9 h-9 rounded-full bg-white/10 text-white hover:bg-white/20 text-xl flex items-center justify-center">&times;</button>
            </div>

            <!-- Tabs -->
            <div class="flex border-b border-slate-200 bg-white">
                <button type="button" class="enc-tab-btn active-tab px-5 py-3 text-xs font-black uppercase tracking-widest border-b-2 border-dranhs-green text-dranhs-green" data-tab="overview">Overview</button>
                <button type="button" class="enc-tab-btn px-5 py-3 text-xs font-black uppercase tracking-widest border-b-2 border-transparent text-slate-400 hover:text-slate-700" data-tab="photo">ID Photo</button>
                <button type="button" class="enc-tab-btn px-5 py-3 text-xs font-black uppercase tracking-widest border-b-2 border-transparent text-slate-400 hover:text-slate-700" data-tab="section">Section</button>
            </div>

            <form method="POST" id="enc-form" enctype="multipart/form-data" class="max-h-[75vh] overflow-y-auto">
                <input type="hidden" name="enc_action" value="enroll">
                <input type="hidden" name="student_id" id="enc-student-id">
                <input type="file" name="id_photo_file" id="enc-photo-file-input" accept="image/*" class="hidden">
                <input type="hidden" name="assigned_section" id="enc-assigned-section">

                <!-- TAB: OVERVIEW -->
                <div id="enc-tab-overview" class="enc-tab-content p-6 space-y-4">
                    <div id="enc-flag-alert" class="hidden rounded-2xl border border-red-200 bg-red-50 px-5 py-4">
                        <p class="text-sm font-bold text-red-800">Red Flag: <span id="enc-flag-type"></span></p>
                        <p id="enc-flag-details" class="mt-1 text-xs font-medium text-red-700"></p>
                    </div>
                    <!-- Basic Info -->
                    <div class="bg-white rounded-2xl border border-slate-200 overflow-hidden relative">
                        <div class="absolute top-0 left-0 w-2 h-full bg-dranhs-green rounded-l-2xl"></div>
                        <div class="px-5 pt-4 pb-2 border-b border-slate-100 ml-2">
                            <h4 class="text-xs font-black uppercase tracking-widest text-dranhs-green">Basic Information</h4>
                        </div>
                        <div class="p-5 ml-2 grid grid-cols-2 md:grid-cols-3 gap-3" id="enc-basic-grid"></div>
                    </div>
                    <!-- Pathway/Strand -->
                    <div class="bg-white rounded-2xl border border-slate-200 overflow-hidden relative">
                        <div class="absolute top-0 left-0 w-2 h-full rounded-l-2xl" style="background:#0ea5e9"></div>
                        <div class="px-5 pt-4 pb-2 border-b border-slate-100 ml-2">
                            <h4 class="text-xs font-black uppercase tracking-widest" style="color:#0ea5e9" id="enc-ps-label">Career Pathway / Strand</h4>
                        </div>
                        <div class="p-5 ml-2 grid grid-cols-2 md:grid-cols-3 gap-3" id="enc-ps-grid"></div>
                    </div>
                </div>

                <!-- TAB: PHOTO -->
                <div id="enc-tab-photo" class="enc-tab-content hidden p-6">
                    <div class="bg-white rounded-2xl border border-slate-200 p-6 space-y-4">
                        <h4 class="text-xs font-black uppercase tracking-widest text-slate-500">ID Photo Capture</h4>
                        <p class="text-xs text-slate-400">Use the webcam to take the student's ID photo, or upload an existing image.</p>

                        <div class="flex flex-col items-center gap-4">
                            <!-- Preview -->
                            <div class="relative w-40 h-48 rounded-xl overflow-hidden border-2 border-slate-300 bg-slate-100 flex items-center justify-center" id="enc-photo-preview-wrap">
                                <img id="enc-photo-preview" src="" class="w-full h-full object-cover hidden">
                                <svg id="enc-photo-placeholder" class="w-16 h-16 text-slate-300" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/></svg>
                            </div>

                            <!-- Webcam -->
                            <video id="enc-webcam" class="hidden w-64 h-48 rounded-xl border-2 border-dranhs-green object-cover" autoplay playsinline></video>
                            <canvas id="enc-canvas" class="hidden"></canvas>

                            <div class="flex flex-wrap gap-2 justify-center">
                                <button type="button" id="enc-cam-start" class="px-4 py-2 rounded-lg bg-dranhs-green text-white text-xs font-bold hover:bg-emerald-700 transition-colors">
                                    Open Webcam
                                </button>
                                <button type="button" id="enc-cam-capture" class="hidden px-4 py-2 rounded-lg bg-blue-600 text-white text-xs font-bold hover:bg-blue-700 transition-colors">
                                    Capture
                                </button>
                                <button type="button" id="enc-cam-stop" class="hidden px-4 py-2 rounded-lg bg-slate-200 text-slate-700 text-xs font-bold hover:bg-slate-300 transition-colors">
                                    Stop Camera
                                </button>
                                <label class="px-4 py-2 rounded-lg bg-amber-50 text-amber-700 border border-amber-200 text-xs font-bold cursor-pointer hover:bg-amber-100 transition-colors">
                                    Upload Photo
                                    <input type="file" id="enc-photo-upload" accept="image/*" class="hidden">
                                </label>
                                <button type="button" id="enc-photo-clear" class="hidden px-4 py-2 rounded-lg bg-red-50 text-red-600 text-xs font-bold hover:bg-red-100 transition-colors">
                                    Clear
                                </button>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- TAB: SECTION -->
                <div id="enc-tab-section" class="enc-tab-content hidden p-6 space-y-4">
                    <div class="bg-white rounded-2xl border border-slate-200 overflow-hidden relative">
                        <div class="absolute top-0 left-0 w-2 h-full rounded-l-2xl" style="background:#6d28d9"></div>
                        <div class="px-5 pt-4 pb-3 border-b border-slate-100 ml-2 flex items-center justify-between">
                            <h4 class="text-xs font-black uppercase tracking-widest" style="color:#6d28d9">Section Assignment</h4>
                            <button type="button" id="enc-auto-assign-btn" class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-violet-600 text-white text-xs font-bold hover:bg-violet-700 transition-colors">
                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/></svg>
                                Auto-Assign
                            </button>
                        </div>
                        <div class="p-5 ml-2">
                            <p class="text-xs text-slate-500 mb-3">Auto-assign picks the section with the most available slots for this student's pathway/strand. You can also select manually below.</p>
                            <div id="enc-section-grid" class="grid grid-cols-1 sm:grid-cols-2 gap-3"></div>
                            <p id="enc-no-section-msg" class="hidden text-sm text-amber-600 font-semibold bg-amber-50 border border-amber-200 rounded-xl px-4 py-3 mt-3">
                                No available sections found for this pathway/strand. Please add a classroom in the Classroom page first.
                            </p>
                        </div>
                    </div>

                    <!-- Selected section display -->
                    <div id="enc-selected-section-wrap" class="hidden bg-emerald-50 border border-emerald-200 rounded-xl px-5 py-4 flex items-center gap-3">
                        <svg class="w-5 h-5 text-dranhs-green shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                        <div>
                            <p class="text-xs font-bold uppercase tracking-wider text-dranhs-green">Assigned Section</p>
                            <p id="enc-selected-section-name" class="text-sm font-black text-dranhs-dark"></p>
                        </div>
                    </div>
                </div>

                <!-- Footer actions -->
                <div class="px-6 pb-6 flex justify-end gap-3">
                    <button type="button" id="enc-cancel" class="px-5 py-3 rounded-xl border-2 border-slate-300 text-slate-600 font-bold text-sm hover:bg-slate-100 transition-colors">Cancel</button>
                    <button type="submit" id="enc-submit-btn" class="px-6 py-3 rounded-xl bg-dranhs-green text-white font-bold text-sm hover:bg-emerald-700 transition-colors shadow-md flex items-center gap-2">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                        Enroll Student
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
const ENC_CATALOG  = <?php echo json_encode($catalog_for_js, JSON_HEX_TAG|JSON_HEX_APOS|JSON_HEX_QUOT|JSON_HEX_AMP); ?>;
const ENC_CLASSROOMS = <?php echo json_encode($classrooms, JSON_HEX_TAG|JSON_HEX_APOS|JSON_HEX_QUOT|JSON_HEX_AMP); ?>;

let encStudent = null;
let encStream  = null;

function tod(v) { return (v && String(v).trim()) ? String(v) : '--'; }
function encName(s) {
    let n = `${s.last_name||''}, ${s.first_name||''}`;
    if (s.middle_name) n += ' ' + s.middle_name.charAt(0).toUpperCase() + '.';
    if (s.extension_name) n += ' ' + s.extension_name;
    return n.trim();
}
function psLabel(gl, code) {
    const opts = ENC_CATALOG[gl] || [];
    const m = opts.find(i => String(i.code).toLowerCase() === String(code||'').toLowerCase());
    return m ? m.label : tod(code);
}
function infoCard(label, value) {
    return `<div class="rounded-xl bg-slate-50 border border-slate-100 p-3">
        <p class="text-xs font-bold uppercase tracking-wider text-slate-400 mb-1">${label}</p>
        <p class="text-sm font-semibold text-slate-700">${tod(value)}</p>
    </div>`;
}

// Tab switching
document.querySelectorAll('.enc-tab-btn').forEach(btn => {
    btn.addEventListener('click', function () {
        document.querySelectorAll('.enc-tab-btn').forEach(b => {
            b.classList.remove('border-dranhs-green','text-dranhs-green','active-tab');
            b.classList.add('border-transparent','text-slate-400');
        });
        this.classList.add('border-dranhs-green','text-dranhs-green','active-tab');
        this.classList.remove('border-transparent','text-slate-400');
        document.querySelectorAll('.enc-tab-content').forEach(t => t.classList.add('hidden'));
        document.getElementById('enc-tab-' + this.dataset.tab).classList.remove('hidden');
    });
});

function openEncModal(student) {
    encStudent = student;
    document.getElementById('enc-modal-name').textContent = encName(student);
    document.getElementById('enc-student-id').value = student.id;

    const flagAlert = document.getElementById('enc-flag-alert');
    const flagType = document.getElementById('enc-flag-type');
    const flagDetails = document.getElementById('enc-flag-details');
    const watchIssueType = String(student.watch_issue_type || '').trim();
    const watchIssueDetails = String(student.watch_issue_details || '').trim();
    if (watchIssueType) {
        flagType.textContent = watchIssueType;
        flagDetails.textContent = watchIssueDetails || 'No additional notes provided in the focus list.';
        flagAlert.classList.remove('hidden');
    } else {
        flagType.textContent = '';
        flagDetails.textContent = '';
        flagAlert.classList.add('hidden');
    }

    // Reset tabs
    document.querySelectorAll('.enc-tab-btn').forEach((b,i) => {
        b.classList.toggle('border-dranhs-green', i===0);
        b.classList.toggle('text-dranhs-green', i===0);
        b.classList.toggle('border-transparent', i!==0);
        b.classList.toggle('text-slate-400', i!==0);
    });
    document.querySelectorAll('.enc-tab-content').forEach((t,i) => t.classList.toggle('hidden', i!==0));

    // Overview — basic info
    const isG11 = student.grade_level === 'Grade 11';
    document.getElementById('enc-ps-label').textContent = isG11 ? 'Career Pathway' : 'Strand';
    document.getElementById('enc-basic-grid').innerHTML = [
        infoCard('LRN', student.lrn),
        infoCard('Grade Level', student.grade_level),
        infoCard('Track', student.track),
        infoCard('Student Type', student.student_type),
        infoCard('Semester', student.semester),
        infoCard('School Year', student.school_year),
        infoCard('Sex', student.sex),
        infoCard('Birthdate', student.birthdate),
    ].join('');

    // Pathway/strand info
    const psCode = student.pathway_strand || '';
    const psLbl  = psLabel(student.grade_level, psCode);
    document.getElementById('enc-ps-grid').innerHTML = [
        infoCard(isG11 ? 'Career Pathway' : 'Strand', psLbl),
        infoCard('Code', psCode),
        infoCard('Track', student.track),
    ].join('');

    // Section tab — filter classrooms by pathway_strand
    renderSectionGrid(psCode, student.grade_level);

    // Reset photo
    document.getElementById('enc-photo-file-input').value = '';
    document.getElementById('enc-photo-preview').src = '';
    document.getElementById('enc-photo-preview').classList.add('hidden');
    document.getElementById('enc-photo-placeholder').classList.remove('hidden');
    document.getElementById('enc-photo-clear').classList.add('hidden');

    document.getElementById('enc-modal').classList.remove('hidden');
    document.body.classList.add('overflow-hidden');
}

function renderSectionGrid(psCode, gradeLevel) {
    const matching = ENC_CLASSROOMS.filter(c =>
        String(c.pathway_strand||'').toLowerCase() === String(psCode||'').toLowerCase()
    );
    const grid = document.getElementById('enc-section-grid');
    const noMsg = document.getElementById('enc-no-section-msg');

    if (matching.length === 0) {
        grid.innerHTML = '';
        noMsg.classList.remove('hidden');
        return;
    }
    noMsg.classList.add('hidden');
    grid.innerHTML = matching.map(c => {
        const enrolled = parseInt(c.enrolled) || 0;
        const cap = parseInt(c.max_capacity) || 40;
        const avail = cap - enrolled;
        const full = avail <= 0;
        const pct = Math.min(100, Math.round(enrolled/cap*100));
        const barColor = pct >= 90 ? 'bg-red-500' : pct >= 70 ? 'bg-amber-400' : 'bg-dranhs-green';
        return `<button type="button"
            class="enc-section-card text-left rounded-xl border-2 p-4 transition-all ${full ? 'border-slate-200 bg-slate-50 opacity-60 cursor-not-allowed' : 'border-slate-200 bg-white hover:border-violet-400 hover:bg-violet-50'}"
            data-section="${c.section_name}" ${full ? 'disabled' : ''}>
            <p class="text-xs font-black uppercase tracking-wider text-slate-400 mb-1">${c.grade_level}</p>
            <p class="text-sm font-bold text-slate-800">${c.section_name}</p>
            <div class="mt-2 mb-1 w-full bg-slate-100 rounded-full h-1.5">
                <div class="${barColor} h-1.5 rounded-full" style="width:${pct}%"></div>
            </div>
            <p class="text-xs ${full?'text-red-500':'text-slate-500'} font-semibold">${full ? 'Full' : `${avail} slot${avail!==1?'s':''} available`} (${enrolled}/${cap})</p>
        </button>`;
    }).join('');

    document.querySelectorAll('.enc-section-card:not([disabled])').forEach(card => {
        card.addEventListener('click', function () {
            document.querySelectorAll('.enc-section-card').forEach(c => {
                c.classList.remove('border-violet-500','bg-violet-50','ring-2','ring-violet-300');
            });
            this.classList.add('border-violet-500','bg-violet-50','ring-2','ring-violet-300');
            const sec = this.dataset.section;
            document.getElementById('enc-assigned-section').value = sec;
            document.getElementById('enc-selected-section-name').textContent = sec;
            document.getElementById('enc-selected-section-wrap').classList.remove('hidden');
        });
    });
}

// Auto-assign: pick section with most available slots
document.getElementById('enc-auto-assign-btn').addEventListener('click', function () {
    const psCode = encStudent?.pathway_strand || '';
    const matching = ENC_CLASSROOMS.filter(c =>
        String(c.pathway_strand||'').toLowerCase() === String(psCode||'').toLowerCase()
    ).filter(c => (parseInt(c.max_capacity)||40) - (parseInt(c.enrolled)||0) > 0);

    if (matching.length === 0) return;
    // Sort by most available slots
    matching.sort((a,b) => ((parseInt(b.max_capacity)||40)-(parseInt(b.enrolled)||0)) - ((parseInt(a.max_capacity)||40)-(parseInt(a.enrolled)||0)));
    const best = matching[0];

    document.querySelectorAll('.enc-section-card').forEach(c => {
        c.classList.remove('border-violet-500','bg-violet-50','ring-2','ring-violet-300');
        if (c.dataset.section === best.section_name) {
            c.classList.add('border-violet-500','bg-violet-50','ring-2','ring-violet-300');
        }
    });
    document.getElementById('enc-assigned-section').value = best.section_name;
    document.getElementById('enc-selected-section-name').textContent = best.section_name;
    document.getElementById('enc-selected-section-wrap').classList.remove('hidden');
});

// Close modal + stop camera
function closeEncModal() {
    stopCamera();
    document.getElementById('enc-modal').classList.add('hidden');
    document.body.classList.remove('overflow-hidden');
}
document.getElementById('enc-close').addEventListener('click', closeEncModal);
document.getElementById('enc-cancel').addEventListener('click', closeEncModal);
document.getElementById('enc-backdrop').addEventListener('click', closeEncModal);

// Open buttons
document.querySelectorAll('.enc-open-btn').forEach(btn => {
    btn.addEventListener('click', function () {
        openEncModal(JSON.parse(this.dataset.student));
    });
});

// ---- WEBCAM ----
function stopCamera() {
    if (encStream) { encStream.getTracks().forEach(t => t.stop()); encStream = null; }
    document.getElementById('enc-webcam').classList.add('hidden');
    document.getElementById('enc-cam-capture').classList.add('hidden');
    document.getElementById('enc-cam-stop').classList.add('hidden');
    document.getElementById('enc-cam-start').classList.remove('hidden');
}

document.getElementById('enc-cam-start').addEventListener('click', async function () {
    try {
        encStream = await navigator.mediaDevices.getUserMedia({ video: true });
        const video = document.getElementById('enc-webcam');
        video.srcObject = encStream;
        video.classList.remove('hidden');
        this.classList.add('hidden');
        document.getElementById('enc-cam-capture').classList.remove('hidden');
        document.getElementById('enc-cam-stop').classList.remove('hidden');
    } catch(e) { alert('Camera not accessible: ' + e.message); }
});

document.getElementById('enc-cam-stop').addEventListener('click', stopCamera);

document.getElementById('enc-cam-capture').addEventListener('click', function () {
    const video  = document.getElementById('enc-webcam');
    const canvas = document.getElementById('enc-canvas');
    canvas.width  = video.videoWidth;
    canvas.height = video.videoHeight;
    canvas.getContext('2d').drawImage(video, 0, 0);
    const dataUrl = canvas.toDataURL('image/jpeg', 0.85);
    setPhoto(dataUrl);
    stopCamera();
});

document.getElementById('enc-photo-upload').addEventListener('change', function () {
    const file = this.files[0];
    if (!file) return;
    const reader = new FileReader();
    reader.onload = e => setPhoto(e.target.result);
    reader.readAsDataURL(file);
    // Also inject directly into the file input
    const dt = new DataTransfer();
    dt.items.add(file);
    document.getElementById('enc-photo-file-input').files = dt.files;
});

function setPhoto(dataUrl) {
    // Show preview
    const img = document.getElementById('enc-photo-preview');
    img.src = dataUrl;
    img.classList.remove('hidden');
    document.getElementById('enc-photo-placeholder').classList.add('hidden');
    document.getElementById('enc-photo-clear').classList.remove('hidden');

    // Convert dataUrl to File and inject into the real file input
    fetch(dataUrl)
        .then(r => r.blob())
        .then(blob => {
            const file = new File([blob], 'id_photo.jpg', { type: 'image/jpeg' });
            const dt = new DataTransfer();
            dt.items.add(file);
            document.getElementById('enc-photo-file-input').files = dt.files;
        });
}

document.getElementById('enc-photo-clear').addEventListener('click', function () {
    document.getElementById('enc-photo-file-input').value = '';
    document.getElementById('enc-photo-preview').src = '';
    document.getElementById('enc-photo-preview').classList.add('hidden');
    document.getElementById('enc-photo-placeholder').classList.remove('hidden');
    this.classList.add('hidden');
});

// Search
document.getElementById('encode-search').addEventListener('input', function () {
    const q = this.value.toLowerCase().trim();
    document.querySelectorAll('#encode-tbody tr').forEach(row => {
        row.style.display = row.textContent.toLowerCase().includes(q) ? '' : 'none';
    });
});
</script>
