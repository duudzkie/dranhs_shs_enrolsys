<?php
// Prevent this component from being loaded directly. It is meant to be included by EMS2/index.php.
if (basename($_SERVER['PHP_SELF']) === 'enroll_modal.php') {
    header('Location: index.php');
    exit;
}

// Fetch curriculum settings
$curr_vis_saved = null;
if (isset($conn) && $conn instanceof mysqli) {
    $c_res = $conn->query("SELECT setting_value FROM system_settings WHERE setting_key = 'curriculum_structure'");
    if ($c_res && $c_res->num_rows > 0) {
        $curr_vis_saved = $c_res->fetch_assoc()['setting_value'];
    }
}
$curriculum_configured = ($curr_vis_saved !== null);
$curriculum_structure = $curriculum_configured ? json_decode($curr_vis_saved, true) : [];

require_once __DIR__ . '/../pathway_strand_catalog.php';
$catalog = load_pathway_strand_catalog();
$grade11_items = $catalog['grade_11'] ?? [];

$pathways_by_track = [
    'Academic' => [],
    'Tech-Pro' => [],
    'ALS' => []
];
$pathway_details = [];
foreach ($grade11_items as $item) {
    if (empty($item['enabled'])) {
        continue;
    }
    $track = trim($item['track'] ?? '');
    if ($track === '') {
        continue;
    }
    if ($track === 'TVL') {
        $track = 'Tech-Pro';
    }
    if (!isset($pathways_by_track[$track])) {
        $pathways_by_track[$track] = [];
    }

    $entry = [
        'name' => $item['label'] ?? '',
        'description' => $item['description'] ?? '',
        'electives' => !empty($item['electives']) ? array_values($item['electives']) : [],
        'category' => $item['category'] ?? '',
        'code' => $item['code'] ?? ''
    ];

    $pathways_by_track[$track][] = $entry;
    if (!isset($pathway_details[$track])) {
        $pathway_details[$track] = [];
    }
    $pathway_details[$track][$entry['name']] = [
        'description' => $entry['description'],
        'electives' => $entry['electives'],
        'category' => $entry['category'],
        'code' => $entry['code']
    ];
}

$js_pathways = [];
foreach ($pathways_by_track as $track => $items) {
    $js_pathways[$track] = array_map(fn($entry) => $entry['name'], $items);
}

?>
<script>
    // Export live configured pathways for form-logic.js
    window.DYNAMIC_PATHWAYS_DATA = <?php echo json_encode($js_pathways); ?>; // Will be properly assembled inside the loops
</script>

