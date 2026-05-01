<!-- Footer (components/footer.php) -->
<footer class="w-full bg-white/95 backdrop-blur-sm fixed bottom-0 left-0 border-t border-slate-200 flex flex-col lg:flex-row justify-between items-center py-3 px-4 lg:px-8 z-40 gap-3 lg:gap-0 lg:h-[70px] shadow-[0_-2px_10px_rgba(0,0,0,0.02)]">
    <div class="<?php echo isset($hide_footer_buttons) && $hide_footer_buttons ? 'hidden' : 'flex'; ?> flex-row gap-2 lg:gap-3 w-full lg:w-auto justify-center lg:justify-start">
        <button id="check-status-btn" class="flex-1 lg:flex-none bg-white border border-slate-200 text-slate-700 py-2 px-3 lg:py-2.5 lg:px-5 rounded-full font-bold text-[0.7rem] lg:text-xs flex items-center justify-center gap-1.5 lg:gap-2 cursor-pointer transition-colors hover:bg-slate-50 hover:text-dranhs-dark shadow-sm uppercase tracking-wide">
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

<!-- ══════════════════════════════════════════════
     CHECK STATUS MODAL
══════════════════════════════════════════════ -->
<div id="check-status-modal" class="fixed inset-0 z-[200] hidden items-center justify-center p-4" style="background:rgba(15,23,42,0.65);backdrop-filter:blur(4px);">
    <div class="w-full max-w-md bg-white rounded-3xl shadow-2xl overflow-hidden flex flex-col" style="max-height:92vh;">

        <!-- Modal Header -->
        <div class="flex items-center gap-4 px-6 py-5 border-b border-slate-100 bg-gradient-to-r from-emerald-50 to-white">
            <div class="w-12 h-12 rounded-2xl bg-dranhs-green/10 flex items-center justify-center shrink-0">
                <svg class="w-6 h-6 text-dranhs-green" fill="none" stroke="currentColor" viewBox="0 0 24 24"><circle cx="11" cy="11" r="8" stroke-width="2"/><line x1="21" y1="21" x2="16.65" y2="16.65" stroke-width="2"/></svg>
            </div>
            <div class="flex-1">
                <h2 class="font-heading font-black text-lg text-dranhs-dark tracking-tight">RECORD LOOKUP</h2>
                <p class="text-xs text-slate-400 font-semibold uppercase tracking-widest">Enrollment Status Tracking</p>
            </div>
            <button id="check-status-close" class="w-9 h-9 rounded-full bg-slate-100 hover:bg-slate-200 flex items-center justify-center text-slate-500 transition-colors text-lg font-bold">&times;</button>
        </div>

        <!-- Search Input -->
        <div class="px-6 pt-5 pb-4">
            <div class="flex gap-2">
                <div class="relative flex-1">
                    <input type="text" id="cs-lrn-input" maxlength="12" placeholder="Enter your LRN (12 digits)"
                        class="w-full border-2 border-slate-200 rounded-xl px-4 py-3 text-sm font-semibold text-slate-800 focus:border-dranhs-green focus:ring-2 focus:ring-dranhs-green/20 outline-none transition-all placeholder-slate-400"
                        inputmode="numeric" pattern="\d*">
                </div>
                <button id="cs-search-btn"
                    class="px-5 py-3 rounded-xl bg-dranhs-green text-white text-sm font-bold hover:bg-emerald-700 transition-colors shrink-0 flex items-center gap-2">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><circle cx="11" cy="11" r="8" stroke-width="2.5"/><line x1="21" y1="21" x2="16.65" y2="16.65" stroke-width="2.5"/></svg>
                    Search
                </button>
            </div>
            <div id="cs-error" class="hidden mt-2 text-xs text-red-600 font-semibold px-1"></div>
        </div>

        <!-- Result area -->
        <div id="cs-result" class="hidden flex-1 overflow-y-auto px-6 pb-6 space-y-4">

            <!-- Student identity card -->
            <div class="flex items-center gap-4 p-4 bg-slate-50 rounded-2xl border border-slate-100">
                <img id="cs-photo" src="" alt="Photo"
                    class="w-20 h-20 rounded-2xl object-cover border-2 border-white shadow-md shrink-0">
                <div class="flex-1 min-w-0">
                    <h3 id="cs-name" class="font-heading font-black text-xl text-dranhs-dark leading-tight truncate"></h3>
                    <div class="flex flex-wrap gap-2 mt-2">
                        <span id="cs-lrn-badge" class="px-3 py-1 bg-white border border-slate-200 rounded-full text-xs font-bold text-slate-600 shadow-sm"></span>
                        <span id="cs-sy-badge" class="px-3 py-1 bg-white border border-slate-200 rounded-full text-xs font-bold text-slate-500 shadow-sm"></span>
                    </div>
                </div>
            </div>

            <!-- Enrollment workflow -->
            <div class="p-4 bg-white rounded-2xl border border-slate-100 shadow-sm">
                <div class="flex items-center justify-between mb-4">
                    <div class="flex items-center gap-2">
                        <svg class="w-4 h-4 text-dranhs-green" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/></svg>
                        <span class="text-xs font-black uppercase tracking-widest text-slate-500">Live Enrollment Workflow</span>
                    </div>
                    <span id="cs-status-badge" class="px-3 py-1 rounded-full text-xs font-black uppercase tracking-wide"></span>
                </div>

                <!-- Step tracker -->
                <div class="relative flex items-center justify-between px-2">
                    <!-- Line -->
                    <div class="absolute left-6 right-6 top-5 h-0.5 bg-slate-200 z-0"></div>
                    <div id="cs-progress-line" class="absolute left-6 top-5 h-0.5 bg-dranhs-green z-0 transition-all duration-500" style="width:0%"></div>

                    <?php
                    $steps = [
                        ['label' => 'Submitted',  'icon' => 'M5 13l4 4L19 7'],
                        ['label' => 'Reviewing',  'icon' => 'M5 13l4 4L19 7'],
                        ['label' => 'Cleared',    'icon' => 'M5 13l4 4L19 7'],
                        ['label' => 'Deployed',   'icon' => 'M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2'],
                    ];
                    foreach ($steps as $i => $s):
                    ?>
                    <div class="relative z-10 flex flex-col items-center gap-1.5" data-step="<?php echo $i+1; ?>">
                        <div class="cs-step-circle w-10 h-10 rounded-full border-2 flex items-center justify-center transition-all duration-300
                            border-slate-200 bg-white text-slate-300">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2.5">
                                <path stroke-linecap="round" stroke-linejoin="round" d="<?php echo $s['icon']; ?>"/>
                            </svg>
                        </div>
                        <span class="cs-step-label text-[9px] font-black uppercase tracking-widest text-slate-400"><?php echo $s['label']; ?></span>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <!-- Academic + Faculty cards -->
            <div class="grid grid-cols-2 gap-3">
                <!-- Academic Node -->
                <div class="p-4 bg-slate-50 rounded-2xl border border-slate-100">
                    <div class="flex items-center gap-2 mb-3">
                        <svg class="w-4 h-4 text-violet-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 14l9-5-9-5-9 5 9 5z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 14l6.16-3.422a12.083 12.083 0 01.665 6.479A11.952 11.952 0 0012 20.055a11.952 11.952 0 00-6.824-2.998 12.078 12.078 0 01.665-6.479L12 14z"/></svg>
                        <span class="text-[9px] font-black uppercase tracking-widest text-slate-400">Academic Node</span>
                    </div>
                    <div class="space-y-2">
                        <div>
                            <div class="text-[8px] font-black uppercase tracking-widest text-slate-400 mb-0.5">Pathway</div>
                            <div id="cs-pathway" class="text-xs font-black text-dranhs-dark leading-tight"></div>
                        </div>
                        <div class="flex gap-4">
                            <div>
                                <div class="text-[8px] font-black uppercase tracking-widest text-slate-400 mb-0.5">Level</div>
                                <div id="cs-grade" class="text-sm font-black text-dranhs-dark"></div>
                            </div>
                            <div>
                                <div class="text-[8px] font-black uppercase tracking-widest text-slate-400 mb-0.5">Section</div>
                                <div id="cs-section" class="text-xs font-black text-dranhs-green"></div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Faculty Advisory -->
                <div class="p-4 bg-slate-50 rounded-2xl border border-slate-100 flex flex-col">
                    <div class="flex items-center gap-2 mb-3">
                        <svg class="w-4 h-4 text-blue-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/></svg>
                        <span class="text-[9px] font-black uppercase tracking-widest text-slate-400">Faculty Advisory</span>
                    </div>
                    <div class="mb-0.5 text-[8px] font-black uppercase tracking-widest text-slate-400">Adviser</div>
                    <div id="cs-adviser" class="text-xs font-black text-dranhs-dark mb-3 flex-1"></div>
                    <a id="cs-gc-btn" href="#" target="_blank" rel="noopener noreferrer"
                        class="hidden w-full inline-flex items-center justify-center gap-2 px-3 py-2.5 rounded-xl bg-violet-600 text-white text-xs font-black hover:bg-violet-700 transition-colors">
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"/></svg>
                        JOIN GC
                    </a>
                    <div id="cs-no-gc" class="hidden text-[9px] text-slate-400 font-semibold italic">No group chat link yet.</div>
                </div>
            </div>

        </div>

        <!-- Loading state -->
        <div id="cs-loading" class="hidden px-6 py-10 flex flex-col items-center gap-3">
            <div class="w-10 h-10 border-4 border-dranhs-green border-t-transparent rounded-full animate-spin"></div>
            <p class="text-sm font-semibold text-slate-500">Looking up record...</p>
        </div>

        <!-- Close link -->
        <div class="px-6 py-3 border-t border-slate-100 flex justify-end">
            <button id="cs-close-bottom" class="text-xs font-black uppercase tracking-widest text-slate-400 hover:text-slate-700 transition-colors">Close</button>
        </div>
    </div>
