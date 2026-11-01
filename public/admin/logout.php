<?php
declare(strict_types=1);

require __DIR__ . '/../../src/bootstrap.php';

// Logging out is a POST with a CSRF token so other sites can't sign the admin out.
if ($_SERVER['REQUEST_METHOD'] === 'POST' && verify_csrf()) {
    admin_logout();
}
header('Location: login.php');
exit;
