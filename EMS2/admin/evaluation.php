<?php
$db_host = 'localhost';
$db_user = 'root';
$db_pass = '';
$db_name = 'dranhswin';
$conn = new mysqli($db_host, $db_user, $db_pass, $db_name);

$evaluation_rows = [];
$selected_student = null;
$db_error = '';

if ($conn->connect_error) {
    $db_error = 'Database connection failed.';
} else {
    $student_id = isset($_GET['student_id']) ? (int) $_GET['student_id'] : 0;

    $sql = "SELECT id, lrn, last_name, first_name, middle_name, extension_name, grade_level, track, pathway_strand, prev_section, created_at
            FROM students
            ORDER BY created_at DESC, id DESC";
    $res = $conn->query($sql);
    if ($res) {
        while ($row = $res->fetch_assoc()) {
            $evaluation_rows[] = $row;
        }
        $res->close();
    } else {
        $db_error = 'Unable to load evaluation records.';
    }

    if ($student_id > 0) {
        $stmt = $conn->prepare("SELECT id, lrn, last_name, first_name, middle_name, extension_name, grade_level, track, pathway_strand, prev_section, created_at
                                FROM students
                                WHERE id = ?");
        if ($stmt) {
            $stmt->bind_param("i", $student_id);
            $stmt->execute();
            $selected_student = $stmt->get_result()->fetch_assoc();
            $stmt->close();
        }
    }

    $conn->close();
}

function eval_full_name($row) {
    $parts = [
        $row['last_name'] ?? '',
        ', ',
        $row['first_name'] ?? ''
    ];

    if (!empty($row['middle_name'])) {
        $parts[] = ' ' . strtoupper(substr($row['middle_name'], 0, 1)) . '.';
    }
    if (!empty($row['extension_name'])) {
        $parts[] = ' ' . $row['extension_name'];
    }
    return trim(implode('', $parts));
}
?>

<?php if ($selected_student): ?>
<div class="mb-6 bg-white rounded-2xl shadow-sm border border-slate-100 overflow-hidden">
    <div class="px-6 py-4 border-b border-slate-100 flex items-center justify-between">
        <div>
            <p class="text-xs font-bold uppercase tracking-[0.2em] text-amber-500 mb-1">Selected Enrollee</p>
            <h3 class="font-heading font-black text-2xl text-dranhs-dark"><?php echo htmlspecialchars(eval_full_name($selected_student)); ?></h3>
        </div>
        <a href="?page=evaluation" class="text-xs font-bold text-slate-500 hover:text-dranhs-dark uppercase tracking-wide">Clear Selection</a>
    </div>
    <div class="p-6 grid grid-cols-1 md:grid-cols-4 gap-4 text-sm">
        <div class="rounded-xl bg-slate-50 border border-slate-100 p-4">
            <p class="text-xs font-bold uppercase tracking-wider text-slate-400 mb-1">LRN</p>
            <p class="font-semibold text-slate-700"><?php echo htmlspecialchars($selected_student['lrn'] ?: '--'); ?></p>
        </div>
        <div class="rounded-xl bg-slate-50 border border-slate-100 p-4">
            <p class="text-xs font-bold uppercase tracking-wider text-slate-400 mb-1">Grade Level</p>
            <p class="font-semibold text-slate-700"><?php echo htmlspecialchars($selected_student['grade_level'] ?: '--'); ?></p>
        </div>
        <div class="rounded-xl bg-slate-50 border border-slate-100 p-4">
            <p class="text-xs font-bold uppercase tracking-wider text-slate-400 mb-1">Track</p>
            <p class="font-semibold text-slate-700"><?php echo htmlspecialchars($selected_student['track'] ?: '--'); ?></p>
        </div>
        <div class="rounded-xl bg-slate-50 border border-slate-100 p-4">
            <p class="text-xs font-bold uppercase tracking-wider text-slate-400 mb-1">Pathway / Strand Code</p>
            <p class="font-semibold text-slate-700"><?php echo htmlspecialchars($selected_student['pathway_strand'] ?: '--'); ?></p>
        </div>
    </div>
    <div class="px-6 pb-6 flex flex-wrap gap-3">
        <button type="button" class="inline-flex items-center justify-center px-4 py-2.5 rounded-lg bg-amber-500 text-white text-sm font-bold shadow-sm cursor-not-allowed opacity-80" disabled>
            Evaluate
        </button>
        <p class="text-sm text-slate-500 self-center">Evaluation workflow can be connected next. The page is now reading real enrollees from the `students` table.</p>
    </div>
</div>
<?php endif; ?>

<div class="bg-white rounded-xl shadow-sm border border-slate-100 overflow-hidden">
    <div class="px-6 py-4 border-b border-slate-100 flex justify-between items-center">
        <div>
            <h3 class="font-heading font-bold text-lg text-dranhs-dark">Evaluation List</h3>
            <p class="text-sm text-slate-500">All current enrollees from the `students` table. Use the action button to open an enrollee for evaluation.</p>
        </div>
    </div>

    <?php if ($db_error): ?>
        <div class="px-6 py-5 text-sm font-semibold text-red-600 bg-red-50 border-t border-red-100"><?php echo htmlspecialchars($db_error); ?></div>
    <?php elseif (empty($evaluation_rows)): ?>
        <div class="px-6 py-10 text-center">
            <p class="text-sm font-semibold text-slate-600">No enrollees found yet.</p>
            <p class="text-sm text-slate-400 mt-1">Once students submit the enrollment form, they will appear here for evaluation.</p>
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
                    <th class="px-6 py-3 tracking-wider">Status</th>
                    <th class="px-6 py-3 tracking-wider">Action</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100 text-sm">
                <?php foreach ($evaluation_rows as $row): ?>
                <tr class="hover:bg-slate-50 transition-colors">
                    <td class="px-6 py-4">
                        <div class="font-semibold text-slate-700"><?php echo htmlspecialchars(eval_full_name($row)); ?></div>
                        <div class="text-xs text-slate-400 mt-1">Filed <?php echo htmlspecialchars(date('M d, Y', strtotime($row['created_at']))); ?></div>
                    </td>
                    <td class="px-6 py-4 text-slate-600"><?php echo htmlspecialchars($row['lrn'] ?: '--'); ?></td>
                    <td class="px-6 py-4 text-slate-600"><?php echo htmlspecialchars($row['grade_level'] ?: '--'); ?></td>
                    <td class="px-6 py-4 text-slate-600"><?php echo htmlspecialchars($row['track'] ?: '--'); ?></td>
                    <td class="px-6 py-4 text-slate-600"><?php echo htmlspecialchars($row['pathway_strand'] ?: '--'); ?></td>
                    <td class="px-6 py-4"><span class="px-2.5 py-1 bg-amber-100 text-amber-700 rounded-full text-xs font-bold">Pending Evaluation</span></td>
                    <td class="px-6 py-4">
                        <a href="?page=evaluation&student_id=<?php echo (int) $row['id']; ?>" class="inline-flex items-center justify-center px-3 py-2 rounded-lg bg-dranhs-green text-white text-xs font-bold uppercase tracking-wide hover:bg-emerald-600 transition-colors">
                            Evaluate
                        </a>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>
</div>
