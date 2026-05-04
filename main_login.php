<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login | DRANHS</title>
    <link rel="stylesheet" href="style.css">
    <style>
        .login-page {
            min-height: 100vh;
            display: grid;
            place-items: center;
            padding: 40px 16px;
            background: linear-gradient(180deg, #f8fafc 0%, #e2f7ec 100%);
        }
        .login-card {
            width: 100%;
            max-width: 420px;
            background: #ffffff;
            border: 1px solid rgba(148, 163, 184, 0.18);
            border-radius: 28px;
            box-shadow: 0 28px 80px rgba(15, 23, 42, 0.12);
            padding: 36px;
        }
        .login-card h1 {
            margin-bottom: 10px;
            font-size: 2rem;
        }
        .login-card p {
            margin-bottom: 26px;
            color: #475569;
        }
        .form-group {
            margin-bottom: 18px;
        }
        .form-group label {
            font-weight: 700;
            font-size: 0.9rem;
            color: #102a43;
            display: block;
            margin-bottom: 8px;
        }
        .form-group input {
            width: 100%;
            border: 1px solid #cbd5e1;
            border-radius: 18px;
            padding: 14px 18px;
            font-size: 0.95rem;
            outline: none;
            transition: border-color 0.2s ease, box-shadow 0.2s ease;
        }
        .form-group input:focus {
            border-color: #16a34a;
            box-shadow: 0 0 0 4px rgba(16, 163, 127, 0.12);
        }
        .login-actions {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 22px;
        }
        .login-actions a {
            color: #16a34a;
            font-weight: 700;
        }
        .login-footer {
            text-align: center;
            margin-top: 18px;
        }
    </style>
</head>
<body class="bg-slate-50 text-slate-900">
    <?php include 'components/main_navbar.php'; ?>
    <div class="login-page">
        <div class="login-card">
            <h1>Admin Login</h1>
            <p>Use the form below to go to the site editor. Authentication is disabled for now.</p>
            <form action="main_admin.php" method="POST">
                <div class="form-group">
                    <label for="username">Username</label>
                    <input type="text" id="username" name="username" placeholder="Enter your username" required>
                </div>
                <div class="form-group">
                    <label for="password">Password</label>
                    <input type="password" id="password" name="password" placeholder="Enter your password" required>
                </div>
                <div class="login-actions">
                    <span></span>
                    <a href="#">Forgot password?</a>
                </div>
                <button type="submit" class="btn btn-primary w-full">Login</button>
            </form>
            <div class="login-footer">
                <a href="index.php" class="btn btn-outline" style="display:inline-flex; justify-content:center; width:100%;">Go back to main page</a>
            </div>
        </div>
    </div>
    <?php include 'components/main_footer.php'; ?>
</body>
</html>
