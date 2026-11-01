<?php
declare(strict_types=1);

require __DIR__ . '/../src/bootstrap.php';
require __DIR__ . '/../src/form.php';
require __DIR__ . '/../src/mailer.php';

send_security_headers();
set_time_limit(90);

$wantsJson = str_contains($_SERVER['HTTP_ACCEPT'] ?? '', 'application/json');

function respond(int $status, array $payload, bool $json): never
{
    http_response_code($status);
    if ($json) {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($payload);
        exit;
    }
    // Fallback page for browsers without JavaScript.
    $ok = $payload['ok'] ?? false;
    $title = $ok ? 'You did it!' : 'Please check your answers';
    echo '<!DOCTYPE html><html lang="en"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1">'
        . '<title>' . e($title) . '</title><link rel="stylesheet" href="assets/app.css"></head><body class="plain"><main class="plain-card">'
        . '<h1>' . e($title) . '</h1>';
    if ($ok) {
        echo '<p>Your profile was saved. Check your inbox for a confirmation email.</p>';
    } else {
        echo '<p>' . e($payload['message'] ?? 'Something went wrong.') . '</p><ul>';
        foreach ($payload['errors'] ?? [] as $field => $msg) {
            echo '<li><b>' . e(form_fields()[$field]['label'] ?? $field) . ':</b> ' . e($msg) . '</li>';
        }
        echo '</ul>';
    }
    echo '<a class="btn btn-primary" href="./">Back to the form</a></main></body></html>';
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ./');
    exit;
}

// A request bigger than post_max_size arrives with empty $_POST.
if (empty($_POST) && (int)($_SERVER['CONTENT_LENGTH'] ?? 0) > 0) {
    respond(413, ['ok' => false, 'message' => 'Your photos are too large. Please use smaller images.'], $wantsJson);
}

if (!verify_csrf()) {
    respond(419, ['ok' => false, 'message' => 'Your session expired. Please refresh the page and try again.'], $wantsJson);
}

// Bots fill in every field, including this invisible one.
if (!empty($_POST['website'])) {
    respond(200, ['ok' => true, 'name' => ''], $wantsJson);
}

try {
    if (throttle_exceeded('submit', 20, 600)) {
        respond(429, ['ok' => false, 'message' => 'Too many submissions from your network. Please wait a few minutes.'], $wantsJson);
    }

    [$values, $errors] = validate_submission($_POST);

    $selfieBytes = decode_data_url_image($_POST['selfie_base64'] ?? null);
    if ($selfieBytes === null) {
        $errors['selfie'] = 'Please take or upload a selfie.';
    }

    $moodBytes = [];
    foreach (MOODS as $mood => $_) {
        [$bytes, $err] = read_upload('mood_photo_' . $mood);
        $reason = $values['mood_reason_' . $mood];
        if ($err) {
            $errors['mood_photo_' . $mood] = $err;
        } elseif ($bytes !== null) {
            if ($reason === '') {
                $errors['mood_reason_' . $mood] = 'Tell us why — or remove the photo.';
            }
            $moodBytes[$mood] = $bytes;
        } elseif ($reason !== '') {
            $errors['mood_photo_' . $mood] = 'Add a photo for this mood — or clear the reason.';
        }
    }

    if (!isset($errors['email'])) {
        $stmt = db()->prepare('SELECT 1 FROM submissions WHERE email = ? AND submitted_at >= (NOW() - INTERVAL 1 MINUTE)');
        $stmt->bind_param('s', $values['email']);
        $stmt->execute();
        if ($stmt->get_result()->num_rows > 0) {
            respond(429, ['ok' => false, 'message' => 'You just submitted with this email. Please wait a minute before trying again.'], $wantsJson);
        }
    }

    if ($errors) {
        respond(422, ['ok' => false, 'message' => 'Some answers need another look.', 'errors' => $errors], $wantsJson);
    }

    // Everything is valid — now (and only now) write files.
    $written = [];
    $selfie = store_image($selfieBytes, 'selfie');
    if ($selfie === null) {
        respond(422, ['ok' => false, 'message' => 'Your selfie could not be read.', 'errors' => ['selfie' => 'Please retake your selfie.']], $wantsJson);
    }
    $written[] = $selfie;

    $moods = [];
    foreach ($moodBytes as $mood => $bytes) {
        $file = store_image($bytes, 'mood_' . $mood);
        if ($file === null) {
            array_map(fn($f) => @unlink(UPLOAD_DIR . '/' . $f), $written);
            respond(422, ['ok' => false, 'message' => 'One of your photos could not be read.',
                'errors' => ['mood_photo_' . $mood => 'This photo looks damaged. Please choose another.']], $wantsJson);
        }
        $written[] = $file;
        $moods[$mood] = ['file' => $file, 'reason' => $values['mood_reason_' . $mood]];
    }

    $conn = db();
    $conn->begin_transaction();
    try {
        $cols = array_keys(form_fields());
        $sql = 'INSERT INTO submissions (' . implode(', ', $cols) . ', selfie) VALUES (' . rtrim(str_repeat('?, ', count($cols) + 1), ', ') . ')';
        $params = array_map(fn($c) => $values[$c], $cols);
        $params[] = $selfie;
        $stmt = $conn->prepare($sql);
        $stmt->bind_param(str_repeat('s', count($params)), ...$params);
        $stmt->execute();
        $id = (int)$conn->insert_id;

        $moodStmt = $conn->prepare('INSERT INTO mood_photos (submission_id, mood, photo_path, reason) VALUES (?, ?, ?, ?)');
        foreach ($moods as $mood => $m) {
            $moodStmt->bind_param('isss', $id, $mood, $m['file'], $m['reason']);
            $moodStmt->execute();
        }
        $conn->commit();
    } catch (Throwable $ex) {
        $conn->rollback();
        array_map(fn($f) => @unlink(UPLOAD_DIR . '/' . $f), $written);
        throw $ex;
    }

    $emailed = send_confirmation_email($id, $values, $selfie, $moods);

    // Fresh token so the same form page can't be replayed.
    unset($_SESSION['csrf']);
    respond(200, ['ok' => true, 'name' => $values['name'], 'emailed' => $emailed], $wantsJson);
} catch (Throwable $ex) {
    error_log('submit.php: ' . $ex);
    respond(500, ['ok' => false, 'message' => 'Something went wrong on our side. Please try again in a moment.'], $wantsJson);
}
