<!-- ═══ BACKDROP ═══ -->
<div id="modal-bg" class="fixed inset-0 z-[100] hidden bg-slate-900/50 backdrop-blur-sm transition-opacity" onclick="closeAllModals()"></div>

<!-- ═══ CREATE / EDIT MODAL ═══ -->
<div id="form-modal" class="fixed inset-0 z-[110] hidden flex items-center justify-center p-4 overflow-y-auto">
<div class="bg-white rounded-2xl w-full max-w-lg shadow-2xl" onclick="event.stopPropagation()">
    <div class="p-6 border-b border-slate-100">
        <h3 id="fm-title" class="text-lg font-heading font-black text-dranhs-dark">New Account</h3>
        <p id="fm-desc" class="text-sm text-slate-500 mt-1">Fill in details and confirm with admin password.</p>
    </div>
    <form id="fm-form" method="POST" action="?page=account" enctype="multipart/form-data" class="p-6 space-y-4 max-h-[70vh] overflow-y-auto sidebar-scroll">
        <input type="hidden" name="action" id="fm-action" value="create_account">
        <input type="hidden" name="target_user_id" id="fm-target-id" value="">

        <div class="grid grid-cols-2 gap-4">
            <div class="col-span-2">
                <label class="text-xs font-bold uppercase tracking-wider text-slate-500">Full Name *</label>
                <input type="text" name="full_name" id="fm-fullname" class="form-input mt-1" required placeholder="e.g. JOEFER D. ALAGASI">
            </div>
            <div>
                <label class="text-xs font-bold uppercase tracking-wider text-slate-500">Username *</label>
                <input type="text" name="new_username" id="fm-username" class="form-input mt-1" required placeholder="joefer">
            </div>
            <div>
                <label class="text-xs font-bold uppercase tracking-wider text-slate-500">Email</label>
                <input type="email" name="email" id="fm-email" class="form-input mt-1" placeholder="optional">
            </div>
        </div>

        <div>
            <label class="text-xs font-bold uppercase tracking-wider text-slate-500">Password *</label>
            <div class="relative mt-1">
                <input type="text" name="new_password" id="fm-password" class="form-input pr-20" placeholder="Min 8 characters">
                <button type="button" onclick="genPass()" class="absolute right-2 top-1/2 -translate-y-1/2 text-xs font-bold text-dranhs-green hover:text-emerald-700 bg-emerald-50 px-2 py-1 rounded">Generate</button>
            </div>
        </div>

        <div>
            <label class="text-xs font-bold uppercase tracking-wider text-slate-500 mb-2 block">Roles</label>
            <div class="flex flex-wrap gap-3">
                <label class="flex items-center gap-2 bg-amber-50 px-3 py-2 rounded-lg cursor-pointer hover:bg-amber-100 transition border border-transparent has-[:checked]:border-amber-400">
                    <input type="checkbox" name="is_admin" id="fm-is-admin" value="1" class="accent-amber-500"> <span class="text-sm font-bold text-amber-800">🛡️ Admin</span>
                </label>
                <label class="flex items-center gap-2 bg-blue-50 px-3 py-2 rounded-lg cursor-pointer hover:bg-blue-100 transition border border-transparent has-[:checked]:border-blue-400">
                    <input type="checkbox" name="roles[]" value="evaluator" class="fm-role accent-blue-500"> <span class="text-sm font-bold text-blue-800">📋 Evaluator</span>
                </label>
                <label class="flex items-center gap-2 bg-emerald-50 px-3 py-2 rounded-lg cursor-pointer hover:bg-emerald-100 transition border border-transparent has-[:checked]:border-emerald-400">
                    <input type="checkbox" name="roles[]" value="encoder" class="fm-role accent-emerald-500"> <span class="text-sm font-bold text-emerald-800">📝 Encoder</span>
                </label>
                <label class="flex items-center gap-2 bg-purple-50 px-3 py-2 rounded-lg cursor-pointer hover:bg-purple-100 transition border border-transparent has-[:checked]:border-purple-400">
                    <input type="checkbox" name="roles[]" value="registrar" class="fm-role accent-purple-500"> <span class="text-sm font-bold text-purple-800">📑 Registrar</span>
                </label>
            </div>
        </div>

                <label class="flex items-center gap-2 bg-pink-50 px-3 py-2 rounded-lg cursor-pointer hover:bg-pink-100 transition border border-transparent has-[:checked]:border-pink-400">
                    <input type="checkbox" name="roles[]" value="adviser" class="fm-role accent-pink-500"> <span class="text-sm font-bold text-pink-800">📚 Adviser</span>
                </label>

        <div>
            <label class="text-xs font-bold uppercase tracking-wider text-slate-500">Profile Photo</label>
            <input type="file" name="avatar" id="fm-avatar" class="form-input mt-1" accept="image/jpeg,image/png,image/webp">
        </div>

        <hr class="border-slate-200">
        <div>
            <label class="text-xs font-bold uppercase tracking-wider text-slate-500">Admin Password *</label>
            <input type="password" name="admin_password" id="fm-adminpass" class="form-input mt-1" required placeholder="Your password to confirm">
        </div>

        <div class="flex justify-end gap-2 pt-2">
            <button type="button" onclick="closeAllModals()" class="px-5 py-2.5 text-sm font-bold border border-slate-300 rounded-xl text-slate-600 hover:bg-slate-100 transition">Cancel</button>
            <button type="submit" class="px-5 py-2.5 text-sm font-bold bg-dranhs-green text-white rounded-xl hover:bg-emerald-700 transition">Confirm</button>
        </div>
    </form>
</div>
</div>

