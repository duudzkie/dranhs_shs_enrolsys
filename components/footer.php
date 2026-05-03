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
        <button id="room-locator-btn" class="flex-1 lg:flex-none bg-white border border-slate-200 text-slate-700 py-2 px-3 lg:py-2.5 lg:px-5 rounded-full font-bold text-[0.7rem] lg:text-xs flex items-center justify-center gap-1.5 lg:gap-2 cursor-pointer transition-colors hover:bg-slate-50 hover:text-dranhs-dark shadow-sm uppercase tracking-wide">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" class="hidden sm:block lg:w-4 lg:h-4">
                <path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"></path>
                <circle cx="12" cy="10" r="3"></circle>
            </svg>
            <span class="hidden sm:inline">ROOM LOCATOR</span>
            <span class="sm:hidden">ROOMS</span>
        </button>
    </div>
    <div class="text-center lg:text-right w-full lg:w-auto text-slate-500 text-[0.65rem] lg:text-[0.7rem] font-bold uppercase tracking-widest pb-1 lg:pb-0 flex items-center gap-3 justify-center lg:justify-end">
        <?php if (!empty($_nav_deped)): ?>
            <img src="<?php echo htmlspecialchars($_nav_deped); ?>" alt="DepEd Logo" class="w-7 h-7 object-contain opacity-70">
        <?php endif; ?>
        <?php if (!empty($_nav_division)): ?>
            <img src="<?php echo htmlspecialchars($_nav_division); ?>" alt="Division Logo" class="w-7 h-7 object-contain opacity-70">
        <?php endif; ?>
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
                <!-- QR Scan button -->
                <button id="cs-qr-btn" title="Scan QR Code"
                    class="px-3 py-3 rounded-xl bg-slate-100 text-slate-600 text-sm font-bold hover:bg-slate-200 transition-colors shrink-0 flex items-center gap-1.5">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                        <rect x="3" y="3" width="7" height="7" rx="1"/><rect x="14" y="3" width="7" height="7" rx="1"/>
                        <rect x="3" y="14" width="7" height="7" rx="1"/>
                        <path d="M14 14h2v2h-2zm4 0h3v3h-3zm0 4h-3v3h3zm-4 0h2v3h-2z"/>
                    </svg>
                </button>
            </div>

            <!-- QR Scanner area (hidden by default) -->
            <div id="cs-qr-area" class="hidden mt-3">
                <div class="relative rounded-xl overflow-hidden border-2 border-dranhs-green/40 bg-black" style="height:220px;">
                    <div id="cs-qr-reader" class="w-full h-full"></div>
                    <!-- Scan frame overlay -->
                    <div class="absolute inset-0 flex items-center justify-center pointer-events-none">
                        <div class="w-40 h-40 border-2 border-dranhs-green rounded-xl relative">
                            <span class="absolute top-0 left-0 w-5 h-5 border-t-4 border-l-4 border-dranhs-green rounded-tl-lg"></span>
                            <span class="absolute top-0 right-0 w-5 h-5 border-t-4 border-r-4 border-dranhs-green rounded-tr-lg"></span>
                            <span class="absolute bottom-0 left-0 w-5 h-5 border-b-4 border-l-4 border-dranhs-green rounded-bl-lg"></span>
                            <span class="absolute bottom-0 right-0 w-5 h-5 border-b-4 border-r-4 border-dranhs-green rounded-br-lg"></span>
                        </div>
                    </div>
                </div>
                <div class="flex items-center justify-between mt-2">
                    <p class="text-xs text-slate-400 font-semibold">Point camera at the QR code on the student ID</p>
                    <button id="cs-qr-stop" class="text-xs font-bold text-red-500 hover:text-red-700">Stop Scanner</button>
                </div>
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
                        ['label' => 'Registration', 'icon' => 'M5 13l4 4L19 7'],
                        ['label' => 'Review',       'icon' => 'M5 13l4 4L19 7'],
                        ['label' => 'Enrolled',     'icon' => 'M5 13l4 4L19 7'],
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
                        <span class="text-[9px] font-black uppercase tracking-widest text-slate-400">Enrollment Profile</span>
                    </div>
                    <div class="space-y-2">
                        <div>
                            <div id="cs-pathway-label" class="text-[8px] font-black uppercase tracking-widest text-slate-400 mb-0.5">Career Pathway</div>
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

