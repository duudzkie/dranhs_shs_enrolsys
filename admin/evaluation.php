<?php
require_once __DIR__ . '/../pathway_strand_catalog.php';
require_once __DIR__ . '/../db.php';

$conn = db_connect();

$evaluation_rows = [];
$classroom_rows = [];
$db_error = '';
$toast_message = '';

// Build catalog for JS
$catalog_for_js = [
    'Grade 11' => get_pathway_strand_options('Grade 11'),
    'Grade 12' => get_pathway_strand_options('Grade 12'),
];

if ($conn->connect_error) {
    $db_error = 'Database connection failed.';
} else {
    // Handle edit form submission
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_student') {
        $sid = (int)($_POST['student_id'] ?? 0);
        if ($sid > 0) {
            $grade_level = trim($_POST['grade_level'] ?? '');
            $track = trim($_POST['track'] ?? '');
            $pathway_code = get_pathway_strand_code($grade_level, trim($_POST['pathway_strand'] ?? ''));
            $age = ($_POST['age'] ?? '') !== '' ? (int)$_POST['age'] : null;
            $stmt = $conn->prepare("UPDATE students SET
                lrn=?,last_name=?,first_name=?,middle_name=?,extension_name=?,
                birthdate=?,age=?,sex=?,place_of_birth=?,mother_tongue=?,religion=?,
                school_year=?,grade_level=?,student_type=?,semester=?,track=?,pathway_strand=?,
                street=?,province=?,city=?,barangay=?,zip_code=?,living_with=?,
                father_contact=?,mother_contact=?,guardian_contact=?,
                prev_school=?,prev_school_year=?,prev_section=?
                WHERE id=?");
            if ($stmt) {
                $p = [
                    $_POST['lrn'],$_POST['last_name'],$_POST['first_name'],$_POST['middle_name'],$_POST['extension_name'],
                    $_POST['birthdate'],$age,$_POST['sex'],$_POST['place_of_birth'],$_POST['mother_tongue'],$_POST['religion'],
                    $_POST['school_year'],$grade_level,$_POST['student_type'],$_POST['semester'],$track,$pathway_code,
                    $_POST['street'],$_POST['province'],$_POST['city'],$_POST['barangay'],$_POST['zip_code'],$_POST['living_with'],
                    $_POST['father_contact']??'',$_POST['mother_contact']??'',$_POST['guardian_contact']??'',
                    $_POST['prev_school'],$_POST['prev_school_year'],$_POST['prev_section'],
                    $sid
                ];
                $stmt->bind_param(str_repeat('s', count($p)-1).'i', ...$p);
                $stmt->execute();
                $stmt->close();
            }
        }
    }

    // Handle POST: mark verified → for_encoding, or not_qualified
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['eval_action'])) {
        $sid = (int)($_POST['student_id'] ?? 0);
        if ($sid > 0) {
            if ($_POST['eval_action'] === 'verify') {
                $final_pathway = trim($_POST['final_pathway'] ?? '');
                if ($final_pathway !== '') {
                    $stmt = $conn->prepare("UPDATE students SET enrollment_status = 'for_encoding', pathway_strand = ? WHERE id = ?");
                    if ($stmt) { $stmt->bind_param("si", $final_pathway, $sid); $stmt->execute(); $stmt->close(); }
                } else {
                    $stmt = $conn->prepare("UPDATE students SET enrollment_status = 'for_encoding' WHERE id = ?");
                    if ($stmt) { $stmt->bind_param("i", $sid); $stmt->execute(); $stmt->close(); }
                }

                // Fetch student details for evaluation record
                $stu = null;
                $sq = $conn->prepare("SELECT lrn, last_name, first_name, middle_name, extension_name, grade_level, track, student_type, semester, school_year FROM students WHERE id = ?");
                if ($sq) { $sq->bind_param("i", $sid); $sq->execute(); $stu = $sq->get_result()->fetch_assoc(); $sq->close(); }

                if ($stu) {
                    $lrn          = $stu['lrn'] ?? '';
                    $student_name = trim(($stu['last_name']??'').', '.($stu['first_name']??'').((!empty($stu['middle_name']))?' '.strtoupper(substr($stu['middle_name'],0,1)).'.':'').((!empty($stu['extension_name']))?' '.$stu['extension_name']:''));
                    $grade_level  = $stu['grade_level'] ?? '';
                    $track        = $stu['track'] ?? '';
                    $student_type = $stu['student_type'] ?? '';
                    $semester     = $stu['semester'] ?? '';
                    $school_year  = $stu['school_year'] ?? '';
                    $doc_sf09     = (int)($_POST['doc_sf09'] ?? 0);
                    $doc_psa      = (int)($_POST['doc_psa'] ?? 0);
                    $doc_good     = (int)($_POST['doc_good'] ?? 0);
                    $notes        = trim($_POST['eval_notes'] ?? '');
                    $evaluated_by = (int)($_SESSION['user_id'] ?? 0);

                    // Upsert: update if already evaluated, insert if new
                    $chk = $conn->prepare("SELECT id FROM evaluations WHERE student_id = ?");
                    if ($chk) { $chk->bind_param("i", $sid); $chk->execute(); $exists = $chk->get_result()->num_rows > 0; $chk->close(); }

                    if (!empty($exists)) {
                        $ins = $conn->prepare("UPDATE evaluations SET lrn=?,student_name=?,grade_level=?,track=?,final_pathway=?,student_type=?,semester=?,school_year=?,doc_sf09=?,doc_psa=?,doc_good_moral=?,notes=?,evaluated_by=?,evaluated_at=NOW() WHERE student_id=?");
                        if ($ins) { $ins->bind_param("ssssssssiisiii",$lrn,$student_name,$grade_level,$track,$final_pathway,$student_type,$semester,$school_year,$doc_sf09,$doc_psa,$doc_good,$notes,$evaluated_by,$sid); $ins->execute(); $ins->close(); }
                    } else {
                        $ins = $conn->prepare("INSERT INTO evaluations (student_id,lrn,student_name,grade_level,track,final_pathway,student_type,semester,school_year,doc_sf09,doc_psa,doc_good_moral,notes,evaluated_by) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?)");
                        if ($ins) { $ins->bind_param("issssssssiisii",$sid,$lrn,$student_name,$grade_level,$track,$final_pathway,$student_type,$semester,$school_year,$doc_sf09,$doc_psa,$doc_good,$notes,$evaluated_by); $ins->execute(); $ins->close(); }
                    }
                }

                $toast_message = 'verified';
            } elseif ($_POST['eval_action'] === 'withdraw') {
                $stmt = $conn->prepare("UPDATE students SET enrollment_status = 'withdrawn' WHERE id = ?");
                if ($stmt) { $stmt->bind_param("i", $sid); $stmt->execute(); $stmt->close(); }
                $toast_message = 'withdrawn';
            }
        }
    }

    // Only show students pending evaluation
    $sql = "SELECT students.*, watchlist.issue_type AS watch_issue_type, watchlist.issue_details AS watch_issue_details
            FROM students
            LEFT JOIN watchlist
                ON watchlist.lrn = students.lrn
               AND watchlist.school_year = students.school_year
            WHERE students.enrollment_status = 'for_evaluation' OR students.enrollment_status IS NULL
            ORDER BY students.created_at DESC, students.id DESC";
    $res = $conn->query($sql);
    if ($res) {
        while ($row = $res->fetch_assoc()) $evaluation_rows[] = $row;
        $res->close();
    } else {
        $db_error = 'Unable to load evaluation records.';
    }

    $classroom_sql = "SELECT c.grade_level, c.pathway_strand, c.max_capacity,
        (SELECT COUNT(*) FROM students s WHERE s.assigned_section = c.section_name AND s.enrollment_status = 'enrolled') AS enrolled
        FROM classrooms c";
    $classroom_res = $conn->query($classroom_sql);
    if ($classroom_res) {
        while ($row = $classroom_res->fetch_assoc()) $classroom_rows[] = $row;
        $classroom_res->close();
    }
    $conn->close();
}

