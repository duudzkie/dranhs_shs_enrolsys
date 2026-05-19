<?php
require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/../activity_log.php';

$conn = db_connect();
ensure_activity_log_table($conn);

// ── Pagination & Filters ─────────────────────────────────────────────────────
$per_page    = 50;
$current_page = max(1, (int)($_GET['pg'] ?? 1));
$filter_action = trim($_GET['fa'] ?? '');
$filter_user   = trim($_GET['fu'] ?? '');
$filter_search = trim($_GET['fs'] ?? '');
$filter_date_from = trim($_GET['df'] ?? '');
$filter_date_to   = trim($_GET['dt'] ?? '');

// Handle CSV export
if (isset($_GET['export']) && $_GET['export'] === 'csv') {
    $where = "1=1";
    $params = [];
    $types  = '';
    if ($filter_action !== '') { $where .= " AND action = ?"; $params[] = $filter_action; $types .= 's'; }
    if ($filter_user   !== '') { $where .= " AND username = ?"; $params[] = $filter_user;   $types .= 's'; }
    if ($filter_search !== '') { $where .= " AND (details LIKE ? OR username LIKE ? OR action LIKE ? OR ip_address LIKE ?)"; $like = "%{$filter_search}%"; $params = array_merge($params, [$like,$like,$like,$like]); $types .= 'ssss'; }
    if ($filter_date_from !== '') { $where .= " AND created_at >= ?"; $params[] = $filter_date_from . ' 00:00:00'; $types .= 's'; }
    if ($filter_date_to   !== '') { $where .= " AND created_at <= ?"; $params[] = $filter_date_to   . ' 23:59:59'; $types .= 's'; }

    $sql = "SELECT created_at, username, role, action, details, target_type, target_id, ip_address FROM activity_logs WHERE {$where} ORDER BY created_at DESC";
    $stmt = $conn->prepare($sql);
    if ($types) $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $result = $stmt->get_result();

    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="activity_logs_' . date('Y-m-d_His') . '.csv"');
    $out = fopen('php://output', 'w');
    fputcsv($out, ['Timestamp', 'Username', 'Role', 'Action', 'Details', 'Target Type', 'Target ID', 'IP Address']);
    while ($row = $result->fetch_assoc()) {
        fputcsv($out, [$row['created_at'], $row['username'], $row['role'], $row['action'], $row['details'], $row['target_type'], $row['target_id'], $row['ip_address']]);
    }
    fclose($out);
    $stmt->close();
    $conn->close();
    exit;
}

// ── Build WHERE ───────────────────────────────────────────────────────────────
$where  = "1=1";
$params = [];
$types  = '';

if ($filter_action !== '') { $where .= " AND action = ?"; $params[] = $filter_action; $types .= 's'; }
if ($filter_user   !== '') { $where .= " AND username = ?"; $params[] = $filter_user;   $types .= 's'; }
if ($filter_search !== '') {
    $where .= " AND (details LIKE ? OR username LIKE ? OR action LIKE ? OR ip_address LIKE ?)";
    $like = "%{$filter_search}%";
    $params = array_merge($params, [$like, $like, $like, $like]);
    $types .= 'ssss';
}
if ($filter_date_from !== '') { $where .= " AND created_at >= ?"; $params[] = $filter_date_from . ' 00:00:00'; $types .= 's'; }
if ($filter_date_to   !== '') { $where .= " AND created_at <= ?"; $params[] = $filter_date_to   . ' 23:59:59'; $types .= 's'; }

// ── Count total ───────────────────────────────────────────────────────────────
$count_sql = "SELECT COUNT(*) AS cnt FROM activity_logs WHERE {$where}";
$count_stmt = $conn->prepare($count_sql);
if ($types) $count_stmt->bind_param($types, ...$params);
$count_stmt->execute();
$total_rows = (int)$count_stmt->get_result()->fetch_assoc()['cnt'];
$count_stmt->close();

$total_pages = max(1, (int)ceil($total_rows / $per_page));
if ($current_page > $total_pages) $current_page = $total_pages;
$offset = ($current_page - 1) * $per_page;

