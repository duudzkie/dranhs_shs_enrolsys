<?php
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/../db.php';

// Load theme settings
$_nav_logo = null;
$_nav_theme = [];
$_nav_conn = @new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME, DB_PORT);
if (!$_nav_conn->connect_error) {
    $_nav_res = $_nav_conn->query("SELECT setting_key, setting_value FROM system_settings WHERE setting_key IN ('school_logo','background','deped_logo','division_logo')");
    if ($_nav_res) { while ($_nr = $_nav_res->fetch_assoc()) $_nav_theme[$_nr['setting_key']] = $_nr['setting_value']; }
    $_nav_conn->close();
}
$_nav_logo     = !empty($_nav_theme['school_logo'])    ? $_nav_theme['school_logo']    : null;
$_nav_bg       = !empty($_nav_theme['background'])     ? $_nav_theme['background']     : null;
$_nav_deped    = !empty($_nav_theme['deped_logo'])     ? $_nav_theme['deped_logo']     : null;
$_nav_division = !empty($_nav_theme['division_logo'])  ? $_nav_theme['division_logo']  : null;
?>

<!-- Top Navbar (components/navbar.php) -->
<nav class="w-full bg-white/95 dark:bg-slate-900/95 backdrop-blur-sm border-b border-slate-200 dark:border-slate-800 py-3 px-4 lg:px-8 z-40 flex flex-col lg:flex-row justify-between items-center gap-3 lg:gap-0 fixed top-0 left-0 shadow-sm transition-colors duration-300">
    <div class="flex items-center gap-0">
        <a href="index.php" class="flex items-center gap-4 w-full justify-start lg:w-auto hover:opacity-80 transition-opacity">
            <div class="w-10 h-10 lg:w-12 lg:h-12 rounded-full border border-slate-200 dark:border-slate-700 flex justify-center items-center overflow-hidden relative shadow-sm bg-white dark:bg-slate-800 shrink-0">
                <?php if ($_nav_logo): ?>
                    <img src="<?php echo htmlspecialchars($_nav_logo); ?>" alt="School Logo" class="w-full h-full object-contain z-20" />
                <?php else: ?>
                    <img src="https://ui-avatars.com/api/?name=DR&background=009b5a&color=fff&size=128" alt="School Logo" class="w-full h-full object-cover z-20" />
                <?php endif; ?>
            </div>
            
            <div class="flex flex-col">
                <span class="text-[1.1rem] lg:text-xl font-black text-dranhs-dark dark:text-white tracking-tight leading-none uppercase">DRANHS SMARTENROLL</span>
            </div>
        </a>
    </div>
    
    <div class="hidden lg:flex items-center justify-center w-full lg:w-auto gap-4 lg:gap-6 mt-1 lg:mt-0">
        <!-- Dark Mode Toggle -->
        <button id="dark-mode-toggle" class="hidden lg:flex text-slate-500 hover:text-slate-800 dark:text-slate-400 dark:hover:text-amber-300 transition-colors">
            <svg id="moon-icon" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="block dark:hidden">
                <path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z"></path>
            </svg>
            <svg id="sun-icon" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="hidden dark:block">
                <circle cx="12" cy="12" r="5"></circle>
                <line x1="12" y1="1" x2="12" y2="3"></line>
                <line x1="12" y1="21" x2="12" y2="23"></line>
                <line x1="4.22" y1="4.22" x2="5.64" y2="5.64"></line>
                <line x1="18.36" y1="18.36" x2="19.78" y2="19.78"></line>
                <line x1="1" y1="12" x2="3" y2="12"></line>
                <line x1="21" y1="12" x2="23" y2="12"></line>
                <line x1="4.22" y1="19.78" x2="5.64" y2="18.36"></line>
                <line x1="18.36" y1="5.64" x2="19.78" y2="4.22"></line>
            </svg>
        </button>

        <div class="relative flex flex-row items-center gap-2 w-full max-w-md lg:w-auto lg:max-w-none">
            <form id="nav-login-form" class="flex flex-row items-center gap-2" action="login.php" method="POST">
                <input type="text" name="username" id="nav-username" placeholder="Username" required
                    class="w-1/3 lg:w-[150px] bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 px-3 py-2 lg:px-4 rounded-full text-slate-800 dark:text-white text-sm outline-none transition-all focus:border-dranhs-green dark:focus:border-emerald-500 focus:ring-2 focus:ring-dranhs-green/20 placeholder-slate-400 dark:placeholder-slate-500 font-medium shadow-sm <?php echo isset($_GET['auth_error']) ? 'border-red-400 dark:border-red-500' : ''; ?>">
                <input type="password" name="password" id="nav-password" placeholder="Password" required
                    class="w-1/3 lg:w-[150px] bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 px-3 py-2 lg:px-4 rounded-full text-slate-800 dark:text-white text-sm outline-none transition-all focus:border-dranhs-green dark:focus:border-emerald-500 focus:ring-2 focus:ring-dranhs-green/20 placeholder-slate-400 dark:placeholder-slate-500 font-medium shadow-sm <?php echo isset($_GET['auth_error']) ? 'border-red-400 dark:border-red-500' : ''; ?>">
                <button type="submit" class="flex-1 lg:flex-none bg-dranhs-green hover:bg-emerald-700 text-white border-none px-4 py-2 rounded-full font-bold text-sm cursor-pointer transition-transform shadow-md hover:-translate-y-0.5 whitespace-nowrap text-center">LOGIN</button>
            </form>

            <?php if (isset($_GET['auth_error']) || isset($_GET['timeout'])): ?>
            <div id="nav-login-error" class="absolute top-full right-0 mt-2 z-50 flex items-center gap-2 bg-red-50 dark:bg-red-900/80 border border-red-200 dark:border-red-700 text-red-700 dark:text-red-300 text-xs font-bold px-4 py-2.5 rounded-xl shadow-lg whitespace-nowrap">
                <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z"/></svg>
                <?php
                if (isset($_GET['timeout'])) {
                    echo 'Session expired due to inactivity. Please log in again.';
                } else {
                    $err_code = $_GET['auth_error'] ?? '';
                    echo $err_code === 'user' ? 'Username not found.' : 'Incorrect password.';
                }
                ?>
                <span class="absolute -top-1.5 right-6 w-3 h-3 bg-red-50 dark:bg-red-900/80 border-l border-t border-red-200 dark:border-red-700 rotate-45"></span>
            </div>
            <script>
                // Auto-dismiss after 4 seconds
                setTimeout(() => {
                    const el = document.getElementById('nav-login-error');
                    if (el) el.style.display = 'none';
                }, 4000);
            </script>
            <?php endif; ?>
        </div>
    </div>
</nav>