function eval_full_name($row) {
    $name = ($row['last_name'] ?? '') . ', ' . ($row['first_name'] ?? '');
    if (!empty($row['middle_name'])) $name .= ' ' . strtoupper(substr($row['middle_name'], 0, 1)) . '.';
    if (!empty($row['extension_name'])) $name .= ' ' . $row['extension_name'];
    return trim($name);
}
?>

<?php if ($toast_message): ?>
<script>window.location.replace('?page=evaluation&msg=<?php echo $toast_message; ?>');</script>
<?php endif; ?>

<?php if (isset($_GET['msg'])): ?>
<div class="mb-4 rounded-xl border px-5 py-4 text-sm font-semibold <?php echo $_GET['msg'] === 'withdrawn' ? 'bg-red-50 border-red-200 text-red-700' : 'bg-emerald-50 border-emerald-200 text-emerald-700'; ?>">
    <?php echo $_GET['msg'] === 'withdrawn' ? 'Enrollee marked for withdrawal. Review on the Student page.' : 'Enrollee verified and moved to Encoding.'; ?>
</div>
<?php endif; ?>

<div class="bg-white rounded-xl shadow-sm border border-slate-100 overflow-hidden">
    <div class="px-6 py-4 border-b border-slate-100 flex justify-between items-center">
        <div>
            <h3 class="font-heading font-bold text-lg text-dranhs-dark">Evaluation List</h3>
            <p class="text-sm text-slate-500">Enrollees pending evaluation. Click Evaluate to review documents and verify.</p>
        </div>
    </div>
    <div class="px-6 py-3 border-b border-slate-100 bg-slate-50">
        <div class="relative max-w-sm">
            <svg class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-4.35-4.35M17 11A6 6 0 1 1 5 11a6 6 0 0 1 12 0z"/></svg>
            <input type="text" id="eval-search" placeholder="Search name, LRN, track, pathway..." class="w-full pl-9 pr-4 py-2 text-sm border border-slate-300 rounded-lg focus:outline-none focus:border-dranhs-green focus:ring-2 focus:ring-dranhs-green/20 bg-white">
        </div>
    </div>

    <?php if ($db_error): ?>
        <div class="px-6 py-5 text-sm font-semibold text-red-600 bg-red-50"><?php echo htmlspecialchars($db_error); ?></div>
    <?php elseif (empty($evaluation_rows)): ?>
        <div class="px-6 py-10 text-center">
            <p class="text-sm font-semibold text-slate-600">No enrollees pending evaluation.</p>
            <p class="text-sm text-slate-400 mt-1">All enrollees have been evaluated or none have been submitted yet.</p>
        </div>
    <?php else: ?>
    <div class="overflow-x-auto">
        <table class="w-full text-left border-collapse">
            <thead class="bg-slate-50 text-xs uppercase text-slate-500 font-bold">
                <tr>
                    <th class="px-6 py-3 tracking-wider">Enrollee</th>
                    <th class="px-6 py-3 tracking-wider">LRN</th>
                    <th class="px-6 py-3 tracking-wider">Grade Level</th>
                    <th class="px-6 py-3 tracking-wider">Track</th>
                    <th class="px-6 py-3 tracking-wider">Pathway / Strand</th>
                    <th class="px-6 py-3 tracking-wider">Action</th>
                </tr>
            </thead>
            <tbody id="eval-tbody" class="divide-y divide-slate-100 text-sm">
                <?php foreach ($evaluation_rows as $row): ?>
                <tr class="hover:bg-slate-50 transition-colors">
                    <td class="px-6 py-4">
                        <div class="font-semibold text-slate-700"><?php echo htmlspecialchars(eval_full_name($row)); ?></div>
                        <div class="text-xs text-slate-400 mt-1">Filed <?php echo htmlspecialchars(date('M d, Y', strtotime($row['created_at']))); ?></div>
                        <?php if (!empty($row['watch_issue_type'])): ?>
                            <div class="mt-2 inline-flex items-center rounded-full bg-amber-50 px-2.5 py-1 text-[11px] font-bold uppercase tracking-wide text-amber-700 border border-amber-200">Flagged: <?php echo htmlspecialchars($row['watch_issue_type']); ?></div>
                        <?php endif; ?>
                    </td>
                    <td class="px-6 py-4 text-slate-600"><?php echo htmlspecialchars($row['lrn'] ?: '--'); ?></td>
                    <td class="px-6 py-4 text-slate-600"><?php echo htmlspecialchars($row['grade_level'] ?: '--'); ?></td>
                    <td class="px-6 py-4 text-slate-600"><?php echo htmlspecialchars($row['track'] ?: '--'); ?></td>
                    <td class="px-6 py-4 text-slate-600"><?php echo htmlspecialchars(get_pathway_strand_label($row['grade_level'] ?? '', $row['pathway_strand'] ?? '')); ?></td>
                    <td class="px-6 py-4">
                        <button type="button"
                            class="eval-open-btn inline-flex items-center justify-center px-3 py-2 rounded-lg bg-dranhs-green text-white text-xs font-bold uppercase tracking-wide hover:bg-emerald-600 transition-colors"
                            data-student='<?php echo htmlspecialchars(json_encode($row, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP), ENT_QUOTES); ?>'>
                            Evaluate
                        </button>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>
</div>