// ── Fetch rows ────────────────────────────────────────────────────────────────
$sql = "SELECT * FROM activity_logs WHERE {$where} ORDER BY created_at DESC LIMIT ? OFFSET ?";
$all_params = $params;
$all_params[] = $per_page;
$all_params[] = $offset;
$all_types = $types . 'ii';

$stmt = $conn->prepare($sql);
if ($all_types) $stmt->bind_param($all_types, ...$all_params);
$stmt->execute();
$rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// ── Stats ─────────────────────────────────────────────────────────────────────
$today = date('Y-m-d');
$stats_total = $total_rows;

$stats_today_stmt = $conn->prepare("SELECT COUNT(*) AS cnt FROM activity_logs WHERE DATE(created_at) = ?");
$stats_today_stmt->bind_param('s', $today);
$stats_today_stmt->execute();
$stats_today = (int)$stats_today_stmt->get_result()->fetch_assoc()['cnt'];
$stats_today_stmt->close();

$stats_users_stmt = $conn->prepare("SELECT COUNT(DISTINCT username) AS cnt FROM activity_logs WHERE DATE(created_at) = ? AND username != 'guest'");
$stats_users_stmt->bind_param('s', $today);
$stats_users_stmt->execute();
$stats_active_users = (int)$stats_users_stmt->get_result()->fetch_assoc()['cnt'];
$stats_users_stmt->close();

// ── Distinct values for dropdowns ─────────────────────────────────────────────
$distinct_actions  = [];
$da = $conn->query("SELECT DISTINCT action FROM activity_logs ORDER BY action ASC");
if ($da) { while ($r = $da->fetch_assoc()) $distinct_actions[] = $r['action']; $da->close(); }

$distinct_users = [];
$du = $conn->query("SELECT DISTINCT username FROM activity_logs ORDER BY username ASC");
if ($du) { while ($r = $du->fetch_assoc()) $distinct_users[] = $r['username']; $du->close(); }

$conn->close();

// ── Action badge helper ───────────────────────────────────────────────────────
function action_badge(string $action): string {
    $map = [
        'login'              => ['bg-emerald-100 text-emerald-700 border-emerald-200', '🔑'],
        'login_failed'       => ['bg-red-100 text-red-700 border-red-200', '🚫'],
        'logout'             => ['bg-slate-100 text-slate-600 border-slate-200', '🚪'],
        'student_enrolled'   => ['bg-green-100 text-green-700 border-green-200', '📝'],
        'student_verified'   => ['bg-blue-100 text-blue-700 border-blue-200', '✅'],
        'student_flagged'    => ['bg-red-100 text-red-700 border-red-200', '🚩'],
        'student_updated'    => ['bg-amber-100 text-amber-700 border-amber-200', '✏️'],
        'student_encoded'    => ['bg-violet-100 text-violet-700 border-violet-200', '📋'],
        'student_withdrawn'  => ['bg-rose-100 text-rose-700 border-rose-200', '⛔'],
        'student_reassigned' => ['bg-orange-100 text-orange-700 border-orange-200', '🔄'],
        'classroom_added'    => ['bg-teal-100 text-teal-700 border-teal-200', '🏫'],
        'classroom_updated'  => ['bg-cyan-100 text-cyan-700 border-cyan-200', '🏫'],
        'classroom_deleted'  => ['bg-red-100 text-red-700 border-red-200', '🏫'],
        'user_created'       => ['bg-indigo-100 text-indigo-700 border-indigo-200', '👤'],
        'user_updated'       => ['bg-indigo-100 text-indigo-700 border-indigo-200', '👤'],
        'user_deleted'       => ['bg-red-100 text-red-700 border-red-200', '👤'],
        'adviser_updated'    => ['bg-purple-100 text-purple-700 border-purple-200', '👨‍🏫'],
        'adviser_deleted'    => ['bg-red-100 text-red-700 border-red-200', '👨‍🏫'],
        'list_entry_added'   => ['bg-sky-100 text-sky-700 border-sky-200', '📄'],
        'list_entry_deleted' => ['bg-red-100 text-red-700 border-red-200', '📄'],
        'list_csv_imported'  => ['bg-sky-100 text-sky-700 border-sky-200', '📥'],
        'settings_updated'   => ['bg-yellow-100 text-yellow-700 border-yellow-200', '⚙️'],
        'report_exported'    => ['bg-lime-100 text-lime-700 border-lime-200', '📊'],
    ];
    $style = $map[$action] ?? ['bg-slate-100 text-slate-600 border-slate-200', '📌'];
    $label = str_replace('_', ' ', $action);
    return '<span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-[11px] font-bold uppercase tracking-wide border ' . $style[0] . '">' . $style[1] . ' ' . htmlspecialchars($label) . '</span>';
}

