<?php
require_once __DIR__ . '/../db.php';
$conn = db_connect();
require_once __DIR__ . '/account_actions.php';
?>

<?php if ($toast_message): ?>
<div id="toast" class="fixed top-20 right-6 z-[200] <?php echo $toast_type==='error'?'bg-rose-100 border-rose-300 text-rose-800':'bg-emerald-100 border-emerald-300 text-emerald-800'; ?> px-5 py-3 rounded-xl shadow-lg flex items-center gap-3 border animate-slide-in">
    <svg class="w-5 h-5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>
    <span class="font-bold text-sm"><?php echo htmlspecialchars($toast_message); ?></span>
</div>
<script>setTimeout(()=>{const t=document.getElementById('toast');if(t)t.style.display='none';},4000);</script>
<?php endif; ?>

<style>
@keyframes slide-in{from{transform:translateX(100%);opacity:0}to{transform:translateX(0);opacity:1}}
.animate-slide-in{animation:slide-in .3s ease-out}
.role-badge{display:inline-flex;align-items:center;gap:4px;padding:3px 10px;border-radius:999px;font-size:11px;font-weight:800;text-transform:uppercase;letter-spacing:.5px}
.role-admin{background:#fef3c7;color:#92400e}.role-evaluator{background:#dbeafe;color:#1e40af}
.role-encoder{background:#d1fae5;color:#065f46}.role-registrar{background:#ede9fe;color:#5b21b6}
.role-faculty{background:#f1f5f9;color:#475569}.role-adviser{background:#fce7f3;color:#9d174d}
.status-active{color:#059669}.status-disabled{color:#dc2626}
</style>

<div class="bg-white rounded-2xl shadow-sm border border-slate-200 overflow-hidden min-h-[600px] flex flex-col">
    <!-- Header -->
    <div class="p-6 lg:p-8 border-b border-slate-100 bg-gradient-to-r from-slate-50 to-white">
        <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4">
            <div>
                <h2 class="text-2xl font-heading font-black text-dranhs-dark flex items-center gap-2">
                    <svg class="w-7 h-7 text-dranhs-green" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"></path></svg>
                    Faculty Accounts
                </h2>
                <p class="text-sm text-slate-500 mt-1">Manage all system users — roles, adviser assignments, and credentials.</p>
            </div>
            <button type="button" onclick="openCreateModal()" class="bg-dranhs-green hover:bg-emerald-600 text-white font-bold px-5 py-2.5 rounded-xl shadow-sm transition flex items-center gap-2 shrink-0">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"></path></svg>
                New Account
            </button>
        </div>
        <!-- Stats -->
        <div class="flex flex-wrap gap-4 mt-5">
            <?php
            $total = count($users);
            $admins = count(array_filter($users, fn($u) => $u['is_admin']));
            $advisers_count = count(array_filter($users, fn($u) => !empty($u['adviser_section'])));
            $disabled = count(array_filter($users, fn($u) => ($u['status']??'active')==='disabled'));
            ?>
            <div class="flex items-center gap-2 bg-slate-100 px-4 py-2 rounded-lg">
                <span class="text-xl font-black text-dranhs-dark"><?php echo $total; ?></span>
                <span class="text-xs font-bold text-slate-500 uppercase">Total</span>
            </div>
            <div class="flex items-center gap-2 bg-amber-50 px-4 py-2 rounded-lg">
                <span class="text-xl font-black text-amber-700"><?php echo $admins; ?></span>
                <span class="text-xs font-bold text-amber-600 uppercase">Admins</span>
            </div>
            <div class="flex items-center gap-2 bg-pink-50 px-4 py-2 rounded-lg">
                <span class="text-xl font-black text-pink-700"><?php echo $advisers_count; ?></span>
                <span class="text-xs font-bold text-pink-600 uppercase">Advisers</span>
            </div>
            <?php if ($disabled > 0): ?>
            <div class="flex items-center gap-2 bg-red-50 px-4 py-2 rounded-lg">
                <span class="text-xl font-black text-red-600"><?php echo $disabled; ?></span>
                <span class="text-xs font-bold text-red-500 uppercase">Disabled</span>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Faculty Table -->
    <div class="flex-1 p-6 lg:p-8 overflow-x-auto">
        <table class="min-w-full divide-y divide-slate-200 border border-slate-100 rounded-xl">
            <thead class="bg-slate-50">
                <tr>
                    <th class="px-4 py-3 text-left text-xs font-bold uppercase tracking-wider w-10">#</th>
                    <th class="px-4 py-3 text-left text-xs font-bold uppercase tracking-wider">Faculty</th>
                    <th class="px-4 py-3 text-left text-xs font-bold uppercase tracking-wider">Username / Email</th>
                    <th class="px-4 py-3 text-left text-xs font-bold uppercase tracking-wider">Roles</th>
                    <th class="px-4 py-3 text-left text-xs font-bold uppercase tracking-wider">Adviser Of</th>
                    <th class="px-4 py-3 text-center text-xs font-bold uppercase tracking-wider">Status</th>
                    <th class="px-4 py-3 text-right text-xs font-bold uppercase tracking-wider">Actions</th>
                </tr>
            </thead>
            <tbody class="bg-white divide-y divide-slate-100">
                <?php if (empty($users)): ?>
                <tr><td colspan="7" class="px-4 py-8 text-sm text-slate-400 text-center">No accounts found.</td></tr>
                <?php else: ?>
                <?php foreach ($users as $i => $u):
                    $uid = (int)$u['id'];
                    $roles_arr = array_filter(explode(',', $u['roles'] ?? ''));
                    $is_adm = (int)($u['is_admin'] ?? 0);
                    $status = $u['status'] ?? 'active';
                    $has_email = !empty($u['email']);
                ?>
                <tr class="hover:bg-slate-50/50 transition <?php echo $status==='disabled'?'opacity-50':''; ?>">
                    <td class="px-4 py-3 text-sm font-semibold text-slate-400"><?php echo $i+1; ?></td>
                    <td class="px-4 py-3">
                        <div class="flex items-center gap-3">
                            <?php if (!empty($u['avatar'])): ?>
                            <img src="../<?php echo htmlspecialchars($u['avatar']); ?>" class="w-10 h-10 rounded-full object-cover border-2 border-slate-200">
                            <?php else: ?>
                            <div class="w-10 h-10 rounded-full bg-gradient-to-br from-dranhs-green to-emerald-600 flex items-center justify-center text-white font-black text-sm">
                                <?php echo strtoupper(substr($u['full_name'] ?? $u['username'], 0, 2)); ?>
                            </div>
                            <?php endif; ?>
                            <span class="font-bold text-sm text-dranhs-dark"><?php echo htmlspecialchars($u['full_name'] ?? $u['username']); ?></span>
                        </div>
                    </td>
                    <td class="px-4 py-3">
                        <div class="text-sm font-semibold text-slate-700"><?php echo htmlspecialchars($u['username']); ?></div>
                        <?php if ($has_email): ?>
                        <div class="text-xs text-slate-400"><?php echo htmlspecialchars($u['email']); ?></div>
                        <?php endif; ?>
                    </td>
                    <td class="px-4 py-3">
                        <div class="flex flex-wrap gap-1">
                            <?php if ($is_adm): ?><span class="role-badge role-admin">🛡️ Admin</span><?php endif; ?>
                            <?php foreach ($roles_arr as $r): ?>
                            <span class="role-badge role-<?php echo htmlspecialchars($r); ?>"><?php echo htmlspecialchars(ucfirst($r)); ?></span>
                            <?php endforeach; ?>
                            <?php if (!$is_adm && empty($roles_arr)): ?><span class="role-badge role-faculty">Faculty</span><?php endif; ?>
                        </div>
                    </td>
                    <td class="px-4 py-3">
                        <?php if (!empty($u['adviser_section'])): ?>
                        <span class="role-badge role-adviser">📚 <?php echo htmlspecialchars($u['adviser_grade_level'] . ' — ' . $u['adviser_section']); ?></span>
                        <?php else: ?>
                        <span class="text-xs text-slate-300">—</span>
                        <?php endif; ?>
                    </td>
                    <td class="px-4 py-3 text-center">
                        <span class="text-xs font-black uppercase tracking-wider status-<?php echo $status; ?>"><?php echo $status; ?></span>
                    </td>
                    <td class="px-4 py-3 text-right">
                        <div class="inline-flex items-center gap-1">
                            <button onclick="<?php if ($has_email): ?>openSendModal(<?php echo $uid; ?>,'<?php echo addslashes($u['full_name']??''); ?>','<?php echo addslashes($u['email']??''); ?>')<?php else: ?>alert('No email set for this user. Edit the account to add an email first.')<?php endif; ?>" class="p-1.5 rounded-lg transition <?php echo $has_email ? 'text-blue-400 hover:text-blue-600 hover:bg-blue-50' : 'text-slate-300 cursor-not-allowed'; ?>" title="<?php echo $has_email ? 'Send Credentials' : 'No email — add email first'; ?>">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"></path></svg>
                            </button>
                            <button onclick='openEditModal(<?php echo json_encode($u); ?>)' class="p-1.5 rounded-lg text-amber-400 hover:text-amber-600 hover:bg-amber-50 transition" title="Edit">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path></svg>
                            </button>
                            <?php if ($uid !== $current_user_id): ?>
                            <button onclick="openDeleteModal(<?php echo $uid; ?>,'<?php echo addslashes($u['full_name']??$u['username']); ?>')" class="p-1.5 rounded-lg text-red-300 hover:text-red-500 hover:bg-red-50 transition" title="Delete">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path></svg>
                            </button>
                            <?php endif; ?>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Modals + JS loaded from separate file -->
<?php include __DIR__ . '/account_modals.php'; ?>
