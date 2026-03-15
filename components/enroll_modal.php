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
                    <div id="btn-grade-11" class="group relative bg-white border-2 border-slate-200 rounded-3xl overflow-hidden cursor-pointer hover:border-violet-600 transition-all duration-300 hover:shadow-xl hover:-translate-y-1 flex flex-col">
                        <div class="h-2 w-full bg-violet-600"></div>
                        <div class="p-8 relative z-10 flex flex-col h-full items-center text-center">
                            <h3 class="text-3xl font-black text-dranhs-dark mb-2 font-heading tracking-tight mt-4">GRADE 11</h3>
                            <p class="text-violet-600 font-bold mb-4 tracking-wider uppercase text-[0.65rem] lg:text-xs bg-violet-50 py-1.5 px-4 rounded-full">New Curriculum</p>
                            <p class="text-slate-600 mb-8 text-sm leading-relaxed flex-grow">
                                For incoming Grade 11 students entering under the updated academic tracking structure.
                            </p>
                            <button class="w-full py-3 px-6 rounded-xl bg-slate-50 group-hover:bg-violet-600 group-hover:text-white text-slate-700 font-bold transition-colors uppercase text-sm tracking-wide shadow-sm">
                                Proceed
                            </button>
                        </div>
                    </div>

                    <!-- Card 2: Incoming Grade 12 -->
                    <div id="btn-grade-12" class="group relative bg-white border-2 border-slate-200 rounded-3xl overflow-hidden cursor-pointer hover:border-pink-500 transition-all duration-300 hover:shadow-xl hover:-translate-y-1 flex flex-col">
                        <div class="h-2 w-full bg-pink-500"></div>
                        <div class="p-8 relative z-10 flex flex-col h-full items-center text-center">
                            <h3 class="text-3xl font-black text-dranhs-dark mb-2 font-heading tracking-tight mt-4">GRADE 12</h3>
                            <p class="text-pink-600 font-bold mb-4 tracking-wider uppercase text-[0.65rem] lg:text-xs bg-pink-50 py-1.5 px-4 rounded-full">Old Curriculum</p>
                            <p class="text-slate-600 mb-8 text-sm leading-relaxed flex-grow">
                                For continuing students finishing their senior high school track under the existing framework.
                            </p>
                            <button class="w-full py-3 px-6 rounded-xl bg-slate-50 group-hover:bg-pink-500 group-hover:text-white text-slate-700 font-bold transition-colors uppercase text-sm tracking-wide shadow-sm">
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
                        <h3 class="font-bold text-slate-800 text-lg mb-1">Old Student</h3>
                        <p class="text-xs text-slate-500 mb-5">Please enter your Learner Reference Number (LRN)</p>
                        <form class="flex flex-col sm:flex-row gap-3" onsubmit="event.preventDefault()">
                            <input type="text" placeholder="Enter 12-digit LRN" required pattern="[0-9]{12}" title="Please enter a valid 12-digit LRN" class="flex-1 bg-white border border-slate-200 px-4 py-3 rounded-xl focus:border-pink-500 focus:ring-2 focus:ring-pink-500/20 outline-none font-medium shadow-sm transition-all" />
                            <button type="submit" class="bg-pink-500 hover:bg-pink-600 text-white px-8 py-3 rounded-xl font-bold transition-colors uppercase tracking-wide shadow-md">Verify LRN</button>
                        </form>
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
                        <button class="bg-slate-800 hover:bg-slate-900 text-white px-8 py-3.5 rounded-xl font-bold w-full max-w-sm transition-colors uppercase tracking-widest shadow-md">NEW APPLICATION</button>
                    </div>
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
                <div class="flex gap-2 lg:gap-6 border-b border-slate-200 mb-6 shrink-0 w-full justify-center">
                    <button id="tab-academic" class="pb-3 px-2 lg:px-6 border-b-[3px] border-emerald-600 text-emerald-700 font-black uppercase text-[0.65rem] sm:text-xs lg:text-sm tracking-widest transition-colors mb-[-2px]">ACADEMIC TRACK</button>
                    <button id="tab-techpro" class="pb-3 px-2 lg:px-6 border-b-[3px] border-transparent text-slate-400 hover:text-slate-700 font-black uppercase text-[0.65rem] sm:text-xs lg:text-sm tracking-widest transition-colors mb-[-2px]">TECH-PRO TRACK</button>
                </div>

                <!-- Academic Track Content (11 Pathways) -->
                <div id="content-academic" class="grid grid-cols-1 md:grid-cols-2 gap-3 w-full">
                    <?php
                        $acad_tracks = [
                            "Medical & Allied Health", "Engineering & Aviation", "Earth, Space & Weather Science",
                            "Pre-Law & Public Governance", "Criminology & Uniformed Services", "Teacher Ed (Lang/Social Sci)",
                            "Social Work & Community Dev.", "Digital Media & Creative Arts", "Accountancy & Financial Mgmt.",
                            "Entrepreneurship & Innovation", "FITNESS AND ATHLETICS DEVELOPMENT"
                        ];
                        foreach($acad_tracks as $track) {
                            echo '<button class="pathway-card relative flex bg-blue-900 border-2 border-transparent rounded-xl p-4 lg:p-5 cursor-pointer hover:border-emerald-400 hover:shadow-lg transition-all group items-center text-left" data-track="Academic" data-pathway="'.$track.'">
                                <div class="w-10 h-10 rounded-full bg-emerald-500/20 text-emerald-400 flex items-center justify-center mr-4 shrink-0 transition-transform group-hover:scale-110">
                                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"></path><polyline points="22 4 12 14.01 9 11.01"></polyline></svg>
                                </div>
                                <span class="font-bold text-slate-100 group-hover:text-emerald-300 group-hover:scale-[1.02] transform transition-transform uppercase text-[0.7rem] lg:text-[0.8rem] tracking-wide leading-snug">'.$track.'</span>
                            </button>';
                        }
                    ?>
                </div>

                <!-- Tech-Pro Track Content (4 Clusters) -->
                <div id="content-techpro" class="grid grid-cols-1 md:grid-cols-2 gap-3 w-full hidden">
                    <?php
                        $tech_tracks = [
                            "ICT (Computer Systems Servicing)",
                            "Industrial (Electrical Installation)",
                            "Kitchen Operations (Cookery)",
                            "Aesthetic Services (Beauty Care)"
                        ];
                        foreach($tech_tracks as $track) {
                            echo '<button class="pathway-card relative flex bg-amber-50 border-2 border-slate-200 rounded-xl p-4 lg:p-5 cursor-pointer hover:border-orange-500 hover:bg-orange-50 hover:shadow-lg transition-all group items-center text-left" data-track="Tech-Pro" data-pathway="'.$track.'">
                                <div class="w-10 h-10 rounded-full bg-orange-100 text-orange-600 flex items-center justify-center mr-4 shrink-0 transition-transform group-hover:scale-110">
                                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M14.7 6.3a1 1 0 0 0 0 1.4l1.6 1.6a1 1 0 0 0 1.4 0l3.77-3.77a6 6 0 0 1-7.94 7.94l-6.91 6.91a2.12 2.12 0 0 1-3-3l6.91-6.91a6 6 0 0 1 7.94-7.94l-3.76 3.76z"></path></svg>
                                </div>
                                <span class="font-bold text-orange-900 group-hover:text-orange-700 group-hover:scale-[1.02] transform transition-transform uppercase text-[0.7rem] lg:text-[0.8rem] tracking-wide leading-snug">'.$track.'</span>
                            </button>';
                        }
                    ?>
                </div>
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
                        <h4 class="text-[0.65rem] font-bold uppercase text-slate-500 tracking-widest text-center mb-3">Universal Core Subjects (160 Hrs/Yr)</h4>
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