<!-- ===== EVALUATION MODAL ===== -->
<div id="eval-modal" class="fixed inset-0 z-50 hidden">
    <div class="absolute inset-0 bg-slate-900/60" id="eval-modal-backdrop"></div>
    <div class="relative z-10 min-h-screen flex items-start justify-center p-4 py-8">
        <div class="w-full max-w-4xl bg-slate-50 rounded-2xl shadow-2xl border border-slate-200 overflow-hidden">

            <!-- Header -->
            <div class="bg-dranhs-dark px-6 py-5 flex items-center justify-between">
                <div>
                    <p class="text-xs font-bold uppercase tracking-[0.2em] text-amber-400 mb-1">Evaluation</p>
                    <h3 id="eval-modal-name" class="font-heading font-black text-2xl text-white">Enrollee Name</h3>
                </div>
                <button type="button" id="eval-modal-close" class="inline-flex items-center justify-center w-10 h-10 rounded-full bg-white/10 text-white hover:bg-white/20 transition-colors text-xl">&times;</button>
            </div>

            <div class="max-h-[82vh] overflow-y-auto p-6 space-y-5">

                <!-- Basic Info -->
                <div class="bg-white rounded-2xl border border-slate-200 overflow-hidden relative">
                    <div class="absolute top-0 left-0 w-2 h-full rounded-l-2xl bg-dranhs-green"></div>
                    <div class="px-6 pt-4 pb-3 border-b border-slate-100 ml-2 flex items-center justify-between">
                        <h4 class="text-sm font-black uppercase tracking-widest text-dranhs-green">Basic Information</h4>
                        <button type="button" id="eval-view-full-btn" class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-blue-50 text-blue-600 text-xs font-bold hover:bg-blue-100 transition-colors">
                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
                            View Full Info
                        </button>
                    </div>
                    <div class="p-5 ml-2 grid grid-cols-2 md:grid-cols-4 gap-4" id="eval-basic-grid"></div>
                </div>

                <div id="eval-distance-panel" class="hidden bg-white rounded-2xl border border-amber-200 overflow-hidden relative">
                    <div class="absolute top-0 left-0 w-2 h-full rounded-l-2xl bg-amber-500"></div>
                    <div class="px-6 pt-4 pb-3 border-b border-amber-100 ml-2">
                        <h4 class="text-sm font-black uppercase tracking-widest text-amber-700">Distance Advisory</h4>
                        <p id="eval-distance-caption" class="text-xs text-amber-700 mt-0.5"></p>
                    </div>
                    <div class="p-5 ml-2 grid grid-cols-1 md:grid-cols-3 gap-4" id="eval-distance-grid"></div>
                </div>

                <div id="eval-special-needs-panel" class="hidden bg-white rounded-2xl border border-sky-200 overflow-hidden relative">
                    <div class="absolute top-0 left-0 w-2 h-full rounded-l-2xl bg-sky-500"></div>
                    <div class="px-6 pt-4 pb-3 border-b border-sky-100 ml-2">
                        <h4 class="text-sm font-black uppercase tracking-widest text-sky-700">Special Needs Information</h4>
                        <p class="text-xs text-sky-700 mt-0.5">Shown only when the enrollee submitted SPED or PWD-related information.</p>
                    </div>
                    <div class="p-5 ml-2 grid grid-cols-1 md:grid-cols-3 gap-4" id="eval-special-needs-grid"></div>
                </div>

                <!-- Documents Checklist -->
                <div class="bg-white rounded-2xl border border-slate-200 overflow-hidden relative">
                    <div class="absolute top-0 left-0 w-2 h-full rounded-l-2xl" style="background:#6d28d9"></div>
                    <div class="px-6 pt-4 pb-3 border-b border-slate-100 ml-2">
                        <h4 class="text-sm font-black uppercase tracking-widest" style="color:#6d28d9">Document Checklist</h4>
                        <p class="text-xs text-slate-500 mt-0.5">Check each document as the enrollee presents the hardcopy.</p>
                    </div>
                    <div class="p-5 ml-2 space-y-3" id="eval-docs-list">
                        <!-- populated by JS based on student_type -->
                    </div>
                    <div class="px-5 pb-5 ml-2">
                        <div id="eval-flag-alert" class="hidden mb-4 rounded-xl border border-amber-200 bg-amber-50 px-4 py-3">
                            <p class="text-sm font-bold text-amber-800">This student is flagged: <span id="eval-flag-type"></span></p>
                            <p id="eval-flag-details" class="mt-1 text-xs font-medium text-amber-700"></p>
                        </div>
                        <label class="block text-xs font-bold uppercase tracking-wider text-slate-500 mb-2">Notes / Remarks</label>
                        <textarea id="eval-notes" rows="2" placeholder="Optional notes about the documents or enrollee..." class="w-full bg-white border-2 border-slate-300 px-4 py-3 rounded-xl text-slate-800 text-sm font-medium focus:border-violet-600 focus:ring-2 focus:ring-violet-600/20 outline-none resize-none"></textarea>
                    </div>
                </div>

                <!-- Available Pathways / Strands -->
                <div class="bg-white rounded-2xl border border-slate-200 overflow-hidden relative">
                    <div class="absolute top-0 left-0 w-2 h-full rounded-l-2xl" style="background:#0ea5e9"></div>
                    <div class="px-6 pt-4 pb-3 border-b border-slate-100 ml-2">
                        <h4 class="text-sm font-black uppercase tracking-widest" style="color:#0ea5e9" id="eval-pathway-section-title">Available Career Pathways</h4>
                        <p class="text-xs text-slate-500 mt-0.5">Select the final <span id="eval-pathway-label-hint">career pathway</span> for this enrollee based on currently available classroom slots.</p>
                    </div>
                    <div class="p-5 ml-2 grid grid-cols-2 md:grid-cols-3 gap-3" id="eval-pathway-grid"></div>
                    <input type="hidden" name="final_pathway" id="eval-final-pathway">
                </div>

                <!-- Action Buttons -->
                <form method="POST" id="eval-action-form" class="flex flex-col sm:flex-row gap-3 pt-1">
                    <input type="hidden" name="student_id" id="eval-form-student-id">
                    <input type="hidden" name="final_pathway" id="eval-form-final-pathway">
                    <input type="hidden" name="doc_sf09" id="eval-form-doc-sf09" value="0">
                    <input type="hidden" name="doc_psa" id="eval-form-doc-psa" value="0">
                    <input type="hidden" name="doc_good" id="eval-form-doc-good" value="0">
                    <input type="hidden" name="eval_notes" id="eval-form-notes" value="">
                    <button type="submit" name="eval_action" value="verify"
                        class="flex-1 inline-flex items-center justify-center gap-2 px-6 py-3.5 rounded-xl bg-dranhs-green text-white font-bold text-sm hover:bg-emerald-700 transition-colors shadow-md">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                        Verified — Move to Encoding
                    </button>
                    <button type="submit" name="eval_action" value="withdraw"
                        onclick="return confirm('Mark this enrollee for withdrawal? They will be removed from evaluation and flagged on the Student page.')"
                        class="inline-flex items-center justify-center gap-2 px-6 py-3.5 rounded-xl bg-red-50 text-red-600 border-2 border-red-200 font-bold text-sm hover:bg-red-100 transition-colors">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 14l2-2m0 0l2-2m-2 2l-2-2m2 2l2 2m7-2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                        For Withdrawal
                    </button>
                    <button type="button" id="eval-cancel-btn"
                        class="inline-flex items-center justify-center px-6 py-3.5 rounded-xl border-2 border-slate-300 text-slate-600 font-bold text-sm hover:bg-slate-100 transition-colors">
                        Cancel
                    </button>
                </form>

            </div><!-- end scrollable -->
        </div>
    </div>
</div>

