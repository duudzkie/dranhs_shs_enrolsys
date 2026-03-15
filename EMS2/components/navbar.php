<!-- Top Navbar (components/navbar.php) -->
<nav class="w-full bg-white/95 dark:bg-slate-900/95 backdrop-blur-sm border-b border-slate-200 dark:border-slate-800 py-3 px-4 lg:px-8 z-40 flex flex-col lg:flex-row justify-between items-center gap-3 lg:gap-0 fixed top-0 left-0 shadow-sm transition-colors duration-300">
    <a href="index.php" class="flex items-center gap-4 w-full justify-start lg:w-auto hover:opacity-80 transition-opacity">
        <div class="w-10 h-10 lg:w-12 lg:h-12 rounded-full border border-slate-200 dark:border-slate-700 flex justify-center items-center overflow-hidden relative shadow-sm bg-white dark:bg-slate-800 shrink-0">
            <!-- You can replace the src attribute with your actual logo image file path -->
            <img src="https://ui-avatars.com/api/?name=DR&background=009b5a&color=fff&size=128" alt="School Logo" class="w-full h-full object-cover z-20" />
        </div>
        
        <div class="flex flex-col">
            <span class="text-[1.1rem] lg:text-xl font-black text-dranhs-dark dark:text-white tracking-tight leading-none uppercase">DRANHS SMARTENROLL</span>
            <span class="text-[0.65rem] lg:text-xs font-black text-dranhs-green dark:text-emerald-400 uppercase mt-1 tracking-wider">Matina Crossing, Davao City</span>
        </div>
    </a>
    
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

        <form class="flex flex-row items-center gap-2 w-full max-w-md lg:w-auto lg:max-w-none" action="#" method="POST" onsubmit="event.preventDefault()">
            <input type="text" name="username" placeholder="Username" required class="w-1/3 lg:w-[150px] bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 px-3 py-2 lg:px-4 rounded-full text-slate-800 dark:text-white text-sm outline-none transition-all focus:border-dranhs-green dark:focus:border-emerald-500 focus:ring-2 focus:ring-dranhs-green/20 placeholder-slate-400 dark:placeholder-slate-500 font-medium shadow-sm">
            <input type="password" name="password" placeholder="Password" required class="w-1/3 lg:w-[150px] bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 px-3 py-2 lg:px-4 rounded-full text-slate-800 dark:text-white text-sm outline-none transition-all focus:border-dranhs-green dark:focus:border-emerald-500 focus:ring-2 focus:ring-dranhs-green/20 placeholder-slate-400 dark:placeholder-slate-500 font-medium shadow-sm">
            <button type="submit" class="flex-1 lg:flex-none bg-dranhs-green hover:bg-emerald-700 text-white border-none px-4 py-2 rounded-full font-bold text-sm cursor-pointer transition-transform shadow-md hover:-translate-y-0.5 whitespace-nowrap text-center">LOGIN</button>
        </form>
    </div>
</nav>