<!-- Enrollment Modal (components/enroll_modal.php) -->
<div id="enroll-modal" class="fixed inset-0 bg-slate-900/60 backdrop-blur-sm z-[100] hidden flex justify-center items-center opacity-0 transition-opacity duration-300 p-4">
    
    <!-- Modal Content Box -->
    <div class="bg-white rounded-[2rem] w-full max-w-4xl p-6 lg:p-10 transform scale-95 transition-transform duration-300 shadow-2xl relative max-h-[90vh] flex flex-col" id="enroll-modal-content">
        
        <!-- Close Button -->
        <button id="close-modal" class="absolute top-4 right-4 lg:top-6 lg:right-6 text-slate-400 hover:text-slate-800 transition-colors p-2 rounded-full hover:bg-slate-100 bg-white z-20">
            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="6" x2="6" y2="18"></line><line x1="6" y1="6" x2="18" y2="18"></line></svg>
        </button>

        <!-- Container for all views with scrolling capability if necessary -->
        <div class="overflow-y-auto flex-1 w-full px-2">
        
            <!-- ================= VIEW 1: Grade Selection ================= -->
            <div id="view-grade-selection" class="block w-full">
                <div class="text-center mb-8 lg:mb-12 mt-4 lg:mt-0">
                    <h2 class="text-3xl lg:text-4xl font-heading font-black text-dranhs-dark mb-3 uppercase tracking-tight">Select Grade Level</h2>
                    <p class="text-slate-500 font-medium text-sm lg:text-base">Please choose the appropriate path for your enrolment</p>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-6 lg:gap-8">
                    <!-- Card 1: Incoming Grade 11 -->
                    <div class="group relative bg-white border-2 border-slate-200 rounded-3xl overflow-hidden cursor-pointer hover:border-violet-600 transition-all duration-300 hover:shadow-xl hover:-translate-y-1 flex flex-col">
                        <div class="h-2 w-full bg-violet-600"></div>
                        <div class="p-8 relative z-10 flex flex-col h-full items-center text-center">
                            <h3 class="text-3xl font-black text-dranhs-dark mb-2 font-heading tracking-tight mt-4">GRADE 11</h3>
                            <p class="text-violet-600 font-bold mb-4 tracking-wider uppercase text-[0.65rem] lg:text-xs bg-violet-50 py-1.5 px-4 rounded-full">New Curriculum</p>
                            <p class="text-slate-600 mb-8 text-sm leading-relaxed flex-grow">
                                For incoming Grade 11 students entering under the updated academic tracking structure.
                            </p>
                            <button id="btn-grade-11" class="w-full py-3 px-6 rounded-xl bg-slate-50 group-hover:bg-violet-600 group-hover:text-white text-slate-700 font-bold transition-colors uppercase text-sm tracking-wide shadow-sm">
                                Proceed
                            </button>
                        </div>
                    </div>

                    <!-- Card 2: Incoming Grade 12 -->
                    <div class="group relative bg-white border-2 border-slate-200 rounded-3xl overflow-hidden cursor-pointer hover:border-pink-500 transition-all duration-300 hover:shadow-xl hover:-translate-y-1 flex flex-col">
                        <div class="h-2 w-full bg-pink-500"></div>
                        <div class="p-8 relative z-10 flex flex-col h-full items-center text-center">
                            <h3 class="text-3xl font-black text-dranhs-dark mb-2 font-heading tracking-tight mt-4">GRADE 12</h3>
                            <p class="text-pink-600 font-bold mb-4 tracking-wider uppercase text-[0.65rem] lg:text-xs bg-pink-50 py-1.5 px-4 rounded-full">Old Curriculum</p>
                            <p class="text-slate-600 mb-8 text-sm leading-relaxed flex-grow">
                                For continuing students finishing their senior high school track under the existing framework.
                            </p>
                            <button id="btn-grade-12" class="w-full py-3 px-6 rounded-xl bg-slate-50 group-hover:bg-pink-500 group-hover:text-white text-slate-700 font-bold transition-colors uppercase text-sm tracking-wide shadow-sm">
                                Proceed
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- ================= VIEW 2: Grade 12 Flow ================= -->
            <div id="view-grade-12" class="hidden w-full">
                <!-- Back Button -->
                <button class="back-to-grades mb-6 text-sm font-bold text-slate-500 hover:text-dranhs-dark uppercase tracking-wide flex items-center gap-2 transition-colors">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><line x1="19" y1="12" x2="5" y2="12"></line><polyline points="12 19 5 12 12 5"></polyline></svg>
                    Back to Grade Selection
                </button>

                <div class="text-center mb-8 lg:mb-10">
                    <h2 class="text-3xl lg:text-4xl font-heading font-black text-pink-500 mb-3 uppercase tracking-tight">Grade 12 Enrollment</h2>
                    <p class="text-slate-500 font-medium">Please select your student status</p>
                </div>

                <div class="max-w-2xl mx-auto flex flex-col gap-6">
                    <!-- Old Student (LRN Input) -->
                    <div class="bg-slate-50 p-6 lg:p-8 rounded-2xl border border-slate-200">
                        <h3 class="font-bold text-slate-800 text-lg mb-1">Old Student (Grade 11 Completer)</h3>
                        <p class="text-xs text-slate-500 mb-5">Enter your Learner Reference Number (LRN) to verify if you're in the Grade 11 completers list</p>
                        <form id="g12-lrn-form" class="flex flex-col sm:flex-row gap-3" onsubmit="event.preventDefault()">
                            <input id="g12-lrn-input" type="text" placeholder="Enter 12-digit LRN" required pattern="[0-9]{12}" title="Please enter a valid 12-digit LRN" class="flex-1 bg-white border border-slate-200 px-4 py-3 rounded-xl focus:border-pink-500 focus:ring-2 focus:ring-pink-500/20 outline-none font-medium shadow-sm transition-all" />
                            <button type="submit" id="g12-lrn-verify-btn" class="bg-pink-500 hover:bg-pink-600 text-white px-8 py-3 rounded-xl font-bold transition-colors uppercase tracking-wide shadow-md flex items-center gap-2 justify-center">
                                <svg id="g12-lrn-spinner" class="hidden animate-spin w-4 h-4" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path></svg>
                                Verify LRN
                            </button>
                        </form>
                        <!-- Status message -->
                        <div id="g12-lrn-status" class="hidden mt-4 p-4 rounded-xl text-sm font-semibold"></div>
                    </div>
                    
                    <div class="flex items-center gap-4 my-2">
                        <div class="flex-1 h-px bg-slate-200"></div>
                        <span class="text-[0.65rem] text-slate-400 font-bold uppercase tracking-widest px-2">OR</span>
                        <div class="flex-1 h-px bg-slate-200"></div>
                    </div>

                    <!-- Transferee/Repeater/Returnee -->
                    <div class="bg-white p-6 lg:p-8 rounded-2xl border border-slate-200 text-center flex flex-col items-center">
                        <h3 class="font-bold text-slate-800 text-lg mb-1">Transferee / Repeater / Returnee</h3>
                        <p class="text-xs text-slate-500 mb-5">Start a new application if you fall into these categories</p>
                        <button id="g12-new-app-btn" class="bg-slate-800 hover:bg-slate-900 text-white px-8 py-3.5 rounded-xl font-bold w-full max-w-sm transition-colors uppercase tracking-widest shadow-md">NEW APPLICATION</button>
                    </div>
                </div>
            </div>

            <!-- ================= VIEW 5: G12 Completer Confirmation ================= -->
            <div id="view-g12-confirm" class="hidden w-full">
                <!-- Back Button -->
                <button id="back-to-g12-form" class="mb-6 text-sm font-bold text-slate-500 hover:text-dranhs-dark uppercase tracking-wide flex items-center gap-2 transition-colors">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><line x1="19" y1="12" x2="5" y2="12"></line><polyline points="12 19 5 12 12 5"></polyline></svg>
                    Back
                </button>

                <div class="text-center mb-6">
                    <div class="inline-flex items-center gap-2 bg-green-100 text-green-700 px-4 py-2 rounded-full text-xs font-black uppercase tracking-widest mb-4">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"></polyline></svg>
                        Grade 11 Completer Found
                    </div>
                    <h2 class="text-3xl lg:text-4xl font-heading font-black text-pink-500 mb-2 uppercase tracking-tight">Confirm Your Details</h2>
                    <p class="text-slate-500 font-medium text-sm">The following information will be pre-filled and locked in the enrollment form.</p>
                </div>

                <div class="max-w-2xl mx-auto">
                    <div class="bg-slate-50 rounded-2xl border border-slate-200 p-6 mb-6 space-y-4">
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <div>
                                <p class="text-xs font-black uppercase tracking-widest text-slate-400 mb-1">Learner Reference No. (LRN)</p>
                                <p id="g12c-lrn" class="text-lg font-black text-slate-800 font-mono tracking-widest"></p>
                            </div>
                            <div>
                                <p class="text-xs font-black uppercase tracking-widest text-slate-400 mb-1">Strand</p>
                                <p id="g12c-strand" class="text-lg font-black text-pink-600 uppercase"></p>
                            </div>
                        </div>
                        <div class="border-t border-slate-200 pt-4">
                            <p class="text-xs font-black uppercase tracking-widest text-slate-400 mb-1">Full Name</p>
                            <p id="g12c-name" class="text-xl font-black text-slate-800 uppercase"></p>
                        </div>
                        <div class="border-t border-slate-200 pt-4">
                            <p class="text-xs font-black uppercase tracking-widest text-slate-400 mb-1">Previous Section</p>
                            <p id="g12c-section" class="text-base font-bold text-slate-600"></p>
                        </div>
                    </div>

                    <div class="bg-blue-50 border border-blue-200 rounded-xl p-4 mb-6 flex items-start gap-3">
                        <svg class="shrink-0 text-blue-500 mt-0.5" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"></circle><line x1="12" y1="8" x2="12" y2="12"></line><line x1="12" y1="16" x2="12.01" y2="16"></line></svg>
                        <p class="text-xs text-blue-700 font-semibold leading-relaxed">As a Grade 11 Completer, your name, LRN, and strand are pre-verified &mdash; they will be <strong>locked</strong> in the form and cannot be changed. Your enrollment will go directly to <strong>encoding</strong> without requiring evaluation.</p>
                    </div>

                    <button id="g12-confirm-proceed-btn" class="bg-pink-600 hover:bg-pink-700 text-white px-10 py-4 rounded-full font-black text-base w-full transition-transform hover:-translate-y-1 shadow-lg uppercase tracking-widest">
                        PROCEED TO ENROLLMENT FORM
                        <svg class="inline ml-2" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><line x1="5" y1="12" x2="19" y2="12"></line><polyline points="12 5 19 12 12 19"></polyline></svg>
                    </button>
                </div>
            </div>

            <!-- ================= VIEW 3: Grade 11 Tracks ================= -->
            <div id="view-grade-11" class="hidden w-full flex flex-col h-full">
                <!-- Back Button -->
                <button class="back-to-grades mb-6 text-sm font-bold text-slate-500 hover:text-dranhs-dark uppercase tracking-wide flex items-center gap-2 transition-colors shrink-0">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><line x1="19" y1="12" x2="5" y2="12"></line><polyline points="12 19 5 12 12 5"></polyline></svg>
                    Back to Grade Selection
                </button>

                <div class="text-center mb-6 lg:mb-8 shrink-0">
                    <h2 class="text-3xl lg:text-4xl font-heading font-black text-violet-600 mb-3 uppercase tracking-tight">Grade 11 Track Selection</h2>
                    <p class="text-slate-500 font-medium">Choose your desired track and specialization</p>
                </div>

                <!-- Tabs -->
                <div class="flex flex-wrap sm:flex-nowrap gap-2 lg:gap-4 border-b border-slate-200 mb-6 shrink-0 w-full justify-center">
                    <button id="tab-academic" class="py-2 lg:py-3 px-3 lg:px-6 border-b-[3px] border-emerald-600 bg-emerald-50 text-emerald-700 rounded-t-xl font-black uppercase text-[0.65rem] sm:text-xs lg:text-sm tracking-widest transition-colors mb-[-2px] flex-1 min-w-[110px] max-w-[200px]">ACADEMIC TRACK</button>
                    <button id="tab-techpro" class="py-2 lg:py-3 px-3 lg:px-6 border-b-[3px] border-transparent bg-transparent text-slate-400 hover:text-slate-700 hover:bg-slate-50 rounded-t-xl font-black uppercase text-[0.65rem] sm:text-xs lg:text-sm tracking-widest transition-colors mb-[-2px] flex-1 min-w-[110px] max-w-[200px]">TECH-PRO TRACK</button>
                    <button id="tab-als" class="py-2 lg:py-3 px-3 lg:px-6 border-b-[3px] border-transparent bg-transparent text-slate-400 hover:text-slate-700 hover:bg-slate-50 rounded-t-xl font-black uppercase text-[0.65rem] sm:text-xs lg:text-sm tracking-widest transition-colors mb-[-2px] flex-1 min-w-[110px] max-w-[200px]">ALS</button>
                </div>

                <!-- Academic Track Content (11 Pathways) -->
                <div id="content-academic" class="grid grid-cols-1 md:grid-cols-2 gap-3 w-full">
                    <?php
                        $acad_tracks = $pathways_by_track['Academic'];
                        foreach($acad_tracks as $path) {
                            echo '<button class="pathway-card relative flex flex-col gap-4 bg-blue-950 border-2 border-transparent rounded-3xl p-5 cursor-pointer hover:border-emerald-400 hover:bg-blue-900 hover:shadow-xl transition-all group text-left" data-track="Academic" data-pathway="'.htmlspecialchars($path['name'], ENT_QUOTES).'">
                                <div class="flex items-center gap-4">
                                    <span class="inline-flex items-center justify-center w-12 h-12 rounded-3xl bg-emerald-500/15 text-emerald-300 text-lg font-black">A</span>
                                    <div>
                                        <p class="text-[0.65rem] uppercase tracking-[0.3em] text-emerald-200 font-semibold">Academic</p>
                                        <h4 class="font-black text-white text-base lg:text-lg leading-snug">'.htmlspecialchars($path['name']).'</h4>
                                    </div>
                                </div>
                            </button>';
                        }
                    ?>
                </div>

                <!-- Tech-Pro Track Content (4 Clusters) -->
                <div id="content-techpro" class="grid grid-cols-1 md:grid-cols-2 gap-3 w-full hidden">
                    <?php
                        $tech_tracks = $pathways_by_track['Tech-Pro'];
                        foreach($tech_tracks as $path) {
                            echo '<button class="pathway-card relative flex flex-col gap-4 bg-amber-50 border-2 border-slate-200 rounded-3xl p-5 cursor-pointer hover:border-orange-500 hover:bg-orange-50 hover:shadow-xl transition-all group text-left" data-track="Tech-Pro" data-pathway="'.htmlspecialchars($path['name'], ENT_QUOTES).'">
                                <div class="flex items-center gap-4">
                                    <span class="inline-flex items-center justify-center w-12 h-12 rounded-3xl bg-orange-100 text-orange-700 text-lg font-black">TP</span>
                                    <div>
                                        <p class="text-[0.65rem] uppercase tracking-[0.3em] text-orange-600 font-semibold">Tech-Pro</p>
                                        <h4 class="font-black text-orange-900 text-base lg:text-lg leading-snug">'.htmlspecialchars($path['name']).'</h4>
                                    </div>
                                </div>
                            </button>';
                        }
                    ?>
                </div>

                <!-- ALS Track Content -->
                <div id="content-als" class="grid grid-cols-1 md:grid-cols-2 gap-3 w-full hidden">
                    <?php
                        $als_tracks = $pathways_by_track['ALS'];
                        foreach($als_tracks as $path) {
                            echo '<button class="pathway-card relative flex flex-col gap-4 bg-rose-50 border-2 border-slate-200 rounded-3xl p-5 cursor-pointer hover:border-rose-500 hover:bg-rose-100 hover:shadow-xl transition-all group text-left" data-track="ALS" data-pathway="'.htmlspecialchars($path['name'], ENT_QUOTES).'">
                                <div class="flex items-center gap-4">
                                    <span class="inline-flex items-center justify-center w-12 h-12 rounded-3xl bg-rose-200 text-rose-700 text-lg font-black">AL</span>
                                    <div>
                                        <p class="text-[0.65rem] uppercase tracking-[0.3em] text-rose-600 font-semibold">ALS</p>
                                        <h4 class="font-black text-rose-900 text-base lg:text-lg leading-snug">'.htmlspecialchars($path['name']).'</h4>
                                    </div>
                                </div>
                            </button>';
                        }
                    ?>
                </div>
                <!-- Re-inject updated JS Pathways -->
                <script>
                    window.DYNAMIC_PATHWAYS_DATA = <?php echo json_encode($js_pathways); ?>;
                    window.PATHWAY_CATALOG_DETAILS = <?php echo json_encode($pathway_details); ?>;
                </script>
            </div>

            <!-- ================= VIEW 4: Pathway Details ================= -->
            <div id="view-pathway-details" class="hidden w-full flex flex-col h-full">
                <!-- Back Button -->
                <button class="back-to-g11-tracks mb-6 text-sm font-bold text-slate-500 hover:text-dranhs-dark uppercase tracking-wide flex items-center gap-2 transition-colors shrink-0">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><line x1="19" y1="12" x2="5" y2="12"></line><polyline points="12 19 5 12 12 5"></polyline></svg>
                    Select Another Pathway
                </button>

                <div class="text-center mb-6 lg:mb-10 shrink-0">
                    <span id="detail-track-name" class="inline-block px-3 py-1 rounded-full text-[0.65rem] font-bold uppercase tracking-widest mb-3">Track Name</span>
                    <span id="detail-schedule" class="px-3 py-1 rounded-full text-[0.65rem] font-black uppercase tracking-widest mb-3 ml-2 border-2 border-rose-400 bg-rose-50 text-rose-600 shadow-sm animate-pulse flex items-center gap-1.5 justify-center max-w-fit mx-auto sm:inline-flex" style="display: none;"></span>
                    <h2 id="detail-pathway-name" class="text-3xl lg:text-4xl font-heading font-black text-dranhs-dark tracking-tight leading-tight px-4">Pathway Name Here</h2>
                    <p id="detail-desc" class="text-slate-600 leading-relaxed font-medium mt-4 max-w-2xl mx-auto px-4">Pathway description goes here.</p>
                </div>

                <div class="flex-1 flex flex-col gap-6">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4 lg:gap-6">
                        <!-- Careers Box -->
                        <div class="bg-white border border-slate-200 rounded-2xl p-5 lg:p-6 shadow-sm">
                            <h3 class="text-xs font-black uppercase text-slate-400 tracking-widest mb-3 flex items-center gap-2">
                                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"></path><polyline points="3.27 6.96 12 12.01 20.73 6.96"></polyline><line x1="12" y1="22.08" x2="12" y2="12"></line></svg>
                                Possible Careers
                            </h3>
                            <ul id="detail-careers" class="text-slate-700 font-bold text-sm lg:text-base leading-snug space-y-2 list-disc pl-4 marker:text-slate-300">
                                <!-- JS Injection -->
                            </ul>
                        </div>
                        <!-- Subjects Box -->
                        <div class="bg-white border border-slate-200 rounded-2xl p-5 lg:p-6 shadow-sm">
                            <h3 id="detail-subjects-title" class="text-xs font-black uppercase text-slate-400 tracking-widest mb-3 flex items-center gap-2">
                                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M2 3h6a4 4 0 0 1 4 4v14a3 3 0 0 0-3-3H2z"></path><path d="M22 3h-6a4 4 0 0 0-4 4v14a3 3 0 0 1 3-3h7z"></path></svg>
                                Grade 11 Electives
                            </h3>
                            <ul id="detail-subjects" class="text-slate-700 font-bold text-sm lg:text-base leading-snug space-y-2 list-disc pl-4 marker:text-slate-300">
                                <!-- JS Injection -->
                            </ul>
                        </div>
                    </div>
                    
                    <!-- Call to Action -->
                    <div class="text-center my-4">
                        <button id="btn-continue-enrollment" class="bg-dranhs-dark hover:bg-black text-white px-10 py-3.5 w-full sm:w-auto mx-auto rounded-full font-black text-sm transition-transform hover:-translate-y-1 shadow-lg hover:shadow-xl uppercase tracking-widest cursor-pointer inline-flex justify-center items-center gap-2">
                            CONTINUE ENROLLMENT 
                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><line x1="5" y1="12" x2="19" y2="12"></line><polyline points="12 5 19 12 12 19"></polyline></svg>
                        </button>
                    </div>

                    <!-- Universal Core Footer -->
                    <div class="mt-auto bg-slate-100 rounded-2xl p-5 border border-slate-200/60">
                        <h4 class="text-[0.65rem] font-bold uppercase text-slate-500 tracking-widest text-center mb-3">Core Subjects</h4>
                        <div class="flex flex-wrap justify-center gap-2 text-[0.65rem] lg:text-xs font-semibold text-slate-600">
                            <span class="bg-white px-3 py-1.5 rounded-md shadow-sm border border-slate-200">Effective Communication</span>
                            <span class="bg-white px-3 py-1.5 rounded-md shadow-sm border border-slate-200">General Math</span>
                            <span class="bg-white px-3 py-1.5 rounded-md shadow-sm border border-slate-200">General Science</span>
                            <span class="bg-white px-3 py-1.5 rounded-md shadow-sm border border-slate-200">Life & Career Skills</span>
                            <span class="bg-white px-3 py-1.5 rounded-md shadow-sm border border-slate-200">Kasaysayan at Lipunang Pilipino</span>
                        </div>
                    </div>
                </div>
            </div>

        </div>
    </div>
</div>