<!-- Edit Modal (from Full Info) -->
<div id="eval-edit-modal" class="fixed inset-0 z-[70] hidden">
    <div class="absolute inset-0 bg-slate-900/70" id="eval-edit-backdrop"></div>
    <div class="relative z-10 min-h-screen flex items-start justify-center p-4 py-8">
        <div class="w-full max-w-4xl bg-slate-50 rounded-2xl shadow-2xl border border-slate-200 overflow-hidden">
            <div class="bg-dranhs-dark px-6 py-5 flex items-center justify-between">
                <div>
                    <p class="text-xs font-bold uppercase tracking-[0.2em] text-amber-400 mb-1">Edit Enrollment Record</p>
                    <h3 id="eval-edit-title" class="font-heading font-black text-2xl text-white">Edit Student</h3>
                </div>
                <button type="button" id="eval-edit-close" class="inline-flex items-center justify-center w-10 h-10 rounded-full bg-white/10 text-white hover:bg-white/20 transition-colors text-xl">&times;</button>
            </div>
            <form method="POST" id="eval-edit-form" class="max-h-[80vh] overflow-y-auto p-6 space-y-5">
                <input type="hidden" name="action" value="update_student">
                <input type="hidden" name="student_id" id="eval-edit-student-id">

                <!-- General Info -->
                <div class="bg-white rounded-2xl shadow-sm border border-slate-200 overflow-hidden relative">
                    <div class="absolute top-0 left-0 w-2 h-full rounded-l-2xl bg-dranhs-green"></div>
                    <div class="px-6 pt-5 pb-3 border-b border-slate-100 ml-2">
                        <h4 class="text-lg font-heading font-black uppercase tracking-tight text-dranhs-green">General Information</h4>
                    </div>
                    <div class="p-6 ml-2 grid grid-cols-1 md:grid-cols-3 gap-5">
                        <div><label class="block text-xs font-bold uppercase tracking-wider text-slate-500 mb-2">Learner Category</label>
                        <select name="student_type" id="ee-student-type" class="w-full bg-white border-2 border-slate-300 px-4 py-3 rounded-xl text-sm font-medium focus:border-dranhs-green outline-none">
                            <option value="">Select...</option>
                            <option value="Grade 10 DRANHS Student">Grade 10 DRANHS Student</option>
                            <option value="Transferee">Transferee</option>
                            <option value="Balik-Aral(Returnee)">Balik-Aral (Returnee)</option>
                            <option value="Repeater">Repeater</option>
                            <option value="Old Student (Repeater)">Old Student (Repeater)</option>
                            <option value="ALS">ALS</option>
                        </select></div>
                        <div><label class="block text-xs font-bold uppercase tracking-wider text-slate-500 mb-2">School Year</label>
                        <input type="text" name="school_year" id="ee-school-year" class="w-full bg-white border-2 border-slate-300 px-4 py-3 rounded-xl text-sm font-medium focus:border-dranhs-green outline-none"></div>
                        <div><label class="block text-xs font-bold uppercase tracking-wider text-slate-500 mb-2">Grade Level</label>
                        <select name="grade_level" id="ee-grade-level" class="w-full bg-white border-2 border-slate-300 px-4 py-3 rounded-xl text-sm font-medium focus:border-dranhs-green outline-none">
                            <option value="Grade 11">Grade 11</option>
                            <option value="Grade 12">Grade 12</option>
                        </select></div>
                        <div><label class="block text-xs font-bold uppercase tracking-wider text-slate-500 mb-2">Term</label>
                        <select name="semester" id="ee-semester" class="w-full bg-white border-2 border-slate-300 px-4 py-3 rounded-xl text-sm font-medium focus:border-dranhs-green outline-none">
                            <option value="term_1">Term 1</option>
                            <option value="term_2">Term 2</option>
                            <option value="term_3">Term 3</option>
                        </select></div>
                        <div><label class="block text-xs font-bold uppercase tracking-wider text-slate-500 mb-2">LRN</label>
                        <input type="text" name="lrn" id="ee-lrn" maxlength="12" class="w-full bg-white border-2 border-slate-300 px-4 py-3 rounded-xl text-sm font-medium focus:border-dranhs-green outline-none"></div>
                        <div><label class="block text-xs font-bold uppercase tracking-wider text-slate-500 mb-2">Track</label>
                        <select name="track" id="ee-track" class="w-full bg-white border-2 border-slate-300 px-4 py-3 rounded-xl text-sm font-medium focus:border-dranhs-green outline-none">
                            <option value="">Select Track...</option>
                            <option value="Academic">Academic</option>
                            <option value="Tech-Pro">Tech-Pro</option>
                            <option value="TVL">TVL</option>
                        </select></div>
                        <div class="md:col-span-2"><label class="block text-xs font-bold uppercase tracking-wider text-slate-500 mb-2">Pathway / Strand</label>
                        <select name="pathway_strand" id="ee-pathway-strand" class="w-full bg-white border-2 border-slate-300 px-4 py-3 rounded-xl text-sm font-medium focus:border-dranhs-green outline-none">
                            <option value="">Select Track first...</option>
                        </select></div>
                    </div>
                </div>

                <!-- Personal Info -->
                <div class="bg-white rounded-2xl shadow-sm border border-slate-200 overflow-hidden relative">
                    <div class="absolute top-0 left-0 w-2 h-full rounded-l-2xl" style="background:#6d28d9"></div>
                    <div class="px-6 pt-5 pb-3 border-b border-slate-100 ml-2">
                        <h4 class="text-lg font-heading font-black uppercase tracking-tight" style="color:#6d28d9">Learner's Personal Information</h4>
                    </div>
                    <div class="p-6 ml-2 grid grid-cols-1 md:grid-cols-3 gap-5">
                        <div><label class="block text-xs font-bold uppercase tracking-wider text-slate-500 mb-2">Last Name</label><input type="text" name="last_name" id="ee-last-name" class="w-full bg-white border-2 border-slate-300 px-4 py-3 rounded-xl text-sm font-medium focus:border-violet-600 outline-none"></div>
                        <div><label class="block text-xs font-bold uppercase tracking-wider text-slate-500 mb-2">First Name</label><input type="text" name="first_name" id="ee-first-name" class="w-full bg-white border-2 border-slate-300 px-4 py-3 rounded-xl text-sm font-medium focus:border-violet-600 outline-none"></div>
                        <div><label class="block text-xs font-bold uppercase tracking-wider text-slate-500 mb-2">Middle Name</label><input type="text" name="middle_name" id="ee-middle-name" class="w-full bg-white border-2 border-slate-300 px-4 py-3 rounded-xl text-sm font-medium focus:border-violet-600 outline-none"></div>
                        <div><label class="block text-xs font-bold uppercase tracking-wider text-slate-500 mb-2">Ext. Name</label><input type="text" name="extension_name" id="ee-extension-name" placeholder="Jr., III" class="w-full bg-white border-2 border-slate-300 px-4 py-3 rounded-xl text-sm font-medium focus:border-violet-600 outline-none"></div>
                        <div><label class="block text-xs font-bold uppercase tracking-wider text-slate-500 mb-2">Birthdate</label><input type="date" name="birthdate" id="ee-birthdate" class="w-full bg-white border-2 border-slate-300 px-4 py-3 rounded-xl text-sm font-medium focus:border-violet-600 outline-none"></div>
                        <div><label class="block text-xs font-bold uppercase tracking-wider text-slate-500 mb-2">Age</label><input type="number" name="age" id="ee-age" readonly class="w-full bg-slate-100 border-2 border-slate-200 px-4 py-3 rounded-xl text-sm font-medium outline-none cursor-not-allowed"></div>
                        <div><label class="block text-xs font-bold uppercase tracking-wider text-slate-500 mb-2">Sex</label>
                        <select name="sex" id="ee-sex" class="w-full bg-white border-2 border-slate-300 px-4 py-3 rounded-xl text-sm font-medium focus:border-violet-600 outline-none">
                            <option value="">Select...</option>
                            <option value="Male">Male</option>
                            <option value="Female">Female</option>
                        </select></div>
                        <div class="md:col-span-2"><label class="block text-xs font-bold uppercase tracking-wider text-slate-500 mb-2">Place of Birth</label><input type="text" name="place_of_birth" id="ee-place-of-birth" class="w-full bg-white border-2 border-slate-300 px-4 py-3 rounded-xl text-sm font-medium focus:border-violet-600 outline-none"></div>
                        <div><label class="block text-xs font-bold uppercase tracking-wider text-slate-500 mb-2">Mother Tongue</label><input type="text" name="mother_tongue" id="ee-mother-tongue" class="w-full bg-white border-2 border-slate-300 px-4 py-3 rounded-xl text-sm font-medium focus:border-violet-600 outline-none"></div>
                        <div><label class="block text-xs font-bold uppercase tracking-wider text-slate-500 mb-2">Religion</label><input type="text" name="religion" id="ee-religion" class="w-full bg-white border-2 border-slate-300 px-4 py-3 rounded-xl text-sm font-medium focus:border-violet-600 outline-none"></div>
                    </div>
                </div>

                <!-- Address -->
                <div class="bg-white rounded-2xl shadow-sm border border-slate-200 overflow-hidden relative">
                    <div class="absolute top-0 left-0 w-2 h-full rounded-l-2xl" style="background:#0ea5e9"></div>
                    <div class="px-6 pt-5 pb-3 border-b border-slate-100 ml-2">
                        <h4 class="text-lg font-heading font-black uppercase tracking-tight" style="color:#0ea5e9">Current Address</h4>
                    </div>
                    <div class="p-6 ml-2 grid grid-cols-1 md:grid-cols-3 gap-5">
                        <div class="md:col-span-3"><label class="block text-xs font-bold uppercase tracking-wider text-slate-500 mb-2">Street / Purok / Sitio</label><input type="text" name="street" id="ee-street" class="w-full bg-white border-2 border-slate-300 px-4 py-3 rounded-xl text-sm font-medium focus:border-sky-500 outline-none"></div>
                        <div><label class="block text-xs font-bold uppercase tracking-wider text-slate-500 mb-2">Barangay</label><input type="text" name="barangay" id="ee-barangay" class="w-full bg-white border-2 border-slate-300 px-4 py-3 rounded-xl text-sm font-medium focus:border-sky-500 outline-none"></div>
                        <div><label class="block text-xs font-bold uppercase tracking-wider text-slate-500 mb-2">Municipality / City</label><input type="text" name="city" id="ee-city" class="w-full bg-white border-2 border-slate-300 px-4 py-3 rounded-xl text-sm font-medium focus:border-sky-500 outline-none"></div>
                        <div><label class="block text-xs font-bold uppercase tracking-wider text-slate-500 mb-2">Province</label><input type="text" name="province" id="ee-province" class="w-full bg-white border-2 border-slate-300 px-4 py-3 rounded-xl text-sm font-medium focus:border-sky-500 outline-none"></div>
                        <div><label class="block text-xs font-bold uppercase tracking-wider text-slate-500 mb-2">ZIP Code</label><input type="text" name="zip_code" id="ee-zip-code" class="w-full bg-white border-2 border-slate-300 px-4 py-3 rounded-xl text-sm font-medium focus:border-sky-500 outline-none"></div>
                        <div><label class="block text-xs font-bold uppercase tracking-wider text-slate-500 mb-2">Living With</label>
                        <select name="living_with" id="ee-living-with" class="w-full bg-white border-2 border-slate-300 px-4 py-3 rounded-xl text-sm font-medium focus:border-sky-500 outline-none">
                            <option value="">Select...</option>
                            <option value="Parents">Parents</option>
                            <option value="Relatives">Relatives</option>
                            <option value="Guardian">Guardian</option>
                            <option value="Boarding House / Dorm">Boarding House / Dorm</option>
                            <option value="Others">Others</option>
                        </select></div>
                    </div>
                </div>

                <!-- Parents -->
                <div class="bg-white rounded-2xl shadow-sm border border-slate-200 overflow-hidden relative">
                    <div class="absolute top-0 left-0 w-2 h-full rounded-l-2xl" style="background:#f59e0b"></div>
                    <div class="px-6 pt-5 pb-3 border-b border-slate-100 ml-2">
                        <h4 class="text-lg font-heading font-black uppercase tracking-tight" style="color:#f59e0b">Parent's / Guardian's Information</h4>
                    </div>
                    <div class="p-6 ml-2 grid grid-cols-1 md:grid-cols-3 gap-5">
                        <div><label class="block text-xs font-bold uppercase tracking-wider text-slate-500 mb-2">Father Contact</label><input type="text" name="father_contact" id="ee-father-contact" class="w-full bg-white border-2 border-slate-300 px-4 py-3 rounded-xl text-sm font-medium focus:border-amber-500 outline-none"></div>
                        <div><label class="block text-xs font-bold uppercase tracking-wider text-slate-500 mb-2">Mother Contact</label><input type="text" name="mother_contact" id="ee-mother-contact" class="w-full bg-white border-2 border-slate-300 px-4 py-3 rounded-xl text-sm font-medium focus:border-amber-500 outline-none"></div>
                        <div><label class="block text-xs font-bold uppercase tracking-wider text-slate-500 mb-2">Guardian Contact</label><input type="text" name="guardian_contact" id="ee-guardian-contact" class="w-full bg-white border-2 border-slate-300 px-4 py-3 rounded-xl text-sm font-medium focus:border-amber-500 outline-none"></div>
                    </div>
                </div>

                <!-- Previous School -->
                <div class="bg-white rounded-2xl shadow-sm border border-slate-200 overflow-hidden relative">
                    <div class="absolute top-0 left-0 w-2 h-full rounded-l-2xl bg-slate-400"></div>
                    <div class="px-6 pt-5 pb-3 border-b border-slate-100 ml-2">
                        <h4 class="text-lg font-heading font-black uppercase tracking-tight text-slate-500">Previous School Information</h4>
                    </div>
                    <div class="p-6 ml-2 grid grid-cols-1 md:grid-cols-3 gap-5">
                        <div><label class="block text-xs font-bold uppercase tracking-wider text-slate-500 mb-2">Previous School</label><input type="text" name="prev_school" id="ee-prev-school" class="w-full bg-white border-2 border-slate-300 px-4 py-3 rounded-xl text-sm font-medium focus:border-slate-500 outline-none"></div>
                        <div><label class="block text-xs font-bold uppercase tracking-wider text-slate-500 mb-2">Last School Year</label><input type="text" name="prev_school_year" id="ee-prev-school-year" placeholder="e.g. 2024-2025" class="w-full bg-white border-2 border-slate-300 px-4 py-3 rounded-xl text-sm font-medium focus:border-slate-500 outline-none"></div>
                        <div><label class="block text-xs font-bold uppercase tracking-wider text-slate-500 mb-2">Previous Section</label><input type="text" name="prev_section" id="ee-prev-section" class="w-full bg-white border-2 border-slate-300 px-4 py-3 rounded-xl text-sm font-medium focus:border-slate-500 outline-none"></div>
                    </div>
                </div>

                <div class="flex justify-end gap-3 pt-2 pb-2">
                    <button type="button" id="eval-edit-cancel" class="px-6 py-3 rounded-xl border-2 border-slate-300 text-slate-600 font-bold text-sm hover:bg-slate-100 transition-colors">Cancel</button>
                    <button type="submit" class="px-6 py-3 rounded-xl bg-dranhs-green text-white font-bold text-sm hover:bg-emerald-700 transition-colors shadow-md">Save Changes</button>
                </div>
            </form>
        </div>
    </div>
