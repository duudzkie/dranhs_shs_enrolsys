<?php
if (session_status() === PHP_SESSION_NONE) session_start();
// If the user is already logged in, redirect to the admin dashboard
if (isset($_SESSION['user_id'])) {
    header('Location: admin/admin.php');
    exit;
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    //
    // DATABASE CONNECTION
    //
    $db_host = 'localhost';
    $db_user = 'root';
    $db_pass = '';
    $db_name = 'dranhswin';

    $conn = new mysqli($db_host, $db_user, $db_pass, $db_name);
    if ($conn->connect_error) {
        die("Connection failed: " . $conn->connect_error);
    }

    $username = $_POST['username'];
    $password = $_POST['password'];

    $sql = "SELECT * FROM users WHERE username = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("s", $username);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        $user = $result->fetch_assoc();
        if (password_verify($password, $user['password'])) {
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['role'] = $user['role'];
            $_SESSION['logged_in'] = true;

            // If adviser, store their linked adviser_id and section
            if ($user['role'] === 'adviser') {
                $adv_stmt = $conn->prepare("SELECT a.id AS adviser_id, c.section_name, c.id AS classroom_id
                    FROM advisers_accounts a
                    LEFT JOIN classrooms c ON c.adviser_id = a.id
                    WHERE a.user_id = ? LIMIT 1");
                if ($adv_stmt) {
                    $adv_stmt->bind_param("i", $user['id']);
                    $adv_stmt->execute();
                    $adv_row = $adv_stmt->get_result()->fetch_assoc();
                    $adv_stmt->close();
                    $_SESSION['adviser_id']      = $adv_row['adviser_id']   ?? null;
                    $_SESSION['adviser_section'] = $adv_row['section_name'] ?? null;
                    $_SESSION['adviser_classroom_id'] = $adv_row['classroom_id'] ?? null;
                }
            }

            header('Location: admin/admin.php');
            exit;
        } else {
            $error = 'Invalid password.';
        }
    } else {
        $error = 'No user found with that username.';
    }

    $stmt->close();
    $conn->close();
}
?>
<!DOCTYPE html>
<html lang="en" class="dark">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login | DRANHS</title>

    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css" integrity="sha512-SnH5WK+bZxgPHs44uWIX+LLJAJ9/2PkPKZ5QiAj6Ta86w+fsb2TkcmfRyVX3pBnMFcV7oQPJkl9QevSCWr3W6A==" crossorigin="anonymous" referrerpolicy="no-referrer" />

    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        "dranhs-green": "#009b5a",
                        "dranhs-dark": "#202221",
                    },
                },
            },
            darkMode: 'class'
        };
    </script>
</head>

<body class="bg-slate-100 dark:bg-slate-950 transition-colors duration-300 antialiased">
    <?php include_once 'components/navbar.php'; ?>

    <!-- Main Content -->
    <main class="w-full min-h-screen h-full flex items-center justify-center pt-28 pb-10 px-4 lg:px-8">
        <div class="w-full max-w-md mx-auto">
            <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 p-6 lg:p-8 rounded-2xl shadow-lg">
                <h1 class="text-3xl font-black text-dranhs-dark dark:text-white tracking-tight text-center">Admin Login</h1>
                <p class="text-slate-500 dark:text-slate-400 text-sm mt-2 text-center">Access the DRANHS enrollment system.</p>

                <?php if ($error) : ?>
                    <div class="mt-6 bg-red-100 dark:bg-red-900/20 border border-red-200 dark:border-red-800/30 text-red-700 dark:text-red-400 px-4 py-3 rounded-lg text-sm">
                        <?= $error ?>
                    </div>
                <?php endif; ?>

                <form class="mt-6" action="login.php" method="POST">
                    <div class="flex flex-col gap-5">
                        <div class="flex flex-col gap-2">
                            <label for="username" class="text-sm font-bold text-slate-700 dark:text-slate-300">Username</label>
                            <input type="text" id="username" name="username" placeholder="Enter your username" required class="w-full bg-slate-50 dark:bg-slate-800 border border-slate-200 dark:border-slate-700 px-4 py-3 rounded-lg text-slate-800 dark:text-white text-sm outline-none transition-all focus:border-dranhs-green dark:focus:border-emerald-500 focus:ring-2 focus:ring-dranhs-green/20 placeholder-slate-400 dark:placeholder-slate-500 font-medium">
                        </div>
                        <div class="flex flex-col gap-2">
                            <label for="password" class="text-sm font-bold text-slate-700 dark:text-slate-300">Password</label>
                            <input type="password" id="password" name="password" placeholder="Enter your password" required class="w-full bg-slate-50 dark:bg-slate-800 border border-slate-200 dark:border-slate-700 px-4 py-3 rounded-lg text-slate-800 dark:text-white text-sm outline-none transition-all focus:border-dranhs-green dark:focus:border-emerald-500 focus:ring-2 focus:ring-dranhs-green/20 placeholder-slate-400 dark:placeholder-slate-500 font-medium">
                        </div>
                        <button type="submit" class="w-full bg-dranhs-green hover:bg-emerald-700 text-white border-none px-6 py-3 rounded-lg font-bold text-sm cursor-pointer transition-transform shadow-md hover:-translate-y-0.5">
                            LOGIN
                        </button>
                    </div>
                </form>
            </div>

            <p class="text-xs text-slate-500 dark:text-slate-600 text-center mt-6">
                &copy; <?php echo date("Y"); ?> DRANHS. All rights reserved.
            </p>
        </div>
    </main>

    <script src="script.js"></script>
</body>

</html>