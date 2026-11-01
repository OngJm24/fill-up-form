<?php
declare(strict_types=1);

require __DIR__ . '/../../src/bootstrap.php';

// Photos live outside the web root; only a signed-in admin can view them.
require_admin(true);

$file = is_string($_GET['f'] ?? null) ? $_GET['f'] : '';
if (!preg_match('/^(?:selfie|mood_[a-z]+)_[A-Za-z0-9_]+\.(?:jpe?g|png|webp)$/', $file)) {
    http_response_code(400);
    exit;
}

$path = UPLOAD_DIR . '/' . $file;
if (!is_file($path)) {
    http_response_code(404);
    exit;
}

$mime = (new finfo(FILEINFO_MIME_TYPE))->file($path);
if (!in_array($mime, ['image/jpeg', 'image/png', 'image/webp'], true)) {
    http_response_code(415);
    exit;
}

header('Content-Type: ' . $mime);
header('Content-Length: ' . filesize($path));
header('X-Content-Type-Options: nosniff');
header("Content-Security-Policy: default-src 'none'");
header('Cache-Control: private, max-age=3600');
readfile($path);
