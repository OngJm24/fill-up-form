<?php
declare(strict_types=1);

/*
 * Single source of truth for the form's fields. index.php renders the HTML
 * constraints from this list and submit.php validates against it, so the
 * browser and the server always agree on what "valid" means.
 */

const NAME_PATTERN = "^[\\p{L}][\\p{L}\\p{M} .'-]*$";
const SIM_NETWORKS = ['Globe', 'TNT', 'Smart', 'DITO', 'TM', 'Other'];
const MOODS = [
    'happy'   => ['label' => 'Happy',   'emoji' => '😊', 'prompt' => 'What makes you happy?'],
    'sad'     => ['label' => 'Sad',     'emoji' => '😢', 'prompt' => 'What makes you sad?'],
    'excited' => ['label' => 'Excited', 'emoji' => '🤩', 'prompt' => 'What gets you excited?'],
    'angry'   => ['label' => 'Angry',   'emoji' => '😡', 'prompt' => 'What makes you angry?'],
];
const MAX_UPLOAD_BYTES = 5 * 1024 * 1024;
const MAX_SELFIE_BYTES = 3 * 1024 * 1024;

function form_fields(): array
{
    return [
        // Step 1
        'name'     => ['label' => 'Full name', 'type' => 'text', 'min' => 2, 'max' => 100, 'pattern' => NAME_PATTERN,
                       'pattern_msg' => 'Use letters, spaces, periods, apostrophes or hyphens only.',
                       'placeholder' => 'Juan Dela Cruz', 'autocomplete' => 'name'],
        'email'    => ['label' => 'Email address', 'type' => 'email', 'min' => 6, 'max' => 100,
                       'placeholder' => 'juan@example.com', 'autocomplete' => 'email'],
        'address'  => ['label' => 'Home address', 'type' => 'text', 'min' => 5, 'max' => 255,
                       'placeholder' => 'Street, Barangay, City', 'autocomplete' => 'street-address'],
        'birthday' => ['label' => 'Birthday', 'type' => 'date', 'autocomplete' => 'bday'],
        'phone'    => ['label' => 'Mobile number', 'type' => 'tel', 'min' => 11, 'max' => 16,
                       'placeholder' => '0917 123 4567', 'autocomplete' => 'tel', 'inputmode' => 'tel'],
        'sim_card' => ['label' => 'SIM network', 'type' => 'choice', 'options' => SIM_NETWORKS],
        // Step 2
        'fav_food'  => ['label' => 'Favorite food',  'type' => 'text', 'min' => 2, 'max' => 100, 'placeholder' => 'Sinigang'],
        'fav_drink' => ['label' => 'Favorite drink', 'type' => 'text', 'min' => 2, 'max' => 100, 'placeholder' => 'Iced coffee'],
        'hobby'     => ['label' => 'Hobby',          'type' => 'text', 'min' => 2, 'max' => 100, 'placeholder' => 'Photography'],
        'fav_color' => ['label' => 'Favorite color', 'type' => 'text', 'min' => 3, 'max' => 50,  'placeholder' => 'Emerald green'],
        // Step 3
        'fun_question' => ['label' => 'If you could have any superpower for one day, what would it be and why?',
                           'type' => 'textarea', 'min' => 10, 'max' => 1000, 'placeholder' => 'I would love to fly because…'],
        'fav_animal'   => ['label' => 'If you were an animal, which one would you be?', 'type' => 'text', 'min' => 2, 'max' => 100, 'placeholder' => 'A lazy cat'],
        'weird_food'   => ['label' => 'Weirdest food combo you love?', 'type' => 'text', 'min' => 2, 'max' => 100, 'placeholder' => 'Fries dipped in ice cream'],
        // Step 4
        'biggest_dream' => ['label' => 'What is your biggest dream in life?', 'type' => 'textarea', 'min' => 10, 'max' => 1000, 'placeholder' => 'I want to travel the world…'],
        'role_model'    => ['label' => 'Who is your role model?', 'type' => 'text', 'min' => 2, 'max' => 100, 'placeholder' => 'My parents'],
        'future_self'   => ['label' => 'Where do you see yourself in 10 years?', 'type' => 'text', 'min' => 5, 'max' => 255, 'placeholder' => 'A successful engineer'],
        'world_change'  => ['label' => 'What is one thing you want to change about the world?', 'type' => 'textarea', 'min' => 10, 'max' => 1000, 'placeholder' => 'I wish there was no more…'],
    ];
}

