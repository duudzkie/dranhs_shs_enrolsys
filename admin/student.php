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
            $stmt = $conn->prepare("UPDATE students SET
                lrn = ?, last_name = ?, first_name = ?, middle_name = ?, extension_name = ?,
                birthdate = ?, age = ?, sex = ?, place_of_birth = ?, mother_tongue = ?, religion = ?,
                school_year = ?, grade_level = ?, student_type = ?, semester = ?, track = ?, pathway_strand = ?,
                street = ?, province = ?, city = ?, barangay = ?, zip_code = ?, living_with = ?,
                prev_school = ?, prev_school_year = ?, prev_section = ?
                WHERE id = ?");

            if ($stmt) {
                $age = ($_POST['age'] ?? '') !== '' ? (int) $_POST['age'] : null;
                $params = [
                    $_POST['lrn'], $_POST['last_name'], $_POST['first_name'], $_POST['middle_name'], $_POST['extension_name'],
                    $_POST['birthdate'], $age, $_POST['sex'], $_POST['place_of_birth'], $_POST['mother_tongue'], $_POST['religion'],
                    $_POST['school_year'], $grade_level, $_POST['student_type'], $_POST['semester'], $track, $pathway_code,
                    $_POST['street'], $_POST['province'], $_POST['city'], $_POST['barangay'], $_POST['zip_code'], $_POST['living_with'],
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
            if (!verify_current_user_password($conn, $user_id, $password)) {
                $toast_message = 'Incorrect password. Withdrawal was not authorized.';
                $toast_type = 'error';
            } else {
                $toast_message = 'Password verified. Withdrawal flow is ready, but no database record was deleted.';
            }
        }
    }

    $res = $conn->query("SELECT * FROM students ORDER BY created_at DESC, id DESC");
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
                    <th class="px-6 py-3 tracking-wider">LRN</th>
                    <th class="px-6 py-3 tracking-wider">Grade Level</th>
                    <th class="px-6 py-3 tracking-wider">Track</th>
                    <th class="px-6 py-3 tracking-wider">Pathway / Strand</th>
                    <th class="px-6 py-3 tracking-wider">Status</th>
                    <th class="px-6 py-3 tracking-wider">Action</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100 text-sm">
                <?php foreach ($student_rows as $row): ?>
                <tr class="hover:bg-slate-50 transition-colors">
                    <td class="px-6 py-4">
                        <div class="font-semibold text-slate-700"><?php echo htmlspecialchars(student_full_name($row)); ?></div>
                        <div class="text-xs text-slate-400 mt-1">Created <?php echo htmlspecialchars(date('M d, Y', strtotime($row['created_at']))); ?></div>
                    </td>
                    <td class="px-6 py-4 text-slate-600"><?php echo htmlspecialchars($row['lrn'] ?: '--'); ?></td>
                    <td class="px-6 py-4 text-slate-600"><?php echo htmlspecialchars($row['grade_level'] ?: '--'); ?></td>
                    <td class="px-6 py-4 text-slate-600"><?php echo htmlspecialchars($row['track'] ?: '--'); ?></td>
                    <td class="px-6 py-4 text-slate-600"><?php echo htmlspecialchars(get_pathway_strand_label($row['grade_level'] ?? '', $row['pathway_strand'] ?? '')); ?></td>
                    <td class="px-6 py-4"><span class="px-2.5 py-1 bg-green-100 text-green-700 rounded-full text-xs font-bold">Enrolled</span></td>
                    <td class="px-6 py-4">
                        <div class="flex items-center gap-2">
                            <button type="button" class="view-student-btn inline-flex items-center justify-center w-9 h-9 rounded-lg bg-blue-50 text-blue-600 hover:bg-blue-100 transition-colors" data-student-id="<?php echo (int) $row['id']; ?>" title="View">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"></path></svg>
                            </button>
                            <button type="button" class="edit-student-btn inline-flex items-center justify-center w-9 h-9 rounded-lg bg-amber-50 text-amber-600 hover:bg-amber-100 transition-colors" data-student-id="<?php echo (int) $row['id']; ?>" title="Edit">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path></svg>
                            </button>
                            <button type="button" class="print-student-btn inline-flex items-center justify-center w-9 h-9 rounded-lg bg-slate-100 text-slate-700 hover:bg-slate-200 transition-colors" data-student-id="<?php echo (int) $row['id']; ?>" title="Print">
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
    <div class="relative z-10 min-h-screen flex items-center justify-center p-4">
        <div class="w-full max-w-5xl bg-white rounded-2xl shadow-2xl border border-slate-200 overflow-hidden">
            <div class="px-6 py-4 border-b border-slate-100 flex items-center justify-between">
                <div>
                    <p class="text-xs font-bold uppercase tracking-[0.2em] text-blue-500 mb-1">Enrollment Details</p>
                    <h3 id="view-student-name" class="font-heading font-black text-2xl text-dranhs-dark">Student Name</h3>
                </div>
                <div class="flex items-center gap-2">
                    <button type="button" id="view-to-edit-btn" class="inline-flex items-center justify-center px-4 py-2.5 rounded-lg bg-amber-500 text-white text-sm font-bold hover:bg-amber-600 transition-colors">Edit Record</button>
                    <button type="button" class="modal-close inline-flex items-center justify-center w-10 h-10 rounded-full bg-slate-100 text-slate-500 hover:bg-slate-200 transition-colors">&times;</button>
                </div>
            </div>
            <div class="p-6 max-h-[80vh] overflow-y-auto grid grid-cols-1 lg:grid-cols-2 gap-6" id="student-view-grid"></div>
        </div>
    </div>
</div>

<div id="student-edit-modal" class="fixed inset-0 z-40 hidden">
    <div class="absolute inset-0 bg-slate-900/60 modal-close"></div>
    <div class="relative z-10 min-h-screen flex items-center justify-center p-4">
        <div class="w-full max-w-5xl bg-white rounded-2xl shadow-2xl border border-slate-200 overflow-hidden">
            <div class="px-6 py-4 border-b border-slate-100 flex items-center justify-between">
                <div>
                    <p class="text-xs font-bold uppercase tracking-[0.2em] text-amber-500 mb-1">Edit Enrollment</p>
                    <h3 id="edit-student-title" class="font-heading font-black text-2xl text-dranhs-dark">Edit Student</h3>
                </div>
                <button type="button" class="modal-close inline-flex items-center justify-center w-10 h-10 rounded-full bg-slate-100 text-slate-500 hover:bg-slate-200 transition-colors">&times;</button>
            </div>
            <form method="POST" class="p-6 max-h-[80vh] overflow-y-auto space-y-6">
                <input type="hidden" name="action" value="update_student">
                <input type="hidden" name="student_id" id="edit-student-id">
                <div id="student-edit-grid" class="grid grid-cols-1 md:grid-cols-3 gap-4"></div>
                <div class="flex justify-end gap-3 pt-2">
                    <button type="button" class="modal-close px-5 py-3 rounded-xl border border-slate-300 text-slate-600 font-bold text-sm hover:bg-slate-50 transition-colors">Cancel</button>
                    <button type="submit" class="px-5 py-3 rounded-xl bg-amber-500 text-white font-bold text-sm hover:bg-amber-600 transition-colors">Save Changes</button>
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
                <button type="button" class="w-full px-5 py-5 rounded-2xl border border-slate-200 bg-slate-50 text-left hover:bg-slate-100 transition-colors" onclick="alert('Print ID function is not connected yet.')">
                    <span class="block text-sm font-black text-dranhs-dark uppercase tracking-wide">Print for ID</span>
                    <span class="block text-xs text-slate-500 mt-2">Prepare ID layout for this student.</span>
                </button>
                <button type="button" class="w-full px-5 py-5 rounded-2xl border border-slate-200 bg-slate-50 text-left hover:bg-slate-100 transition-colors" onclick="alert('Print document function is not connected yet.')">
                    <span class="block text-sm font-black text-dranhs-dark uppercase tracking-wide">Print for Document</span>
                    <span class="block text-xs text-slate-500 mt-2">Prepare document printout for this student.</span>
                </button>
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
    return match ? `${match.label} (${match.code})` : textOrDash(code);
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

function populateViewModal(student) {
    document.getElementById('view-student-name').textContent = fullName(student);
    document.getElementById('view-to-edit-btn').dataset.studentId = student.id;
    viewGrid.innerHTML = [
        fieldBlock('LRN', student.lrn),
        fieldBlock('Grade Level', student.grade_level),
        fieldBlock('Track', student.track),
        fieldBlock('Pathway / Strand', pathwayLabel(student.grade_level, student.pathway_strand)),
        fieldBlock('Student Type', student.student_type),
        fieldBlock('Semester', student.semester),
        fieldBlock('School Year', student.school_year),
        fieldBlock('Birthdate', student.birthdate),
        fieldBlock('Age', student.age),
        fieldBlock('Sex', student.sex),
        fieldBlock('Place of Birth', student.place_of_birth),
        fieldBlock('Mother Tongue', student.mother_tongue),
        fieldBlock('Religion', student.religion),
        fieldBlock('Street', student.street),
        fieldBlock('Barangay', student.barangay),
        fieldBlock('City', student.city),
        fieldBlock('Province', student.province),
        fieldBlock('ZIP Code', student.zip_code),
        fieldBlock('Living With', student.living_with),
        fieldBlock('Previous School', student.prev_school),
        fieldBlock('Previous School Year', student.prev_school_year),
        fieldBlock('Previous Section', student.prev_section),
        fieldBlock('Father Contact', student.father_contact),
        fieldBlock('Mother Contact', student.mother_contact),
        fieldBlock('Guardian Contact', student.guardian_contact),
        fieldBlock('Created At', student.created_at)
    ].join('');
}

function populateEditModal(student) {
    document.getElementById('edit-student-title').textContent = `Edit ${fullName(student)}`;
    document.getElementById('edit-student-id').value = student.id || '';
    const gradeLevel = student.grade_level || 'Grade 11';
    const track = student.track || '';
    editGrid.innerHTML = [
        inputField('edit-lrn', 'lrn', 'LRN', student.lrn),
        selectField('edit-grade-level', 'grade_level', 'Grade Level', `<option value="Grade 11"${gradeLevel === 'Grade 11' ? ' selected' : ''}>Grade 11</option><option value="Grade 12"${gradeLevel === 'Grade 12' ? ' selected' : ''}>Grade 12</option>`),
        selectField('edit-track', 'track', 'Track', `<option value="">Select Track</option><option value="Academic"${track === 'Academic' ? ' selected' : ''}>Academic</option><option value="Tech-Pro"${track === 'Tech-Pro' ? ' selected' : ''}>Tech-Pro</option><option value="TVL"${track === 'TVL' ? ' selected' : ''}>TVL</option>`),
        selectField('edit-pathway-strand', 'pathway_strand', 'Pathway / Strand', renderPathwayOptions(gradeLevel, track, student.pathway_strand)),
        inputField('edit-last-name', 'last_name', 'Last Name', student.last_name),
        inputField('edit-first-name', 'first_name', 'First Name', student.first_name),
        inputField('edit-middle-name', 'middle_name', 'Middle Name', student.middle_name),
        inputField('edit-extension-name', 'extension_name', 'Extension', student.extension_name),
        inputField('edit-birthdate', 'birthdate', 'Birthdate', student.birthdate, 'date'),
        inputField('edit-age', 'age', 'Age', student.age, 'number'),
        selectField('edit-sex', 'sex', 'Sex', `<option value="Male"${student.sex === 'Male' ? ' selected' : ''}>Male</option><option value="Female"${student.sex === 'Female' ? ' selected' : ''}>Female</option>`),
        inputField('edit-student-type', 'student_type', 'Student Type', student.student_type),
        inputField('edit-place-of-birth', 'place_of_birth', 'Place of Birth', student.place_of_birth),
        inputField('edit-mother-tongue', 'mother_tongue', 'Mother Tongue', student.mother_tongue),
        inputField('edit-religion', 'religion', 'Religion', student.religion),
        inputField('edit-school-year', 'school_year', 'School Year', student.school_year),
        inputField('edit-semester', 'semester', 'Semester', student.semester),
        inputField('edit-street', 'street', 'Street', student.street),
        inputField('edit-barangay', 'barangay', 'Barangay', student.barangay),
        inputField('edit-city', 'city', 'City', student.city),
        inputField('edit-province', 'province', 'Province', student.province),
        inputField('edit-zip-code', 'zip_code', 'ZIP Code', student.zip_code),
        inputField('edit-living-with', 'living_with', 'Living With', student.living_with),
        inputField('edit-prev-school', 'prev_school', 'Previous School', student.prev_school),
        inputField('edit-prev-school-year', 'prev_school_year', 'Previous School Year', student.prev_school_year),
        inputField('edit-prev-section', 'prev_section', 'Previous Section', student.prev_section)
    ].join('');

    document.getElementById('edit-grade-level').addEventListener('change', syncPathwaySelect);
    document.getElementById('edit-track').addEventListener('change', syncPathwaySelect);
}

function syncPathwaySelect() {
    const gradeLevel = document.getElementById('edit-grade-level').value;
    const track = document.getElementById('edit-track').value;
    const select = document.getElementById('edit-pathway-strand');
    const selected = select.value;
    select.innerHTML = renderPathwayOptions(gradeLevel, track, selected);
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
document.querySelectorAll('.print-student-btn').forEach(btn => btn.addEventListener('click', function () {
    const student = studentMap.get(this.dataset.studentId);
    if (!student) return;
    document.getElementById('print-student-title').textContent = `Print Options for ${fullName(student)}`;
    openModal('student-print-modal');
}));
document.querySelectorAll('.withdraw-student-btn').forEach(btn => btn.addEventListener('click', function () {
    const student = studentMap.get(this.dataset.studentId);
    if (!student) return;
    document.getElementById('withdraw-student-id').value = student.id || '';
    document.getElementById('withdraw-student-title').textContent = `Authorize Withdrawal for ${fullName(student)}`;
    document.getElementById('withdraw-student-name').textContent = fullName(student);
    openModal('student-withdraw-modal');
}));
</script>