<!-- ══════════════════════════════════════════════
     ROOM LOCATOR MODAL
══════════════════════════════════════════════ -->
<div id="room-locator-modal" class="fixed inset-0 z-[200] hidden items-start justify-center p-4 pt-6 overflow-y-auto" style="background:rgba(15,23,42,0.65);backdrop-filter:blur(4px);">
    <div class="w-full max-w-4xl bg-white rounded-3xl shadow-2xl overflow-hidden mb-8">

        <!-- Header -->
        <div class="flex items-center gap-4 px-6 py-5 border-b border-slate-100 bg-gradient-to-r from-violet-50 to-white">
            <div class="w-12 h-12 rounded-2xl bg-violet-100 flex items-center justify-center shrink-0">
                <svg class="w-6 h-6 text-violet-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
            </div>
            <div class="flex-1">
                <h2 class="font-heading font-black text-lg text-dranhs-dark tracking-tight">ROOM LOCATOR</h2>
                <p class="text-xs text-slate-400 font-semibold uppercase tracking-widest">Section to Room Mapping</p>
            </div>
            <div class="flex items-center gap-2">
                <!-- Search -->
                <div class="relative hidden sm:block">
                    <input type="text" id="rl-search" placeholder="Search section..."
                        class="border border-slate-200 rounded-xl px-3 py-2 pl-8 text-xs font-semibold text-slate-700 focus:border-violet-400 outline-none w-40">
                    <svg class="w-3.5 h-3.5 text-slate-400 absolute left-2.5 top-1/2 -translate-y-1/2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><circle cx="11" cy="11" r="8" stroke-width="2.5"/><line x1="21" y1="21" x2="16.65" y2="16.65" stroke-width="2.5"/></svg>
                </div>
                <button id="room-locator-close" class="w-9 h-9 rounded-full bg-slate-100 hover:bg-slate-200 flex items-center justify-center text-slate-500 transition-colors text-lg font-bold">&times;</button>
            </div>
        </div>

        <!-- Building tabs -->
        <div class="flex border-b border-slate-100 bg-slate-50/50">
            <button id="rl-tab-14" onclick="rlSwitchBldg('14')" class="px-6 py-3 text-xs font-black uppercase tracking-widest text-dranhs-green border-b-2 border-dranhs-green bg-white transition-colors">
                🏢 Bldg 14
            </button>
            <button id="rl-tab-15" onclick="rlSwitchBldg('15')" class="px-6 py-3 text-xs font-black uppercase tracking-widest text-slate-400 border-b-2 border-transparent hover:text-slate-700 transition-colors">
                🏢 Bldg 15
            </button>
        </div>

        <!-- Loading -->
        <div id="rl-loading" class="flex flex-col items-center justify-center py-16 gap-3">
            <div class="w-8 h-8 border-4 border-violet-500 border-t-transparent rounded-full animate-spin"></div>
            <p class="text-xs font-semibold text-slate-400">Loading room map...</p>
        </div>

        <!-- Building views -->
        <div id="rl-content" class="hidden p-6 space-y-8">

            <!-- BLDG 14 -->
            <div id="rl-bldg-14" class="rl-bldg-view">
                <div class="flex items-center gap-2 mb-4">
                    <svg class="w-5 h-5 text-blue-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/></svg>
                    <h3 class="font-heading font-black text-base text-dranhs-dark uppercase tracking-wide">Senior High Complex — Bldg 14</h3>
                    <span class="text-[9px] font-bold text-slate-400 uppercase tracking-widest">4 Floors · Rooms 41–56</span>
                </div>
                <div class="bg-blue-50/40 border border-blue-100 rounded-2xl p-5 space-y-5" id="rl-floors-14"></div>
            </div>

            <!-- BLDG 15 -->
            <div id="rl-bldg-15" class="rl-bldg-view hidden">
                <div class="flex items-center gap-2 mb-4">
                    <svg class="w-5 h-5 text-orange-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/></svg>
                    <h3 class="font-heading font-black text-base text-dranhs-dark uppercase tracking-wide">Senior High Complex — Bldg 15</h3>
                    <span class="text-[9px] font-bold text-slate-400 uppercase tracking-widest">4 Floors · Rooms 21–40</span>
                </div>
                <div class="bg-orange-50/40 border border-orange-100 rounded-2xl p-5 space-y-5" id="rl-floors-15"></div>
            </div>

        </div>

        <!-- Legend -->
        <div class="px-6 pb-5 flex flex-wrap gap-4 text-[9px] font-black uppercase tracking-widest text-slate-400">
            <span class="flex items-center gap-1.5"><span class="w-3 h-3 rounded bg-emerald-200 inline-block"></span> Assigned (G11)</span>
            <span class="flex items-center gap-1.5"><span class="w-3 h-3 rounded bg-pink-200 inline-block"></span> Assigned (G12)</span>
            <span class="flex items-center gap-1.5"><span class="w-3 h-3 rounded bg-slate-100 border border-slate-200 inline-block"></span> Vacant</span>
        </div>

        <!-- Annex entries (populated by JS) -->
        <div id="rl-annex-section" class="hidden px-6 pb-4"></div>

        <!-- Facilities (populated by JS) -->
        <div id="rl-facilities-section" class="hidden px-6 pb-4"></div>

        <div class="px-6 pb-5 flex justify-end">
            <button id="rl-close-bottom" class="text-xs font-black uppercase tracking-widest text-slate-400 hover:text-slate-700 transition-colors">Close</button>
        </div>
    </div>
