<?php
ob_start();
require_once __DIR__ . '/../session.php';
ems2_session_start();
require_once __DIR__ . '/../db.php';
// Check if user is actually logged in — also verify user still exists in DB
if (!isset($_SESSION['user_id']) || !isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    ems2_login_redirect();
}

// ── Auto session timeout: 5 minutes of inactivity ────────────────────────────
define('SESSION_TIMEOUT', 300); // 5 minutes in seconds
if (isset($_SESSION['_last_activity'])) {
    if (time() - $_SESSION['_last_activity'] > SESSION_TIMEOUT) {
        session_unset();
        session_destroy();
        ems2_login_redirect('timeout=1');
    }
}
$_SESSION['_last_activity'] = time();

if (isset($_GET['keepalive'])) {
    http_response_code(204);
    exit;
}

// Validate session against DB (prevents stale sessions)
$_sv_conn = db_connect();
$_admin_school_logo = null;
if (!$_sv_conn->connect_error) {
    $_sv_stmt = $_sv_conn->prepare("SELECT u.id, u.roles, u.is_admin, u.full_name, u.status, c.section_name AS adviser_section, c.id AS adviser_classroom_id FROM users u LEFT JOIN classrooms c ON c.adviser_id = u.id WHERE u.id = ? LIMIT 1");
    if ($_sv_stmt) {
        $_sv_stmt->bind_param("i", $_SESSION['user_id']);
        $_sv_stmt->execute();
        $_sv_result = $_sv_stmt->get_result()->fetch_assoc();
        $_sv_stmt->close();
        if (!$_sv_result || ($_sv_result['status'] ?? 'active') === 'disabled') {
            session_destroy();
            ems2_login_redirect();
        }
        // Sync session with DB on every request
        $_SESSION['roles']     = $_sv_result['roles'] ?? '';
        $_SESSION['is_admin']  = (int)($_sv_result['is_admin'] ?? 0);
        $_SESSION['full_name'] = $_sv_result['full_name'] ?? $_SESSION['username'];
        $_SESSION['adviser_section']      = $_sv_result['adviser_section'] ?? null;
        $_SESSION['adviser_classroom_id'] = $_sv_result['adviser_classroom_id'] ?? null;
        // Backward compat
        $is_admin_flag = (int)($_sv_result['is_admin'] ?? 0);
        $roles_str = trim($_sv_result['roles'] ?? '');
        $_SESSION['role'] = $is_admin_flag ? 'admin' : ($roles_str ?: 'faculty');
    }
    // Load school logo for sidebar
    $_logo_res = $_sv_conn->query("SELECT setting_value FROM system_settings WHERE setting_key='school_logo' LIMIT 1");
    if ($_logo_res && $_logo_row = $_logo_res->fetch_assoc()) {
        $_admin_school_logo = $_logo_row['setting_value'];
    }
    $_sv_conn->close();
}
$loggedIn = true;
$userRole = $_SESSION['role'] ?? 'faculty';
$username = $_SESSION['username'] ?? 'User';
$userRolesStr = $_SESSION['roles'] ?? '';
$userRolesArr = array_filter(array_map('trim', explode(',', $userRolesStr)));
$isAdmin = (int)($_SESSION['is_admin'] ?? 0);
$isAdviser = !empty($_SESSION['adviser_section']);


// Simple routing logic
$page = isset($_GET['page']) ? $_GET['page'] : 'dashboard';
$page_title = ucfirst($page);

// Build allowed pages from combined roles
$allowed_pages = ['dashboard', 'student']; // everyone gets these

if ($isAdmin) {
    $allowed_pages = [
        'dashboard', 
        'student', 
        'classroom', 
        'evaluation', 
        'encode', 
        'list', 
        'account', 
        'logs', 
        'system_settings'
    ];
} else {
    if (in_array('evaluator', $userRolesArr)) {
        $allowed_pages[] = 'evaluation';
    }
    if (in_array('encoder', $userRolesArr)) {
        $allowed_pages[] = 'encode';
        if (!in_array('classroom', $allowed_pages)) $allowed_pages[] = 'classroom';
    }
    if (in_array('registrar', $userRolesArr)) {
        if (!in_array('list', $allowed_pages)) $allowed_pages[] = 'list';
        if (!in_array('classroom', $allowed_pages)) $allowed_pages[] = 'classroom';
    }
    if ($isAdviser) {
        if (!in_array('classroom', $allowed_pages)) $allowed_pages[] = 'classroom';
    }
}

