<?php
$db_host = 'localhost';
$db_user = 'root';
$db_pass = '';
$db_name = 'dranhswin';
$conn = new mysqli($db_host, $db_user, $db_pass, $db_name);

$school_year = '2026 - 2027';
$semester = '1st';
$phase_start_date = date('Y-m-d');
$previous_school_year = '';

if (preg_match('/^\s*(\d{4})\s*[-–]\s*(\d{4})\s*$/', $school_year, $matches)) {
    $start_year = intval($matches[1]);
    $previous_school_year = ($start_year - 1) . '-' . $start_year;
}

if (!$conn->connect_error) {
    $res = $conn->query("SELECT setting_key, setting_value FROM system_settings");
    if ($res) {
        while ($row = $res->fetch_assoc()) {
            if ($row['setting_key'] === 'academic_year') $school_year = $row['setting_value'];
            if ($row['setting_key'] === 'active_semester') $semester = $row['setting_value'];
            if ($row['setting_key'] === 'phase_start_date') $phase_start_date = $row['setting_value'];
        }
    }

    // Fetch sections
    $g10_sections = [];
    $g12_sections = [];
    $sec_res = $conn->query("SELECT grade_level, name FROM add_sections ORDER BY name");
    if ($sec_res) {
        while ($sec = $sec_res->fetch_assoc()) {
            if ($sec['grade_level'] == '10') $g10_sections[] = $sec['name'];
            if ($sec['grade_level'] == '12') $g12_sections[] = $sec['name'];
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Grade 12 Enrollment Form | DRANHS</title>
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:ital,wght@0,400;0,700;0,900;1,800&family=Outfit:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    fontFamily: {
                        sans: ['Outfit', 'sans-serif'],
                        heading: ['Montserrat', 'sans-serif'],
                    },
                    colors: {
                        'dranhs-green': '#009b5a',
                        'dranhs-dark': '#1c2434',
                    }
                }
            }
        }
    </script>
    <script>
        // Settings injected from backend
        const SYSTEM_PHASE_START_DATE = '<?php echo $phase_start_date; ?>';
        const CURRENT_SCHOOL_YEAR = '<?php echo addslashes($school_year); ?>';
        const PREVIOUS_SCHOOL_YEAR = '<?php echo addslashes($previous_school_year); ?>';
        const G10_SECTIONS = <?php echo json_encode($g10_sections); ?>;
        const G12_SECTIONS = <?php echo json_encode($g12_sections); ?>;
    </script>
    <style type="text/tailwindcss">
        .form-section { @apply bg-white rounded-2xl shadow-sm border border-slate-200 p-6 lg:p-8 mb-6 relative overflow-hidden; }
        .section-header { @apply flex items-center mb-6 pb-4 border-b border-slate-100; }
        .section-title { @apply text-2xl font-heading font-black text-pink-600 uppercase tracking-tight; }
        .form-group { @apply flex flex-col gap-1.5; }
        .form-label { @apply text-sm font-bold text-slate-600 uppercase tracking-wider; }
        .form-input { @apply w-full bg-white border-2 border-solid border-slate-400 shadow-sm px-4 py-3 rounded-xl text-slate-800 text-base outline-none transition-all focus:border-pink-600 focus:ring-4 focus:ring-pink-600/20 font-medium placeholder-slate-400; }
        .form-input:disabled { @apply bg-slate-100 text-slate-500 border-slate-200 cursor-not-allowed; }
        #age-input { width: 4.4rem; min-width: 4.4rem; max-width: 4.4rem; box-sizing: border-box; text-align: center; }
        #extension-name { width: 100%; min-width: 3rem; max-width: 6rem; box-sizing: border-box; }
        #sex-input { max-width: 10rem; }
        .checkbox-label { @apply flex items-center gap-2 cursor-pointer text-base font-semibold text-slate-700; }
        .form-group:has(input:required) .form-label::after,
        .form-group:has(select:required) .form-label::after {
            content: ' *';
            color: #ef4444;
        }
        .bg-school-pattern {
            background-color: #f8fafc;
            background-image: linear-gradient(rgba(255, 255, 255, 0.8), rgba(255, 255, 255, 0.95)), url('data:image/svg+xml,%3Csvg width="60" height="60" viewBox="0 0 60 60" xmlns="http://www.w3.org/2000/svg"%3E%3Cg fill="none" fill-rule="evenodd"%3E%3Cg fill="%23cbd5e1" fill-opacity="0.3"%3E%3Cpath d="M36 34v-4h-2v4h-4v2h4v4h2v-4h4v-2h-4zm0-30V0h-2v4h-4v2h4v4h2V6h4V4h-4zM6 34v-4H4v4H0v2h4v4h2v-4h4v-2H6zM6 4V0H4v4H0v2h4v4h2V6h4V4H6z"/%3E%3C/g%3E%3C/g%3E%3C/svg%3E');
        }
    </style>
