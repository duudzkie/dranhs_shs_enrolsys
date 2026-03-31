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
    const tabAls = document.getElementById('tab-als');
    const contentAcademic = document.getElementById('content-academic');
    const contentTechpro = document.getElementById('content-techpro');
    const contentAls = document.getElementById('content-als');

    const resetViews = () => {
        // Hide sub-views and show main view
        viewGradeSelection.classList.remove('hidden');
        viewGrade12.classList.add('hidden');
        viewGrade11.classList.add('hidden');
        if (viewPathwayDetails) viewPathwayDetails.classList.add('hidden');

        // Reset tabs to Academic
        if (tabAcademic) {
            tabAcademic.className = "py-2 lg:py-3 px-3 lg:px-6 border-b-[3px] border-emerald-600 bg-emerald-50 text-emerald-700 rounded-t-xl font-black uppercase text-[0.65rem] sm:text-xs lg:text-sm tracking-widest transition-colors mb-[-2px] flex-1 min-w-[110px] max-w-[200px]";
            tabTechpro.className = "py-2 lg:py-3 px-3 lg:px-6 border-b-[3px] border-transparent bg-transparent text-slate-400 hover:text-slate-700 hover:bg-slate-50 rounded-t-xl font-black uppercase text-[0.65rem] sm:text-xs lg:text-sm tracking-widest transition-colors mb-[-2px] flex-1 min-w-[110px] max-w-[200px]";
            if (tabAls) tabAls.className = "py-2 lg:py-3 px-3 lg:px-6 border-b-[3px] border-transparent bg-transparent text-slate-400 hover:text-slate-700 hover:bg-slate-50 rounded-t-xl font-black uppercase text-[0.65rem] sm:text-xs lg:text-sm tracking-widest transition-colors mb-[-2px] flex-1 min-w-[110px] max-w-[200px]";
            
            contentAcademic.classList.remove('hidden');
            contentTechpro.classList.add('hidden');
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
        const g12Form = viewGrade12 ? viewGrade12.querySelector('form') : null;
        const g12NewBtn = viewGrade12 ? viewGrade12.querySelector('button.bg-slate-800') : null;

        if (g12Form) {
            g12Form.addEventListener('submit', (e) => {
                e.preventDefault();
                const lrnInput = g12Form.querySelector('input').value;
                window.location.href = 'enrollment_form_g12.php?lrn=' + encodeURIComponent(lrnInput);
            });
        }

        if (g12NewBtn) {
            g12NewBtn.addEventListener('click', () => {
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

        // Comprehensive Pathway Data Dictionary
        const pathwayData = {
            "Medical & Allied Health": {
                desc: "This pathway focuses on the biological and chemical foundations required for clinical research and healthcare delivery.",
                careers: ["Doctors", "Nurses", "Pharmacists", "Medical Technologists"],
                subjects: ["Biology 1 & 2", "Chemistry 1 & 2"],
            },
            "Engineering & Aviation": {
                desc: "This pathway prioritizes physical laws and mathematical reasoning to prepare for design, structural engineering, and flight industries.",
                careers: ["Civil/Mechanical Engineers", "Pilots", "Architects", "Avionics Technicians"],
                subjects: ["Physics 1 & 2", "Finite Mathematics 1 & 2"],
            },
            "Earth, Space & Weather Science": {
                desc: "This pathway explores atmospheric systems, geology, and the cosmos through advanced scientific data and observation.",
                careers: ["Meteorologists", "Geologists", "Astronomers", "Weather Experts"],
                subjects: ["Earth and Space Science 1 & 2", "Finite Mathematics 1 & 2"],
            },
            "Pre-Law & Public Governance": {
                desc: "This pathway develops critical reasoning and legal literacy for leadership in public service and justice systems.",
                careers: ["Lawyers", "Public Servants", "Legal Researchers", "Diplomats"],
                subjects: ["Philippine Governance", "Social Sciences (Theory & Practice)", "Creative Composition 1", "Contemporary Literature 1"],
            },
            "Criminology & Uniformed Services": {
                desc: "This pathway prepares students for community protection and national defense through civic duty and movement science.",
                careers: ["Police Officers", "Soldiers", "Firefighters", "Criminologists"],
                subjects: ["Social Sciences (Theory & Practice)", "Philippine Governance", "Citizenship & Civic Engagement", "Human Movement 1"],
            },
            "Teacher Ed (Lang/Social Sci)": {
                desc: "This pathway focuses on pedagogical foundations, philosophy, and language proficiency for future classroom leaders.",
                careers: ["High School Teachers", "School Administrators", "Corporate Trainers"],
                subjects: ["Introduction to Philosophy", "Social Sciences (Theory & Practice)", "Malikhaing Pagsulat", "Filipino 1"],
            },
            "Social Work & Community Dev.": {
                desc: "This pathway emphasizes ethical intervention and community-level social improvements to promote societal welfare.",
                careers: ["Social Workers", "NGO Program Officers", "Community Organizers"],
                subjects: ["Social Sciences (Theory & Practice)", "Introduction to Philosophy", "Citizenship & Civic Engagement", "Philippine Governance"],
            },
            "Digital Media & Creative Arts": {
                desc: "This pathway blends visual arts and creative composition to communicate powerful ideas in the modern digital landscape.",
                careers: ["Graphic Designers", "Writers", "Content Creators", "Digital Artists"],
                subjects: ["Arts 1 & 2 (Creative Industries)", "Creative Composition 1 & 2"],
            },
            "Accountancy & Financial Mgmt.": {
                desc: "This pathway is built on rigorous financial literacy, accounting principles, and economic analysis.",
                careers: ["Accountants", "Financial Analysts", "Auditors", "Bank Managers"],
                subjects: ["Business 1 (Basic Accounting)", "Business 2 (Finance)", "Introduction to Organization & Management", "Business 3 (Economics)"],
            },
            "Entrepreneurship & Innovation": {
                desc: "This pathway is designed for future business owners, focusing on market trends, startup logic, and management.",
                careers: ["Business Owners", "Marketing Managers", "Startup Founders", "Retail Managers"],
                subjects: ["Business 1 (Basic Accounting)", "Introduction to Organization & Management", "Entrepreneurship", "Contemporary Marketing"],
            },
            "FITNESS AND ATHLETICS DEVELOPMENT": {
                desc: "This pathway focuses on the science of human movement, athletic performance, and professional coaching.",
                careers: ["Sports Coaches", "Fitness Trainers", "Sports Officials", "Gym Managers"],
                subjects: ["Human Movement 1 & 2", "Physical Education 1 (Fitness)", "Sports Coaching"],
            },
            "ICT (Computer Systems Servicing)": {
                desc: "Provides intensive training in computer hardware, network assembly, and software systems servicing.",
                careers: ["IT Technicians", "Network Administrators", "System Support Specialists"],
                subjects: ["Computer Systems Servicing (NC II)"],
            },
            "Industrial (Electrical Installation)": {
                desc: "Offers professional training in building electrical systems, wiring, and power maintenance.",
                careers: ["Master Electricians", "Maintenance Engineers", "Linemen"],
                subjects: ["Electrical Installation Maintenance (NC II)"],
            },
            "Kitchen Operations (Cookery)": {
                desc: "Focuses on culinary arts and kitchen management training for commercial food service and hospitality.",
                careers: ["Chefs", "Kitchen Managers", "Caterers", "Pastry Chefs"],
                subjects: ["Kitchen Operations (NC II)"],
            },
            "Aesthetic Services (Beauty Care)": {
                desc: "Provides specialized training in professional hair, skin, and wellness services for personal care careers.",
                careers: ["Salon Owners", "Professional Beauticians", "Wellness Consultants"],
                subjects: ["Aesthetic Services (Beauty Care) (NC II)"],
            },
            "ALS - ICT (Computer Systems Servicing)": {
                desc: "ALS certification track providing training in computer hardware, network assembly, and software systems servicing. This program combines academic foundations with technical proficiency in computer systems. As a daily program, classes are held Monday to Friday to ensure comprehensive skill development.",
                careers: ["IT Technicians", "Network Administrators", "System Support Specialists", "Computer Repair Technicians"],
                subjects: ["Computer Systems Servicing (NC II)", "Basic Computer Operations", "Network Fundamentals"],
                schedule: "Monday to Friday (Daily Program)",
            },
            "ALS - Kitchen Operations (Cookery)": {
                desc: "ALS certification track focusing on culinary arts and kitchen management training for commercial food service context. This program integrates academic knowledge of food science with practical cooking techniques. Classes are held on Saturdays with blended learning approach combining in-person instruction and online modules.",
                careers: ["Chefs", "Kitchen Managers", "Caterers", "Food Service Supervisors"],
                subjects: ["Kitchen Operations (NC II)", "Food Safety and Sanitation", "Culinary Arts Fundamentals"],
                schedule: "Saturdays (Blended Learning)",
            },
            "ALS - Food Processing": {
                desc: "ALS certification track teaching standard food handling, sanitation, and techniques in extending shelf life and quality for food products. This program covers academic principles of food chemistry alongside technical processing methods. Classes are held on Saturdays with blended learning approach combining hands-on training and digital resources.",
                careers: ["Food Safety Inspectors", "Production Operators", "Food Processors", "Quality Control Specialists"],
                subjects: ["Food Processing (NC II)", "Food Chemistry", "Processing Techniques"],
                schedule: "Saturdays (Blended Learning)",
            },
        };

        // Handle Pathway Card Clicks
        const pathwayCards = document.querySelectorAll('.pathway-card');
        pathwayCards.forEach((card) => {
            card.addEventListener('click', () => {
                selectedG11Track = card.getAttribute('data-track');
                selectedG11Pathway = card.getAttribute('data-pathway');

                // Populate Modal Thematic Headers
                let dataKey = selectedG11Pathway;
                if (selectedG11Track === 'ALS') {
                    dataKey = 'ALS - ' + selectedG11Pathway;
                    // Fallback to non-prefixed if it doesn't exist
                    if (!pathwayData[dataKey]) dataKey = selectedG11Pathway;
                }
                const data = pathwayData[dataKey] || { desc: '', careers: [], subjects: [] };
                const detailDesc = document.getElementById('detail-desc');
                const careersList = document.getElementById('detail-careers');
                const subjectsList = document.getElementById('detail-subjects');
                const subjectsTitle = document.getElementById('detail-subjects-title');
                const detailSchedule = document.getElementById('detail-schedule');

                if (detailTrackName) {
                    detailTrackName.textContent = selectedG11Track.toUpperCase() + ' TRACK';
                    if (selectedG11Track === 'Tech-Pro') {
                        detailTrackName.className = "inline-block px-3 py-1 bg-amber-100 text-orange-700 border border-orange-200 rounded-full text-[0.65rem] font-bold uppercase tracking-widest mb-3";
                        if (subjectsTitle) subjectsTitle.innerHTML = '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M14.7 6.3a1 1 0 0 0 0 1.4l1.6 1.6a1 1 0 0 0 1.4 0l3.77-3.77a6 6 0 0 1-7.94 7.94l-6.91 6.91a2.12 2.12 0 0 1-3-3l6.91-6.91a6 6 0 0 1 7.94-7.94l-3.76 3.76z"></path></svg> TechPro Specialization';
                    } else if (selectedG11Track === 'ALS') {
                        detailTrackName.className = "inline-block px-3 py-1 bg-rose-100 text-rose-700 border border-rose-200 rounded-full text-[0.65rem] font-bold uppercase tracking-widest mb-3";
                        if (subjectsTitle) subjectsTitle.innerHTML = '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M14.7 6.3a1 1 0 0 0 0 1.4l1.6 1.6a1 1 0 0 0 1.4 0l3.77-3.77a6 6 0 0 1-7.94 7.94l-6.91 6.91a2.12 2.12 0 0 1-3-3l6.91-6.91a6 6 0 0 1 7.94-7.94l-3.76 3.76z"></path></svg> ALS Specialization';
                    } else {
                        detailTrackName.className = "inline-block px-3 py-1 bg-emerald-100 text-emerald-800 border border-emerald-200 rounded-full text-[0.65rem] font-bold uppercase tracking-widest mb-3";
                        if (subjectsTitle) subjectsTitle.innerHTML = '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M2 3h6a4 4 0 0 1 4 4v14a3 3 0 0 0-3-3H2z"></path><path d="M22 3h-6a4 4 0 0 0-4 4v14a3 3 0 0 1 3-3h7z"></path></svg> Grade 11 Electives';
                    }
                }

                if (detailPathwayName) detailPathwayName.textContent = selectedG11Pathway;
                if (detailDesc) detailDesc.textContent = data.desc;

                if (detailSchedule) {
                    if (data.schedule) {
                        detailSchedule.innerHTML = '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"></rect><line x1="16" y1="2" x2="16" y2="6"></line><line x1="8" y1="2" x2="8" y2="6"></line><line x1="3" y1="10" x2="21" y2="10"></line></svg> ' + data.schedule;
                        detailSchedule.style.display = '';
                    } else {
                        detailSchedule.style.display = 'none';
                    }
                }

                if (careersList) {
                    careersList.innerHTML = data.careers.map((c) => `<li>${c}</li>`).join('');
                }
                if (subjectsList) {
                    subjectsList.innerHTML = data.subjects.map((s) => `<li>${s}</li>`).join('');
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
                tabAls.className = "py-2 lg:py-3 px-3 lg:px-6 border-b-[3px] border-transparent bg-transparent text-slate-400 hover:text-slate-700 hover:bg-slate-50 rounded-t-xl font-black uppercase text-[0.65rem] sm:text-xs lg:text-sm tracking-widest transition-colors mb-[-2px] flex-1 min-w-[110px] max-w-[200px]";
                contentAcademic.classList.remove('hidden');
                contentTechpro.classList.add('hidden');
                contentAls.classList.add('hidden');
            });

            tabTechpro.addEventListener('click', () => {
                tabTechpro.className = "py-2 lg:py-3 px-3 lg:px-6 border-b-[3px] border-blue-600 bg-blue-50 text-blue-700 rounded-t-xl font-black uppercase text-[0.65rem] sm:text-xs lg:text-sm tracking-widest transition-colors mb-[-2px] flex-1 min-w-[110px] max-w-[200px]";
                tabAcademic.className = "py-2 lg:py-3 px-3 lg:px-6 border-b-[3px] border-transparent bg-transparent text-slate-400 hover:text-slate-700 hover:bg-slate-50 rounded-t-xl font-black uppercase text-[0.65rem] sm:text-xs lg:text-sm tracking-widest transition-colors mb-[-2px] flex-1 min-w-[110px] max-w-[200px]";
                tabAls.className = "py-2 lg:py-3 px-3 lg:px-6 border-b-[3px] border-transparent bg-transparent text-slate-400 hover:text-slate-700 hover:bg-slate-50 rounded-t-xl font-black uppercase text-[0.65rem] sm:text-xs lg:text-sm tracking-widest transition-colors mb-[-2px] flex-1 min-w-[110px] max-w-[200px]";
                contentTechpro.classList.remove('hidden');
                contentAcademic.classList.add('hidden');
                contentAls.classList.add('hidden');
            });
            
            tabAls.addEventListener('click', () => {
                tabAls.className = "py-2 lg:py-3 px-3 lg:px-6 border-b-[3px] border-rose-600 bg-rose-50 text-rose-700 rounded-t-xl font-black uppercase text-[0.65rem] sm:text-xs lg:text-sm tracking-widest transition-colors mb-[-2px] flex-1 min-w-[110px] max-w-[200px]";
                tabAcademic.className = "py-2 lg:py-3 px-3 lg:px-6 border-b-[3px] border-transparent bg-transparent text-slate-400 hover:text-slate-700 hover:bg-slate-50 rounded-t-xl font-black uppercase text-[0.65rem] sm:text-xs lg:text-sm tracking-widest transition-colors mb-[-2px] flex-1 min-w-[110px] max-w-[200px]";
                tabTechpro.className = "py-2 lg:py-3 px-3 lg:px-6 border-b-[3px] border-transparent bg-transparent text-slate-400 hover:text-slate-700 hover:bg-slate-50 rounded-t-xl font-black uppercase text-[0.65rem] sm:text-xs lg:text-sm tracking-widest transition-colors mb-[-2px] flex-1 min-w-[110px] max-w-[200px]";
                contentAls.classList.remove('hidden');
                contentAcademic.classList.add('hidden');
                contentTechpro.classList.add('hidden');
            });
        }
    }
});