// Redirect back to dashboard if trying to access unauthorized page
if (!in_array($page, $allowed_pages)) {
    $page = 'dashboard';
    $page_title = ucfirst($page);
}

// Active link classes
$activeClasses = 'bg-dranhs-green text-white shadow-md';
$inactiveClasses = 'text-slate-300 hover:bg-slate-800 hover:text-white';

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title; ?> | DRANHS Portal</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:ital,wght@0,400;0,700;0,900;1,800&family=Outfit:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            darkMode: 'class',
            theme: {
                extend: {
                    fontFamily: {
                        sans: ['Outfit', 'sans-serif'],
                        heading: ['Montserrat', 'sans-serif'],
                    },
                    colors: {
                        'dranhs-green': '#009b5a',
                        'dranhs-dark': '#1c2434',
                        'dranhs-light': '#f1f5f9',
                    }
                }
            }
        }
    </script>
    <style>
        /* Custom Scrollbar for Sidebar */
        .sidebar-scroll::-webkit-scrollbar {
            width: 5px;
        }
        .sidebar-scroll::-webkit-scrollbar-track {
            background: #2d3748;
        }
        .sidebar-scroll::-webkit-scrollbar-thumb {
            background: #4a5568;
            border-radius: 5px;
        }
        .sidebar-scroll::-webkit-scrollbar-thumb:hover {
            background: #718096;
        }
    </style>