</div>
<div id="eval-fullinfo-modal" class="fixed inset-0 z-[60] hidden">
    <div class="absolute inset-0 bg-slate-900/70" id="eval-fullinfo-backdrop"></div>
    <div class="relative z-10 min-h-screen flex items-start justify-center p-4 py-8">
        <div class="w-full max-w-4xl bg-slate-50 rounded-2xl shadow-2xl border border-slate-200 overflow-hidden">
            <div class="bg-dranhs-dark px-6 py-5 flex items-center justify-between">
                <div>
                    <p class="text-xs font-bold uppercase tracking-[0.2em] text-dranhs-green mb-1">Full Enrollment Record</p>
                    <h3 id="eval-fullinfo-name" class="font-heading font-black text-2xl text-white">Student Name</h3>
                </div>
                <div class="flex items-center gap-2">
                    <button type="button" id="eval-fullinfo-edit-btn" class="inline-flex items-center gap-2 px-4 py-2 rounded-lg bg-amber-500 text-white text-sm font-bold hover:bg-amber-600 transition-colors">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path></svg>
                        Edit
                    </button>
                    <button type="button" id="eval-fullinfo-close" class="inline-flex items-center justify-center w-10 h-10 rounded-full bg-white/10 text-white hover:bg-white/20 transition-colors text-xl">&times;</button>
                </div>
            </div>
            <div class="max-h-[80vh] overflow-y-auto p-6 space-y-5" id="eval-fullinfo-grid"></div>
        </div>
    </div>