function role_badge(string $role): string {
    $colors = [
        'admin'     => 'bg-emerald-100 text-emerald-700',
        'evaluator' => 'bg-blue-100 text-blue-700',
        'encoder'   => 'bg-violet-100 text-violet-700',
        'adviser'   => 'bg-amber-100 text-amber-700',
    ];
    $cls = $colors[$role] ?? 'bg-slate-100 text-slate-600';
    return '<span class="px-2 py-0.5 rounded-full text-[10px] font-bold uppercase tracking-wider ' . $cls . '">' . htmlspecialchars($role ?: 'public') . '</span>';
}

// Build current filter query string for pagination links
function filter_qs(array $overrides = []): string {
    $base = [
        'page' => 'logs',
        'fa'   => $_GET['fa'] ?? '',
        'fu'   => $_GET['fu'] ?? '',
        'fs'   => $_GET['fs'] ?? '',
        'df'   => $_GET['df'] ?? '',
        'dt'   => $_GET['dt'] ?? '',
    ];
    $merged = array_merge($base, $overrides);
    // Remove empty values except 'page'
    foreach ($merged as $k => $v) {
        if ($k !== 'page' && $v === '') unset($merged[$k]);
    }
    return '?' . http_build_query($merged);
}
?>

<!-- Stats Bar -->
<div class="grid grid-cols-1 sm:grid-cols-3 gap-4 mb-6">
    <div class="bg-white rounded-2xl border border-slate-200 px-5 py-4 flex items-center gap-4">
        <div class="w-12 h-12 rounded-xl bg-blue-50 flex items-center justify-center shrink-0">
            <svg class="w-6 h-6 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
        </div>
        <div>
            <p class="text-xs font-bold uppercase tracking-widest text-slate-400">Total Entries</p>
            <p class="text-2xl font-heading font-black text-dranhs-dark"><?php echo number_format($stats_total); ?></p>
        </div>
    </div>
    <div class="bg-white rounded-2xl border border-slate-200 px-5 py-4 flex items-center gap-4">
        <div class="w-12 h-12 rounded-xl bg-emerald-50 flex items-center justify-center shrink-0">
            <svg class="w-6 h-6 text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
        </div>
        <div>
            <p class="text-xs font-bold uppercase tracking-widest text-slate-400">Today's Activity</p>
            <p class="text-2xl font-heading font-black text-dranhs-dark"><?php echo number_format($stats_today); ?></p>
        </div>
    </div>
    <div class="bg-white rounded-2xl border border-slate-200 px-5 py-4 flex items-center gap-4">
        <div class="w-12 h-12 rounded-xl bg-violet-50 flex items-center justify-center shrink-0">
            <svg class="w-6 h-6 text-violet-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
        </div>
        <div>
            <p class="text-xs font-bold uppercase tracking-widest text-slate-400">Active Users Today</p>
            <p class="text-2xl font-heading font-black text-dranhs-dark"><?php echo $stats_active_users; ?></p>
        </div>
    </div>
</div>

