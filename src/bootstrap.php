<?php
declare(strict_types=1);

define('APP_ROOT', dirname(__DIR__));
define('UPLOAD_DIR', APP_ROOT . '/storage/uploads');
define('LOG_DIR', APP_ROOT . '/storage/logs');

// Never show raw PHP errors to visitors; log them privately instead.
ini_set('display_errors', '0');
ini_set('log_errors', '1');
ini_set('error_log', LOG_DIR . '/php_error.log');
error_reporting(E_ALL);

$configFile = APP_ROOT . '/config.local.php';
if (!is_file($configFile)) {
    http_response_code(500);
    exit('Server is not configured yet. Copy config.example.php to config.local.php.');
}
$GLOBALS['APP_CONFIG'] = require $configFile;
date_default_timezone_set($GLOBALS['APP_CONFIG']['timezone'] ?? 'Asia/Manila');

require_once APP_ROOT . '/vendor/autoload.php';

function config(string $key, mixed $default = null): mixed
{
    return $GLOBALS['APP_CONFIG'][$key] ?? $default;
}

function db(): mysqli
{
    static $conn = null;
    if ($conn === null) {
        mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
        $c = config('db');
        $conn = new mysqli($c['host'], $c['user'], $c['pass'], $c['name'], (int)($c['port'] ?? 3306));
        $conn->set_charset('utf8mb4');
    }
    return $conn;
}

/** HTML-escape for output. */
function e(?string $value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function is_https(): bool
{
    return (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (($_SERVER['SERVER_PORT'] ?? '') === '443');
}

function send_security_headers(): void
{
    header("Content-Security-Policy: default-src 'self'; "
        . "script-src 'self'; "
        . "style-src 'self' https://fonts.googleapis.com; "
        . "font-src https://fonts.gstatic.com; "
        . "img-src 'self' data: blob:; "
        . "media-src 'self' blob: mediastream:; "
        . "connect-src 'self'; "
        . "form-action 'self'; "
        . "frame-ancestors 'none'; base-uri 'none'; object-src 'none'");
    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: DENY');
    header('Referrer-Policy: same-origin');
    header('Permissions-Policy: camera=(self), microphone=(), geolocation=()');
    header('Cross-Origin-Opener-Policy: same-origin');
    if (is_https()) {
        header('Strict-Transport-Security: max-age=31536000');
    }
}

function start_session(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        return;
    }
    ini_set('session.use_strict_mode', '1');
    ini_set('session.use_only_cookies', '1');
    session_name('fuf_sid');
    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => '/',
        'secure'   => is_https(),
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();
}

function csrf_token(): string
{
    start_session();
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf'];
}

function csrf_field(): string
{
    return '<input type="hidden" name="csrf_token" value="' . e(csrf_token()) . '">';
}

function verify_csrf(): bool
{
    start_session();
    $sent = $_POST['csrf_token'] ?? '';
    return is_string($sent) && !empty($_SESSION['csrf']) && hash_equals($_SESSION['csrf'], $sent);
}

/** Hashed so raw visitor IPs are never stored. */
function client_key(): string
{
    return hash_hmac('sha256', $_SERVER['REMOTE_ADDR'] ?? 'unknown', config('app_secret'));
}

/**
 * Records one attempt for $action and reports whether this visitor is now over the limit.
 */
function throttle_exceeded(string $action, int $max, int $windowSeconds, bool $record = true): bool
{
    $conn = db();
    $key = client_key();

    $conn->query('DELETE FROM throttle WHERE attempted_at < (NOW() - INTERVAL 1 DAY)');

    $stmt = $conn->prepare('SELECT COUNT(*) FROM throttle WHERE action = ? AND client_key = ? AND attempted_at >= (NOW() - INTERVAL ? SECOND)');
    $stmt->bind_param('ssi', $action, $key, $windowSeconds);
    $stmt->execute();
    $count = (int)$stmt->get_result()->fetch_row()[0];

    if ($record) {
        $ins = $conn->prepare('INSERT INTO throttle (action, client_key) VALUES (?, ?)');
        $ins->bind_param('ss', $action, $key);
        $ins->execute();
    }
    return $count >= $max;
}

function throttle_clear(string $action): void
{
    $key = client_key();
    $stmt = db()->prepare('DELETE FROM throttle WHERE action = ? AND client_key = ?');
    $stmt->bind_param('ss', $action, $key);
    $stmt->execute();
}

function app_log(string $file, string $message): void
{
    file_put_contents(LOG_DIR . '/' . $file, '[' . date('Y-m-d H:i:s') . '] ' . $message . "\n", FILE_APPEND | LOCK_EX);
}

/* ---------- Admin session ---------- */

const ADMIN_IDLE_TIMEOUT = 1800;

function admin_is_logged_in(): bool
{
    start_session();
    if (empty($_SESSION['admin']) || empty($_SESSION['admin_last_seen'])) {
        return false;
    }
    if (time() - $_SESSION['admin_last_seen'] > ADMIN_IDLE_TIMEOUT) {
        admin_logout();
        return false;
    }
    $_SESSION['admin_last_seen'] = time();
    return true;
}

function require_admin(bool $json = false): void
{
    if (admin_is_logged_in()) {
        return;
    }
    if ($json) {
        http_response_code(403);
        header('Content-Type: application/json');
        exit(json_encode(['error' => 'Forbidden']));
    }
    header('Location: login.php');
    exit;
}

function admin_logout(): void
{
    start_session();
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $p = session_get_cookie_params();
        setcookie(session_name(), '', [
            'expires' => time() - 42000, 'path' => $p['path'], 'secure' => $p['secure'],
            'httponly' => $p['httponly'], 'samesite' => $p['samesite'],
        ]);
    }
    session_destroy();
}
