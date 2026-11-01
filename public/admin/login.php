<?php
declare(strict_types=1);

require __DIR__ . '/../../src/bootstrap.php';

send_security_headers();
header('Cache-Control: no-store');

if (admin_is_logged_in()) {
    header('Location: ./');
    exit;
}

$error = '';
$username = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = is_string($_POST['username'] ?? null) ? trim($_POST['username']) : '';
    $password = is_string($_POST['password'] ?? null) ? $_POST['password'] : '';
    $admin = config('admin');

    if (!verify_csrf()) {
        $error = 'Your session expired. Please try again.';
    } elseif (throttle_exceeded('login', 5, 900, false)) {
        $error = 'Too many failed attempts. Please wait 15 minutes and try again.';
    } else {
        $hash = $admin['password_hash'] ?? '';
        // Always run password_verify so a wrong username takes as long as a wrong password.
        $passOk = password_verify($password, $hash !== '' ? $hash : password_hash(random_bytes(16), PASSWORD_DEFAULT));
        $userOk = hash_equals((string)$admin['username'], $username);

        if ($hash !== '' && $userOk && $passOk) {
            throttle_clear('login');
            session_regenerate_id(true);
            $_SESSION['admin'] = $admin['username'];
            $_SESSION['admin_last_seen'] = time();
            unset($_SESSION['csrf']);
            header('Location: ./');
            exit;
        }
        throttle_exceeded('login', 5, 900);
        $error = $hash === '' ? 'Admin password is not set. Run: php bin/set-admin-password.php' : 'Incorrect username or password.';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title>Admin sign in</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../assets/app.css">
</head>
<body class="auth-page">
    <div class="aurora" aria-hidden="true"><span></span><span></span><span></span></div>
    <main class="auth-card">
        <div class="brand brand-dark">
            <span class="brand-mark" aria-hidden="true">✦</span>
            <span>Admin console</span>
        </div>
        <h1>Welcome back</h1>
        <p class="muted">Sign in to view form submissions.</p>

        <?php if ($error): ?>
            <div class="alert alert-error" role="alert"><?= e($error) ?></div>
        <?php endif; ?>

        <form method="post" action="login.php" class="stack" novalidate>
            <?= csrf_field() ?>
            <div class="field">
                <label for="username">Username</label>
                <input id="username" name="username" type="text" autocomplete="username" required maxlength="100" value="<?= e($username) ?>" autofocus>
            </div>
            <div class="field">
                <label for="password">Password</label>
                <input id="password" name="password" type="password" autocomplete="current-password" required maxlength="200">
            </div>
            <button type="submit" class="btn btn-primary btn-block">Sign in</button>
        </form>
        <a class="back-link" href="../">← Back to the form</a>
    </main>
</body>
</html>