</div>

<script>
(function () {
    const modal     = document.getElementById('check-status-modal');
    const openBtn   = document.getElementById('check-status-btn');
    const closeBtn  = document.getElementById('check-status-close');
    const closeBot  = document.getElementById('cs-close-bottom');
    const input     = document.getElementById('cs-lrn-input');
    const searchBtn = document.getElementById('cs-search-btn');
    const errorDiv  = document.getElementById('cs-error');
    const resultDiv = document.getElementById('cs-result');
    const loadDiv   = document.getElementById('cs-loading');

    function openModal() {
        modal.classList.remove('hidden');
        modal.classList.add('flex');
        input.focus();
    }
    function closeModal() {
        modal.classList.add('hidden');
        modal.classList.remove('flex');
        resetState();
    }
    function resetState() {
        input.value = '';
        errorDiv.classList.add('hidden');
        resultDiv.classList.add('hidden');
        loadDiv.classList.add('hidden');
    }

    openBtn.addEventListener('click', openModal);
    closeBtn.addEventListener('click', closeModal);
    closeBot.addEventListener('click', closeModal);
    modal.addEventListener('click', function (e) { if (e.target === modal) closeModal(); });
    input.addEventListener('keydown', function (e) { if (e.key === 'Enter') doSearch(); });
    searchBtn.addEventListener('click', doSearch);

    function doSearch() {
        const lrn = input.value.trim().replace(/\D/g, '');
        errorDiv.classList.add('hidden');
        resultDiv.classList.add('hidden');

        if (lrn.length < 10) {
            errorDiv.innerHTML = 'Please enter a valid LRN (10–12 digits).';
            errorDiv.classList.remove('hidden');
            return;
        }

        loadDiv.classList.remove('hidden');
        searchBtn.disabled = true;

        fetch('check_status.php?lrn=' + encodeURIComponent(lrn))
            .then(r => r.json())
            .then(data => {
                loadDiv.classList.add('hidden');
                searchBtn.disabled = false;

                if (!data.found) {
                    errorDiv.innerHTML = data.message || 'No record found.';
                    errorDiv.classList.remove('hidden');
                    return;
                }

                populateResult(data);
                resultDiv.classList.remove('hidden');
            })
            .catch(() => {
                loadDiv.classList.add('hidden');
                searchBtn.disabled = false;
                errorDiv.innerHTML = 'Connection error. Please try again.';
                errorDiv.classList.remove('hidden');
            });
    }

    function populateResult(d) {
        // Photo
        const photo = document.getElementById('cs-photo');
        if (d.photo_path) {
            photo.src = d.photo_path;
            photo.onerror = function () {
                photo.src = 'https://ui-avatars.com/api/?name=' + encodeURIComponent(d.full_name.charAt(0)) + '&background=009b5a&color=fff&size=128&bold=true';
            };
        } else {
            photo.src = 'https://ui-avatars.com/api/?name=' + encodeURIComponent(d.full_name.charAt(0)) + '&background=009b5a&color=fff&size=128&bold=true';
        }

        document.getElementById('cs-name').textContent = d.full_name;
        document.getElementById('cs-lrn-badge').textContent = d.lrn;
        document.getElementById('cs-sy-badge').textContent = 'S.Y. ' + d.school_year;
        document.getElementById('cs-pathway').textContent = d.pathway || d.track || '--';
        document.getElementById('cs-grade').textContent = d.grade_num || '--';
        document.getElementById('cs-section').textContent = d.section || '--';
        document.getElementById('cs-adviser').textContent = d.adviser || '--';

        // Status badge
        const badge = document.getElementById('cs-status-badge');
        const statusColors = {
            'enrolled':       'bg-emerald-100 text-emerald-700',
            'for_evaluation': 'bg-amber-100 text-amber-700',
            'for_encoding':   'bg-blue-100 text-blue-700',
            'withdrawn':      'bg-red-100 text-red-700',
        };
        badge.className = 'px-3 py-1 rounded-full text-xs font-black uppercase tracking-wide ' + (statusColors[d.status] || 'bg-slate-100 text-slate-600');
        badge.textContent = d.status_label;

        // Step tracker
        const circles = document.querySelectorAll('.cs-step-circle');
        const labels  = document.querySelectorAll('.cs-step-label');
        const step    = parseInt(d.step) || 0;
        circles.forEach((c, i) => {
            if (i < step) {
                c.classList.remove('border-slate-200', 'bg-white', 'text-slate-300');
                c.classList.add('border-dranhs-green', 'bg-dranhs-green', 'text-white');
                labels[i].classList.remove('text-slate-400');
                labels[i].classList.add('text-dranhs-green');
            } else if (i === step) {
                c.classList.remove('border-slate-200', 'bg-white', 'text-slate-300');
                c.classList.add('border-dranhs-green', 'bg-white', 'text-dranhs-green');
            } else {
                c.classList.remove('border-dranhs-green', 'bg-dranhs-green', 'text-white', 'text-dranhs-green');
                c.classList.add('border-slate-200', 'bg-white', 'text-slate-300');
                labels[i].classList.remove('text-dranhs-green');
                labels[i].classList.add('text-slate-400');
            }
        });

        // Progress line width
        const pct = step === 0 ? 0 : Math.round(((step - 1) / 3) * 100);
        document.getElementById('cs-progress-line').style.width = pct + '%';

        // Group chat button
        const gcBtn  = document.getElementById('cs-gc-btn');
        const noGc   = document.getElementById('cs-no-gc');
        if (d.group_chat_url) {
            gcBtn.href = d.group_chat_url;
            gcBtn.classList.remove('hidden');
            noGc.classList.add('hidden');
        } else {
            gcBtn.classList.add('hidden');
            noGc.classList.remove('hidden');
        }
    }
})();
</script>