</div>

<script>
const EVAL_CATALOG = <?php echo json_encode($catalog_for_js, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP); ?>;
const EVAL_CLASSROOMS = <?php echo json_encode($classroom_rows, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP); ?>;

let currentEvalStudent = null;

function evalFullName(s) {
    let n = `${s.last_name || ''}, ${s.first_name || ''}`;
    if (s.middle_name) n += ' ' + s.middle_name.charAt(0).toUpperCase() + '.';
    if (s.extension_name) n += ' ' + s.extension_name;
    return n.trim();
}

function tod(v) { return (v && String(v).trim()) ? String(v) : '--'; }

function normalizeTermValue(value) {
    if (value === '1st') return 'term_1';
    if (value === '2nd') return 'term_2';
    return value || '';
}

function formatTermLabel(value) {
    const normalized = normalizeTermValue(value);
    if (normalized === 'term_1') return 'Term 1';
    if (normalized === 'term_2') return 'Term 2';
    if (normalized === 'term_3') return 'Term 3';
    return tod(value);
}

function infoCard(label, value) {
    return `<div class="rounded-xl bg-slate-50 border border-slate-100 p-3">
        <p class="text-xs font-bold uppercase tracking-wider text-slate-400 mb-1">${label}</p>
        <p class="text-sm font-semibold text-slate-700">${tod(value)}</p>
    </div>`;
}

function pathwayLabel(gradeLevel, code) {
    const opts = EVAL_CATALOG[gradeLevel] || [];
    const m = opts.find(i => String(i.code).toLowerCase() === String(code||'').toLowerCase());
    return m ? `${m.label}` : tod(code);
}

function slotSummary(gradeLevel, code) {
    const matching = EVAL_CLASSROOMS.filter(classroom =>
        String(classroom.grade_level || '').toLowerCase() === String(gradeLevel || '').toLowerCase() &&
        String(classroom.pathway_strand || '').toLowerCase() === String(code || '').toLowerCase()
    );

    if (matching.length === 0) {
        return { text: 'No classroom yet', tone: 'text-slate-400 font-semibold' };
    }

    const totals = matching.reduce((acc, classroom) => {
        acc.capacity += parseInt(classroom.max_capacity, 10) || 0;
        acc.enrolled += parseInt(classroom.enrolled, 10) || 0;
        return acc;
    }, { capacity: 0, enrolled: 0 });

    const available = Math.max(0, totals.capacity - totals.enrolled);
    if (available <= 0) {
        return { text: `Full (${totals.enrolled}/${totals.capacity})`, tone: 'text-red-600 font-bold' };
    }

    return {
        text: `${available} slot${available !== 1 ? 's' : ''} available (${totals.enrolled}/${totals.capacity})`,
        tone: 'text-emerald-600 font-bold'
    };
}

function classifyDistanceZone(student) {
    const cityNormalized = String(student.city || '').trim().toLowerCase();
    const brgy = String(student.barangay || '').trim().toLowerCase();
    const isDavaoCity = cityNormalized === 'davao city' || cityNormalized === 'city of davao';
    if (!isDavaoCity || !brgy) return null;

    const nearBarangays = [
        'matina crossing',
        'matina aplaya',
        'ecoland',
        'bangkal',
        'matina pangi'
    ];

    const restrictedBarangays = [
        'calinan',
        'toril',
        'sasa',
        'panacan',
        'maramag',
        'marahan',
        'sibulan'
    ];

    const warningBarangays = [
        'talomo',
        'ma-a',
        'maa',
        'sandawa',
        'mintal',
        'tugbok',
        'buhangin',
        'el rio',
        'elrio'
    ];

    if (nearBarangays.some(w => brgy.includes(w))) return null;
    if (restrictedBarangays.some(w => brgy.includes(w))) return 'restricted';
    if (warningBarangays.some(w => brgy.includes(w))) return 'warning';
    return 'warning';
}