</div>

<!-- Room detail modal (for non-Bldg14/15 rooms) -->
<div id="rl-room-detail" class="fixed inset-0 z-[300] hidden items-center justify-center p-4" style="background:rgba(15,23,42,0.5);">
    <div class="bg-white rounded-2xl shadow-2xl w-full max-w-sm p-6">
        <div class="flex items-center justify-between mb-4">
            <h3 class="font-heading font-black text-lg text-dranhs-dark">Room Details</h3>
            <button id="rl-room-detail-close" class="w-8 h-8 rounded-full bg-slate-100 hover:bg-slate-200 flex items-center justify-center text-slate-500">&times;</button>
        </div>
        <div id="rl-room-detail-content" class="space-y-3"></div>
    </div>
</div>

<script>
(function () {
    const modal     = document.getElementById('check-status-modal');    const openBtn   = document.getElementById('check-status-btn');
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
        // Career Pathway for G11, Strand for G12
        const pathwayLabel = d.grade_level === 'Grade 11' ? 'Career Pathway' : 'Strand';
        document.getElementById('cs-pathway-label').textContent = pathwayLabel;
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

        // Step mapping (3 steps):
        // 1 = Registration (for_evaluation — just submitted)
        // 2 = Review       (for_encoding — passed evaluation)
        // 3 = Enrolled     (enrolled — fully enrolled)
        const statusStepMap = {
            'for_evaluation': 1,
            'for_encoding':   2,
            'enrolled':       3,
            'withdrawn':      1,
        };
        const step = statusStepMap[d.status] || 1;

        const circles = document.querySelectorAll('.cs-step-circle');
        const labels  = document.querySelectorAll('.cs-step-label');

        circles.forEach((c, i) => {
            const circleStep = i + 1;
            if (circleStep <= step) {
                c.classList.remove('border-slate-200', 'bg-white', 'text-slate-300', 'border-dranhs-green');
                c.classList.add('border-dranhs-green', 'bg-dranhs-green', 'text-white');
                labels[i].classList.remove('text-slate-400');
                labels[i].classList.add('text-dranhs-green');
            } else {
                c.classList.remove('border-dranhs-green', 'bg-dranhs-green', 'text-white');
                c.classList.add('border-slate-200', 'bg-white', 'text-slate-300');
                labels[i].classList.remove('text-dranhs-green');
                labels[i].classList.add('text-slate-400');
            }
        });

        // Progress line — 0%, 50%, 100%
        const pct = step <= 1 ? 0 : Math.round(((step - 1) / 2) * 100);
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

    // ── QR Scanner ────────────────────────────────────────────────────────────
    let _qrScanner = null;
    const qrBtn    = document.getElementById('cs-qr-btn');
    const qrArea   = document.getElementById('cs-qr-area');
    const qrStop   = document.getElementById('cs-qr-stop');

    function stopQrScanner() {
        if (_qrScanner) {
            _qrScanner.stop().catch(() => {});
            _qrScanner.clear();
            _qrScanner = null;
        }
        qrArea.classList.add('hidden');
        qrBtn.classList.remove('bg-dranhs-green', 'text-white');
        qrBtn.classList.add('bg-slate-100', 'text-slate-600');
    }

    qrBtn.addEventListener('click', function () {
        if (!qrArea.classList.contains('hidden')) {
            stopQrScanner();
            return;
        }

        // Load html5-qrcode library on demand
        if (!window.Html5Qrcode) {
            const script = document.createElement('script');
            script.src = 'https://unpkg.com/html5-qrcode@2.3.8/html5-qrcode.min.js';
            script.onload = startQrScanner;
            document.head.appendChild(script);
        } else {
            startQrScanner();
        }

        qrArea.classList.remove('hidden');
        qrBtn.classList.remove('bg-slate-100', 'text-slate-600');
        qrBtn.classList.add('bg-dranhs-green', 'text-white');
    });

    qrStop.addEventListener('click', stopQrScanner);

    function startQrScanner() {
        if (_qrScanner) return;
        _qrScanner = new Html5Qrcode('cs-qr-reader');
        _qrScanner.start(
            { facingMode: 'environment' },
            { fps: 10, qrbox: { width: 200, height: 200 } },
            (decodedText) => {
                // Extract LRN from QR data format: "LRN:123456789012|NAME:...|SY:...|..."
                // Only reads the LRN field, ignores all other data
                const lrnMatch = decodedText.match(/(?:^|[|,])LRN:(\d{10,12})(?:[|,]|$)/);
                let lrn = lrnMatch ? lrnMatch[1] : decodedText.replace(/\D/g, '');
                lrn = lrn.replace(/\D/g, '');
                if (lrn.length >= 10) {
                    stopQrScanner();
                    document.getElementById('cs-lrn-input').value = lrn;
                    doSearch();
                }
            },
            () => {} // ignore scan errors
        ).catch(err => {
            errorDiv.innerHTML = 'Camera access denied or not available.';
            errorDiv.classList.remove('hidden');
            stopQrScanner();
        });
    }

    // Stop scanner when modal closes
    const _origClose = closeModal;
    function closeModal() {
        stopQrScanner();
        _origClose();
    }
    closeBtn.addEventListener('click', closeModal);
    closeBot.addEventListener('click', closeModal);
    modal.addEventListener('click', function (e) { if (e.target === modal) closeModal(); });
})();

