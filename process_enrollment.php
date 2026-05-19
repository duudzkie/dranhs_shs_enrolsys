<?php
// process_enrollment.php - Handle enrollment form submission with image upload and compression

require_once __DIR__ . '/pathway_strand_catalog.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/activity_log.php';
$conn = db_connect();

function post_value($key, $default = null) {
    return isset($_POST[$key]) ? $_POST[$key] : $default;
}

function fetch_current_school_year($conn) {
    $default_year = date('Y') . ' - ' . (date('Y') + 1);
    $stmt = $conn->prepare("SELECT setting_value FROM system_settings WHERE setting_key = 'academic_year' LIMIT 1");
    if (!$stmt) return $default_year;
    $stmt->execute();
    $result = $stmt->get_result();
    $value = $default_year;
    if ($result && ($row = $result->fetch_assoc())) {
        $value = trim((string)($row['setting_value'] ?? '')) ?: $default_year;
    }
    $stmt->close();
    return $value;
}

function is_stem_qualifier_enabled($conn) {
    $stmt = $conn->prepare("SELECT setting_value FROM system_settings WHERE setting_key = 'stem_qualifier_enabled' LIMIT 1");
    if (!$stmt) return true;
    $stmt->execute();
    $result = $stmt->get_result();
    $enabled = true;
    if ($result && ($row = $result->fetch_assoc())) {
        $enabled = (($row['setting_value'] ?? '1') === '1');
    }
    $stmt->close();
    return $enabled;
}

