<?php
if (session_status() === PHP_SESSION_NONE) session_start();

$loginError = '';

// This is a simple hardcoded login for demo purposes.
// In production, replace with a proper user store and hashed passwords.
$validUsers = [
    'admin' => password_hash('admin123', PASSWORD_DEFAULT),
    'teacher' => password_hash('teacher123', PASSWORD_DEFAULT),
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $user = trim($_POST['username'] ?? '');
    $pass = $_POST['password'] ?? '';

    if ($user && isset($validUsers[$user]) && password_verify($pass, $validUsers[$user])) {
        $_SESSION['logged_in'] = true;
        $_SESSION['username'] = $user;
        // Redirect after login to avoid form re-submission
        header('Location: admin.php');
        exit;
    }

    $loginError = 'Invalid username or password.';
}

$loggedIn = !empty($_SESSION['logged_in']);

if (!$loggedIn && !empty($_GET['login_required'])) {
    $loginError = 'Please log in to access the admin dashboard.';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard | DRANHS Portal</title>
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

    <?php if (!\$loggedIn): ?>
        <div class="min-h-screen flex items-center justify-center bg-slate-50 p-4">
            <div class="w-full max-w-md bg-white rounded-3xl shadow-xl p-8">
                <h1 class="text-3xl font-heading font-black text-dranhs-dark mb-4 text-center">Admin Login</h1>
                <?php if (!empty(\$loginError)): ?>
                    <div class="mb-4 text-sm text-red-700 bg-red-100 border border-red-200 rounded-lg px-4 py-3">
                        <?= htmlspecialchars(\$loginError) ?>
                    </div>
                <?php endif; ?>
                <form action="admin.php" method="POST" class="space-y-4">
                    <div>
                        <label class="block text-sm font-bold text-slate-600 mb-1" for="username">Username</label>
                        <input id="username" name="username" type="text" required class="w-full px-4 py-3 border border-slate-200 rounded-xl focus:border-dranhs-green focus:ring-dranhs-green/30 outline-none" />
                    </div>
                    <div>
                        <label class="block text-sm font-bold text-slate-600 mb-1" for="password">Password</label>
                        <input id="password" name="password" type="password" required class="w-full px-4 py-3 border border-slate-200 rounded-xl focus:border-dranhs-green focus:ring-dranhs-green/30 outline-none" />
                    </div>
                    <button type="submit" class="w-full bg-dranhs-green hover:bg-emerald-700 text-white py-3 rounded-xl font-bold text-sm transition">Sign In</button>
                </form>
                <p class="text-xs text-slate-500 mt-4 text-center">Use <span class="font-bold">admin/admin123</span> or <span class="font-bold">teacher/teacher123</span></p>
            </div>
        </div>
        <?php exit; ?>
    <?php endif; ?>

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
            <span class="font-heading font-bold text-lg tracking-wide">DRANHS<span class="text-dranhs-green">PORTAL</span></span>
        </div>

        <!-- Navigation -->
        <div class="flex-1 overflow-y-auto sidebar-scroll py-6 px-3 space-y-1">
            
            <!-- Dashboard -->
            <a href="#" class="flex items-center gap-3 px-3 py-2.5 rounded-lg bg-dranhs-green text-white shadow-md group transition-all">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2V6zM14 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2V6zM4 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2v-2zM14 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2v-2z"></path></svg>
                <span class="font-medium text-sm">Dashboard</span>
            </a>

            <!-- Section Label -->
            <div class="px-3 mt-6 mb-2 text-xs font-bold text-slate-500 uppercase tracking-wider">Academic Management</div>

            <!-- Student -->
            <a href="#" class="flex items-center gap-3 px-3 py-2.5 rounded-lg text-slate-300 hover:bg-slate-800 hover:text-white transition-colors group">
                <svg class="w-5 h-5 text-slate-400 group-hover:text-white transition-colors" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"></path></svg>
                <span class="font-medium text-sm">Student</span>
            </a>

            <!-- Classroom -->
            <a href="#" class="flex items-center gap-3 px-3 py-2.5 rounded-lg text-slate-300 hover:bg-slate-800 hover:text-white transition-colors group">
                <svg class="w-5 h-5 text-slate-400 group-hover:text-white transition-colors" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 14v3m4-3v3m4-3v3M3 21h18M3 10h18M3 7l9-4 9 4M4 10h16v11H4V10z"></path></svg>
                <span class="font-medium text-sm">Classroom</span>
            </a>

            <!-- Evaluation -->
            <a href="#" class="flex items-center gap-3 px-3 py-2.5 rounded-lg text-slate-300 hover:bg-slate-800 hover:text-white transition-colors group">
                <svg class="w-5 h-5 text-slate-400 group-hover:text-white transition-colors" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-3 7h3m-3 4h3m-6-4h.01M9 16h.01"></path></svg>
                <span class="font-medium text-sm">Evaluation</span>
            </a>

            <!-- Encode -->
            <a href="#" class="flex items-center gap-3 px-3 py-2.5 rounded-lg text-slate-300 hover:bg-slate-800 hover:text-white transition-colors group">
                <svg class="w-5 h-5 text-slate-400 group-hover:text-white transition-colors" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path></svg>
                <span class="font-medium text-sm">Encode Grades</span>
            </a>

            <!-- List -->
            <a href="#" class="flex items-center gap-3 px-3 py-2.5 rounded-lg text-slate-300 hover:bg-slate-800 hover:text-white transition-colors group">
                <svg class="w-5 h-5 text-slate-400 group-hover:text-white transition-colors" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 10h16M4 14h16M4 18h16"></path></svg>
                <span class="font-medium text-sm">Master List</span>
            </a>

            <!-- Section Label -->
            <div class="px-3 mt-6 mb-2 text-xs font-bold text-slate-500 uppercase tracking-wider">System & Logs</div>

            <!-- Accounts -->
            <a href="#" class="flex items-center gap-3 px-3 py-2.5 rounded-lg text-slate-300 hover:bg-slate-800 hover:text-white transition-colors group">
                <svg class="w-5 h-5 text-slate-400 group-hover:text-white transition-colors" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 7a2 2 0 012 2m4 0a6 6 0 01-7.743 5.743L11 17H9v2H7v2H4a1 1 0 01-1-1v-2.586a1 1 0 01.293-.707l5.964-5.964A6 6 0 1121 9z"></path></svg>
                <span class="font-medium text-sm">Accounts</span>
            </a>

            <!-- Logs -->
            <a href="#" class="flex items-center gap-3 px-3 py-2.5 rounded-lg text-slate-300 hover:bg-slate-800 hover:text-white transition-colors group">
                <svg class="w-5 h-5 text-slate-400 group-hover:text-white transition-colors" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path></svg>
                <span class="font-medium text-sm">Logs</span>
            </a>

            <!-- System Settings -->
            <a href="#" class="flex items-center gap-3 px-3 py-2.5 rounded-lg text-slate-300 hover:bg-slate-800 hover:text-white transition-colors group">
                <svg class="w-5 h-5 text-slate-400 group-hover:text-white transition-colors" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"></path><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path></svg>
                <span class="font-medium text-sm">System Settings</span>
            </a>
        </div>

        <!-- User Info Footer -->
        <div class="p-4 border-t border-slate-700 bg-[#151b29]">
            <div class="flex items-center gap-3">
                <img src="https://ui-avatars.com/api/?name=Teacher+Admin&background=009b5a&color=fff" alt="Admin" class="w-10 h-10 rounded-full bg-slate-600">
                <div class="flex-1 min-w-0">
                    <p class="text-sm font-bold text-white truncate">Teacher Admin</p>
                    <p class="text-xs text-slate-400 truncate">Faculty Dept.</p>
                </div>
                <a href="logout.php" class="text-slate-400 hover:text-white transition-colors" title="Logout">
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
                <h1 class="text-xl font-heading font-bold text-dranhs-dark hidden sm:block">Dashboard Overview</h1>
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
            
            <!-- Welcome Banner -->
            <div class="bg-gradient-to-r from-dranhs-dark to-slate-800 rounded-2xl p-6 lg:p-10 mb-8 text-white relative overflow-hidden shadow-lg">
                <div class="absolute right-0 top-0 h-full w-1/2 opacity-10 bg-[url('https://www.transparenttextures.com/patterns/cubes.png')]"></div>
                <div class="relative z-10">
                    <h2 class="text-3xl font-heading font-black mb-2">Welcome back, Teacher!</h2>
                    <p class="text-slate-300 max-w-xl">You have 4 pending evaluations and 2 new student enrollment requests to review today.</p>
                    <div class="mt-6 flex gap-3">
                        <button class="bg-dranhs-green hover:bg-emerald-600 text-white px-5 py-2.5 rounded-lg font-bold text-sm transition-colors shadow-lg shadow-emerald-900/20">View Enrollments</button>
                        <button class="bg-white/10 hover:bg-white/20 text-white px-5 py-2.5 rounded-lg font-bold text-sm transition-colors backdrop-blur-sm">System Logs</button>
                    </div>
                </div>
            </div>

            <!-- Stats Grid -->
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
                <!-- Card 1 -->
                <div class="bg-white p-6 rounded-xl shadow-sm border border-slate-100 flex items-start justify-between group hover:border-dranhs-green transition-colors">
                    <div>
                        <p class="text-xs font-bold text-slate-400 uppercase tracking-wider mb-1">Total Students</p>
                        <h3 class="text-3xl font-heading font-black text-dranhs-dark">2,450</h3>
                        <p class="text-xs text-green-500 font-bold mt-2 flex items-center">
                            <svg class="w-3 h-3 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6"></path></svg>
                            +12% from last sem
                        </p>
                    </div>
                    <div class="p-3 bg-blue-50 text-blue-600 rounded-lg group-hover:bg-blue-100 transition-colors">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0z"></path></svg>
                    </div>
                </div>

                <!-- Card 2 -->
                <div class="bg-white p-6 rounded-xl shadow-sm border border-slate-100 flex items-start justify-between group hover:border-purple-500 transition-colors">
                    <div>
                        <p class="text-xs font-bold text-slate-400 uppercase tracking-wider mb-1">Active Classrooms</p>
                        <h3 class="text-3xl font-heading font-black text-dranhs-dark">48</h3>
                        <p class="text-xs text-slate-400 font-bold mt-2">Active Sessions</p>
                    </div>
                    <div class="p-3 bg-purple-50 text-purple-600 rounded-lg group-hover:bg-purple-100 transition-colors">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"></path></svg>
                    </div>
                </div>

                <!-- Card 3 -->
                <div class="bg-white p-6 rounded-xl shadow-sm border border-slate-100 flex items-start justify-between group hover:border-amber-500 transition-colors">
                    <div>
                        <p class="text-xs font-bold text-slate-400 uppercase tracking-wider mb-1">Pending Requests</p>
                        <h3 class="text-3xl font-heading font-black text-dranhs-dark">15</h3>
                        <p class="text-xs text-amber-500 font-bold mt-2">Requires Action</p>
                    </div>
                    <div class="p-3 bg-amber-50 text-amber-600 rounded-lg group-hover:bg-amber-100 transition-colors">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4"></path></svg>
                    </div>
                </div>

                <!-- Card 4 -->
                <div class="bg-white p-6 rounded-xl shadow-sm border border-slate-100 flex items-start justify-between group hover:border-pink-500 transition-colors">
                    <div>
                        <p class="text-xs font-bold text-slate-400 uppercase tracking-wider mb-1">System Status</p>
                        <h3 class="text-3xl font-heading font-black text-dranhs-dark">98%</h3>
                        <p class="text-xs text-green-500 font-bold mt-2">Operational</p>
                    </div>
                    <div class="p-3 bg-pink-50 text-pink-500 rounded-lg group-hover:bg-pink-100 transition-colors">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 12h14M5 12a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v4a2 2 0 01-2 2M5 12a2 2 0 00-2 2v4a2 2 0 002 2h14a2 2 0 002-2v-4a2 2 0 00-2-2m-2-4h.01M17 16h.01"></path></svg>
                    </div>
                </div>
            </div>

            <!-- Recent Activity Section -->
            <div class="bg-white rounded-xl shadow-sm border border-slate-100 overflow-hidden">
                <div class="px-6 py-4 border-b border-slate-100 flex justify-between items-center">
                    <h3 class="font-heading font-bold text-lg text-dranhs-dark">Recent System Logs</h3>
                    <button class="text-xs font-bold text-dranhs-green hover:underline uppercase tracking-wide">View All</button>
                </div>
                <div class="overflow-x-auto">
                    <table class="w-full text-left border-collapse">
                        <thead class="bg-slate-50 text-xs uppercase text-slate-500 font-bold">
                            <tr>
                                <th class="px-6 py-3 tracking-wider">User</th>
                                <th class="px-6 py-3 tracking-wider">Action</th>
                                <th class="px-6 py-3 tracking-wider">Module</th>
                                <th class="px-6 py-3 tracking-wider">Timestamp</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 text-sm">
                            <tr class="hover:bg-slate-50 transition-colors">
                                <td class="px-6 py-4 font-semibold text-slate-700">Juan Dela Cruz</td>
                                <td class="px-6 py-4 text-slate-600">Enrolled Student 123456789012</td>
                                <td class="px-6 py-4"><span class="px-2 py-1 bg-green-100 text-green-700 rounded-full text-xs font-bold">Enrollment</span></td>
                                <td class="px-6 py-4 text-slate-400">2 mins ago</td>
                            </tr>
                            <tr class="hover:bg-slate-50 transition-colors">
                                <td class="px-6 py-4 font-semibold text-slate-700">Maria Santos</td>
                                <td class="px-6 py-4 text-slate-600">Updated Grade 11 Schedule</td>
                                <td class="px-6 py-4"><span class="px-2 py-1 bg-blue-100 text-blue-700 rounded-full text-xs font-bold">Classroom</span></td>
                                <td class="px-6 py-4 text-slate-400">15 mins ago</td>
                            </tr>
                            <tr class="hover:bg-slate-50 transition-colors">
                                <td class="px-6 py-4 font-semibold text-slate-700">Admin System</td>
                                <td class="px-6 py-4 text-slate-600">System Backup Completed</td>
                                <td class="px-6 py-4"><span class="px-2 py-1 bg-slate-100 text-slate-700 rounded-full text-xs font-bold">System</span></td>
                                <td class="px-6 py-4 text-slate-400">1 hr ago</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

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