function openEvalModal(student) {
    currentEvalStudent = student;
    document.getElementById('eval-modal-name').textContent = evalFullName(student);
    document.getElementById('eval-form-student-id').value = student.id;

    // Basic info grid
    document.getElementById('eval-basic-grid').innerHTML = [
        infoCard('LRN', student.lrn),
        infoCard('Grade Level', student.grade_level),
        infoCard('Track', student.track),
        infoCard('Pathway / Strand', pathwayLabel(student.grade_level, student.pathway_strand)),
        infoCard('Student Type', student.student_type),
        infoCard('Term', formatTermLabel(student.semester)),
        infoCard('School Year', student.school_year),
        infoCard('Filed', student.created_at ? new Date(student.created_at).toLocaleDateString('en-PH', {year:'numeric',month:'short',day:'numeric'}) : '--'),
    ].join('');

    const distancePanel = document.getElementById('eval-distance-panel');
    const distanceCaption = document.getElementById('eval-distance-caption');
    const distanceGrid = document.getElementById('eval-distance-grid');
    const distanceZone = classifyDistanceZone(student);
    if (distanceZone) {
        const panelTone = distanceZone === 'restricted'
            ? {
                panel: 'bg-red-50 border-red-200',
                bar: 'bg-red-500',
                title: 'text-red-700',
                caption: 'Enrollee address is from a far Davao City barangay. Please review travel feasibility carefully before proceeding.',
                badge: 'Far Davao City Barangay'
            }
            : {
                panel: 'bg-amber-50 border-amber-200',
                bar: 'bg-amber-500',
                title: 'text-amber-700',
                caption: 'Enrollee address is from a warning-distance Davao City barangay. Confirm travel commitment during evaluation.',
                badge: 'Warning Distance'
            };

        distancePanel.className = `bg-white rounded-2xl border overflow-hidden relative ${panelTone.panel}`;
        distancePanel.querySelector('.absolute').className = `absolute top-0 left-0 w-2 h-full rounded-l-2xl ${panelTone.bar}`;
        distancePanel.querySelector('h4').className = `text-sm font-black uppercase tracking-widest ${panelTone.title}`;
        distanceCaption.textContent = panelTone.caption;
        distanceGrid.innerHTML = [
            infoCard('Distance Status', panelTone.badge),
            infoCard('Barangay', student.barangay),
            infoCard('Living With', student.living_with),
        ].join('');
        distancePanel.classList.remove('hidden');
    } else {
        distanceGrid.innerHTML = '';
        distanceCaption.textContent = '';
        distancePanel.classList.add('hidden');
    }

    const specialNeedsPanel = document.getElementById('eval-special-needs-panel');
    const specialNeedsGrid = document.getElementById('eval-special-needs-grid');
    const hasSped = String(student.sped || '').trim().toLowerCase() === 'yes' || String(student.sped_diagnosis || '').trim() !== '';
    const hasPwd = String(student.pwd || '').trim().toLowerCase() === 'yes' || String(student.pwd_id || '').trim() !== '';
    if (hasSped || hasPwd) {
        const cards = [];
        if (hasSped) {
            cards.push(infoCard('SPED', String(student.sped || '').trim() || 'Yes'));
            cards.push(infoCard('SPED Diagnosis', student.sped_diagnosis));
        }
        if (hasPwd) {
            cards.push(infoCard('PWD', String(student.pwd || '').trim() || 'Yes'));
            cards.push(infoCard('PWD ID', student.pwd_id));
        }
        specialNeedsGrid.innerHTML = cards.join('');
        specialNeedsPanel.classList.remove('hidden');
    } else {
        specialNeedsGrid.innerHTML = '';
        specialNeedsPanel.classList.add('hidden');
    }

    // Documents checklist — SF09 for regular, AF-5 for ALS
    const isALS = (student.student_type || '').toLowerCase().includes('als');
    const docs = [
        { id: 'doc_sf09', label: isALS ? 'AF-5 (ALS Certificate of Completion)' : 'SF09 / School Card (Report Card)', required: true },
        { id: 'doc_psa',  label: 'PSA Birth Certificate', required: true },
        { id: 'doc_good', label: 'Good Moral Certificate', required: false },
    ];
    document.getElementById('eval-docs-list').innerHTML = docs.map(d => `
        <label class="flex items-start gap-3 p-3 rounded-xl border-2 border-slate-200 hover:border-violet-400 cursor-pointer transition-colors has-[:checked]:border-violet-500 has-[:checked]:bg-violet-50">
            <input type="checkbox" id="${d.id}" class="w-5 h-5 mt-0.5 rounded accent-violet-600 shrink-0">
            <span class="text-sm font-semibold text-slate-700">
                ${d.label}
                ${d.required ? '<span class="text-red-500 ml-1">*</span>' : '<span class="text-xs text-slate-400 font-normal ml-1">(optional)</span>'}
            </span>
        </label>`).join('');

    const flagAlert = document.getElementById('eval-flag-alert');
    const flagType = document.getElementById('eval-flag-type');
    const flagDetails = document.getElementById('eval-flag-details');
    const watchIssueType = String(student.watch_issue_type || '').trim();
    const watchIssueDetails = String(student.watch_issue_details || '').trim();

    if (watchIssueType) {
        flagType.textContent = watchIssueType;
        flagDetails.textContent = watchIssueDetails || 'No additional notes provided in the watchlist.';
        flagAlert.classList.remove('hidden');
        document.getElementById('eval-notes').value = watchIssueDetails;
    } else {
        flagType.textContent = '';
        flagDetails.textContent = '';
        flagAlert.classList.add('hidden');
        document.getElementById('eval-notes').value = '';
    }

    // Pathway / Strand cards — selectable, grade-level-aware label
    const isGrade11 = (student.grade_level || '') === 'Grade 11';
    const sectionLabel = isGrade11 ? 'Career Pathway' : 'Strand';
    document.getElementById('eval-pathway-section-title').textContent = `Available ${sectionLabel}s`;
    document.getElementById('eval-pathway-label-hint').textContent = sectionLabel.toLowerCase();

    let selectedPathway = student.pathway_strand || '';
    const options = EVAL_CATALOG[student.grade_level] || [];

    function renderPathwayCards() {
        document.getElementById('eval-pathway-grid').innerHTML = options.length === 0
            ? `<p class="col-span-3 text-sm text-slate-400">No ${sectionLabel.toLowerCase()}s found for this grade level.</p>`
            : options.map(opt => {
                const isSelected = String(opt.code).toLowerCase() === String(selectedPathway).toLowerCase();
                const slots = slotSummary(student.grade_level, opt.code);
                return `<button type="button"
                    class="eval-pathway-card text-left rounded-xl border-2 p-4 transition-all ${isSelected ? 'border-dranhs-green bg-emerald-50 ring-2 ring-dranhs-green/30' : 'border-slate-200 bg-white hover:border-sky-400 hover:bg-sky-50'}"
                    data-code="${opt.code}"
                    data-selected="${isSelected ? '1' : '0'}"
                    data-slot-text="${slots.text}">
                    <p class="text-xs font-black uppercase tracking-wider ${isSelected ? 'text-dranhs-green' : 'text-slate-400'} mb-1">${opt.track || ''}</p>
                    <p class="text-sm font-bold text-slate-800">${opt.label}</p>
                    <div class="mt-2 flex items-center gap-1.5">
                        <span class="w-2 h-2 rounded-full ${isSelected ? 'bg-dranhs-green' : 'bg-slate-300'} inline-block"></span>
                        <span class="eval-pathway-status text-xs ${isSelected ? 'text-dranhs-green font-bold' : slots.tone}">
                            ${isSelected ? '✓ Selected' : 'Slots: N/A'}
                        </span>
                    </div>
                </button>`;
            }).join('');

        // Apply status labels and wire card clicks
        document.querySelectorAll('.eval-pathway-card').forEach(card => {
            const status = card.querySelector('.eval-pathway-status');
            if (status) {
                status.textContent = card.dataset.selected === '1' ? 'Selected' : (card.dataset.slotText || 'No classroom yet');
            }
            card.addEventListener('click', function () {
                selectedPathway = this.dataset.code;
                document.getElementById('eval-form-final-pathway').value = selectedPathway;
                renderPathwayCards();
            });
        });
    }

    document.getElementById('eval-form-final-pathway').value = selectedPathway;
    renderPathwayCards();

    document.getElementById('eval-modal').classList.remove('hidden');
    document.body.classList.add('overflow-hidden');
}

function closeEvalModal() {
    document.getElementById('eval-modal').classList.add('hidden');
    document.body.classList.remove('overflow-hidden');
}

function openFullInfoModal(student) {
    document.getElementById('eval-fullinfo-name').textContent = evalFullName(student);
    const g = document.getElementById('eval-fullinfo-grid');

    function section(title, color, fields) {
        const cols = fields.map(f => `
            <div class="form-view-group">
                <p class="text-xs font-bold text-slate-500 uppercase tracking-wider mb-1">${f.label}</p>
                <p class="w-full bg-white border-2 border-slate-300 px-4 py-3 rounded-xl text-slate-800 text-sm font-medium">${tod(f.value)}</p>
            </div>`).join('');
        return `<div class="bg-white rounded-2xl shadow-sm border border-slate-200 overflow-hidden relative">
            <div class="absolute top-0 left-0 w-2 h-full rounded-l-2xl" style="background:${color}"></div>
            <div class="px-6 pt-5 pb-2 border-b border-slate-100 ml-2">
                <h4 class="text-lg font-heading font-black uppercase tracking-tight" style="color:${color}">${title}</h4>
            </div>
            <div class="p-6 ml-2 grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-5">${cols}</div>
        </div>`;
    }

    g.innerHTML = [
        section('General Information', '#009b5a', [
            { label: 'Learner Category', value: student.student_type },
            { label: 'School Year',      value: student.school_year },
            { label: 'Grade Level',      value: student.grade_level },
            { label: 'Term',             value: formatTermLabel(student.semester) },
            { label: 'LRN',              value: student.lrn },
            { label: 'Track',            value: student.track },
            { label: 'Pathway / Strand', value: pathwayLabel(student.grade_level, student.pathway_strand) },
        ]),
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
            { label: 'Street / Purok', value: student.street },
            { label: 'Barangay',       value: student.barangay },
            { label: 'City',           value: student.city },
            { label: 'Province',       value: student.province },
            { label: 'ZIP Code',       value: student.zip_code },
            { label: 'Living With',    value: student.living_with },
        ]),
        section("Parent's / Guardian's Information", '#f59e0b', [
            { label: 'Father Contact',   value: student.father_contact },
            { label: 'Mother Contact',   value: student.mother_contact },
            { label: 'Guardian Contact', value: student.guardian_contact },
        ]),
        section('Previous School', '#64748b', [
            { label: 'Previous School',  value: student.prev_school },
            { label: 'Last School Year', value: student.prev_school_year },
            { label: 'Previous Section', value: student.prev_section },
        ]),
    ].join('');

    document.getElementById('eval-fullinfo-modal').classList.remove('hidden');
}