<!-- ═══ DELETE MODAL ═══ -->
<div id="delete-modal" class="fixed inset-0 z-[110] hidden flex items-center justify-center p-4">
<div class="bg-white rounded-2xl w-full max-w-sm shadow-2xl p-6" onclick="event.stopPropagation()">
    <div class="w-12 h-12 rounded-full bg-red-100 flex items-center justify-center mx-auto mb-4">
        <svg class="w-6 h-6 text-red-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path></svg>
    </div>
    <h3 class="text-lg font-bold text-center text-dranhs-dark">Delete Account</h3>
    <p id="del-desc" class="text-sm text-slate-500 text-center mt-2">Are you sure you want to delete this account?</p>
    <form method="POST" action="?page=account" class="mt-5 space-y-3">
        <input type="hidden" name="action" value="delete_account">
        <input type="hidden" name="target_user_id" id="del-id" value="">
        <input type="password" name="admin_password" class="form-input" placeholder="Admin password" required>
        <div class="flex gap-2">
            <button type="button" onclick="closeAllModals()" class="flex-1 px-4 py-2.5 text-sm font-bold border border-slate-300 rounded-xl text-slate-600 hover:bg-slate-100">Cancel</button>
            <button type="submit" class="flex-1 px-4 py-2.5 text-sm font-bold bg-red-500 text-white rounded-xl hover:bg-red-600">Delete</button>
        </div>
    </form>
</div>
</div>

<!-- ═══ SEND CREDENTIALS MODAL ═══ -->
<div id="send-modal" class="fixed inset-0 z-[110] hidden flex items-center justify-center p-4">
<div class="bg-white rounded-2xl w-full max-w-sm shadow-2xl p-6" onclick="event.stopPropagation()">
    <div class="w-12 h-12 rounded-full bg-blue-100 flex items-center justify-center mx-auto mb-4">
        <svg class="w-6 h-6 text-blue-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"></path></svg>
    </div>
    <h3 class="text-lg font-bold text-center text-dranhs-dark">Send Credentials</h3>
    <p id="send-desc" class="text-sm text-slate-500 text-center mt-2">Email login credentials to this user.</p>
    <form method="POST" action="?page=account" class="mt-5 space-y-3">
        <input type="hidden" name="action" value="send_credentials">
        <input type="hidden" name="target_user_id" id="send-id" value="">
        <div>
            <label class="text-xs font-bold text-slate-500 uppercase">Password to include</label>
            <input type="text" name="send_password" class="form-input mt-1" placeholder="Enter password to send" required>
            <p class="text-xs text-slate-400 mt-1">We can't retrieve stored passwords. Enter the password you want to send.</p>
        </div>
        <input type="password" name="admin_password" class="form-input" placeholder="Admin password to confirm" required>
        <div class="flex gap-2">
            <button type="button" onclick="closeAllModals()" class="flex-1 px-4 py-2.5 text-sm font-bold border border-slate-300 rounded-xl text-slate-600 hover:bg-slate-100">Cancel</button>
            <button type="submit" class="flex-1 px-4 py-2.5 text-sm font-bold bg-blue-500 text-white rounded-xl hover:bg-blue-600">📧 Send Email</button>
        </div>
    </form>
</div>
</div>

<script>

function closeAllModals(){
    document.getElementById('modal-bg').classList.add('hidden');
    document.getElementById('form-modal').classList.add('hidden');
    document.getElementById('delete-modal').classList.add('hidden');
    document.getElementById('send-modal').classList.add('hidden');
}
function showModal(id){
    document.getElementById('modal-bg').classList.remove('hidden');
    document.getElementById(id).classList.remove('hidden');
}

function openCreateModal(){
    document.getElementById('fm-form').reset();
    document.getElementById('fm-action').value='create_account';
    document.getElementById('fm-target-id').value='';
    document.getElementById('fm-title').textContent='New Account';
    document.getElementById('fm-desc').textContent='Create a new faculty account.';
    document.getElementById('fm-password').required=true;
    showModal('form-modal');
}

function openEditModal(user, classrooms){
    document.getElementById('fm-form').reset();
    document.getElementById('fm-action').value='edit_account';
    document.getElementById('fm-target-id').value=user.id;
    document.getElementById('fm-title').textContent='Edit: '+(user.full_name||user.username);
    document.getElementById('fm-desc').textContent='Update account details.';
    document.getElementById('fm-fullname').value=user.full_name||'';
    document.getElementById('fm-username').value=user.username||'';
    document.getElementById('fm-email').value=user.email||'';
    document.getElementById('fm-password').value='';
    document.getElementById('fm-password').required=false;
    document.getElementById('fm-password').placeholder='Leave empty to keep current';
    document.getElementById('fm-is-admin').checked=!!parseInt(user.is_admin);
    // Set role checkboxes
    const roles=(user.roles||'').split(',');
    document.querySelectorAll('.fm-role').forEach(cb=>{cb.checked=roles.includes(cb.value);});

    showModal('form-modal');
}

function openDeleteModal(id,name){
    document.getElementById('del-id').value=id;
    document.getElementById('del-desc').textContent='Permanently delete account: '+name+'?';
    showModal('delete-modal');
}

function openSendModal(id,name,email){
    document.getElementById('send-id').value=id;
    document.getElementById('send-desc').textContent='Send credentials to '+name+' ('+email+')';
    showModal('send-modal');
}


function genPass(){
    const chars='ABCDEFGHJKMNPQRSTUVWXYZabcdefghjkmnpqrstuvwxyz23456789!@#$%';
    let p='';for(let i=0;i<12;i++)p+=chars[Math.floor(Math.random()*chars.length)];
    document.getElementById('fm-password').value=p;
}
</script>