</head>
<body class="bg-slate-50 text-slate-800 font-sans antialiased selection:bg-dranhs-green selection:text-white">

    <!-- Mobile Overlay -->
    <div id="sidebar-overlay" class="fixed inset-0 z-20 bg-slate-900/50 hidden lg:hidden transition-opacity opacity-0"></div>

    <!-- Sidebar -->
    <aside id="sidebar" class="fixed top-0 left-0 z-30 w-64 h-screen bg-dranhs-dark text-white transition-transform transform -translate-x-full lg:translate-x-0 flex flex-col shadow-xl">
        <!-- Logo Area -->
        <div class="h-16 flex items-center gap-3 px-6 border-b border-slate-700 bg-[#151b29]">
            <div class="w-8 h-8 rounded-full bg-dranhs-green flex items-center justify-center shrink-0 overflow-hidden">
                <?php if (!empty($_admin_school_logo)): ?>
                    <img src="../<?php echo htmlspecialchars($_admin_school_logo); ?>" alt="Logo" class="w-full h-full object-contain">
                <?php else: ?>
                    <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"></path></svg>
                <?php endif; ?>
            </div>
            <span class="font-heading font-bold text-lg tracking-wide">DRANHS<span class="text-dranhs-green"> SMARTENROLL</span></span>
        </div>

        <!-- Navigation -->
        <div class="flex-1 overflow-y-auto sidebar-scroll py-6 px-3 space-y-1">
            
            <!-- Dashboard -->
            <?php if (in_array('dashboard', $allowed_pages)): ?>
            <a href="?page=dashboard" class="flex items-center gap-3 px-3 py-2.5 rounded-lg group transition-all <?php echo ($page === 'dashboard') ? $activeClasses : $inactiveClasses; ?>">
                <svg class="w-5 h-5 <?php echo ($page === 'dashboard') ? '' : 'text-slate-400 group-hover:text-white'; ?>" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2V6zM14 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2V6zM4 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2v-2zM14 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2v-2z"></path></svg>
                <span class="font-medium text-sm">Dashboard</span>
            </a>
            <?php endif; ?>

            <!-- Section Label -->
            <div class="px-3 mt-6 mb-2 text-xs font-bold text-slate-500 uppercase tracking-wider">Academic Management</div>

            <!-- Student -->
            <?php if (in_array('student', $allowed_pages)): ?>
            <a href="?page=student" class="flex items-center gap-3 px-3 py-2.5 rounded-lg group transition-colors <?php echo ($page === 'student') ? $activeClasses : $inactiveClasses; ?>">
                <svg class="w-5 h-5 text-slate-400 group-hover:text-white transition-colors" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"></path></svg>
                <span class="font-medium text-sm">Student</span>
            </a>
            <?php endif; ?>

            <!-- Evaluation -->
            <?php if (in_array('evaluation', $allowed_pages)): ?>
            <a href="?page=evaluation" class="flex items-center gap-3 px-3 py-2.5 rounded-lg group transition-colors <?php echo ($page === 'evaluation') ? $activeClasses : $inactiveClasses; ?>">
                <svg class="w-5 h-5 text-slate-400 group-hover:text-white transition-colors" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-3 7h3m-3 4h3m-6-4h.01M9 16h.01"></path></svg>
                <span class="font-medium text-sm">Evaluation</span>
            </a>
            <?php endif; ?>

            <!-- Encode -->
            <?php if (in_array('encode', $allowed_pages)): ?>
            <a href="?page=encode" class="flex items-center gap-3 px-3 py-2.5 rounded-lg group transition-colors <?php echo ($page === 'encode') ? $activeClasses : $inactiveClasses; ?>">
                <svg class="w-5 h-5 text-slate-400 group-hover:text-white transition-colors" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path></svg>
                <span class="font-medium text-sm">Encode</span>
            </a>
            <?php endif; ?>

            <!-- Classroom -->
            <?php if (in_array('classroom', $allowed_pages)): ?>
            <a href="?page=classroom" class="flex items-center gap-3 px-3 py-2.5 rounded-lg group transition-colors <?php echo ($page === 'classroom') ? $activeClasses : $inactiveClasses; ?>">
                <svg class="w-5 h-5 text-slate-400 group-hover:text-white transition-colors" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 14v3m4-3v3m4-3v3M3 21h18M3 10h18M3 7l9-4 9 4M4 10h16v11H4V10z"></path></svg>
                <span class="font-medium text-sm">Classroom</span>
            </a>
            <?php endif; ?>

            <!-- List -->
            <?php if (in_array('list', $allowed_pages)): ?>
            <a href="?page=list" class="flex items-center gap-3 px-3 py-2.5 rounded-lg group transition-colors <?php echo ($page === 'list') ? $activeClasses : $inactiveClasses; ?>">
                <svg class="w-5 h-5 text-slate-400 group-hover:text-white transition-colors" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 10h16M4 14h16M4 18h16"></path></svg>
                <span class="font-medium text-sm">List</span>
            </a>
            <?php endif; ?>

            <?php if (in_array('account', $allowed_pages) || in_array('logs', $allowed_pages) || in_array('system_settings', $allowed_pages)): ?>
            <!-- Section Label -->
            <div class="px-3 mt-6 mb-2 text-xs font-bold text-slate-500 uppercase tracking-wider">Section</div>
            <?php endif; ?>

            <!-- Accounts -->
            <?php if (in_array('account', $allowed_pages)): ?>
            <a href="?page=account" class="flex items-center gap-3 px-3 py-2.5 rounded-lg group transition-colors <?php echo ($page === 'account') ? $activeClasses : $inactiveClasses; ?>">
                <svg class="w-5 h-5 text-slate-400 group-hover:text-white transition-colors" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 7a2 2 0 012 2m4 0a6 6 0 01-7.743 5.743L11 17H9v2H7v2H4a1 1 0 01-1-1v-2.586a1 1 0 01.293-.707l5.964-5.964A6 6 0 1121 9z"></path></svg>
                <span class="font-medium text-sm">Accounts</span>
            </a>
            <?php endif; ?>

            <!-- Logs -->
            <?php if (in_array('logs', $allowed_pages)): ?>
            <a href="?page=logs" class="flex items-center gap-3 px-3 py-2.5 rounded-lg group transition-colors <?php echo ($page === 'logs') ? $activeClasses : $inactiveClasses; ?>">
                <svg class="w-5 h-5 text-slate-400 group-hover:text-white transition-colors" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path></svg>
                <span class="font-medium text-sm">Logs</span>
            </a>
            <?php endif; ?>

            <!-- System Settings -->
            <?php if (in_array('system_settings', $allowed_pages)): ?>
            <a href="?page=system_settings" class="flex items-center gap-3 px-3 py-2.5 rounded-lg group transition-colors <?php echo ($page === 'system_settings') ? $activeClasses : $inactiveClasses; ?>">
                <svg class="w-5 h-5 text-slate-400 group-hover:text-white transition-colors" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"></path><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path></svg>
                <span class="font-medium text-sm">System Settings</span>
            </a>
            <?php endif; ?>
        </div>

        <!-- User Info Footer -->
        <div class="p-4 border-t border-slate-700 bg-[#151b29]">
            <div class="flex items-center gap-3">
                <img src="https://ui-avatars.com/api/?name=<?php echo urlencode($username); ?>&background=009b5a&color=fff" alt="<?php echo htmlspecialchars(ucfirst($username)); ?>" class="w-10 h-10 rounded-full bg-slate-600">
                <div class="flex-1 min-w-0">
                    <p class="text-sm font-bold text-white truncate"><?php echo htmlspecialchars(ucfirst($username)); ?></p>
                    <p class="text-xs text-slate-400 truncate uppercase tracking-widest"><?php echo htmlspecialchars($userRole); ?></p>
                </div>
                <a href="../logout.php" class="text-slate-400 hover:text-white transition-colors" title="Logout">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"></path></svg>
                </a>
            </div>
        </div>
    </aside>

    <!-- Main Content Layout -->
    <div class="lg:ml-64 min-h-screen flex flex-col transition-all duration-300">
        
        <!-- Top Navigation Bar -->
        <header class="bg-white h-16 shadow-sm flex items-center justify-between px-4 lg:px-8 sticky top-0 z-20">
            <div class="flex items-center gap-4">
                <button id="sidebar-toggle" class="lg:hidden text-slate-500 hover:text-dranhs-dark focus:outline-none">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"></path></svg>
                </button>
                <h1 class="text-xl font-heading font-bold text-dranhs-dark hidden sm:block"><?php echo $page_title; ?></h1>
            </div>
            
            <div class="flex items-center gap-4">
                <!-- Notifications -->
                <button class="relative p-2 text-slate-400 hover:text-dranhs-green transition-colors rounded-full hover:bg-slate-50">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"></path></svg>
                    <span class="absolute top-2 right-2 w-2.5 h-2.5 bg-red-500 rounded-full border-2 border-white"></span>
                </button>
                <!-- Date Widget -->
                <div class="hidden md:flex flex-col items-end">
                    <span class="text-xs font-bold text-slate-400 uppercase tracking-wider">Today</span>
                    <span class="text-sm font-bold text-dranhs-dark"><?php echo date('F d, Y'); ?></span>
                </div>
            </div>
        </header>

        <!-- Main Page Content -->
        <main class="flex-1 p-4 lg:p-8 overflow-x-hidden">
            <?php
                // Include page content based on the 'page' parameter
                if (in_array($page, $allowed_pages)) {
                    $page_path = __DIR__ . DIRECTORY_SEPARATOR . $page . '.php';
                    if (file_exists($page_path)) {
                        include $page_path;
                    } else {
                        echo '<p class="text-red-600 font-bold p-4">File not found: ' . htmlspecialchars($page_path) . '</p>';
                    }
                } else {
                    echo '<div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative" role="alert">';
                    echo '<strong class="font-bold">Error:</strong>';
                    echo '<span class="block sm:inline"> Page not found.</span>';
                    echo '</div>';
                }
            ?>
        </main>
    </div>

    <script>
        // Mobile Sidebar Logic
        const sidebarToggle = document.getElementById('sidebar-toggle');
        const sidebar = document.getElementById('sidebar');
        const overlay = document.getElementById('sidebar-overlay');

        function toggleSidebar() {
            const isClosed = sidebar.classList.contains('-translate-x-full');
            if (isClosed) {
                sidebar.classList.remove('-translate-x-full');
                overlay.classList.remove('hidden');
                setTimeout(() => overlay.classList.remove('opacity-0'), 10);
            } else {
                sidebar.classList.add('-translate-x-full');
                overlay.classList.add('opacity-0');
                setTimeout(() => overlay.classList.add('hidden'), 300);
            }
        }

        if(sidebarToggle) {
            sidebarToggle.addEventListener('click', toggleSidebar);
        }

        if(overlay) {
            overlay.addEventListener('click', toggleSidebar);
        }
    </script>

    <!-- ── Session Timeout Warning ── -->
    <div id="session-timeout-modal" class="fixed inset-0 z-[9999] hidden items-center justify-center" style="background:rgba(15,23,42,0.75);backdrop-filter:blur(4px);">
        <div class="bg-white rounded-2xl shadow-2xl w-full max-w-sm p-6 text-center">
            <div class="w-14 h-14 rounded-full bg-amber-100 flex items-center justify-center mx-auto mb-4">
                <svg class="w-7 h-7 text-amber-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z"/></svg>
            </div>
            <h3 class="font-heading font-black text-lg text-slate-800 mb-1">Session Expiring</h3>
            <p class="text-sm text-slate-500 mb-4">You will be logged out in <span id="timeout-countdown" class="font-black text-amber-600">30</span> seconds due to inactivity.</p>
            <div class="flex gap-3">
                <button onclick="resetSessionTimer()" class="flex-1 px-4 py-2.5 rounded-xl bg-dranhs-green text-white text-sm font-bold hover:bg-emerald-700 transition-colors">Stay Logged In</button>
                <a href="../logout.php" class="flex-1 px-4 py-2.5 rounded-xl bg-slate-100 text-slate-700 text-sm font-bold hover:bg-slate-200 transition-colors text-center">Logout Now</a>
            </div>
        </div>
    </div>

    <script>
    // Session timeout: 5 min (300s) inactivity → warn at 4:30 (270s), logout at 5:00 (300s)
    const SESSION_TIMEOUT_MS  = 300 * 1000; // 5 minutes
    const WARN_BEFORE_MS      = 30 * 1000;  // warn 30s before
    let _sessionTimer, _countdownTimer, _countdownVal = 30;
    const _modal = document.getElementById('session-timeout-modal');
    const _countEl = document.getElementById('timeout-countdown');

    function resetSessionTimer() {
        clearTimeout(_sessionTimer);
        clearInterval(_countdownTimer);
        _modal.classList.add('hidden');
        _modal.classList.remove('flex');
        _countdownVal = 30;
        if (_countEl) _countEl.textContent = '30';
        _sessionTimer = setTimeout(showTimeoutWarning, SESSION_TIMEOUT_MS - WARN_BEFORE_MS);
    }

    function showTimeoutWarning() {
        _countdownVal = 30;
        _modal.classList.remove('hidden');
        _modal.classList.add('flex');
        _countdownTimer = setInterval(() => {
            _countdownVal--;
            if (_countEl) _countEl.textContent = _countdownVal;
            if (_countdownVal <= 0) {
                clearInterval(_countdownTimer);
                window.location.href = '../logout.php?timeout=1';
            }
        }, 1000);
    }

    // Reset timer on any user activity
    ['mousemove','keydown','click','scroll','touchstart'].forEach(evt => {
        document.addEventListener(evt, resetSessionTimer, { passive: true });
    });

    // Start the timer
    resetSessionTimer();

    let _keepAliveTimer;
    function pingKeepAlive() {
        fetch('admin.php?keepalive=1', {
            method: 'GET',
            credentials: 'same-origin',
            cache: 'no-store'
        }).catch(() => {});
    }

    function scheduleKeepAlive() {
        clearTimeout(_keepAliveTimer);
        _keepAliveTimer = setTimeout(() => {
            pingKeepAlive();
            scheduleKeepAlive();
        }, 120000);
    }

    ['mousemove','keydown','click','scroll','touchstart'].forEach(evt => {
        document.addEventListener(evt, scheduleKeepAlive, { passive: true });
    });

    scheduleKeepAlive();
    </script>
</body>
</html>
<?php ob_end_flush(); ?>
