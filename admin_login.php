<?php
session_start();

// 1. If the admin is already logged in, just send them to the dashboard
if (isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true) {
    header("Location: admin_dashboard.php");
    exit;
}

// 2. Handle the Login Form Submission
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $user = $_POST['username'] ?? '';
    $pass = $_POST['password'] ?? '';

    if ($user === 'admin' && $pass === '123123') {
        $_SESSION['admin_logged_in'] = true;
        header("Location: admin_dashboard.php");
        exit;
    } else {
        // Redirect back to login page with an error message
        header("Location: admin_login.php?error=1");
        exit;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Login</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary-color: #6366f1;
            --primary-hover: #4f46e5;
            --bg-gradient: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            --card-bg: rgba(255, 255, 255, 0.95);
            --text-main: #1e293b;
            --text-muted: #64748b;
            --border-color: #e2e8f0;
            --shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1);
        }
        body {
            font-family: 'Inter', sans-serif;
            background: var(--bg-gradient);
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
            margin: 0;
            padding: 20px;
        }
        .login-card {
            background: var(--card-bg);
            width: 100%;
            max-width: 400px;
            padding: 3rem;
            border-radius: 24px;
            box-shadow: var(--shadow);
            text-align: center;
        }
        h2 { margin-bottom: 1.5rem; color: var(--text-main); font-weight: 700; }
        .form-group { text-align: left; margin-bottom: 1.5rem; }
        label { display: block; margin-bottom: 0.5rem; font-size: 0.875rem; font-weight: 500; color: var(--text-main); }
        input {
            width: 100%;
            padding: 0.8rem;
            border: 1px solid var(--border-color);
            border-radius: 12px;
            font-size: 1rem;
            box-sizing: border-box;
        }
        .btn-login {
            width: 100%;
            padding: 1rem;
            background: var(--primary-color);
            color: white;
            border: none;
            border-radius: 12px;
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s;
        }
        .btn-login:hover { background: var(--primary-hover); transform: translateY(-2px); }
        .error-msg { color: #ef4444; margin-bottom: 1rem; font-size: 0.875rem; display: none; }
    </style>
</head>
<body>
    <div class="login-card">
        <h2>Admin Access</h2>
        <div id="error" class="error-msg">Invalid username or password!</div>
        <form action="admin_login.php" method="POST">
            <div class="form-group">
                <label>Username</label>
                <input type="text" name="username" required>
            </div>
            <div class="form-group">
                <label>Password</label>
                <input type="password" name="password" required>
            </div>
            <button type="submit" class="btn-login">Login to Dashboard</button>
        </form>
    </div>

    <script>
        if (window.location.search.includes('error')) {
            document.getElementById('error').style.display = 'block';
        }
    </script>
</body>
</html>
