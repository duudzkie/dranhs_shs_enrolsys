// Shared logic for both G11 and G12 Enrollment Forms

function initFormLogic(themeColor) {

    let lrnCheckState = 'idle';
    let lastCheckedLrn = '';
    let currentStemQualified = false;
    let currentG11Completer = null;

    const setLrnStatus = (message = '', status = 'idle') => {
        const lrnInput = document.getElementById('lrn-input');
        const lrnStatus = document.getElementById('lrn-status');
        if (!lrnInput || !lrnStatus) return;

        lrnStatus.textContent = message;
        lrnStatus.className = 'text-xs font-semibold mt-2 min-h-[1.25rem]';
        lrnInput.classList.remove('border-red-500', 'ring-4', 'ring-red-500/20', 'border-emerald-500', 'ring-emerald-500/20');

        if (status === 'error') {
            lrnStatus.classList.add('text-red-600');
            lrnInput.classList.add('border-red-500', 'ring-4', 'ring-red-500/20');
        } else if (status === 'success') {
            lrnStatus.classList.add('text-emerald-600');
            lrnInput.classList.add('border-emerald-500', 'ring-4', 'ring-emerald-500/20');
        } else if (status === 'loading') {
            lrnStatus.classList.add('text-slate-500');
        } else {
            lrnStatus.classList.add('text-slate-400');
        }
    };

    const STEM_PATHWAYS = [
        'Medical & Allied Health',
        'Engineering & Aviation',
        'Earth, Space & Weather Science'
    ];

    const applyStemQualifierSelection = (pathwayLabel) => {
        const trackSelect = document.getElementById('g11-track');
        const pathwaySelect = document.getElementById('g11-pathway');
        const trackHiddenInput = document.getElementById('g11-track-hidden');
        const pathwayHiddenInput = document.getElementById('g11-pathway-hidden');
        if (!trackSelect || !pathwaySelect || !pathwayLabel) return;

        trackSelect.value = 'Academic';
        trackSelect.dispatchEvent(new Event('change', { bubbles: true }));

        if (pathwaySelect.querySelector(`option[value="${pathwayLabel}"]`)) {
            pathwaySelect.value = pathwayLabel;
        }

        if (trackHiddenInput) trackHiddenInput.value = trackSelect.value;
        if (pathwayHiddenInput) pathwayHiddenInput.value = pathwaySelect.value;
    };

    const applyG12CompleterSelection = (strandValue) => {
        const g12TrackSelect = document.getElementById('g12-track');
        const g12StrandSelect = document.getElementById('g12-strand');
        if (!g12TrackSelect || !g12StrandSelect || !strandValue) return;

        const academicStrands = ['GAS', 'STEM', 'HUMSS', 'ABM'];
        g12TrackSelect.value = academicStrands.includes(String(strandValue).toUpperCase()) ? 'Academic' : 'TVL';
        g12TrackSelect.dispatchEvent(new Event('change', { bubbles: true }));

        if (g12StrandSelect.querySelector(`option[value="${strandValue}"]`)) {
            g12StrandSelect.value = strandValue;
        }
    };

    const checkLrnAvailability = async (lrn) => {
        if (!lrn || lrn.length !== 12) {
            lrnCheckState = 'idle';
            lastCheckedLrn = '';
            return;
        }

        lrnCheckState = 'loading';
        lastCheckedLrn = lrn;
        setLrnStatus('Checking LRN availability...', 'loading');

        try {
            const response = await fetch(`scripts/check_lrn.php?lrn=${encodeURIComponent(lrn)}`, {
                headers: { 'Accept': 'application/json' }
            });
            const data = await response.json();

            currentStemQualified = !!data.stem_qualified;
            currentG11Completer = data.g11_completer ? data : null;
            if (typeof updateStemPathwayRestrictions === 'function') updateStemPathwayRestrictions();

            if (lrnInput.value !== lrn) return;

            if (!data.ok) {
                lrnCheckState = 'error';
                setLrnStatus(data.message || 'Unable to verify LRN right now.', 'error');
                return;
            }

            if (data.exists) {
                lrnCheckState = 'exists';
                setLrnStatus(data.message || 'This LRN already exists in the database.', 'error');
            } else {
                lrnCheckState = 'available';
                if (data.stem_qualified && data.stem_pathway_label) {
                    applyStemQualifierSelection(data.stem_pathway_label);
                    setLrnStatus(`STEM QUALIFIED. Career pathway set to ${data.stem_pathway_label}.`, 'success');
                } else if (document.getElementById('g11-pathway')) {
                    setLrnStatus('You are not in the STEM qualifiers list. You may confirm your LRN, but STEM pathways are locked unless you are qualified.', 'idle');
                } else if (data.g11_completer && data.g11_completer_strand) {
                    if (g12StudentTypeSelect && g12StudentTypeSelect.querySelector('option[value="Old Student (Grade 11 Completer)"]')) {
                        g12StudentTypeSelect.value = 'Old Student (Grade 11 Completer)';
                    }
                    applyG12CompleterSelection(data.g11_completer_strand);
                    setLrnStatus(`GRADE 11 COMPLETER FOUND. Strand set to ${data.g11_completer_strand}.`, 'success');
                } else {
                    setLrnStatus(data.message || 'LRN is available.', 'success');
                }
            }
        } catch (error) {
            if (lrnInput.value !== lrn) return;
            lrnCheckState = 'error';
            setLrnStatus('Unable to verify LRN right now. You can try again in a moment.', 'error');
        }
    };

    // 1. LRN Validation and Counter
    const lrnInput = document.getElementById('lrn-input');
    const lrnCounter = document.getElementById('lrn-counter');
    if (lrnInput && lrnCounter) {
        // Restrict to numbers only
        lrnInput.addEventListener('input', function(e) {
            // Remove any non-digit character
            this.value = this.value.replace(/\D/g, '');
            
            // Update counter
            const count = this.value.length;
            lrnCounter.textContent = `${count}/12`;
            
            // Visual feedback when full
            if (count === 12) {
                lrnCounter.classList.remove('text-slate-400', 'text-red-500');
                lrnCounter.classList.add(`text-${themeColor}-600`);
            } else if (count > 0 && count < 12) {
                lrnCounter.classList.remove('text-slate-400', `text-${themeColor}-600`);
                lrnCounter.classList.add('text-red-500');
            } else {
                lrnCounter.classList.remove('text-red-500', `text-${themeColor}-600`);
                lrnCounter.classList.add('text-slate-400');
            }

            if (count === 0) {
                lrnCheckState = 'idle';
                lastCheckedLrn = '';
                currentStemQualified = false;
                currentG11Completer = null;
                if (typeof updateStemPathwayRestrictions === 'function') updateStemPathwayRestrictions();
                setLrnStatus('', 'idle');
                return;
            }

            if (count < 12) {
                lrnCheckState = 'idle';
                lastCheckedLrn = '';
                currentStemQualified = false;
                currentG11Completer = null;
                if (typeof updateStemPathwayRestrictions === 'function') updateStemPathwayRestrictions();
                setLrnStatus('Complete the 12-digit LRN to check if it already exists.', 'idle');
                return;
            }

            checkLrnAvailability(this.value);
        });

        lrnInput.addEventListener('blur', function() {
            if (this.value.length === 12 && this.value !== lastCheckedLrn) {
                checkLrnAvailability(this.value);
            }
        });
    }

    // 2. Auto-Calculate Age
    const birthdateInput = document.getElementById('birthdate-input');
    const ageInput = document.getElementById('age-input');

    if (birthdateInput && ageInput) {
        birthdateInput.addEventListener('change', function() {
            const birthDate = new Date(this.value);
            if (!isNaN(birthDate)) {
                let referenceDate = new Date();
                if (typeof SYSTEM_PHASE_START_DATE !== 'undefined' && SYSTEM_PHASE_START_DATE) {
                    referenceDate = new Date(SYSTEM_PHASE_START_DATE);
                }
                let age = referenceDate.getFullYear() - birthDate.getFullYear();
                const m = referenceDate.getMonth() - birthDate.getMonth();
                if (m < 0 || (m === 0 && referenceDate.getDate() < birthDate.getDate())) {
                    age--;
                }
                ageInput.value = age >= 0 ? age : 0;
            } else {
                ageInput.value = '';
            }
        });
    }

    // 3. Toggle IP Specify Field
    const ipRadios = document.querySelectorAll('input[name="ip"]');
    const ipSpecify = document.getElementById('ip-specify');

    if (ipRadios.length > 0 && ipSpecify) {
        ipRadios.forEach(radio => {
            radio.addEventListener('change', (e) => {
                if(e.target.value === 'Yes') {
                    ipSpecify.disabled = false;
                    ipSpecify.focus();
                } else {
                    ipSpecify.disabled = true;
                    ipSpecify.value = '';
                }
            });
        });
    }

    // 4. Toggle 4Ps Specify Field
    const fpsRadios = document.querySelectorAll('input[name="4ps"]');
    const fpsSpecify = document.getElementById('fps-specify');

    if (fpsRadios.length > 0 && fpsSpecify) {
        fpsRadios.forEach(radio => {
            radio.addEventListener('change', (e) => {
                if(e.target.value === 'Yes') {
                    fpsSpecify.disabled = false;
                    fpsSpecify.focus();
                } else {
                    fpsSpecify.disabled = true;
                    fpsSpecify.value = '';
                }
            });
        });
    }

    // 5. G11 Academic Setup Unlock and Pathway Filtering
    const toggleAcademicSetup = document.getElementById('toggle-academic-setup');
    const acadInputs = document.querySelectorAll('.acad-input');
    const trackSelect = document.getElementById('g11-track');
    const pathwaySelect = document.getElementById('g11-pathway');
    const trackHiddenInput = document.getElementById('g11-track-hidden');
    const pathwayHiddenInput = document.getElementById('g11-pathway-hidden');

    const syncG11AcademicHiddenFields = () => {
        if (trackHiddenInput) trackHiddenInput.value = trackSelect ? trackSelect.value : '';
        if (pathwayHiddenInput) pathwayHiddenInput.value = pathwaySelect ? pathwaySelect.value : '';
    };

    // Define Pathways dynamically
    const pathwaysData = window.DYNAMIC_PATHWAYS_DATA || {
        'Academic': [],
        'Tech-Pro': []
    };

    const updateStemPathwayRestrictions = () => {
        if (!pathwaySelect) return;
        const selectedTrack = trackSelect ? trackSelect.value : '';
        Array.from(pathwaySelect.options).forEach(option => {
            const isStemPathway = STEM_PATHWAYS.includes(option.value);
            option.disabled = selectedTrack === 'Academic' && isStemPathway && !currentStemQualified;
        });

        if (!currentStemQualified && STEM_PATHWAYS.includes(pathwaySelect.value)) {
            pathwaySelect.value = '';
            syncG11AcademicHiddenFields();
        }
    };

    if (toggleAcademicSetup && acadInputs.length > 0) {
        toggleAcademicSetup.addEventListener('change', function() {
            const isUnlocked = this.checked;
            acadInputs.forEach(input => {
                input.disabled = !isUnlocked;
            });
        });
    }

    if(trackSelect && pathwaySelect) {
        const updatePathways = () => {
            const selectedTrack = trackSelect.value;
            // Store current selected value to attempt re-selection
            const currentPathway = pathwaySelect.value;
            
            // Clear current options except the first placeholder
            pathwaySelect.innerHTML = '<option value="">Select Pathway...</option>';
            
            if (selectedTrack && pathwaysData[selectedTrack]) {
                pathwaysData[selectedTrack].forEach(pathway => {
                    const opt = document.createElement('option');
                    opt.value = opt.textContent = pathway;
                    pathwaySelect.appendChild(opt);
                });
            }
            // Restore if exists
            if (pathwaySelect.querySelector(`option[value="${currentPathway}"]`)) {
                pathwaySelect.value = currentPathway;
            }

            updateStemPathwayRestrictions();
            syncG11AcademicHiddenFields();
        };

        trackSelect.addEventListener('change', updatePathways);
        pathwaySelect.addEventListener('change', function() {
            if (!currentStemQualified && STEM_PATHWAYS.includes(this.value)) {
                this.value = '';
                setLrnStatus('You are not in the STEM qualifiers list. STEM pathways cannot be selected unless you are qualified.', 'error');
            }
            syncG11AcademicHiddenFields();
        });
        // Also trigger on init
        if(trackSelect.value) updatePathways();
        syncG11AcademicHiddenFields();
    }

    // 5.5 G12 Academic Setup Strand Filtering
    const g12TrackSelect = document.getElementById('g12-track');
    const g12StrandSelect = document.getElementById('g12-strand');
    const g12StudentTypeSelect = document.querySelector('select[name="student_type"]');

    const strandsData = {
        'Academic': ['ABM', 'GAS', 'HUMSS', 'STEM'],
        'TVL': ['CSS', 'EIM', 'Cookery', 'Beauty Care']
    };

    if(g12TrackSelect && g12StrandSelect) {
        const updateStrands = () => {
            // If this form was opened for a G11-verified completer, skip — strand is already locked
            if (g12StrandSelect.dataset.g11Locked === '1') return;

            const selectedTrack = g12TrackSelect.value;
            const currentStrand = g12StrandSelect.value;
            
            g12StrandSelect.innerHTML = '<option value="">Select Strand...</option>';
            
            if (selectedTrack && strandsData[selectedTrack]) {
                strandsData[selectedTrack].forEach(strand => {
                    const opt = document.createElement('option');
                    opt.value = opt.textContent = strand;
                    g12StrandSelect.appendChild(opt);
                });
                g12StrandSelect.disabled = false;
            } else {
                g12StrandSelect.innerHTML = '<option value="">Select Track First...</option>';
                g12StrandSelect.disabled = true;
            }
            
            if (g12StrandSelect.querySelector(`option[value="${currentStrand}"]`)) {
                g12StrandSelect.value = currentStrand;
            }
        };

        g12TrackSelect.addEventListener('change', updateStrands);
        if(g12TrackSelect.value) updateStrands();
    }

    if (g12StudentTypeSelect) {
        g12StudentTypeSelect.addEventListener('change', function() {
            if (this.value === 'Old Student (Grade 11 Completer)' && (!currentG11Completer || !currentG11Completer.g11_completer)) {
                setLrnStatus('This LRN is not in the Grade 11 completer list. Please verify it first before using Old Student (Grade 11 Completer).', 'error');
            }
        });
    }

    // 6. Address Dropdown Logic
    const provSelect = document.getElementById('addr-province');
    const citySelect = document.getElementById('addr-city');
    const brgySelect = document.getElementById('addr-brgy');
    const streetInput = document.getElementById('addr-street');

    if(provSelect && citySelect && brgySelect && typeof davaoRegionData !== 'undefined') {
        // Initialize Provinces
        davaoRegionData.forEach(prov => {
            const opt = document.createElement('option');
            opt.value = opt.textContent = prov.name;
            provSelect.appendChild(opt);
        });

        provSelect.addEventListener('change', () => {
            const val = provSelect.value;
            citySelect.innerHTML = '<option value="">Select City/Municipality</option>';
            brgySelect.innerHTML = '<option value="">Select City First</option>';
            brgySelect.disabled = true;
            streetInput.disabled = true;

            if (val) {
                const provObj = davaoRegionData.find(p => p.name === val);
                if (provObj && provObj.cities) {
                    provObj.cities.forEach(city => {
                        const opt = document.createElement('option');
                        opt.value = opt.textContent = city.name;
                        citySelect.appendChild(opt);
                    });
                }
                citySelect.disabled = false;
            } else {
                citySelect.innerHTML = '<option value="">Select Province First</option>';
                citySelect.disabled = true;
            }
        });

        citySelect.addEventListener('change', () => {
            const pVal = provSelect.value;
            const cVal = citySelect.value;
            const zipInput = document.getElementById('addr-zip');
            brgySelect.innerHTML = '<option value="">Select Barangay</option>';
            streetInput.disabled = true;

            const getZip = (provName, cityName) => {
                if (!cityName) return "";
                const zips = {
                    "Davao City": "8000", "City of Davao": "8000",
                    "Digos": "8002", "Bansalan": "8005", "Hagonoy": "8006", "Kiblawan": "8008", "Magsaysay": "8004", "Malalag": "8010", "Matanao": "8003", "Padada": "8007", "Santa Cruz": "8001", "Sulop": "8009",
                    "Tagum": "8100", "Panabo": "8105", "Samal": "8119", "Asuncion": "8102", "Dujali": "8106", "Carmen": "8101", "Kapalong": "8113", "New Corella": "8104", "Santo Tomas": "8112", "Talaingod": "8110",
                    "Nabunturan": "8800", "Compostela": "8803", "Laak": "8810", "Mabini": "8807", "Maco": "8806", "Maragusan": "8808", "Mawab": "8802", "Monkayo": "8805", "Montevista": "8801", "New Bataan": "8804", "Pantukan": "8809",
                    "Mati": "8200", "Baganga": "8204", "Banaybanay": "8208", "Boston": "8206", "Caraga": "8203", "Cateel": "8205", "Generoso": "8210", "Lupon": "8207", "Manay": "8202", "Tarragona": "8201",
                    "Malita": "8012", "Marcelino": "8013", "Jose Abad Santos": "8014", "Sarangani": "8015", "Santa Maria": "8011"
                };
                if (cityName.includes("San Isidro")) return provName.includes("Norte") ? "8109" : "8209";
                for (const [key, zip] of Object.entries(zips)) {
                    if (cityName.includes(key)) return zip;
                }
                return "";
            };

            if (pVal && cVal) {
                if(zipInput) zipInput.value = getZip(pVal, cVal);
                const provObj = davaoRegionData.find(p => p.name === pVal);
                if (provObj && provObj.cities) {
                    const cityObj = provObj.cities.find(c => c.name === cVal);
                    if (cityObj && cityObj.barangays) {
                        cityObj.barangays.sort().forEach(brgy => {
                            const opt = document.createElement('option');
                            opt.value = opt.textContent = brgy;
                            brgySelect.appendChild(opt);
                        });
                    }
                }
                brgySelect.disabled = false;
            } else {
                if(zipInput) zipInput.value = '';
                brgySelect.innerHTML = '<option value="">Select City First</option>';
                brgySelect.disabled = true;
            }
        });

        brgySelect.addEventListener('change', () => {
            if (brgySelect.value) {
                streetInput.disabled = false;
            } else {
                streetInput.disabled = true;
                streetInput.value = '';
            }
        });
    }

    // 7. Distance Restriction Logic
    const submitBtn = document.getElementById('submit-btn');
    const warningContainer = document.getElementById('distance-warning-container');
    const restrictedContainer = document.getElementById('distance-restricted-container');
    const distCheckbox = document.getElementById('distance-checkbox');
    const submitError = document.getElementById('submit-error');

    if(submitBtn && warningContainer && restrictedContainer && distCheckbox) {
        let currentMode = 'normal'; // 'normal', 'warning', 'restricted'

        const checkDistance = () => {
            // Need a tiny delay to ensure values are updated by other listeners
            setTimeout(() => {
                const city = citySelect ? citySelect.value : '';
                const prov = provSelect ? provSelect.value : '';
                const brgy = brgySelect ? brgySelect.value.toLowerCase() : '';
                
                if (!city || !brgy || city.includes('Select')) {
                    currentMode = 'normal';
                } else if (prov && !prov.toLowerCase().includes('davao')) {
                    // Outside the Davao region range is blocked.
                    currentMode = 'restricted';
                } else {
                    // Within Davao region: compute more specific zoning rules.
                    // City fields in Davao Del Norte/Sur/Oro/etc are allowed by default.
                    const isRestrictedBrgy = [
                        'marilog', 'paquibato', 'baguio', 'tambobong', 'tapak', 'salaysay', 'gumitan', 'suawan', 
                        'lasang', 'bunawan', 'tibungco', 'ilang', 'mudiang', 'mahagob', 'dalag', 'dalagdag',
                        'biao', 'mapula', 'talandang', 'sumimao', 'malabog', 'colosas', 'paradise embak',
                        'fatima', 'wines', 'lamanan', 'inayangan', 'sibulan', 'tighungco', 'tapaco'
                    ].some(w => brgy.includes(w));
                    
                    // Moderately distant areas that could cause attendance issues
                    const isWarningBrgy = [
                        'calinan', 'tugbok', 'toril', 'mintal', 'buhangin', 'panacan', 'sasa', 'indangan', 
                        'cabantian', 'ma-a', 'maa', 'poblacion', 'agdao', 'downtown', 'tigatto', 'wawa', 
                        'mandug', 'callawa', 'lampianao', 'acacia', 'daliao', 'binugao', 'sirawan', 'lizada', 
                        'lubogan', 'bato', 'balengaeng', 'los amigos', 'tacunan', 'mulig', 'bago', 'tibuloy',
                        'eden', 'catigan', 'bayabas', 'marapangi', 'barangay'
                    ].some(w => brgy.includes(w));
                    
                    // Everything else (Talomo district, Matina, etc) defaults to normal
                    if (isRestrictedBrgy) currentMode = 'restricted';
                    else if (isWarningBrgy) currentMode = 'warning';
                    else currentMode = 'normal';
                }

                // Apply UI Changes
                warningContainer.classList.add('hidden');
                restrictedContainer.classList.add('hidden');
                submitError.classList.add('hidden');
                distCheckbox.required = false;

                if (currentMode === 'normal') {
                    submitBtn.disabled = false;
                } else if (currentMode === 'warning') {
                    warningContainer.classList.remove('hidden');
                    distCheckbox.required = true;
                    submitBtn.disabled = !distCheckbox.checked;
                } else if (currentMode === 'restricted') {
                    restrictedContainer.classList.remove('hidden');
                    submitBtn.disabled = true;
                }
            }, 50);
        };

        if(brgySelect) brgySelect.addEventListener('change', checkDistance);
        if(citySelect) citySelect.addEventListener('change', checkDistance);

        distCheckbox.addEventListener('change', () => {
            if(currentMode === 'warning') {
                submitBtn.disabled = !distCheckbox.checked;
                if(distCheckbox.checked) submitError.classList.add('hidden');
            }
        });

        const form = submitBtn.closest('form');
        if(form) {
            form.addEventListener('submit', async (e) => {
                const currentLrn = lrnInput ? lrnInput.value.trim() : '';

                if (lrnInput && currentLrn.length === 12 && currentLrn !== lastCheckedLrn) {
                    e.preventDefault();
                    await checkLrnAvailability(currentLrn);
                    if (lrnCheckState !== 'available') return;
                    form.requestSubmit();
                    return;
                }

                if (lrnInput && currentLrn.length === 12 && lrnCheckState === 'exists') {
                    e.preventDefault();
                    setLrnStatus('This LRN already exists in the database.', 'error');
                    lrnInput.focus();
                    return;
                }

                if (pathwaySelect && trackSelect && trackSelect.value === 'Academic' && STEM_PATHWAYS.includes(pathwaySelect.value) && !currentStemQualified) {
                    e.preventDefault();
                    setLrnStatus('You are not in the STEM qualifiers list. STEM pathways cannot be submitted unless you are qualified.', 'error');
                    pathwaySelect.focus();
                    return;
                }

                if (g12StudentTypeSelect && g12StudentTypeSelect.value === 'Old Student (Grade 11 Completer)') {
                    // Skip this check if the student arrived via the G11-verified modal flow
                    const g11VerifiedInput = document.getElementById('g11-verified-input');
                    const isG11Verified = g11VerifiedInput && g11VerifiedInput.value === '1';
                    if (!isG11Verified && (!currentG11Completer || !currentG11Completer.g11_completer)) {
                        e.preventDefault();
                        setLrnStatus('This LRN is not in the Grade 11 completer list. Please verify it before submitting as an Old Student (Grade 11 Completer).', 'error');
                        lrnInput.focus();
                        return;
                    }
                }

                if (currentMode === 'warning' && !distCheckbox.checked) {
                    e.preventDefault();
                    submitError.classList.remove('hidden');
                } else if (currentMode === 'restricted') {
                    e.preventDefault();
                } else {
                    // Allow the form to submit to process_enrollment.php.
                    // Remove the fake success behavior and let the server handle persistence.
                }
            });
        }
    }
}
