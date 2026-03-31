<?php
if (basename($_SERVER['PHP_SELF']) == basename(__FILE__)) {
    header('Location: admin.php');
    exit;
}
?>

            <!-- Welcome Banner -->
            <div class="bg-gradient-to-r from-dranhs-dark to-slate-800 rounded-2xl p-6 lg:p-10 mb-8 text-white relative overflow-hidden shadow-lg">
                <div class="absolute right-0 top-0 h-full w-1/2 opacity-10 bg-[url('https://www.transparenttextures.com/patterns/cubes.png')]"></div>
                <div class="relative z-10">
                    <h2 class="text-3xl font-heading font-black mb-2">Welcome back, <?php echo htmlspecialchars(ucfirst($username)); ?>!</h2>
                    <p class="text-slate-300 max-w-xl">You have 4 pending evaluations and 2 new student enrollment requests to review today.</p>
                    <div class="mt-6 flex gap-3">
                        <a href="?page=student" class="bg-dranhs-green hover:bg-emerald-600 text-white px-5 py-2.5 rounded-lg font-bold text-sm transition-colors shadow-lg shadow-emerald-900/20">View Enrollments</a>
                        <a href="?page=logs" class="bg-white/10 hover:bg-white/20 text-white px-5 py-2.5 rounded-lg font-bold text-sm transition-colors backdrop-blur-sm">System Logs</a>
                    </div>
                </div>
            </div>

            <!-- Stats Grid -->
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
                <!-- Card 1 -->
                <a href="?page=student" class="bg-white p-6 rounded-xl shadow-sm border border-slate-100 flex items-start justify-between group hover:border-dranhs-green transition-colors">
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
                </a>

                <!-- Card 2 -->
                <a href="?page=classroom" class="bg-white p-6 rounded-xl shadow-sm border border-slate-100 flex items-start justify-between group hover:border-purple-500 transition-colors">
                    <div>
                        <p class="text-xs font-bold text-slate-400 uppercase tracking-wider mb-1">Active Classrooms</p>
                        <h3 class="text-3xl font-heading font-black text-dranhs-dark">48</h3>
                        <p class="text-xs text-slate-400 font-bold mt-2">Active Sessions</p>
                    </div>
                    <div class="p-3 bg-purple-50 text-purple-600 rounded-lg group-hover:bg-purple-100 transition-colors">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"></path></svg>
                    </div>
                </a>

                <!-- Card 3 -->
                <a href="?page=evaluation" class="bg-white p-6 rounded-xl shadow-sm border border-slate-100 flex items-start justify-between group hover:border-amber-500 transition-colors">
                    <div>
                        <p class="text-xs font-bold text-slate-400 uppercase tracking-wider mb-1">Pending Requests</p>
                        <h3 class="text-3xl font-heading font-black text-dranhs-dark">15</h3>
                        <p class="text-xs text-amber-500 font-bold mt-2">Requires Action</p>
                    </div>
                    <div class="p-3 bg-amber-50 text-amber-600 rounded-lg group-hover:bg-amber-100 transition-colors">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4"></path></svg>
                    </div>
                </a>

                <!-- Card 4 -->
                <a href="?page=system_settings" class="bg-white p-6 rounded-xl shadow-sm border border-slate-100 flex items-start justify-between group hover:border-pink-500 transition-colors">
                    <div>
                        <p class="text-xs font-bold text-slate-400 uppercase tracking-wider mb-1">System Status</p>
                        <h3 class="text-3xl font-heading font-black text-dranhs-dark">98%</h3>
                        <p class="text-xs text-green-500 font-bold mt-2">Operational</p>
                    </div>
                    <div class="p-3 bg-pink-50 text-pink-500 rounded-lg group-hover:bg-pink-100 transition-colors">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 12h14M5 12a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v4a2 2 0 01-2 2M5 12a2 2 0 00-2 2v4a2 2 0 002 2h14a2 2 0 002-2v-4a2 2 0 00-2-2m-2-4h.01M17 16h.01"></path></svg>
                    </div>
                </a>
            </div>

            <!-- Recent Activity Section -->
            <div class="bg-white rounded-xl shadow-sm border border-slate-100 overflow-hidden">
                <div class="px-6 py-4 border-b border-slate-100 flex justify-between items-center">
                    <h3 class="font-heading font-bold text-lg text-dranhs-dark">Recent System Logs</h3>
                    <a href="?page=logs" class="text-xs font-bold text-dranhs-green hover:underline uppercase tracking-wide">View All</a>
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
