<?php
// Copy this file to config.local.php and fill in real values. config.local.php is git-ignored —
// never commit real passwords.
return [
    'db' => [
        'host' => 'localhost',
        'port' => 3306,
        'user' => 'root',
        'pass' => '',
        'name' => 'simple-forms',
    ],
    'smtp' => [
        'host'      => 'smtp.gmail.com',
        'port'      => 587,
        'user'      => 'you@gmail.com',
        'pass'      => 'your-16-char-app-password',
        'from'      => 'you@gmail.com',
        'from_name' => 'Simple Form Team',
    ],
    'admin' => [
        'username'      => 'admin',
        // Set with: php bin/set-admin-password.php
        'password_hash' => '',
    ],
    // Random secret used to hash visitor IPs for rate limiting. Generate with:
    // php -r "echo bin2hex(random_bytes(32));"
    'app_secret' => 'change-me',
    'timezone'   => 'Asia/Manila',
];