<!-- Main Card -->
<div class="bg-white rounded-2xl shadow-sm border border-slate-200 overflow-hidden">

    <!-- Header -->
    <div class="px-6 py-5 border-b border-slate-100 flex flex-col md:flex-row justify-between items-start md:items-center gap-4">
        <div>
            <h2 class="text-2xl font-heading font-black text-dranhs-dark">Activity Logs</h2>
            <p class="text-sm text-slate-500 mt-1">Track every system action — logins, evaluations, encoding, account changes, and more.</p>
        </div>
        <a href="<?php echo htmlspecialchars('admin.php' . filter_qs(['export' => 'csv'])); ?>"
           class="inline-flex items-center gap-2 px-4 py-2.5 rounded-xl bg-dranhs-green text-white text-sm font-bold hover:bg-emerald-700 transition-colors shrink-0">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/></svg>
            Export CSV
        </a>
    </div>

    <!-- Filters -->
    <form method="GET" action="admin.php" class="px-6 py-4 border-b border-slate-100 bg-slate-50/50">
        <input type="hidden" name="page" value="logs">
        <div class="flex flex-wrap gap-3 items-end">
            <!-- Search -->
            <div class="flex-1 min-w-[200px]">
                <label class="block text-[10px] font-bold uppercase tracking-widest text-slate-400 mb-1">Search</label>
                <div class="relative">
                    <svg class="w-4 h-4 text-slate-400 absolute left-3 top-1/2 -translate-y-1/2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                    <input type="text" name="fs" value="<?php echo htmlspecialchars($filter_search); ?>"
                        placeholder="Search details, user, action..."
                        class="w-full pl-9 pr-4 py-2 text-sm border border-slate-300 rounded-lg focus:outline-none focus:border-dranhs-green bg-white">
                </div>
            </div>
            <!-- Action -->
            <div class="w-44">
                <label class="block text-[10px] font-bold uppercase tracking-widest text-slate-400 mb-1">Action</label>
                <select name="fa" class="w-full py-2 px-3 text-sm border border-slate-300 rounded-lg focus:outline-none focus:border-dranhs-green bg-white">
                    <option value="">All Actions</option>
                    <?php foreach ($distinct_actions as $a): ?>
                        <option value="<?php echo htmlspecialchars($a); ?>" <?php echo $filter_action === $a ? 'selected' : ''; ?>><?php echo htmlspecialchars(str_replace('_', ' ', $a)); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <!-- User -->
            <div class="w-36">
                <label class="block text-[10px] font-bold uppercase tracking-widest text-slate-400 mb-1">User</label>
                <select name="fu" class="w-full py-2 px-3 text-sm border border-slate-300 rounded-lg focus:outline-none focus:border-dranhs-green bg-white">
                    <option value="">All Users</option>
                    <?php foreach ($distinct_users as $u): ?>
                        <option value="<?php echo htmlspecialchars($u); ?>" <?php echo $filter_user === $u ? 'selected' : ''; ?>><?php echo htmlspecialchars($u); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <!-- Date From -->
            <div class="w-36">
                <label class="block text-[10px] font-bold uppercase tracking-widest text-slate-400 mb-1">From</label>
                <input type="date" name="df" value="<?php echo htmlspecialchars($filter_date_from); ?>"
                    class="w-full py-2 px-3 text-sm border border-slate-300 rounded-lg focus:outline-none focus:border-dranhs-green bg-white">
            </div>
            <!-- Date To -->
            <div class="w-36">
                <label class="block text-[10px] font-bold uppercase tracking-widest text-slate-400 mb-1">To</label>
                <input type="date" name="dt" value="<?php echo htmlspecialchars($filter_date_to); ?>"
                    class="w-full py-2 px-3 text-sm border border-slate-300 rounded-lg focus:outline-none focus:border-dranhs-green bg-white">
            </div>
            <!-- Buttons -->
            <div class="flex gap-2">
                <button type="submit" class="px-4 py-2 rounded-lg bg-dranhs-green text-white text-sm font-bold hover:bg-emerald-700 transition-colors">Filter</button>
                <a href="?page=logs" class="px-4 py-2 rounded-lg border border-slate-300 text-slate-600 text-sm font-bold hover:bg-slate-100 transition-colors">Clear</a>
            </div>
        </div>
    </form>

    <!-- Table -->
    <div class="overflow-x-auto">
        <table class="min-w-full text-sm">
            <thead class="bg-slate-50 text-slate-500 uppercase text-[11px] font-bold tracking-wider">
                <tr>
                    <th class="px-4 py-3 text-left">Timestamp</th>
                    <th class="px-4 py-3 text-left">User</th>
                    <th class="px-4 py-3 text-left">Action</th>
                    <th class="px-4 py-3 text-left">Details</th>
                    <th class="px-4 py-3 text-left">IP Address</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100 bg-white">
                <?php if (empty($rows)): ?>
                    <tr>
                        <td colspan="5" class="px-4 py-12 text-center">
                            <div class="flex flex-col items-center gap-2">
                                <svg class="w-12 h-12 text-slate-200" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                                <p class="text-sm font-semibold text-slate-400">No log entries found.</p>
                                <p class="text-xs text-slate-400">Actions will appear here as users interact with the system.</p>
                            </div>
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($rows as $row): ?>
                    <tr class="hover:bg-slate-50/50 transition-colors">
                        <td class="px-4 py-3 whitespace-nowrap">
                            <div class="text-xs font-semibold text-slate-700"><?php echo date('M d, Y', strtotime($row['created_at'])); ?></div>
                            <div class="text-[11px] text-slate-400 mt-0.5"><?php echo date('h:i:s A', strtotime($row['created_at'])); ?></div>
                        </td>
                        <td class="px-4 py-3 whitespace-nowrap">
                            <div class="text-sm font-bold text-slate-700"><?php echo htmlspecialchars($row['username']); ?></div>
                            <div class="mt-1"><?php echo role_badge($row['role']); ?></div>
                        </td>
                        <td class="px-4 py-3 whitespace-nowrap">
                            <?php echo action_badge($row['action']); ?>
                        </td>
                        <td class="px-4 py-3">
                            <div class="text-sm text-slate-600 max-w-md truncate" title="<?php echo htmlspecialchars($row['details']); ?>">
                                <?php echo htmlspecialchars($row['details'] ?: '—'); ?>
                            </div>
                            <?php if ($row['target_type']): ?>
                                <div class="text-[10px] text-slate-400 mt-0.5 uppercase tracking-wider font-bold">
                                    <?php echo htmlspecialchars($row['target_type']); ?><?php echo $row['target_id'] ? ' #' . (int)$row['target_id'] : ''; ?>
                                </div>
                            <?php endif; ?>
                        </td>
                        <td class="px-4 py-3 whitespace-nowrap">
                            <span class="text-xs font-mono text-slate-400"><?php echo htmlspecialchars($row['ip_address'] ?: '—'); ?></span>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <!-- Pagination -->
    <?php if ($total_pages > 1): ?>
    <div class="px-6 py-4 border-t border-slate-100 bg-slate-50/50 flex flex-col sm:flex-row justify-between items-center gap-3">
        <div class="text-xs text-slate-500 font-semibold">
            Showing <?php echo number_format($offset + 1); ?>–<?php echo number_format(min($offset + $per_page, $total_rows)); ?> of <?php echo number_format($total_rows); ?> entries
        </div>
        <div class="flex items-center gap-1">
            <?php if ($current_page > 1): ?>
                <a href="<?php echo htmlspecialchars('admin.php' . filter_qs(['pg' => $current_page - 1])); ?>"
                   class="px-3 py-1.5 rounded-lg border border-slate-300 text-slate-600 text-xs font-bold hover:bg-white transition-colors">← Prev</a>
            <?php endif; ?>

            <?php
            $start = max(1, $current_page - 2);
            $end   = min($total_pages, $current_page + 2);
            for ($p = $start; $p <= $end; $p++):
            ?>
                <a href="<?php echo htmlspecialchars('admin.php' . filter_qs(['pg' => $p])); ?>"
                   class="px-3 py-1.5 rounded-lg text-xs font-bold transition-colors <?php echo $p === $current_page ? 'bg-dranhs-green text-white' : 'border border-slate-300 text-slate-600 hover:bg-white'; ?>">
                    <?php echo $p; ?>
                </a>
            <?php endfor; ?>

            <?php if ($current_page < $total_pages): ?>
                <a href="<?php echo htmlspecialchars('admin.php' . filter_qs(['pg' => $current_page + 1])); ?>"
                   class="px-3 py-1.5 rounded-lg border border-slate-300 text-slate-600 text-xs font-bold hover:bg-white transition-colors">Next →</a>
            <?php endif; ?>
        </div>
    </div>
    <?php endif; ?>
</div>