// ── Room Locator ──────────────────────────────────────────────────────────────
(function () {
    const rlModal   = document.getElementById('room-locator-modal');
    const rlOpenBtn = document.getElementById('room-locator-btn');
    const rlClose   = document.getElementById('room-locator-close');
    const rlCloseBot= document.getElementById('rl-close-bottom');
    const rlLoading = document.getElementById('rl-loading');
    const rlContent = document.getElementById('rl-content');
    const rlSearch  = document.getElementById('rl-search');
    const rlDetail  = document.getElementById('rl-room-detail');
    const rlDetailClose = document.getElementById('rl-room-detail-close');

    // Building room layouts
    const BLDG_14 = {
        4: [53, 54, 55, 56],
        3: [49, 50, 51, 52],
        2: [45, 46, 47, 48],
        1: [41, 42, 43, 44],
    };
    const BLDG_15 = {
        4: [40, 39, 38, 37, 36],
        3: [35, 34, 33, 32, 31],
        2: [30, 29, 28, 27, 26],
        1: [25, 24, 23, 22, 21],
    };

    let roomData = {}; // { roomNumber: [{section, grade, adviser, track, pathway}] }
    let loaded = false;

    function openModal() {
        rlModal.classList.remove('hidden');
        rlModal.classList.add('flex');
        if (!loaded) loadRooms();
    }
    function closeModal() {
        rlModal.classList.add('hidden');
        rlModal.classList.remove('flex');
    }

    rlOpenBtn.addEventListener('click', openModal);
    rlClose.addEventListener('click', closeModal);
    rlCloseBot.addEventListener('click', closeModal);
    rlModal.addEventListener('click', function (e) { if (e.target === rlModal) closeModal(); });
    rlDetailClose.addEventListener('click', function () {
        rlDetail.classList.add('hidden');
        rlDetail.classList.remove('flex');
    });

    function loadRooms() {
        rlLoading.classList.remove('hidden');
        rlContent.classList.add('hidden');

        fetch('room_locator.php')
            .then(r => r.json())
            .then(data => {
                roomData = data.rooms || {};
                loaded = true;
                renderBuilding('14', BLDG_14, 'rl-floors-14', 4);
                renderBuilding('15', BLDG_15, 'rl-floors-15', 5);

                // Annex entries
                const annexWrap = document.getElementById('rl-annex-section');
                if (data.annex && data.annex.length > 0) {
                    annexWrap.innerHTML = `
                        <div class="text-[9px] font-black uppercase tracking-widest text-indigo-500 mb-2 flex items-center gap-1.5">
                            <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16m-7 6h7"/></svg>
                            Manual Annex Entries
                        </div>
                        <div class="grid grid-cols-2 sm:grid-cols-3 gap-2">
                            ${data.annex.map(a => `
                                <div class="bg-indigo-50 border border-indigo-100 rounded-xl px-3 py-2">
                                    <div class="text-xs font-black text-indigo-800">${a.section_name}</div>
                                    <div class="text-[9px] font-bold text-indigo-400 mt-0.5">Bldg ${a.building_number} · Floor ${a.floor_number} · Room ${a.room_number}</div>
                                </div>
                            `).join('')}
                        </div>`;
                    annexWrap.classList.remove('hidden');
                }

                // Facilities
                const facWrap = document.getElementById('rl-facilities-section');
                if (data.facilities && data.facilities.length > 0) {
                    facWrap.innerHTML = `
                        <div class="text-[9px] font-black uppercase tracking-widest text-amber-500 mb-2 flex items-center gap-1.5">
                            <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/></svg>
                            Faculty / Laboratories
                        </div>
                        <div class="grid grid-cols-2 sm:grid-cols-3 gap-2">
                            ${data.facilities.map(f => `
                                <div class="bg-amber-50 border border-amber-100 rounded-xl px-3 py-2">
                                    <div class="text-xs font-black text-amber-800">${f.facility_name}</div>
                                    <div class="text-[9px] font-bold text-amber-400 mt-0.5">Bldg ${f.building_number} · Floor ${f.floor_number} · Room ${f.room_number}</div>
                                </div>
                            `).join('')}
                        </div>`;
                    facWrap.classList.remove('hidden');
                }

                rlLoading.classList.add('hidden');
                rlContent.classList.remove('hidden');
            })
            .catch(() => {
                rlLoading.innerHTML = '<p class="text-xs text-red-500 font-semibold">Failed to load room data.</p>';
            });
    }

    function renderBuilding(bldgId, floors, containerId, cols) {
        const container = document.getElementById(containerId);
        container.innerHTML = '';

        const floorNums = Object.keys(floors).map(Number).sort((a, b) => b - a);
        floorNums.forEach(floor => {
            const rooms = floors[floor];
            const floorDiv = document.createElement('div');
            floorDiv.className = 'flex flex-col sm:flex-row items-start sm:items-center gap-3';

            const label = document.createElement('div');
            label.className = 'w-16 shrink-0 text-[9px] font-black uppercase tracking-widest text-slate-400';
            label.textContent = 'Floor ' + floor;
            floorDiv.appendChild(label);

            const grid = document.createElement('div');
            grid.className = 'flex-1 grid gap-2';
            grid.style.gridTemplateColumns = `repeat(${cols}, minmax(0, 1fr))`;

            rooms.forEach(roomNum => {
                const sections = roomData[String(roomNum)] || [];
                const card = buildRoomCard(roomNum, sections);
                grid.appendChild(card);
            });

            floorDiv.appendChild(grid);
            container.appendChild(floorDiv);
        });
    }

    function buildRoomCard(roomNum, sections) {
        const card = document.createElement('div');
        const hasG11 = sections.find(s => s.grade === '11');
        const hasG12 = sections.find(s => s.grade === '12');
        const isEmpty = sections.length === 0;

        let bgClass = 'bg-white border border-slate-200 text-slate-400';
        if (hasG11 && hasG12) bgClass = 'bg-gradient-to-b from-emerald-50 to-pink-50 border border-emerald-200';
        else if (hasG11) bgClass = 'bg-emerald-50 border border-emerald-200';
        else if (hasG12) bgClass = 'bg-pink-50 border border-pink-200';

        card.className = `${bgClass} rounded-xl p-2 text-center cursor-pointer hover:shadow-md transition-all select-none rl-room-card`;
        card.dataset.room = roomNum;

        const numDiv = document.createElement('div');
        numDiv.className = 'text-xs font-black text-slate-600';
        numDiv.textContent = roomNum;
        card.appendChild(numDiv);

        if (!isEmpty) {
            sections.forEach(s => {
                const secDiv = document.createElement('div');
                secDiv.className = 'text-[8px] font-black truncate ' + (s.grade === '11' ? 'text-emerald-700' : 'text-pink-700');
                secDiv.textContent = s.section;
                card.appendChild(secDiv);
            });
        }

        card.addEventListener('click', function () {
            showRoomDetail(roomNum, sections);
        });

        return card;
    }

    function showRoomDetail(roomNum, sections) {
        const content = document.getElementById('rl-room-detail-content');
        content.innerHTML = '';

        const roomHeader = document.createElement('div');
        roomHeader.className = 'text-center pb-3 border-b border-slate-100';
        roomHeader.innerHTML = `<div class="text-3xl font-black text-dranhs-dark">Room ${roomNum}</div>
            <div class="text-xs text-slate-400 font-semibold mt-1">${getRoomBuilding(roomNum)}</div>`;
        content.appendChild(roomHeader);

        if (sections.length === 0) {
            const empty = document.createElement('div');
            empty.className = 'text-center py-4 text-sm text-slate-400 font-semibold italic';
            empty.textContent = 'No section assigned to this room.';
            content.appendChild(empty);
        } else {
            sections.forEach(s => {
                const sec = document.createElement('div');
                sec.className = 'p-3 rounded-xl ' + (s.grade === '11' ? 'bg-emerald-50 border border-emerald-100' : 'bg-pink-50 border border-pink-100');
                sec.innerHTML = `
                    <div class="flex items-center justify-between mb-1">
                        <span class="text-xs font-black ${s.grade === '11' ? 'text-emerald-700' : 'text-pink-700'} uppercase tracking-wide">${s.section}</span>
                        <span class="text-[9px] font-bold px-2 py-0.5 rounded-full ${s.grade === '11' ? 'bg-emerald-100 text-emerald-600' : 'bg-pink-100 text-pink-600'}">Grade ${s.grade}</span>
                    </div>
                    ${s.adviser ? `<div class="text-[9px] text-slate-500 font-semibold">Adviser: ${s.adviser}</div>` : ''}
                    ${s.track ? `<div class="text-[9px] text-slate-400">${s.track}${s.pathway ? ' · ' + s.pathway : ''}</div>` : ''}
                `;
                content.appendChild(sec);
            });
        }

        rlDetail.classList.remove('hidden');
        rlDetail.classList.add('flex');
    }

    function getRoomBuilding(num) {
        num = parseInt(num);
        if (num >= 41 && num <= 56) {
            const floor = Math.ceil((num - 40) / 4);
            return `Building 14 · Floor ${floor}`;
        }
        if (num >= 21 && num <= 40) {
            const floor = Math.ceil((num - 20) / 5);
            return `Building 15 · Floor ${floor}`;
        }
        return 'Other Building';
    }

    // Building tab switching
    window.rlSwitchBldg = function (id) {
        document.querySelectorAll('.rl-bldg-view').forEach(v => v.classList.add('hidden'));
        document.getElementById('rl-bldg-' + id).classList.remove('hidden');
        ['14', '15'].forEach(b => {
            const tab = document.getElementById('rl-tab-' + b);
            if (b === id) {
                tab.classList.add('text-dranhs-green', 'border-dranhs-green', 'bg-white');
                tab.classList.remove('text-slate-400', 'border-transparent');
            } else {
                tab.classList.remove('text-dranhs-green', 'border-dranhs-green', 'bg-white');
                tab.classList.add('text-slate-400', 'border-transparent');
            }
        });
    };

    // Search
    if (rlSearch) {
        rlSearch.addEventListener('input', function () {
            const q = this.value.toLowerCase().trim();
            document.querySelectorAll('.rl-room-card').forEach(card => {
                const text = card.textContent.toLowerCase();
                card.style.opacity = (!q || text.includes(q)) ? '1' : '0.25';
                card.style.transform = (!q || text.includes(q)) ? '' : 'scale(0.95)';
            });
        });
    }
})();
</script>