/** Collapse whitespace and drop control characters; keep newlines only for textareas. */
function clean_text(mixed $value, bool $multiline): string
{
    if (!is_string($value)) {
        return '';
    }
    if (!mb_check_encoding($value, 'UTF-8')) {
        return '';
    }
    $value = str_replace(["\r\n", "\r"], "\n", $value);
    $value = preg_replace($multiline ? '/[^\P{C}\n]/u' : '/\p{C}/u', '', $value) ?? '';
    $value = $multiline
        ? preg_replace("/\n{3,}/", "\n\n", preg_replace('/[ \t]+/', ' ', $value))
        : preg_replace('/\s+/u', ' ', $value);
    return trim((string)$value);
}

/** Accepts 09XXXXXXXXX, +639XXXXXXXXX or 639XXXXXXXXX (spaces/dashes allowed); returns 09XXXXXXXXX or null. */
function normalize_ph_mobile(string $raw): ?string
{
    $digits = preg_replace('/[\s\-().]/', '', $raw);
    if (preg_match('/^(?:\+?63|0)(9\d{9})$/', $digits, $m)) {
        return '0' . $m[1];
    }
    return null;
}

/**
 * @return array{0: array<string,string>, 1: array<string,string>} [clean values, errors]
 */
function validate_submission(array $post): array
{
    $values = [];
    $errors = [];

    foreach (form_fields() as $key => $f) {
        $raw = clean_text($post[$key] ?? '', $f['type'] === 'textarea');
        $values[$key] = $raw;

        if ($raw === '') {
            $errors[$key] = 'This field is required.';
            continue;
        }

        $len = mb_strlen($raw);
        if (isset($f['min']) && $len < $f['min'] && $f['type'] !== 'tel') {
            $errors[$key] = "Please enter at least {$f['min']} characters.";
            continue;
        }
        if (isset($f['max']) && $len > $f['max']) {
            $errors[$key] = "Please keep it under {$f['max']} characters.";
            continue;
        }
        if (isset($f['pattern']) && !preg_match('/' . $f['pattern'] . '/u', $raw)) {
            $errors[$key] = $f['pattern_msg'];
            continue;
        }

        switch ($f['type']) {
            case 'email':
                if (!filter_var($raw, FILTER_VALIDATE_EMAIL)) {
                    $errors[$key] = 'Please enter a valid email address.';
                } else {
                    $values[$key] = mb_strtolower($raw);
                }
                break;
            case 'tel':
                $phone = normalize_ph_mobile($raw);
                if ($phone === null) {
                    $errors[$key] = 'Enter a PH mobile number like 0917 123 4567.';
                } else {
                    $values[$key] = $phone;
                }
                break;
            case 'date':
                $d = DateTimeImmutable::createFromFormat('!Y-m-d', $raw);
                if (!$d || $d->format('Y-m-d') !== $raw) {
                    $errors[$key] = 'Please enter a valid date.';
                    break;
                }
                $today = new DateTimeImmutable('today');
                $age = $d->diff($today)->y;
                if ($d > $today) {
                    $errors[$key] = 'Your birthday can’t be in the future.';
                } elseif ($age < 5 || $age > 120) {
                    $errors[$key] = 'Please enter your real birthday.';
                }
                break;
            case 'choice':
                if (!in_array($raw, $f['options'], true)) {
                    $errors[$key] = 'Please pick one of the options.';
                }
                break;
        }
    }

    foreach (MOODS as $mood => $_) {
        $values['mood_reason_' . $mood] = clean_text($post['mood_reason_' . $mood] ?? '', true);
        if (mb_strlen($values['mood_reason_' . $mood]) > 500) {
            $errors['mood_reason_' . $mood] = 'Please keep it under 500 characters.';
        }
    }

    if (($post['consent'] ?? '') !== '1') {
        $errors['consent'] = 'Please agree before submitting.';
    }

    return [$values, $errors];
}

