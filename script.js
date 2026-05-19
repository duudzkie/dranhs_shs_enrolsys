document.addEventListener('DOMContentLoaded', () => {
    // Debug: ensure script runs (remove once confirmed working)
    console.debug('[EnrollModal] DOMContentLoaded');

    // Modal Logic & View Management
    const startEnrollBtn = document.getElementById('btn-start-enroll') ||
        Array.from(document.querySelectorAll('button')).find(b => b.textContent.trim().toLowerCase().includes('enrollment'));
    const modal = document.getElementById('enroll-modal');
    const modalContent = document.getElementById('enroll-modal-content');
    const closeModalBtn = document.getElementById('close-modal');

    if (!startEnrollBtn) console.warn('[EnrollModal] startEnrollBtn not found');
    if (!modal) console.warn('[EnrollModal] modal element not found');
    if (!modalContent) console.warn('[EnrollModal] modalContent element not found');
    if (!closeModalBtn) console.warn('[EnrollModal] closeModalBtn element not found');

    // Views
    const viewGradeSelection = document.getElementById('view-grade-selection');
    const viewGrade12 = document.getElementById('view-grade-12');
    const viewGrade11 = document.getElementById('view-grade-11');
    const viewPathwayDetails = document.getElementById('view-pathway-details');
    const btnGrade11 = document.getElementById('btn-grade-11');
    const btnGrade12 = document.getElementById('btn-grade-12');
    const backBtns = document.querySelectorAll('.back-to-grades');

    // Tabs
    const tabAcademic = document.getElementById('tab-academic');
    const tabTechpro = document.getElementById('tab-techpro');
    const tabTvl = document.getElementById('tab-tvl');
    const tabAls = document.getElementById('tab-als');
    const contentAcademic = document.getElementById('content-academic');
    const contentTechpro = document.getElementById('content-techpro');
    const contentTvl = document.getElementById('content-tvl');
    const contentAls = document.getElementById('content-als');

    const resetViews = () => {
        // Hide sub-views and show main view
        viewGradeSelection.classList.remove('hidden');
        viewGrade12.classList.add('hidden');
        viewGrade11.classList.add('hidden');
        if (viewPathwayDetails) viewPathwayDetails.classList.add('hidden');
        const viewG12Confirm = document.getElementById('view-g12-confirm');
        if (viewG12Confirm) viewG12Confirm.classList.add('hidden');

        // Reset tabs to Academic
        if (tabAcademic) {
            tabAcademic.className = "py-2 lg:py-3 px-3 lg:px-6 border-b-[3px] border-emerald-600 bg-emerald-50 text-emerald-700 rounded-t-xl font-black uppercase text-[0.65rem] sm:text-xs lg:text-sm tracking-widest transition-colors mb-[-2px] flex-1 min-w-[110px] max-w-[200px]";
            tabTechpro.className = "py-2 lg:py-3 px-3 lg:px-6 border-b-[3px] border-transparent bg-transparent text-slate-400 hover:text-slate-700 hover:bg-slate-50 rounded-t-xl font-black uppercase text-[0.65rem] sm:text-xs lg:text-sm tracking-widest transition-colors mb-[-2px] flex-1 min-w-[110px] max-w-[200px]";
            if (tabTvl) tabTvl.className = "py-2 lg:py-3 px-3 lg:px-6 border-b-[3px] border-transparent bg-transparent text-slate-400 hover:text-slate-700 hover:bg-slate-50 rounded-t-xl font-black uppercase text-[0.65rem] sm:text-xs lg:text-sm tracking-widest transition-colors mb-[-2px] flex-1 min-w-[110px] max-w-[200px]";
            if (tabAls) tabAls.className = "py-2 lg:py-3 px-3 lg:px-6 border-b-[3px] border-transparent bg-transparent text-slate-400 hover:text-slate-700 hover:bg-slate-50 rounded-t-xl font-black uppercase text-[0.65rem] sm:text-xs lg:text-sm tracking-widest transition-colors mb-[-2px] flex-1 min-w-[110px] max-w-[200px]";
            
            contentAcademic.classList.remove('hidden');
            contentTechpro.classList.add('hidden');
            if (contentTvl) contentTvl.classList.add('hidden');
            if (contentAls) contentAls.classList.add('hidden');
        }
    };

    if (startEnrollBtn && modal && modalContent && closeModalBtn) {
        // Guard: make sure all modal views exist before wiring up events
        if (!viewGradeSelection || !viewGrade12 || !viewGrade11 || !viewPathwayDetails) {
            return;
        }

        // Open modal
        startEnrollBtn.addEventListener('click', () => {
            resetViews();
            modal.classList.remove('hidden');
            setTimeout(() => {
                modal.classList.remove('opacity-0');
                modalContent.classList.remove('scale-95');
                modalContent.classList.add('scale-100');
            }, 10);
        });

        // Close modal function
        const closeModal = () => {
            modal.classList.add('opacity-0');
            modalContent.classList.remove('scale-100');
            modalContent.classList.add('scale-95');

            setTimeout(() => {
                modal.classList.add('hidden');
            }, 300);
        };

        closeModalBtn.addEventListener('click', closeModal);

        modal.addEventListener('click', (e) => {
            if (e.target === modal) {
                closeModal();
            }
        });

        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape' && !modal.classList.contains('hidden')) {
                closeModal();
            }
        });

        // Sub-view Navigation
        if (btnGrade11) {
            btnGrade11.addEventListener('click', () => {
                viewGradeSelection.classList.add('hidden');
                viewGrade11.classList.remove('hidden');
                modalContent.querySelector('.overflow-y-auto').scrollTop = 0;
            });
        }

        if (btnGrade12) {
            btnGrade12.addEventListener('click', () => {
                viewGradeSelection.classList.add('hidden');
                viewGrade12.classList.remove('hidden');
                modalContent.querySelector('.overflow-y-auto').scrollTop = 0;
            });
        }

        backBtns.forEach((btn) => {
            btn.addEventListener('click', () => {
                resetViews();
                modalContent.querySelector('.overflow-y-auto').scrollTop = 0;
            });
        });

        // G12 Navigation Logic
        const viewG12Confirm = document.getElementById('view-g12-confirm');
        const g12LrnForm = document.getElementById('g12-lrn-form');
        const g12LrnInput = document.getElementById('g12-lrn-input');
        const g12LrnVerifyBtn = document.getElementById('g12-lrn-verify-btn');
        const g12LrnSpinner = document.getElementById('g12-lrn-spinner');
        const g12LrnStatus = document.getElementById('g12-lrn-status');
        const g12NewAppBtn = document.getElementById('g12-new-app-btn');
        const backToG12Form = document.getElementById('back-to-g12-form');
        const g12ConfirmProceedBtn = document.getElementById('g12-confirm-proceed-btn');

        // Stores verified G11 completer data
        let g12CompleterData = null;

        function showG12Status(msg, type) {
            // type: 'error' | 'success' | 'warning'
            g12LrnStatus.classList.remove('hidden', 'bg-red-50', 'text-red-700', 'bg-green-50', 'text-green-700', 'bg-amber-50', 'text-amber-700', 'border-red-200', 'border-green-200', 'border-amber-200', 'border');
            if (type === 'error') {
                g12LrnStatus.className = 'block mt-4 p-4 rounded-xl text-sm font-semibold bg-red-50 text-red-700 border border-red-200';
            } else if (type === 'success') {
                g12LrnStatus.className = 'block mt-4 p-4 rounded-xl text-sm font-semibold bg-green-50 text-green-700 border border-green-200';
            } else {
                g12LrnStatus.className = 'block mt-4 p-4 rounded-xl text-sm font-semibold bg-amber-50 text-amber-700 border border-amber-200';
            }
            g12LrnStatus.textContent = msg;
        }

        if (g12LrnForm) {
            g12LrnForm.addEventListener('submit', async (e) => {
                e.preventDefault();
                const lrn = g12LrnInput ? g12LrnInput.value.trim() : '';
                if (!/^\d{12}$/.test(lrn)) {
                    showG12Status('LRN must be exactly 12 digits.', 'error');
                    return;
                }

                // Show loading state
                if (g12LrnSpinner) g12LrnSpinner.classList.remove('hidden');
                if (g12LrnVerifyBtn) g12LrnVerifyBtn.disabled = true;
                if (g12LrnStatus) g12LrnStatus.className = 'hidden';

                try {
                    const res = await fetch('scripts/check_lrn.php?lrn=' + encodeURIComponent(lrn), {
                        headers: { 'Accept': 'application/json' }
                    });
                    if (!res.ok) {
                        throw new Error('LRN verification request failed.');
                    }
                    const data = await res.json();

                    if (!data.ok) {
                        showG12Status('Verification failed. Please try again.', 'error');
                        return;
                    }

                    if (data.exists) {
                        showG12Status('⚠️ This LRN is already enrolled in the system.', 'error');
                        return;
                    }

                    if (!data.g11_completer) {
                        showG12Status('❌ Student not found in the Grade 11 completers list. Please verify your LRN or see the class adviser.', 'error');
                        return;
                    }

                    // G11 completer found — store data and show confirmation view
                    g12CompleterData = {
                        lrn: lrn,
                        lastName: data.g11_completer_last_name || '',
                        firstName: data.g11_completer_first_name || '',
                        middleName: data.g11_completer_middle_name || '',
                        strand: data.g11_completer_strand || '',
                        section: data.g11_completer_section || '',
                    };

                    // Populate confirmation view
                    const fullName = [g12CompleterData.lastName, g12CompleterData.firstName, g12CompleterData.middleName]
                        .filter(Boolean).join(', ');
                    document.getElementById('g12c-lrn').textContent = lrn;
                    document.getElementById('g12c-strand').textContent = g12CompleterData.strand || '(not set)';
                    document.getElementById('g12c-name').textContent = fullName || '—';
                    document.getElementById('g12c-section').textContent = g12CompleterData.section || '—';

                    // Transition to confirmation view
                    viewGrade12.classList.add('hidden');
                    if (viewG12Confirm) {
                        viewG12Confirm.classList.remove('hidden');
                        modalContent.querySelector('.overflow-y-auto').scrollTop = 0;
                    }

                } catch (err) {
                    showG12Status('Network error. Please check your connection and try again.', 'error');
                } finally {
                    if (g12LrnSpinner) g12LrnSpinner.classList.add('hidden');
                    if (g12LrnVerifyBtn) g12LrnVerifyBtn.disabled = false;
                }
            });
        }

        // Back from confirmation to G12 form
        if (backToG12Form) {
            backToG12Form.addEventListener('click', () => {
                if (viewG12Confirm) viewG12Confirm.classList.add('hidden');
                viewGrade12.classList.remove('hidden');
                modalContent.querySelector('.overflow-y-auto').scrollTop = 0;
            });
        }

        // Proceed to G12 enrollment form (Grade 11 Completer path — skip evaluation)
        if (g12ConfirmProceedBtn) {
            g12ConfirmProceedBtn.addEventListener('click', () => {
                if (!g12CompleterData) return;
                const params = new URLSearchParams({
                    lrn: g12CompleterData.lrn,
                    last_name: g12CompleterData.lastName,
                    first_name: g12CompleterData.firstName,
                    middle_name: g12CompleterData.middleName,
                    strand: g12CompleterData.strand,
                    section: g12CompleterData.section,
                    student_type: 'Old Student (Grade 11 Completer)',
                    g11_verified: '1',
                });
                window.location.href = 'enrollment_form_g12.php?' + params.toString();
            });
        }

        // New Application button (Transferee/Repeater/Returnee — goes to evaluation)
        if (g12NewAppBtn) {
            g12NewAppBtn.addEventListener('click', () => {
                window.location.href = 'enrollment_form_g12.php';
            });
        }

        // G11 Navigation Logic
        const btnContinueEnrollment = document.getElementById('btn-continue-enrollment');
        const detailTrackName = document.getElementById('detail-track-name');
        const detailPathwayName = document.getElementById('detail-pathway-name');
        const backToG11TracksBtn = document.querySelector('.back-to-g11-tracks');

        let selectedG11Track = '';
        let selectedG11Pathway = '';

        // Handle Pathway Card Clicks
        const pathwayCards = document.querySelectorAll('.pathway-card');
        const pathwayData = window.PATHWAY_CATALOG_DETAILS || {};
        pathwayCards.forEach((card) => {
            card.addEventListener('click', () => {
                selectedG11Track = card.getAttribute('data-track');
                selectedG11Pathway = card.getAttribute('data-pathway');
                const data = (pathwayData[selectedG11Track] && pathwayData[selectedG11Track][selectedG11Pathway]) ? pathwayData[selectedG11Track][selectedG11Pathway] : { description: '', electives: [] };
                const careersList = document.getElementById('detail-careers');
                const subjectsList = document.getElementById('detail-subjects');
                const subjectsTitle = document.getElementById('detail-subjects-title');
                const detailSchedule = document.getElementById('detail-schedule');

                if (detailTrackName) {
                    detailTrackName.textContent = selectedG11Track.toUpperCase() + ' TRACK';
                    if (selectedG11Track === 'Tech-Pro') {
                        detailTrackName.className = "inline-block px-3 py-1 bg-amber-100 text-orange-700 border border-orange-200 rounded-full text-[0.65rem] font-bold uppercase tracking-widest mb-3";
                    } else if (selectedG11Track === 'ALS') {
                        detailTrackName.className = "inline-block px-3 py-1 bg-rose-100 text-rose-700 border border-rose-200 rounded-full text-[0.65rem] font-bold uppercase tracking-widest mb-3";
                    } else if (selectedG11Track === 'TVL') {
                        detailTrackName.className = "inline-block px-3 py-1 bg-slate-100 text-slate-700 border border-slate-200 rounded-full text-[0.65rem] font-bold uppercase tracking-widest mb-3";
                    } else {
                        detailTrackName.className = "inline-block px-3 py-1 bg-emerald-100 text-emerald-800 border border-emerald-200 rounded-full text-[0.65rem] font-bold uppercase tracking-widest mb-3";
                    }
                }

                if (detailPathwayName) detailPathwayName.textContent = selectedG11Pathway;

                if (detailSchedule) {
                    if (data.schedule) {
                        detailSchedule.innerHTML = '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"></rect><line x1="16" y1="2" x2="16" y2="6"></line><line x1="8" y1="2" x2="8" y2="6"></line><line x1="3" y1="10" x2="21" y2="10"></line></svg> ' + data.schedule;
                        detailSchedule.style.display = '';
                    } else {
                        detailSchedule.style.display = 'none';
                    }
                }

                if (careersList) {
                    if (Array.isArray(data.careers) && data.careers.length > 0) {
                        careersList.innerHTML = data.careers.map((c) => `<li>${c}</li>`).join('');
                    } else if (data.description) {
                        careersList.innerHTML = `<li>${data.description}</li>`;
                    } else {
                        careersList.innerHTML = '<li>No career details available for this pathway yet.</li>';
                    }
                }
                if (subjectsList) {
                    const electives = Array.isArray(data.electives) ? data.electives : [];
                    subjectsList.innerHTML = electives.length > 0 ? electives.map((s) => `<li>${s}</li>`).join('') : '<li>No grade 11 electives configured for this pathway.</li>';
                }

                if (subjectsTitle) {
                    subjectsTitle.textContent = selectedG11Track === 'ALS' ? 'ALS Focus Areas' : 'Grade 11 Electives';
                }

                // Transition View
                viewGrade11.classList.add('hidden');
                viewPathwayDetails.classList.remove('hidden');
                modalContent.querySelector('.overflow-y-auto').scrollTop = 0;
            });
        });

        // Handle Back from Details to Grade 11
        if (backToG11TracksBtn) {
            backToG11TracksBtn.addEventListener('click', () => {
                viewPathwayDetails.classList.add('hidden');
                viewGrade11.classList.remove('hidden');
                modalContent.querySelector('.overflow-y-auto').scrollTop = 0;
            });
        }

        // Final Continue to Form
        if (btnContinueEnrollment) {
            btnContinueEnrollment.addEventListener('click', () => {
                window.location.href = 'enrollment_form_g11.php?track=' + encodeURIComponent(selectedG11Track) + '&pathway=' + encodeURIComponent(selectedG11Pathway);
            });
        }

        // Tabs Logic
        if (tabAcademic && tabTechpro && tabAls) {
            tabAcademic.addEventListener('click', () => {
                tabAcademic.className = "py-2 lg:py-3 px-3 lg:px-6 border-b-[3px] border-emerald-600 bg-emerald-50 text-emerald-700 rounded-t-xl font-black uppercase text-[0.65rem] sm:text-xs lg:text-sm tracking-widest transition-colors mb-[-2px] flex-1 min-w-[110px] max-w-[200px]";
                tabTechpro.className = "py-2 lg:py-3 px-3 lg:px-6 border-b-[3px] border-transparent bg-transparent text-slate-400 hover:text-slate-700 hover:bg-slate-50 rounded-t-xl font-black uppercase text-[0.65rem] sm:text-xs lg:text-sm tracking-widest transition-colors mb-[-2px] flex-1 min-w-[110px] max-w-[200px]";
                if (tabTvl) tabTvl.className = "py-2 lg:py-3 px-3 lg:px-6 border-b-[3px] border-transparent bg-transparent text-slate-400 hover:text-slate-700 hover:bg-slate-50 rounded-t-xl font-black uppercase text-[0.65rem] sm:text-xs lg:text-sm tracking-widest transition-colors mb-[-2px] flex-1 min-w-[110px] max-w-[200px]";
                tabAls.className = "py-2 lg:py-3 px-3 lg:px-6 border-b-[3px] border-transparent bg-transparent text-slate-400 hover:text-slate-700 hover:bg-slate-50 rounded-t-xl font-black uppercase text-[0.65rem] sm:text-xs lg:text-sm tracking-widest transition-colors mb-[-2px] flex-1 min-w-[110px] max-w-[200px]";
                contentAcademic.classList.remove('hidden');
                contentTechpro.classList.add('hidden');
                if (contentTvl) contentTvl.classList.add('hidden');
                contentAls.classList.add('hidden');
            });

            tabTechpro.addEventListener('click', () => {
                tabTechpro.className = "py-2 lg:py-3 px-3 lg:px-6 border-b-[3px] border-blue-600 bg-blue-50 text-blue-700 rounded-t-xl font-black uppercase text-[0.65rem] sm:text-xs lg:text-sm tracking-widest transition-colors mb-[-2px] flex-1 min-w-[110px] max-w-[200px]";
                tabAcademic.className = "py-2 lg:py-3 px-3 lg:px-6 border-b-[3px] border-transparent bg-transparent text-slate-400 hover:text-slate-700 hover:bg-slate-50 rounded-t-xl font-black uppercase text-[0.65rem] sm:text-xs lg:text-sm tracking-widest transition-colors mb-[-2px] flex-1 min-w-[110px] max-w-[200px]";
                if (tabTvl) tabTvl.className = "py-2 lg:py-3 px-3 lg:px-6 border-b-[3px] border-transparent bg-transparent text-slate-400 hover:text-slate-700 hover:bg-slate-50 rounded-t-xl font-black uppercase text-[0.65rem] sm:text-xs lg:text-sm tracking-widest transition-colors mb-[-2px] flex-1 min-w-[110px] max-w-[200px]";
                tabAls.className = "py-2 lg:py-3 px-3 lg:px-6 border-b-[3px] border-transparent bg-transparent text-slate-400 hover:text-slate-700 hover:bg-slate-50 rounded-t-xl font-black uppercase text-[0.65rem] sm:text-xs lg:text-sm tracking-widest transition-colors mb-[-2px] flex-1 min-w-[110px] max-w-[200px]";
                contentTechpro.classList.remove('hidden');
                contentAcademic.classList.add('hidden');
                if (contentTvl) contentTvl.classList.add('hidden');
                contentAls.classList.add('hidden');
            });
            
            if (tabTvl) {
                tabTvl.addEventListener('click', () => {
                    tabTvl.className = "py-2 lg:py-3 px-3 lg:px-6 border-b-[3px] border-slate-600 bg-slate-50 text-slate-700 rounded-t-xl font-black uppercase text-[0.65rem] sm:text-xs lg:text-sm tracking-widest transition-colors mb-[-2px] flex-1 min-w-[110px] max-w-[200px]";
                    tabAcademic.className = "py-2 lg:py-3 px-3 lg:px-6 border-b-[3px] border-transparent bg-transparent text-slate-400 hover:text-slate-700 hover:bg-slate-50 rounded-t-xl font-black uppercase text-[0.65rem] sm:text-xs lg:text-sm tracking-widest transition-colors mb-[-2px] flex-1 min-w-[110px] max-w-[200px]";
                    tabTechpro.className = "py-2 lg:py-3 px-3 lg:px-6 border-b-[3px] border-transparent bg-transparent text-slate-400 hover:text-slate-700 hover:bg-slate-50 rounded-t-xl font-black uppercase text-[0.65rem] sm:text-xs lg:text-sm tracking-widest transition-colors mb-[-2px] flex-1 min-w-[110px] max-w-[200px]";
                    tabAls.className = "py-2 lg:py-3 px-3 lg:px-6 border-b-[3px] border-transparent bg-transparent text-slate-400 hover:text-slate-700 hover:bg-slate-50 rounded-t-xl font-black uppercase text-[0.65rem] sm:text-xs lg:text-sm tracking-widest transition-colors mb-[-2px] flex-1 min-w-[110px] max-w-[200px]";
                    if (contentTvl) contentTvl.classList.remove('hidden');
                    contentAcademic.classList.add('hidden');
                    contentTechpro.classList.add('hidden');
                    contentAls.classList.add('hidden');
                });
            }

            tabAls.addEventListener('click', () => {
                tabAls.className = "py-2 lg:py-3 px-3 lg:px-6 border-b-[3px] border-rose-600 bg-rose-50 text-rose-700 rounded-t-xl font-black uppercase text-[0.65rem] sm:text-xs lg:text-sm tracking-widest transition-colors mb-[-2px] flex-1 min-w-[110px] max-w-[200px]";
                tabAcademic.className = "py-2 lg:py-3 px-3 lg:px-6 border-b-[3px] border-transparent bg-transparent text-slate-400 hover:text-slate-700 hover:bg-slate-50 rounded-t-xl font-black uppercase text-[0.65rem] sm:text-xs lg:text-sm tracking-widest transition-colors mb-[-2px] flex-1 min-w-[110px] max-w-[200px]";
                tabTechpro.className = "py-2 lg:py-3 px-3 lg:px-6 border-b-[3px] border-transparent bg-transparent text-slate-400 hover:text-slate-700 hover:bg-slate-50 rounded-t-xl font-black uppercase text-[0.65rem] sm:text-xs lg:text-sm tracking-widest transition-colors mb-[-2px] flex-1 min-w-[110px] max-w-[200px]";
                if (tabTvl) tabTvl.className = "py-2 lg:py-3 px-3 lg:px-6 border-b-[3px] border-transparent bg-transparent text-slate-400 hover:text-slate-700 hover:bg-slate-50 rounded-t-xl font-black uppercase text-[0.65rem] sm:text-xs lg:text-sm tracking-widest transition-colors mb-[-2px] flex-1 min-w-[110px] max-w-[200px]";
                contentAls.classList.remove('hidden');
                contentAcademic.classList.add('hidden');
                contentTechpro.classList.add('hidden');
                if (contentTvl) contentTvl.classList.add('hidden');
            });
        }
    }
});
