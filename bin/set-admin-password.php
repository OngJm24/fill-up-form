<?php
// Usage:  php bin/set-admin-password.php            (prompts for a password)
//         php bin/set-admin-password.php --generate (creates a strong random one)
if (PHP_SAPI !== 'cli') {
    exit("Run this from the command line.\n");
}

$configFile = dirname(__DIR__) . '/config.local.php';
$config = require $configFile;

if (in_array('--generate', $argv, true)) {
    $alphabet = 'ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnopqrstuvwxyz23456789';
    $password = '';
    for ($i = 0; $i < 20; $i++) {
        $password .= $alphabet[random_int(0, strlen($alphabet) - 1)];
    }
} else {
    echo 'New admin password (min 12 chars): ';
    $password = trim((string)fgets(STDIN));
    if (strlen($password) < 12) {
        exit("Password must be at least 12 characters.\n");
    }
}

$config['admin']['password_hash'] = password_hash($password, PASSWORD_DEFAULT);
$out = "<?php\n// Local secrets — git-ignored. See config.example.php.\nreturn " . var_export($config, true) . ";\n";
if (file_put_contents($configFile, $out, LOCK_EX) === false) {
    exit("Could not write config.local.php\n");
}

echo "Admin password updated for user '{$config['admin']['username']}'.\n";
if (in_array('--generate', $argv, true)) {
    echo "Generated password: $password\n";
}