/**
 * Decodes, verifies and re-encodes an image as a fresh JPEG. Re-encoding throws away anything
 * that isn't pixels (hidden scripts, EXIF/GPS location data), so only a real photo is stored.
 * Returns the stored file name, or null when the bytes aren't a usable image.
 */
function store_image(string $bytes, string $prefix): ?string
{
    $info = @getimagesizefromstring($bytes);
    if ($info === false || !in_array($info[2], [IMAGETYPE_JPEG, IMAGETYPE_PNG, IMAGETYPE_WEBP], true)) {
        return null;
    }
    [$w, $h] = $info;
    if ($w < 64 || $h < 64 || $w > 8000 || $h > 8000) {
        return null;
    }

    $src = @imagecreatefromstring($bytes);
    if ($src === false) {
        return null;
    }

    if ($info[2] === IMAGETYPE_JPEG && function_exists('exif_read_data')) {
        $exif = @exif_read_data('data://image/jpeg;base64,' . base64_encode($bytes));
        $rot = [3 => 180, 6 => -90, 8 => 90][$exif['Orientation'] ?? 1] ?? 0;
        if ($rot) {
            $src = imagerotate($src, $rot, 0);
            [$w, $h] = [imagesx($src), imagesy($src)];
        }
    }

    $scale = min(1, 1280 / max($w, $h));
    $nw = (int)round($w * $scale);
    $nh = (int)round($h * $scale);
    $dst = imagecreatetruecolor($nw, $nh);
    imagefill($dst, 0, 0, imagecolorallocate($dst, 255, 255, 255));
    imagecopyresampled($dst, $src, 0, 0, 0, 0, $nw, $nh, $w, $h);

    $name = $prefix . '_' . bin2hex(random_bytes(12)) . '.jpg';
    $ok = imagejpeg($dst, UPLOAD_DIR . '/' . $name, 85);
    return $ok ? $name : null;
}

/** Turns a canvas data URL into bytes, or null if it isn't a small JPEG/PNG/WebP data URL. */
function decode_data_url_image(mixed $dataUrl): ?string
{
    if (!is_string($dataUrl) || strlen($dataUrl) > MAX_SELFIE_BYTES * 1.4) {
        return null;
    }
    if (!preg_match('#^data:image/(jpeg|png|webp);base64,([A-Za-z0-9+/=]+)$#', $dataUrl, $m)) {
        return null;
    }
    $bytes = base64_decode($m[2], true);
    return ($bytes === false || strlen($bytes) > MAX_SELFIE_BYTES) ? null : $bytes;
}

/** Reads one uploaded file safely. Returns [bytes|null, error|null]; [null, null] means "no file". */
function read_upload(string $field): array
{
    $f = $_FILES[$field] ?? null;
    if (!$f || !is_array($f) || ($f['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
        return [null, null];
    }
    if (is_array($f['error'])) {
        return [null, 'Please upload a single photo.'];
    }
    if ($f['error'] === UPLOAD_ERR_INI_SIZE || $f['error'] === UPLOAD_ERR_FORM_SIZE || $f['size'] > MAX_UPLOAD_BYTES) {
        return [null, 'Photos must be 5 MB or smaller.'];
    }
    if ($f['error'] !== UPLOAD_ERR_OK || !is_uploaded_file($f['tmp_name'])) {
        return [null, 'The upload failed. Please try again.'];
    }
    $mime = (new finfo(FILEINFO_MIME_TYPE))->file($f['tmp_name']);
    if (!in_array($mime, ['image/jpeg', 'image/png', 'image/webp'], true)) {
        return [null, 'Only JPG, PNG or WebP photos are allowed.'];
    }
    return [file_get_contents($f['tmp_name']), null];
}
