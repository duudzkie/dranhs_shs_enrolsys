<?php
require_once __DIR__ . '/session.php';
ems2_session_start();

require_once __DIR__ . '/db.php';
$conn = db_connect();

$enrollment_locked = false;
$_theme = [];
if (!$conn->connect_error) {
    $res = $conn->query("SELECT setting_key, setting_value FROM system_settings WHERE setting_key IN ('enrollment_status','school_logo','background','deped_logo','division_logo')");
    if ($res) {
        while ($row = $res->fetch_assoc()) {
            if ($row['setting_key'] === 'enrollment_status') {
                $enrollment_locked = ($row['setting_value'] === 'locked');
            } else {
                $_theme[$row['setting_key']] = $row['setting_value'];
            }
        }
    }
}
$_bg_image = !empty($_theme['background']) ? $_theme['background'] : null;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>DRANHS SMARTENROLL</title>
    <meta name="description" content="Forge your path to excellence at Davao's premier SHS community.">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:ital,wght@0,400;0,700;0,900;1,800&family=Outfit:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            darkMode: 'class',
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
    <style>
        /* Only lock scrolling on larger screens where everything fits */
        @media (min-width: 1024px) {
            body.no-scroll { 
                overflow: hidden;
            }
        }
        .bg-school-pattern {
            background-color: #f8fafc;
            /* Placeholder pattern that gives a light textured feel like a washed out background image */
            background-image: linear-gradient(rgba(255, 255, 255, 0.7), rgba(255, 255, 255, 0.95)), url('data:image/svg+xml,%3Csvg width="60" height="60" viewBox="0 0 60 60" xmlns="http://www.w3.org/2000/svg"%3E%3Cg fill="none" fill-rule="evenodd"%3E%3Cg fill="%23cbd5e1" fill-opacity="0.3"%3E%3Cpath d="M36 34v-4h-2v4h-4v2h4v4h2v-4h4v-2h-4zm0-30V0h-2v4h-4v2h4v4h2V6h4V4h-4zM6 34v-4H4v4H0v2h4v4h2v-4h4v-2H6zM6 4V0H4v4H0v2h4v4h2V6h4V4H6z"/%3E%3C/g%3E%3C/g%3E%3C/svg%3E');
            background-size: cover;
            background-position: center;
        }
    </style>
</head>
<body class="bg-slate-50 text-slate-800 min-h-screen flex flex-col no-scroll font-sans relative dark:bg-slate-900 dark:text-slate-100 transition-colors duration-300"
    <?php if ($_bg_image): ?>
    style="background-image: url('<?php echo htmlspecialchars($_bg_image); ?>'); background-size: cover; background-position: center; background-attachment: fixed;"
    <?php endif; ?>>

    <!-- Background overlay -->
    <div class="absolute inset-0 z-[-1] <?php echo $_bg_image ? 'bg-white/80 dark:bg-slate-900/80' : 'bg-school-pattern'; ?>"></div>

    <!-- Include Navbar -->
    <?php include 'components/navbar.php'; ?>

    <!-- Include Main Hero Section -->
    <?php include 'components/hero.php'; ?>

    <!-- Include Footer -->
    <?php include 'components/footer.php'; ?>

    <!-- Include Enrollment Modal -->
    <?php include 'components/enroll_modal.php'; ?>

    <script src="script.js?v=<?php echo time(); ?>"></script>
</body>
</html>