// Function to compress and resize image
function compressImage($source, $destination, $quality = 80, $maxWidth = 800, $maxHeight = 800) {
    $info = getimagesize($source);
    if (!$info) return false;

    $mime = $info['mime'];
    switch ($mime) {
        case 'image/jpeg':
            $image = imagecreatefromjpeg($source);
            break;
        case 'image/png':
            $image = imagecreatefrompng($source);
            break;
        case 'image/webp':
            $image = imagecreatefromwebp($source);
            break;
        default:
            return false;
    }

    // Get original dimensions
    $width = imagesx($image);
    $height = imagesy($image);

    // Calculate new dimensions
    if ($width > $height) {
        if ($width > $maxWidth) {
            $newWidth = $maxWidth;
            $newHeight = ($height / $width) * $maxWidth;
        } else {
            $newWidth = $width;
            $newHeight = $height;
        }
    } else {
        if ($height > $maxHeight) {
            $newHeight = $maxHeight;
            $newWidth = ($width / $height) * $maxHeight;
        } else {
            $newWidth = $width;
            $newHeight = $height;
        }
    }

    // Create new image
    $newImage = imagecreatetruecolor($newWidth, $newHeight);

    // Preserve transparency for PNG
    if ($mime == 'image/png') {
        imagealphablending($newImage, false);
        imagesavealpha($newImage, true);
        $transparent = imagecolorallocatealpha($newImage, 255, 255, 255, 127);
        imagefill($newImage, 0, 0, $transparent);
    }

    // Resize
    imagecopyresampled($newImage, $image, 0, 0, 0, 0, $newWidth, $newHeight, $width, $height);

    // Save compressed image
    $success = false;
    if ($mime == 'image/jpeg') {
        $success = imagejpeg($newImage, $destination, $quality);
    } elseif ($mime == 'image/png') {
        $success = imagepng($newImage, $destination, 9 - round($quality / 10)); // PNG quality is 0-9, lower is better
    } elseif ($mime == 'image/webp') {
        $success = imagewebp($newImage, $destination, $quality);
    }

    // Clean up memory
    imagedestroy($image);
    imagedestroy($newImage);

    return $success;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    session_start();

    // Validate required fields (add more as needed)
    $required = ['last_name', 'first_name', 'birthdate', 'sex', 'place_of_birth', 'lrn'];
    foreach ($required as $field) {
        if (empty($_POST[$field])) {
            die("Error: $field is required");
        }
    }

    // LRN should be exactly 12 digits
    if (!preg_match('/^[0-9]{12}$/', $_POST['lrn'])) {
        die("Error: LRN must be 12 digits.");
    }

    // No student photo upload required
    $avatarPath = null;

    // Check if LRN already exists
    $lrn_check = $conn->prepare("SELECT id FROM students WHERE lrn = ?");
    $lrn_check->bind_param("s", $_POST['lrn']);
    $lrn_check->execute();
    $lrn_check->store_result();
    if ($lrn_check->num_rows > 0) {
        die("Error: LRN already exists. Please use a unique Learner's Reference Number.");
    }
    $lrn_check->close();

    // Normalize optional fields with defaults
    $ip = post_value('ip', 'No');
    $ip_specify = post_value('ip_specify', '');
    $family_4ps = post_value('4ps', 'No');
    $fps_id = post_value('fps_specify', '');
    $sped = post_value('sped', 'No');
    $sped_diagnosis = post_value('sped_diagnosis', '');
    $pwd = post_value('pwd', 'No');
    $pwd_id = post_value('pwd_id', '');

    $grade_level = post_value('grade_level', '');
    $current_school_year = fetch_current_school_year($conn);
    $stem_qualifier_enabled = is_stem_qualifier_enabled($conn);

    // Pathway/strand normalization (Grade 12 uses strand, Grade 11 uses pathway)
    $track = post_value('track', '');
    $pathway = post_value('pathway', '');
    $strand = post_value('strand', '');
    $selected_pathway_strand = strcasecmp($grade_level, 'Grade 12') === 0 ? $strand : $pathway;
    $pathway_strand = get_pathway_strand_code($grade_level, $selected_pathway_strand);

    if ($stem_qualifier_enabled && strcasecmp($grade_level, 'Grade 11') === 0) {
        $stem_pathways = [
            'Medical & Allied Health',
            'Engineering & Aviation',
            'Earth, Space & Weather Science',
        ];

        if (in_array($selected_pathway_strand, $stem_pathways, true)) {
            $stem_check = $conn->prepare("SELECT id FROM stem_qualifiers WHERE lrn = ? AND school_year = ? LIMIT 1");
            if ($stem_check) {
                $stem_check->bind_param("ss", $_POST['lrn'], $current_school_year);
                $stem_check->execute();
                $stem_check->store_result();
                $is_stem_qualified = $stem_check->num_rows > 0;
                $stem_check->close();

                if (!$is_stem_qualified) {
                    die("Error: You are not in the STEM qualifiers list. STEM pathways are only available to qualified students.");
                }
            }
        }
    }

    $student_type = post_value('student_type', '');
    if (strcasecmp($grade_level, 'Grade 12') === 0 && $student_type === 'Old Student (Grade 11 Completer)') {
        $g11_check = $conn->prepare("SELECT strand FROM g11_completers WHERE lrn = ? AND school_year = ? LIMIT 1");
        if ($g11_check) {
            $g11_check->bind_param("ss", $_POST['lrn'], $current_school_year);
            $g11_check->execute();
            $g11_result = $g11_check->get_result();
            $g11_row = $g11_result ? $g11_result->fetch_assoc() : null;
            $g11_check->close();

            if (!$g11_row) {
                die("Error: This LRN is not in the Grade 11 completer list.");
            }

            $completer_strand = trim((string)($g11_row['strand'] ?? ''));
            if ($completer_strand !== '') {
                $selected_pathway_strand = $completer_strand;
                $pathway_strand = get_pathway_strand_code($grade_level, $selected_pathway_strand);
                $track = in_array(strtoupper($completer_strand), ['GAS', 'STEM', 'HUMSS', 'ABM'], true) ? 'Academic' : 'TVL';
            }
        }
    }

    $enrollment_status = ($student_type === 'Old Student (Grade 11 Completer)') ? 'for_encoding' : 'for_evaluation';

    // Prepare data for insertion (simplified - add all form fields)
    $stmt = $conn->prepare("INSERT INTO students (
        last_name, first_name, middle_name, extension_name, birthdate, age, sex, place_of_birth,
        mother_tongue, religion, ip_community, ip_specify, family_4ps, fps_id,
        father_last_name, father_first_name, father_middle_name, father_contact,
        mother_last_name, mother_first_name, mother_middle_name, mother_contact,
        guardian_last_name, guardian_first_name, guardian_middle_name, guardian_contact,
        sped, sped_diagnosis, pwd, pwd_id,
        semester, track, pathway_strand, school_year, grade_level, lrn, student_type, enrollment_status,
        height, weight, psa_birth_cert,
        street, province, city, barangay, zip_code, living_with,
        prev_school, prev_school_year, prev_section
    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");

    if (!$stmt) {
        die("Prepare failed: " . $conn->error);
    }

    $params = [
        $_POST['last_name'], $_POST['first_name'], post_value('middle_name', ''), post_value('extension_name', ''),
        $_POST['birthdate'], post_value('age', null), $_POST['sex'], $_POST['place_of_birth'],
        post_value('mother_tongue', ''), post_value('religion', ''), $ip, $ip_specify,
        $family_4ps, $fps_id,
        post_value('father_last_name', ''), post_value('father_first_name', ''), post_value('father_middle_name', ''), post_value('father_contact', ''),
        post_value('mother_last_name', ''), post_value('mother_first_name', ''), post_value('mother_middle_name', ''), post_value('mother_contact', ''),
        post_value('guardian_last_name', ''), post_value('guardian_first_name', ''), post_value('guardian_middle_name', ''), post_value('guardian_contact', ''),
        $sped, $sped_diagnosis, $pwd, $pwd_id,
        post_value('semester', ''), $track, $pathway_strand, post_value('school_year', ''), $grade_level,
        $_POST['lrn'], $student_type, $enrollment_status, post_value('height', null), post_value('weight', null), post_value('psa_birth_cert', ''),
        post_value('street', ''), post_value('province', ''), post_value('city', ''), post_value('barangay', ''),
        post_value('zip_code', ''), post_value('living_with', ''),
        post_value('prev_school', null), post_value('prev_school_year', null), post_value('prev_section', null)
    ];

    $types = str_repeat('s', count($params));
    $stmt->bind_param($types, ...$params);

    if ($stmt->execute()) {
        // Log public enrollment (no session/user)
        log_activity($conn, 'student_enrolled', 'Public enrollment: ' . $_POST['last_name'] . ', ' . $_POST['first_name'] . ' (LRN: ' . $_POST['lrn'] . ', ' . $grade_level . ')', 'student', null);

        $clean_lrn = htmlspecialchars($_POST['lrn']);
        echo '<!DOCTYPE html><html><head><meta charset="UTF-8"><title>Enrollment Success</title><style>';
        echo 'body{margin:0;font-family:Outfit,system-ui,Segoe UI,Roboto,Helvetica,Arial,sans-serif;background:linear-gradient(135deg,#f8fafc 0%,#eef2ff 100%);color:#1f2937;}';
        echo '.modal-wrap{min-height:100vh;display:flex;justify-content:center;align-items:center;padding:1.5rem;position:relative;overflow:hidden;}';
        echo '.modal-wrap:before,.modal-wrap:after{content:"";position:absolute;border-radius:999px;filter:blur(18px);opacity:.6;}';
        echo '.modal-wrap:before{width:260px;height:260px;background:#c4b5fd;top:-60px;left:-40px;}';
        echo '.modal-wrap:after{width:220px;height:220px;background:#f9a8d4;bottom:-60px;right:-30px;}';
        echo '.card{position:relative;z-index:1;max-width:620px;width:100%;background:rgba(255,255,255,.95);backdrop-filter:blur(8px);border-radius:24px;box-shadow:0 28px 60px rgba(15,23,42,.18);padding:2rem;border:1px solid rgba(226,232,240,.9);}';
        echo '.tag{display:inline-flex;align-items:center;gap:.45rem;background:#dcfce7;color:#166534;padding:.4rem .8rem;border-radius:999px;font-size:.75rem;font-weight:800;margin-bottom:1rem;text-transform:uppercase;letter-spacing:.08em;}';
        echo '.title{font-size:2rem;font-weight:900;color:#0f172a;margin:0 0 .8rem;}';
        echo '.text{line-height:1.7;color:#475569;margin:.5rem 0 1rem;}';
        echo '.strong{font-weight:800;color:#0f172a;}';
        echo '.badge{display:inline-block;padding:.45rem .8rem;border-radius:.9rem;font-size:.95rem;background:#eef2ff;color:#5b21b6;font-weight:800;margin-top:.35rem;}';
        echo '.actions{display:flex;flex-wrap:wrap;gap:.8rem;margin-top:1.4rem;}';
        echo '.button{display:inline-flex;align-items:center;justify-content:center;padding:.9rem 1.2rem;font-weight:800;background:#111827;color:#fff;border:0;border-radius:.95rem;cursor:pointer;text-decoration:none;min-width:180px;}';
        echo '.button-secondary{display:inline-flex;align-items:center;justify-content:center;padding:.9rem 1.2rem;font-weight:800;background:#f8fafc;color:#334155;border:1px solid #cbd5e1;border-radius:.95rem;cursor:not-allowed;text-decoration:none;min-width:180px;opacity:.75;}';
        echo '.list{margin:.4rem 0 0 1.2rem;color:#475569;line-height:1.8;padding:0;}';
        echo '</style></head><body>';
        echo '<div class="modal-wrap">';
        echo '<div class="card">';
        echo '<div class="tag">Enrollment Success</div>';
        echo '<h1 class="title">Enrollment submitted successfully</h1>';
        echo '<p class="text">Your enrollment request has been saved in the system. Keep your LRN for future status checking and follow-up.</p>';
        echo '<p class="text"><span class="strong">Learner Reference Number</span><br><span class="badge">' . $clean_lrn . '</span></p>';
        echo '<p class="text">Please prepare these documents when needed:</p>';
        echo '<ul class="list">';
        echo '<li>School Card</li>';
        echo '<li>PSA Birth Certificate</li>';
        echo '<li>LRN for verification and status follow-up</li>';
        echo '</ul>';
        echo '<p class="text">The status checker is not connected yet, but the button is ready as a placeholder for your next step.</p>';
        echo '<div class="actions">';
        echo '<a href="index.php" class="button">Go to Landing Page</a>';
        echo '<a href="#" class="button-secondary" onclick="return false;" aria-disabled="true">Check Status</a>';
        echo '</div>';
        echo '</div></div>';
        echo '</body></html>';
        $stmt->close();
        $conn->close();
        exit;
    } else {
        if ($conn->errno == 1062) {
            die("Error: Duplicate LRN detected. LRN must be unique.");
        }
        die("Error: " . $stmt->error);
    }

    $stmt->close();
}

$conn->close();
?>