</head>
<body class="bg-school-pattern text-slate-800 min-h-screen font-sans">
    
    <!-- Include Navbar -->
    <?php include 'components/navbar.php'; ?>

    <main class="pt-[110px] pb-[100px] px-4 max-w-5xl mx-auto">
        <div class="mb-6 lg:mb-8">
            <a href="index.php" class="inline-flex items-center gap-2 text-xs lg:text-sm font-bold text-slate-500 hover:text-pink-600 transition-colors uppercase tracking-widest bg-white pr-4 py-2 rounded-full shadow-sm border border-slate-100 pl-3 border-l-4 border-l-pink-500 hover:shadow-md">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><line x1="19" y1="12" x2="5" y2="12"></line><polyline points="12 19 5 12 12 5"></polyline></svg>
                Return Home
            </a>
        </div>

        <div class="text-center mb-10">
            <h1 class="text-4xl lg:text-5xl font-heading font-black text-dranhs-dark uppercase tracking-tight mb-2">Basic Education Enrollment Form</h1>
            <p class="text-lg text-pink-600 font-bold tracking-widest uppercase">Grade 12</p>
            <p class="text-slate-500 text-sm mt-2 max-w-2xl mx-auto">Please print legibly in all required fields. Submit accomplished form to the Person-in-Charge/Registrar. All information is handled with confidentially.</p>
        </div>

        <form action="process_enrollment.php" method="POST" class="space-y-8">
            
            <!-- Type of Student & General Info -->
            <div class="form-section">
                <div class="absolute top-0 left-0 w-2 h-full bg-pink-500"></div>
                <div class="mb-8 border-b border-slate-100 pb-6">
                    <div class="form-group w-full md:w-1/2 lg:w-1/3">
                        <label class="form-label">Learner Category</label>
                        <select name="student_type" class="form-input" required>
                            <option value="">Select Category...</option>
                            <option value="Transferee">Transferee</option>
                            <option value="Old Student (Repeater)">Old Student (Repeater)</option>
                            <option value="Transferred In (Repeater)">Transferred In (Repeater)</option>
                        </select>
                    </div>
                </div>

                <!-- Previous School Information (conditional) -->
                <div id="prev-school-section" class="hidden mt-2 border-t border-slate-100 pt-2">
                    <h3 class="font-bold text-slate-800 mb-4 uppercase text-sm tracking-widest">Previous School Information</h3>
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                        <div class="form-group">
                            <label class="form-label">Previous School</label>
                            <input type="text" name="prev_school" id="prev-school" class="form-input" placeholder="School Name">
                        </div>
                        <div class="form-group">
                            <label class="form-label">Last School Year Attended</label>
                            <input type="text" name="prev_school_year" id="prev-school-year" class="form-input" placeholder="e.g. 2024-2025">
                        </div>
                        <div class="form-group">
                            <label class="form-label">Previous Section</label>
                            <input type="text" name="prev_section" id="prev-section" class="form-input" placeholder="Section Name">
                        </div>
                    </div>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                    <div class="form-group">
                        <label class="form-label">School Year</label>
                        <input type="text" name="school_year" class="form-input" value="<?php echo htmlspecialchars($school_year); ?>" placeholder="e.g. 2026 - 2027" readonly>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Grade Level To Enroll</label>
                        <input type="text" name="grade_level" class="form-input" value="Grade 12" readonly>
                    </div>
                    <div class="form-group relative">
                        <div class="flex justify-between items-center w-full">
                            <label class="form-label">Learner Reference No. (LRN)</label>
                            <span id="lrn-counter" class="text-xs font-bold text-slate-400">0/12</span>
                        </div>
                        <input type="text" id="lrn-input" name="lrn" class="form-input" placeholder="12-digit number" maxlength="12" pattern="\d{12}">
                        <p id="lrn-status" class="text-xs font-semibold mt-2 min-h-[1.25rem] text-slate-400"></p>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Height (cm)</label>
                        <input type="number" name="height" class="form-input" placeholder="Height in cm">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Weight (kg)</label>
                        <input type="number" step="0.1" name="weight" class="form-input" placeholder="Weight in kg">
                    </div>
                </div>
            </div>

            <!-- Learner's Personal Info -->
            <div class="form-section">
                <div class="section-header">
                    <h2 class="section-title">Learner's Personal Information</h2>
                </div>
                
                <div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-6">
                    <div class="form-group md:col-span-2">
                        <label class="form-label">PSA Birth Certificate No.</label>
                        <input type="text" name="psa_birth_cert" class="form-input" placeholder="(if available upon registration)">
                    </div>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-12 gap-5 mb-6 items-end">
                    <div class="form-group md:col-span-4">
                        <label class="form-label">Last Name</label>
                        <input type="text" name="last_name" class="form-input" required>
                    </div>
                    <div class="form-group md:col-span-4">
                        <label class="form-label">First Name</label>
                        <input type="text" name="first_name" class="form-input" required>
                    </div>
                    <div class="form-group md:col-span-3">
                        <label class="form-label">Middle Name</label>
                        <input type="text" name="middle_name" class="form-input">
                    </div>
                    <div class="form-group md:col-span-1">
                        <label class="form-label" title="Extension Name">Ext. Name</label>
                        <input type="text" id="extension-name" name="extension_name" class="form-input" placeholder="Jr., III">
                    </div>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-12 gap-5 mb-6 border-t border-slate-100 pt-6">
                    <div class="form-group md:col-span-3">
                        <label class="form-label">Birthdate</label>
                        <input type="date" id="birthdate-input" name="birthdate" class="form-input" required>
                        <p class="text-xs text-slate-500 mt-1">Age is calculated as of phase start date: <strong><?php echo htmlspecialchars($phase_start_date); ?></strong></p>
                    </div>
                    <div class="form-group md:col-span-1">
                        <label class="form-label">Age</label>
                        <input type="number" id="age-input" name="age" class="form-input px-2 text-center" readonly>
                    </div>
                    <div class="form-group md:col-span-2">
                        <label class="form-label">Sex</label>
                        <select id="sex-input" name="sex" class="form-input" required>
                            <option value="">Select...</option>
                            <option value="Male">Male</option>
                            <option value="Female">Female</option>
                        </select>
                    </div>
                    <div class="form-group md:col-span-6">
                        <label class="form-label">Place of Birth</label>
                        <input type="text" name="place_of_birth" class="form-input" placeholder="Municipality/City" required>
                    </div>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6 border-t border-slate-100 pt-6">
                    <div class="form-group">
                        <label class="form-label">Belonging to IP Community?</label>
                        <div class="flex flex-col sm:flex-row sm:items-center gap-3 mt-1">
                            <div class="flex items-center gap-4 shrink-0">
                                <label class="checkbox-label"><input type="radio" name="ip" value="Yes" class="w-5 h-5 text-pink-600"> Yes</label>
                                <label class="checkbox-label"><input type="radio" name="ip" value="No" checked class="w-5 h-5 text-pink-600"> No</label>
                            </div>
                            <input type="text" id="ip-specify" name="ip_specify" class="form-input flex-1 min-w-0" placeholder="If Yes, please specify" disabled>
                        </div>
                    </div>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                        <div class="form-group">
                            <label class="form-label">Mother Tongue</label>
                            <input type="text" name="mother_tongue" class="form-input" required>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Religion</label>
                            <input type="text" name="religion" class="form-input" required>
                        </div>
                    </div>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-6 border-t border-slate-100 pt-6">
                    <div class="form-group">
                        <label class="form-label">Family beneficiary of 4Ps?</label>
                        <div class="flex flex-col sm:flex-row sm:items-center gap-3 mt-1">
                            <div class="flex items-center gap-4 shrink-0">
                                <label class="checkbox-label"><input type="radio" name="4ps" value="Yes" class="w-5 h-5 text-pink-600"> Yes</label>
                                <label class="checkbox-label"><input type="radio" name="4ps" value="No" checked class="w-5 h-5 text-pink-600"> No</label>
                            </div>
                            <input type="text" id="fps-specify" name="fps_specify" class="form-input flex-1 min-w-0" placeholder="4Ps Household ID Number" disabled>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Current Address -->
            <div class="form-section">
                <div class="section-header">
                    <h2 class="section-title">Current Address</h2>
                </div>
                <div class="grid grid-cols-1 md:grid-cols-4 gap-5 mb-5">
                    <div class="form-group md:col-span-3">
                        <label class="form-label">Sitio/Purok/Street Name</label>
                        <input type="text" id="addr-street" name="street" class="form-input" disabled required placeholder="House No. / Street">
                    </div>
                    <div class="form-group md:col-span-1">
                        <label class="form-label">Country</label>
                        <input type="text" class="form-input bg-slate-50 text-slate-500 font-bold" value="PHILIPPINES" readonly>
                    </div>
                </div>
                <div class="grid grid-cols-1 md:grid-cols-4 gap-5 mb-5">
                    <div class="form-group">
                        <label class="form-label">Province</label>
                        <select id="addr-province" name="province" class="form-input" required>
                            <option value="">Select Province</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Municipality/City</label>
                        <select id="addr-city" name="city" class="form-input" disabled required>
                            <option value="">Select Category First</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Barangay</label>
                        <select id="addr-brgy" name="barangay" class="form-input" disabled required>
                            <option value="">Select City First</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label">ZIP CODE</label>
                        <input type="text" id="addr-zip" name="zip_code" class="form-input px-2 text-center bg-slate-50 text-slate-600 font-bold" placeholder="Auto-fill" readonly>
                    </div>
                </div>
                <div class="grid grid-cols-1 md:grid-cols-4 gap-5">
                    <div class="form-group md:col-span-2 lg:col-span-1">
                        <label class="form-label">Living with</label>
                        <select id="addr-living-with" name="living_with" class="form-input" required>
                            <option value="">Select...</option>
                            <option value="Parents">Parents</option>
                            <option value="Relatives">Relatives</option>
                            <option value="Guardian">Guardian</option>
                            <option value="Boarding House / Dorm">Boarding House / Dorm</option>
                            <option value="Others">Others</option>
                        </select>
                    </div>
                </div>

                <!-- Distance Restrictions (moved to Current Address section) -->
                <div id="distance-warning-container" class="hidden mt-6 bg-amber-50 border-l-4 border-amber-500 p-4 rounded-r-xl">
                    <div class="flex items-start gap-4">
                        <div class="shrink-0 text-amber-500 mt-1">
                            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"></path><line x1="12" y1="9" x2="12" y2="13"></line><line x1="12" y1="17" x2="12.01" y2="17"></line></svg>
                        </div>
                        <div>
                            <h3 class="text-amber-800 font-bold uppercase tracking-wider text-sm mb-1">Far Travel Distance Noticed</h3>
                            <p class="text-sm text-amber-700 leading-relaxed mb-3">Your selected location is considered far; long travel durations may affect consistent class attendance. You must acknowledge this before submitting.</p>
                            <label class="checkbox-label text-amber-900 border-t border-amber-200/50 pt-3 flex items-start gap-2 text-xs lg:text-sm">
                                <input type="checkbox" id="distance-checkbox" class="w-5 h-5 mt-0.5 rounded border-amber-300 text-amber-600 focus:ring-amber-500">
                                <span class="leading-snug font-bold">I understand the travel conditions and I will take responsibility for my daily attendance despite distance challenges.</span>
                            </label>
                        </div>
                    </div>
                </div>

                <div id="distance-restricted-container" class="hidden mt-6 bg-red-50 border-l-4 border-red-500 p-4 rounded-r-xl">
                    <div class="flex items-start gap-4">
                        <div class="shrink-0 text-red-500 mt-1">
                            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"></circle><line x1="12" y1="8" x2="12" y2="12"></line><line x1="12" y1="16" x2="12.01" y2="16"></line></svg>
                        </div>
                        <div>
                            <h3 class="text-red-800 font-bold uppercase tracking-wider text-sm mb-1">Enrollment Restricted: Location Too Far</h3>
                            <p class="text-sm text-red-700 leading-relaxed font-semibold">Your location is over an hour of travel from Matina Crossing. Policy restricts enrollment from extremely distant areas to protect student well-being. Application from this distance cannot be processed.</p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Parent's Info -->
            <div class="form-section">
                <div class="section-header">
                    <h2 class="section-title">Parent's/Guardian's Information</h2>
                </div>
                
                <!-- Father -->
                <h3 class="font-bold text-slate-800 mb-2">Father's Name <span class="text-red-500">*</span></h3>
                <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-6">
                    <div class="form-group"><input type="text" name="father_last_name" class="form-input" placeholder="Last Name" required></div>
                    <div class="form-group"><input type="text" name="father_first_name" class="form-input" placeholder="First Name" required></div>
                    <div class="form-group"><input type="text" name="father_middle_name" class="form-input" placeholder="Middle Name" required></div>
                    <div class="form-group"><input type="text" name="father_contact" class="form-input" placeholder="Contact Number/s" required></div>
                </div>

                <!-- Mother -->
                <h3 class="font-bold text-slate-800 mb-2">Mother's Maiden Name <span class="text-red-500">*</span></h3>
                <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-6">
                    <div class="form-group"><input type="text" name="mother_last_name" class="form-input" placeholder="Last Name" required></div>
                    <div class="form-group"><input type="text" name="mother_first_name" class="form-input" placeholder="First Name" required></div>
                    <div class="form-group"><input type="text" name="mother_middle_name" class="form-input" placeholder="Middle Name" required></div>
                    <div class="form-group"><input type="text" name="mother_contact" class="form-input" placeholder="Contact Number/s" required></div>
                </div>

                <!-- Guardian -->
                <h3 class="font-bold text-slate-800 mb-2">Legal Guardian's Name</h3>
                <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
                    <div class="form-group"><input type="text" name="guardian_last_name" class="form-input" placeholder="Last Name" required></div>
                    <div class="form-group"><input type="text" name="guardian_first_name" class="form-input" placeholder="First Name" required></div>
                    <div class="form-group"><input type="text" name="guardian_middle_name" class="form-input" placeholder="Middle Name"></div>
                    <div class="form-group"><input type="text" name="guardian_contact" class="form-input" placeholder="Contact Number/s" required></div>
                </div>
            </div>

            <!-- Grade 12 Specific SHS Info -->
            <div class="form-section">
                <div class="absolute top-0 left-0 w-2 h-full bg-pink-500"></div>
                <div class="section-header">
                    <h2 class="section-title">Grade 12 Academic Setup</h2>
                </div>
                <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                    <div class="form-group">
                        <label class="form-label">Semester</label>
                        <select class="form-input" disabled>
                            <option value="1st"<?php echo ($semester === '1st' ? ' selected' : ''); ?>>1st Semester</option>
                            <option value="2nd"<?php echo ($semester === '2nd' ? ' selected' : ''); ?>>2nd Semester</option>
                        </select>
                        <input type="hidden" name="semester" value="<?php echo htmlspecialchars($semester); ?>">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Track</label>
                        <select class="form-input" name="track" id="g12-track" required>
                            <option value="">Select Track...</option>
                            <option value="Academic">Academic</option>
                            <option value="TVL">TVL</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Strand</label>
                        <select class="form-input" name="strand" id="g12-strand" disabled required>
                            <option value="">Select Track First...</option>
                        </select>
                    </div>
                </div>
            </div>

            <!-- Agreements & Signatures -->
            <div class="form-section pb-10">
                <div class="section-header">
                    <h2 class="section-title">Agreements & Declarations</h2>
                </div>
                
                <div class="bg-slate-50 border border-slate-200 p-5 rounded-xl mb-6">
                    <h3 class="font-bold text-slate-800 mb-2 uppercase text-sm tracking-widest">PARENT'S AGREEMENT</h3>
                    <p class="text-xs text-slate-600 leading-relaxed text-justify mb-4">
                        Uyon ko sa pagsunod sa mga palisiya sa Daniel R. Aguinaldo National High School ug sa pagsuporta sa akademikong responsibilidad ug mga kalihokan sa akong anak. Siguraduhon nako nga siya motambong sa klase kanunay, pag-abot sa eskwelahan sa tukmang oras matag adlaw sa klase, makasumite sa mga gikinahanglan, ug ako motambong sa mga miting sa ginikanan ug eskwelahan. Nasabtan nako ang mga sangputanan kung adunay dili maayong pamatasan o kapakyasan sa pagsunod sa mga patakaran sa eskwelahan. Andam ko nga makigtambayayong sa mga opisyal sa eskwelahan kung gikinahanglan. Nasayud usab ako nga ang kakulangan sa opisyal nga rekord sa akong estudyante mahimong makaapekto sa iyang enrollment o pag-promote sa sunod nga grado. Gipirmahan nako kini isip timaan sa akong bug-os nga pagdawat ug responsibilidad.
                    </p>
                    <label class="checkbox-label text-pink-600 font-bold border-t border-slate-200 pt-4 mt-2">
                        <input type="checkbox" required class="w-5 h-5 rounded border-slate-300 text-pink-500 focus:ring-pink-500">
                        I have read and agree to the Parent's Agreement
                    </label>
                </div>

                <div class="bg-slate-50 border border-slate-200 p-5 rounded-xl mb-8">
                    <h3 class="font-bold text-slate-800 mb-2 uppercase text-sm tracking-widest">SCHOOL UNIFORM and HAIRCUT AGREEMENT</h3>
                    <p class="text-xs text-slate-600 leading-relaxed text-justify mb-4">
                        Isip usa ka ginikanan o guardian, kusang-loob nako nga gitugotan ang akong anak sa pagsul-ob sa kumpletong uniporme og pagmintinar sa hapsay nga gupit. Nagtuo ko nga kini nagpakita sa disiplina, pagtahod, ug responsibilidad. Nagasaad ko nga giyahan ug dasigon nako ang akong anak aron kanunay siyang motuman niining mga lagda. Kini among pagapirmahan uban sa akong estudyante sa ubos isip pagtuman sa maong saad.
                    </p>
                    <label class="checkbox-label text-pink-600 font-bold border-t border-slate-200 pt-4 mt-2">
                        <input type="checkbox" required class="w-5 h-5 rounded border-slate-300 text-pink-500 focus:ring-pink-500">
                        I have read and agree to the School Uniform and Haircut Agreement
                    </label>
                </div>

                <div class="flex flex-col items-center justify-center pt-8 border-t border-slate-200">
                    <p class="text-xs text-slate-500 mb-6 text-center max-w-3xl">I hereby certify that the above information given are true and correct to the best of my knowledge and I allow the Department of Education to use my child's details to create and/or update his/her learner profile in the Learner Information System. The information herein shall be treated as confidential in compliance with the Data Privacy Act of 2012.</p>
                    <button type="submit" id="submit-btn" class="bg-pink-600 hover:bg-pink-700 text-white px-12 py-4 rounded-full font-black text-lg transition-transform hover:-translate-y-1 shadow-lg hover:shadow-xl uppercase tracking-widest disabled:opacity-50 disabled:cursor-not-allowed disabled:hover:translate-y-0 disabled:hover:shadow-lg">SUBMIT ENROLLMENT</button>
                    <p id="submit-error" class="text-red-600 text-sm font-bold mt-3 hidden">Please acknowledge the distance warning before submitting.</p>
                </div>
            </div>

        </form>
    </main>

    <!-- Include Footer -->
    <?php 
        $hide_footer_buttons = true;
        include 'components/footer.php'; 
    ?>

    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const urlParams = new URLSearchParams(window.location.search);
            const lrn = urlParams.get('lrn');
            if(lrn) {
                const lrnInput = document.getElementById('lrn-input');
                if(lrnInput) {
                    lrnInput.value = lrn;
                    // manually trigger input event to update counter
                    lrnInput.dispatchEvent(new Event('input'));
                }
            }
            initFormLogic('pink');

            // Handle student type change for previous school info
            const studentTypeSelect = document.querySelector('select[name="student_type"]');
            const prevSection = document.getElementById('prev-school-section');
            const prevSchool = document.getElementById('prev-school');
            const prevYear = document.getElementById('prev-school-year');
            const prevSectionInput = document.getElementById('prev-section');

            studentTypeSelect.addEventListener('change', function() {
                const selected = this.value;

                if (selected === 'Transferee') {
                    prevSection.classList.remove('hidden');
                    prevSchool.value = '';
                    prevSchool.readOnly = false;
                    prevYear.value = '';
                    prevYear.disabled = false;
                    prevSectionInput.value = '';
                    prevSectionInput.disabled = false;
                    prevSectionInput.placeholder = 'Section Name';
                } else if (selected === 'Old Student (Repeater)') {
                    prevSection.classList.remove('hidden');
                    prevSchool.value = 'Daniel R. Aguinaldo National High School';
                    prevSchool.readOnly = true;
                    prevYear.value = PREVIOUS_SCHOOL_YEAR || '2025-2026';
                    prevYear.disabled = false;
                    showSelectSection(G12_SECTIONS);
                } else if (selected === 'Transferred In (Repeater)') {
                    prevSection.classList.remove('hidden');
                    prevSchool.value = '';
                    prevSchool.readOnly = false;
                    prevYear.value = '';
                    prevYear.disabled = false;
                    prevSectionInput.value = '';
                    prevSectionInput.disabled = false;
                    prevSectionInput.placeholder = 'Section Name';
                } else {
                    prevSection.classList.add('hidden');
                }
            });

            function showSelectSection(options) {
                if (!Array.isArray(options) || options.length === 0) {
                    prevSectionInput.value = '';
                    prevSectionInput.placeholder = 'No Grade 12 sections yet';
                    return;
                }

                prevSectionInput.disabled = false;
                prevSectionInput.placeholder = 'Select or type Section Name';
                prevSectionInput.value = options[0] || '';
            }

            function populateSections(select, sections) {
                if (!select || select.tagName !== 'SELECT') return;
                select.innerHTML = '<option value="">Select Section...</option>';
                sections.forEach(sec => {
                    const option = document.createElement('option');
                    option.value = sec;
                    option.textContent = sec;
                    select.appendChild(option);
                });
            }

            function populateYear(select) {
                if (!select || select.tagName !== 'SELECT') return;
                const defaultYear = PREVIOUS_SCHOOL_YEAR || '2025-2026';
                const yearParts = defaultYear.split('-').map(v => parseInt(v.trim()));
                let start = yearParts[0] || 2025;

                select.innerHTML = '<option value="">Select Year...</option>';
                for (let i = 0; i < 6; i++) {
                    const val = `${start + i}-${start + i + 1}`;
                    const option = document.createElement('option');
                    option.value = val;
                    option.textContent = val;
                    select.appendChild(option);
                }
            }
        });
    </script>
    <script src="davao-address.js"></script>
    <script src="form-logic.js"></script>
</body>
</html>
