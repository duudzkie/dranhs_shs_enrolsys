<!-- Footer (components/footer.php) -->
<footer class="w-full bg-white/95 backdrop-blur-sm fixed bottom-0 left-0 border-t border-slate-200 flex flex-col lg:flex-row justify-between items-center py-3 px-4 lg:px-8 z-40 gap-3 lg:gap-0 lg:h-[70px] shadow-[0_-2px_10px_rgba(0,0,0,0.02)]">
    <div class="<?php echo isset($hide_footer_buttons) && $hide_footer_buttons ? 'hidden' : 'flex'; ?> flex-row gap-2 lg:gap-3 w-full lg:w-auto justify-center lg:justify-start">
        <button class="flex-1 lg:flex-none bg-white border border-slate-200 text-slate-700 py-2 px-3 lg:py-2.5 lg:px-5 rounded-full font-bold text-[0.7rem] lg:text-xs flex items-center justify-center gap-1.5 lg:gap-2 cursor-pointer transition-colors hover:bg-slate-50 hover:text-dranhs-dark shadow-sm uppercase tracking-wide">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" class="hidden sm:block lg:w-4 lg:h-4">
                <circle cx="11" cy="11" r="8"></circle>
                <line x1="21" y1="21" x2="16.65" y2="16.65"></line>
            </svg>
            <span class="hidden sm:inline">CHECK STATUS</span>
            <span class="sm:hidden">STATUS</span>
        </button>
        <button class="flex-1 lg:flex-none bg-white border border-slate-200 text-slate-700 py-2 px-3 lg:py-2.5 lg:px-5 rounded-full font-bold text-[0.7rem] lg:text-xs flex items-center justify-center gap-1.5 lg:gap-2 cursor-pointer transition-colors hover:bg-slate-50 hover:text-dranhs-dark shadow-sm uppercase tracking-wide">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" class="hidden sm:block lg:w-4 lg:h-4">
                <path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"></path>
                <circle cx="12" cy="10" r="3"></circle>
            </svg>
            <span class="hidden sm:inline">ROOM LOCATOR</span>
            <span class="sm:hidden">ROOMS</span>
        </button>
    </div>
    <div class="text-center lg:text-right w-full lg:w-auto text-slate-500 text-[0.65rem] lg:text-[0.7rem] font-bold uppercase tracking-widest pb-1 lg:pb-0">
        &copy; 2026 Daniel R. Aguinaldo National High School, all rights reserved
    </div>
</footer>
