// Shared logic for both G11 and G12 Enrollment Forms

function initFormLogic(themeColor) {
    
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
        });
    }

    // 2. Auto-Calculate Age
    const birthdateInput = document.getElementById('birthdate-input');
    const ageInput = document.getElementById('age-input');

    if (birthdateInput && ageInput) {
        birthdateInput.addEventListener('change', function() {
            const birthDate = new Date(this.value);
            if (!isNaN(birthDate)) {
                const today = new Date();
                let age = today.getFullYear() - birthDate.getFullYear();
                const m = today.getMonth() - birthDate.getMonth();
                if (m < 0 || (m === 0 && today.getDate() < birthDate.getDate())) {
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

    // Define Pathways dynamically
    const pathwaysData = {
        'Academic': [
            "Medical & Allied Health",
            "Engineering & Aviation",
            "Earth, Space & Weather Science",
            "Pre-Law & Public Governance",
            "Criminology & Uniformed Services",
            "Teacher Ed (Lang/Social Sci)",
            "Social Work & Community Dev.",
            "Digital Media & Creative Arts",
            "Accountancy & Financial Mgmt.",
            "Entrepreneurship & Innovation",
            "FITNESS AND ATHLETICS DEVELOPMENT"
        ],
        'Tech-Pro': [
            "ICT (Computer Systems Servicing)",
            "Industrial (Electrical Installation)",
            "Kitchen Operations (Cookery)",
            "Aesthetic Services (Beauty Care)"
        ]
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
        };

        trackSelect.addEventListener('change', updatePathways);
        // Also trigger on init
        if(trackSelect.value) updatePathways();
    }

    // 5.5 G12 Academic Setup Strand Filtering
    const g12TrackSelect = document.getElementById('g12-track');
    const g12StrandSelect = document.getElementById('g12-strand');

    const strandsData = {
        'Academic': ['ABM', 'GAS', 'HUMSS', 'STEM'],
        'TVL': ['CSS', 'EIM', 'Cookery', 'Beauty Care']
    };

    if(g12TrackSelect && g12StrandSelect) {
        const updateStrands = () => {
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
                const brgy = brgySelect ? brgySelect.value.toLowerCase() : '';
                
                if (!city || !brgy || city.includes('Select')) {
                    currentMode = 'normal';
                } else if (!city.includes('Davao')) {
                    // Any other city/municipality entirely restricted
                    currentMode = 'restricted';
                } else {
                    // Far-flung, mountainous, or completely distinct areas
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
            form.addEventListener('submit', (e) => {
                if (currentMode === 'warning' && !distCheckbox.checked) {
                    e.preventDefault();
                    submitError.classList.remove('hidden');
                } else if (currentMode === 'restricted') {
                    e.preventDefault();
                } else {
                    e.preventDefault(); 
                    alert("Enrollment Form Submitted Successfully!");
                    window.location.href = 'index.php';
                }
            });
        }
    }
}
