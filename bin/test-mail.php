<?php
// Usage: php bin/test-mail.php you@example.com   — sends a test email using config.local.php
if (PHP_SAPI !== 'cli') {
    exit("Run this from the command line.\n");
}
require dirname(__DIR__) . '/src/bootstrap.php';
require dirname(__DIR__) . '/src/mailer.php';

$to = $argv[1] ?? config('smtp')['user'];
try {
    $mail = make_mailer();
    $mail->addAddress($to);
    $mail->Subject = 'SMTP connection test';
    $mail->Body = 'If you can read this, your SMTP settings work.';
    $mail->send();
    echo "Sent to $to\n";
} catch (Throwable $e) {
    echo 'Failed: ' . ($mail->ErrorInfo ?? $e->getMessage()) . "\n";
    exit(1);
}