// Event listeners
document.querySelectorAll('.eval-open-btn').forEach(btn => {
    btn.addEventListener('click', function () {
        const student = JSON.parse(this.dataset.student);
        openEvalModal(student);
    });
});

document.getElementById('eval-modal-close').addEventListener('click', closeEvalModal);
document.getElementById('eval-cancel-btn').addEventListener('click', closeEvalModal);
document.getElementById('eval-modal-backdrop').addEventListener('click', closeEvalModal);

document.getElementById('eval-view-full-btn').addEventListener('click', function () {
    if (currentEvalStudent) openFullInfoModal(currentEvalStudent);
});

document.getElementById('eval-fullinfo-close').addEventListener('click', function () {
    document.getElementById('eval-fullinfo-modal').classList.add('hidden');
});
document.getElementById('eval-fullinfo-backdrop').addEventListener('click', function () {
    document.getElementById('eval-fullinfo-modal').classList.add('hidden');
});

// Sync checkboxes + notes into hidden form fields before submit
document.getElementById('eval-action-form').addEventListener('submit', function () {
    document.getElementById('eval-form-doc-sf09').value = document.getElementById('doc_sf09')?.checked ? '1' : '0';
    document.getElementById('eval-form-doc-psa').value  = document.getElementById('doc_psa')?.checked  ? '1' : '0';
    document.getElementById('eval-form-doc-good').value = document.getElementById('doc_good')?.checked ? '1' : '0';
    document.getElementById('eval-form-notes').value    = document.getElementById('eval-notes')?.value || '';
});
function eeSet(id, val) {
    const el = document.getElementById(id);
    if (!el) return;
    el.value = val || '';
}
function eeSetSelect(id, val) {
    const el = document.getElementById(id);
    if (!el) return;
    const normalized = normalizeTermValue(val);
    el.value = normalized || '';
    if (el.value === '' && normalized) {
        const opt = document.createElement('option');
        opt.value = normalized; opt.textContent = formatTermLabel(normalized);
        el.appendChild(opt); el.value = normalized;
    }
}
function eeRefreshPathway() {
    const gl = document.getElementById('ee-grade-level').value;
    const tr = document.getElementById('ee-track').value;
    const sel = document.getElementById('ee-pathway-strand');
    const cur = sel.value;
    const opts = (EVAL_CATALOG[gl] || []).filter(i => !tr || i.track === tr);
    sel.innerHTML = '<option value="">Select Pathway / Strand</option>';
    opts.forEach(i => {
        const o = document.createElement('option');
        o.value = i.code; o.textContent = `${i.label} (${i.code})`;
        if (String(i.code).toLowerCase() === String(cur).toLowerCase()) o.selected = true;
        sel.appendChild(o);
    });
}
function eeCalcAge(bd) {
    if (!bd) return '';
    const t = new Date(), d = new Date(bd);
    let a = t.getFullYear() - d.getFullYear();
    const m = t.getMonth() - d.getMonth();
    if (m < 0 || (m === 0 && t.getDate() < d.getDate())) a--;
    return a >= 0 ? a : '';
}

function openEvalEditModal(student) {
    document.getElementById('eval-edit-title').textContent = `Edit: ${evalFullName(student)}`;
    document.getElementById('eval-edit-student-id').value = student.id || '';
    eeSetSelect('ee-student-type', student.student_type);
    eeSet('ee-school-year', student.school_year);
    eeSetSelect('ee-grade-level', student.grade_level || 'Grade 11');
    eeSetSelect('ee-semester', student.semester || 'term_1');
    eeSet('ee-lrn', student.lrn);
    eeSetSelect('ee-track', student.track);
    eeRefreshPathway();
    eeSetSelect('ee-pathway-strand', student.pathway_strand);
    eeSet('ee-last-name', student.last_name);
    eeSet('ee-first-name', student.first_name);
    eeSet('ee-middle-name', student.middle_name);
    eeSet('ee-extension-name', student.extension_name);
    eeSet('ee-birthdate', student.birthdate);
    document.getElementById('ee-age').value = student.age || eeCalcAge(student.birthdate);
    eeSetSelect('ee-sex', student.sex);
    eeSet('ee-place-of-birth', student.place_of_birth);
    eeSet('ee-mother-tongue', student.mother_tongue);
    eeSet('ee-religion', student.religion);
    eeSet('ee-street', student.street);
    eeSet('ee-barangay', student.barangay);
    eeSet('ee-city', student.city);
    eeSet('ee-province', student.province);
    eeSet('ee-zip-code', student.zip_code);
    eeSetSelect('ee-living-with', student.living_with);
    eeSet('ee-father-contact', student.father_contact);
    eeSet('ee-mother-contact', student.mother_contact);
    eeSet('ee-guardian-contact', student.guardian_contact);
    eeSet('ee-prev-school', student.prev_school);
    eeSet('ee-prev-school-year', student.prev_school_year);
    eeSet('ee-prev-section', student.prev_section);

    document.getElementById('ee-birthdate').addEventListener('change', function () {
        document.getElementById('ee-age').value = eeCalcAge(this.value);
    });
    document.getElementById('ee-grade-level').addEventListener('change', eeRefreshPathway);
    document.getElementById('ee-track').addEventListener('change', eeRefreshPathway);

    document.getElementById('eval-edit-modal').classList.remove('hidden');
}

document.getElementById('eval-fullinfo-edit-btn').addEventListener('click', function () {
    if (currentEvalStudent) {
        document.getElementById('eval-fullinfo-modal').classList.add('hidden');
        openEvalEditModal(currentEvalStudent);
    }
});
document.getElementById('eval-edit-close').addEventListener('click', function () {
    document.getElementById('eval-edit-modal').classList.add('hidden');
});
document.getElementById('eval-edit-cancel').addEventListener('click', function () {
    document.getElementById('eval-edit-modal').classList.add('hidden');
});
document.getElementById('eval-edit-backdrop').addEventListener('click', function () {
    document.getElementById('eval-edit-modal').classList.add('hidden');
});

// Search
document.getElementById('eval-search').addEventListener('input', function () {
    const q = this.value.toLowerCase().trim();
    document.querySelectorAll('#eval-tbody tr').forEach(row => {
        row.style.display = row.textContent.toLowerCase().includes(q) ? '' : 'none';
    });
});
</script>
