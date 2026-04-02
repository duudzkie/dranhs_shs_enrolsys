<?php
if (session_status() === PHP_SESSION_NONE) session_start();
// Check if user is actually logged in
if (!isset($_SESSION['user_id']) || !isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    header('Location: ../login.php');
    exit;
}
$loggedIn = true;
$userRole = $_SESSION['role'] ?? 'admin';
$username = $_SESSION['username'] ?? 'User';


// Simple routing logic
$page = isset($_GET['page']) ? $_GET['page'] : 'dashboard';
$page_title = ucfirst($page);

// Whitelist of allowed pages
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
            <div class="w-8 h-8 rounded-full bg-dranhs-green flex items-center justify-center shrink-0">
                <!-- Simple Logo Icon -->
                <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"></path></svg>
            </div>
            <span class="font-heading font-bold text-lg tracking-wide">DRANHS<span class="text-dranhs-green"> SMARTENROLL</span></span>
        </div>

        <!-- Navigation -->
        <div class="flex-1 overflow-y-auto sidebar-scroll py-6 px-3 space-y-1">
            
            <!-- Dashboard -->
            <a href="?page=dashboard" class="flex items-center gap-3 px-3 py-2.5 rounded-lg group transition-all <?php echo ($page === 'dashboard') ? $activeClasses : $inactiveClasses; ?>">
                <svg class="w-5 h-5 <?php echo ($page === 'dashboard') ? '' : 'text-slate-400 group-hover:text-white'; ?>" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2V6zM14 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2V6zM4 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2v-2zM14 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2v-2z"></path></svg>
                <span class="font-medium text-sm">Dashboard</span>
            </a>

            <!-- Section Label -->
            <div class="px-3 mt-6 mb-2 text-xs font-bold text-slate-500 uppercase tracking-wider">Academic Management</div>

            <!-- Student -->
            <a href="?page=student" class="flex items-center gap-3 px-3 py-2.5 rounded-lg group transition-colors <?php echo ($page === 'student') ? $activeClasses : $inactiveClasses; ?>">
                <svg class="w-5 h-5 text-slate-400 group-hover:text-white transition-colors" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"></path></svg>
                <span class="font-medium text-sm">Student</span>
            </a>

            <!-- Classroom -->
            <a href="?page=classroom" class="flex items-center gap-3 px-3 py-2.5 rounded-lg group transition-colors <?php echo ($page === 'classroom') ? $activeClasses : $inactiveClasses; ?>">
                <svg class="w-5 h-5 text-slate-400 group-hover:text-white transition-colors" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 14v3m4-3v3m4-3v3M3 21h18M3 10h18M3 7l9-4 9 4M4 10h16v11H4V10z"></path></svg>
                <span class="font-medium text-sm">Classroom</span>
            </a>

            <!-- Evaluation -->
            <a href="?page=evaluation" class="flex items-center gap-3 px-3 py-2.5 rounded-lg group transition-colors <?php echo ($page === 'evaluation') ? $activeClasses : $inactiveClasses; ?>">
                <svg class="w-5 h-5 text-slate-400 group-hover:text-white transition-colors" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-3 7h3m-3 4h3m-6-4h.01M9 16h.01"></path></svg>
                <span class="font-medium text-sm">Evaluation</span>
            </a>

            <!-- Encode -->
            <a href="?page=encode" class="flex items-center gap-3 px-3 py-2.5 rounded-lg group transition-colors <?php echo ($page === 'encode') ? $activeClasses : $inactiveClasses; ?>">
                <svg class="w-5 h-5 text-slate-400 group-hover:text-white transition-colors" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path></svg>
                <span class="font-medium text-sm">Encode Grades</span>
            </a>

            <!-- List -->
            <a href="?page=list" class="flex items-center gap-3 px-3 py-2.5 rounded-lg group transition-colors <?php echo ($page === 'list') ? $activeClasses : $inactiveClasses; ?>">
                <svg class="w-5 h-5 text-slate-400 group-hover:text-white transition-colors" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 10h16M4 14h16M4 18h16"></path></svg>
                <span class="font-medium text-sm">Master List</span>
            </a>

            <!-- Section Label -->
            <div class="px-3 mt-6 mb-2 text-xs font-bold text-slate-500 uppercase tracking-wider">Section</div>

            <!-- Accounts -->
            <a href="?page=account" class="flex items-center gap-3 px-3 py-2.5 rounded-lg group transition-colors <?php echo ($page === 'account') ? $activeClasses : $inactiveClasses; ?>">
                <svg class="w-5 h-5 text-slate-400 group-hover:text-white transition-colors" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 7a2 2 0 012 2m4 0a6 6 0 01-7.743 5.743L11 17H9v2H7v2H4a1 1 0 01-1-1v-2.586a1 1 0 01.293-.707l5.964-5.964A6 6 0 1121 9z"></path></svg>
                <span class="font-medium text-sm">Accounts</span>
            </a>

            <!-- Logs -->
            <a href="?page=logs" class="flex items-center gap-3 px-3 py-2.5 rounded-lg group transition-colors <?php echo ($page === 'logs') ? $activeClasses : $inactiveClasses; ?>">
                <svg class="w-5 h-5 text-slate-400 group-hover:text-white transition-colors" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path></svg>
                <span class="font-medium text-sm">Logs</span>
            </a>

            <!-- System Settings -->
            <a href="?page=system_settings" class="flex items-center gap-3 px-3 py-2.5 rounded-lg group transition-colors <?php echo ($page === 'system_settings') ? $activeClasses : $inactiveClasses; ?>">
                <svg class="w-5 h-5 text-slate-400 group-hover:text-white transition-colors" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"></path><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path></svg>
                <span class="font-medium text-sm">System Settings</span>
            </a>
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
                    $page_path = $page . '.php';
                    if (file_exists($page_path)) {
                        include $page_path;
                    } else {
                        echo '<div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative" role="alert">';
                        echo '<strong class="font-bold">Error:</strong>';
                        echo '<span class="block sm:inline"> File not found: ' . $page_path . '</span>';
                        echo '</div>';
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
</body>
</html>