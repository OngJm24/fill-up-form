<?php
declare(strict_types=1);

require __DIR__ . '/../src/bootstrap.php';
require __DIR__ . '/../src/form.php';

start_session();
send_security_headers();

$fields = form_fields();

$steps = [
    1 => ['title' => 'Basics',        'sub' => 'Who you are',            'icon' => '👤', 'heading' => 'Let’s start with the basics',  'lead' => 'Help us get to know you. We keep this private.'],
    2 => ['title' => 'Favorites',     'sub' => 'Things you love',        'icon' => '💜', 'heading' => 'A few of your favorite things', 'lead' => 'The fun part — tell us what you love.'],
    3 => ['title' => 'Fun & quirky',  'sub' => 'Let’s get weird',        'icon' => '🎈', 'heading' => 'Fun & quirky',                 'lead' => 'No wrong answers here. Be yourself.'],
    4 => ['title' => 'Life & dreams', 'sub' => 'Who you want to be',     'icon' => '🌟', 'heading' => 'Life & aspirations',           'lead' => 'A deeper look at who you are and who you want to become.'],
    5 => ['title' => 'Selfie',        'sub' => 'Put a face to the name', 'icon' => '📸', 'heading' => 'Say cheese!',                  'lead' => 'Snap a quick selfie, or upload one if your camera isn’t available.'],
    6 => ['title' => 'Moods',         'sub' => 'Feelings in photos',     'icon' => '🎭', 'heading' => 'Your moods',                   'lead' => 'Optional — share a photo for any mood and tell us why.'],
    7 => ['title' => 'Review',        'sub' => 'Check & send',           'icon' => '✅', 'heading' => 'One last look',                'lead' => 'Make sure everything is right before sending.'],
];

$stepFields = [
    1 => [['name', 'wide'], ['email', 'wide'], ['address', 'wide'], ['birthday', ''], ['phone', ''], ['sim_card', 'wide']],
    2 => [['fav_food', ''], ['fav_drink', ''], ['hobby', ''], ['fav_color', '']],
    3 => [['fun_question', 'wide'], ['fav_animal', ''], ['weird_food', '']],
    4 => [['biggest_dream', 'wide'], ['role_model', ''], ['future_self', ''], ['world_change', 'wide']],
];

function render_field(string $key, array $f, string $class): string
{
    $id = e($key);
    $errId = "$id-error";
    $label = e($f['label']);

    if ($f['type'] === 'choice') {
        $html = "<fieldset class=\"field $class\" data-field=\"$id\" data-required aria-describedby=\"$errId\"><legend>$label</legend><div class=\"pills\">";
        foreach ($f['options'] as $opt) {
            $o = e($opt);
            $html .= "<label class=\"pill\"><input type=\"radio\" name=\"$id\" value=\"$o\" required><span>$o</span></label>";
        }
        return $html . "</div><p class=\"field-meta\"><span class=\"error\" id=\"$errId\"></span></p></fieldset>";
    }

    $attrs = [
        'id' => $key, 'name' => $key, 'required' => true,
        'maxlength' => $f['max'] ?? null,
        'data-min' => $f['min'] ?? null,
        'data-pattern' => $f['pattern'] ?? null,
        'data-pattern-msg' => $f['pattern_msg'] ?? null,
        'placeholder' => $f['placeholder'] ?? null,
        'autocomplete' => $f['autocomplete'] ?? 'off',
        'inputmode' => $f['inputmode'] ?? null,
        'aria-describedby' => $errId,
    ];
    if ($f['type'] === 'date') {
        $attrs['max'] = (new DateTimeImmutable('-5 years'))->format('Y-m-d');
        $attrs['min'] = (new DateTimeImmutable('-120 years'))->format('Y-m-d');
    }
    $attrHtml = '';
    foreach ($attrs as $k => $v) {
        if ($v === true) {
            $attrHtml .= " $k";
        } elseif ($v !== null) {
            $attrHtml .= ' ' . $k . '="' . e((string)$v) . '"';
        }
    }

    $control = $f['type'] === 'textarea'
        ? "<textarea$attrHtml rows=\"4\"></textarea>"
        : '<input type="' . e($f['type']) . "\"$attrHtml>";
    $counter = $f['type'] === 'textarea' ? '<span class="count" aria-hidden="true">0/' . (int)$f['max'] . '</span>' : '';

    return "<div class=\"field $class\" data-field=\"$id\"><label for=\"$id\">$label</label>"
        . "<div class=\"control\">$control<span class=\"state\" aria-hidden=\"true\"></span></div>"
        . "<p class=\"field-meta\"><span class=\"error\" id=\"$errId\"></span>$counter</p></div>";
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#140f2e">
    <title>Profile Journey</title>
    <meta name="description" content="Tell us your story in a few fun steps.">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/app.css">
    <script src="assets/form.js" defer></script>
</head>
<body class="form-page">
    <div class="aurora" aria-hidden="true"><span></span><span></span><span></span></div>

    <div class="shell">
        <aside class="rail">
            <div class="brand">
                <span class="brand-mark" aria-hidden="true">✦</span>
                <span>Profile Journey</span>
            </div>
            <h1 class="rail-title">Tell us your story.</h1>
            <p class="rail-lead">Seven quick steps, about five minutes. Your answers are kept private.</p>

            <ol class="steps" id="step-list">
                <?php foreach ($steps as $n => $s): ?>
                    <li>
                        <button type="button" class="step-link" data-goto="<?= $n ?>" <?= $n > 1 ? 'disabled' : '' ?>>
                            <span class="step-dot" aria-hidden="true"><span class="step-num"><?= $n ?></span><span class="step-check">✓</span></span>
                            <span class="step-text"><b><?= e($s['title']) ?></b><small><?= e($s['sub']) ?></small></span>
                        </button>
                    </li>
                <?php endforeach; ?>
            </ol>

            <p class="rail-note"><span aria-hidden="true">🔒</span> Sent over a secure connection. Photos are cleaned of hidden location data.</p>
        </aside>

        <main class="card" id="main">
            <div class="progress" aria-hidden="true">
                <div class="progress-label"><span id="progress-step">Step 1 of 7</span><span id="progress-title"><?= e($steps[1]['title']) ?></span></div>
                <div class="progress-track"><div class="progress-bar" id="progress-bar"></div></div>
            </div>

            <form id="profile-form" action="submit.php" method="post" enctype="multipart/form-data" novalidate>
                <?= csrf_field() ?>
                <div class="hp" aria-hidden="true">
                    <label for="website">Leave this empty</label>
                    <input type="text" id="website" name="website" tabindex="-1" autocomplete="off">
                </div>

                <div class="form-alert" id="form-alert" role="alert" hidden></div>

                <?php foreach ($steps as $n => $s): ?>
                <section class="step" data-step="<?= $n ?>" data-title="<?= e($s['title']) ?>" <?= $n > 1 ? 'hidden' : '' ?> aria-labelledby="step-<?= $n ?>-h">
                    <header class="step-head">
                        <span class="eyebrow"><span aria-hidden="true"><?= $s['icon'] ?></span> Step <?= $n ?> of <?= count($steps) ?></span>
                        <h2 id="step-<?= $n ?>-h" tabindex="-1"><?= e($s['heading']) ?></h2>
                        <p><?= e($s['lead']) ?></p>
                    </header>

                    <?php if (isset($stepFields[$n])): ?>
                        <div class="grid">
                            <?php foreach ($stepFields[$n] as [$key, $class]) echo render_field($key, $fields[$key], $class); ?>
                        </div>

                    <?php elseif ($n === 5): ?>
                        <div class="selfie" data-field="selfie">
                            <div class="camera" id="camera">
                                <video id="video" playsinline muted hidden></video>
                                <img id="selfie-preview" alt="Your selfie" hidden>
                                <div class="camera-idle" id="camera-idle">
                                    <span class="camera-icon" aria-hidden="true">📷</span>
                                    <p id="camera-msg">Your camera is off.</p>
                                    <button type="button" class="btn btn-primary" id="start-camera">Turn on camera</button>
                                </div>
                                <div class="countdown" id="countdown" aria-live="assertive" hidden></div>
                                <div class="flash" id="flash"></div>
                                <div class="ring" aria-hidden="true"></div>
                            </div>
                            <div class="camera-actions">
                                <button type="button" class="btn btn-primary" id="capture-btn" hidden>📸 Take photo</button>
                                <button type="button" class="btn btn-ghost" id="retake-btn" hidden>↺ Retake</button>
                                <label class="btn btn-ghost" for="selfie-file">⬆ Upload a photo</label>
                                <input type="file" id="selfie-file" class="sr-only" accept="image/jpeg,image/png,image/webp">
                            </div>
                            <input type="hidden" name="selfie_base64" id="selfie_base64">
                            <p class="field-meta center"><span class="error" id="selfie-error" role="alert"></span></p>
                        </div>

                    <?php elseif ($n === 6): ?>
                        <div class="moods">
                            <?php foreach (MOODS as $mood => $m): ?>
                                <div class="mood mood-<?= e($mood) ?>" data-mood="<?= e($mood) ?>">
                                    <div class="mood-top">
                                        <span class="mood-emoji" aria-hidden="true"><?= $m['emoji'] ?></span>
                                        <b><?= e($m['label']) ?></b>
                                        <button type="button" class="link-btn mood-remove" hidden>Remove</button>
                                    </div>
                                    <label class="drop" data-field="mood_photo_<?= e($mood) ?>">
                                        <input type="file" name="mood_photo_<?= e($mood) ?>" class="sr-only" accept="image/jpeg,image/png,image/webp" aria-label="<?= e($m['label']) ?> photo" aria-describedby="mood_photo_<?= e($mood) ?>-error">
                                        <img alt="" hidden>
                                        <span class="drop-hint"><span aria-hidden="true">＋</span> Drop a photo or <u>browse</u><small>JPG, PNG or WebP · up to 5 MB</small></span>
                                    </label>
                                    <p class="field-meta"><span class="error" id="mood_photo_<?= e($mood) ?>-error"></span></p>
                                    <div class="field" data-field="mood_reason_<?= e($mood) ?>">
                                        <label class="sr-only" for="reason_<?= e($mood) ?>">Why do you feel <?= e(strtolower($m['label'])) ?>?</label>
                                        <textarea id="reason_<?= e($mood) ?>" name="mood_reason_<?= e($mood) ?>" maxlength="500" rows="2" placeholder="<?= e($m['prompt']) ?>" aria-describedby="mood_reason_<?= e($mood) ?>-error"></textarea>
                                        <p class="field-meta"><span class="error" id="mood_reason_<?= e($mood) ?>-error"></span></p>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>

                    <?php else: ?>
                        <div class="review" id="review"></div>
                        <div class="field consent" data-field="consent">
                            <label class="check">
                                <input type="checkbox" name="consent" value="1" id="consent" aria-describedby="consent-error">
                                <span>I confirm my answers are true, and I agree that my information and photos will be stored and viewed by the form administrator.</span>
                            </label>
                            <p class="field-meta"><span class="error" id="consent-error"></span></p>
                        </div>
                    <?php endif; ?>
                </section>
                <?php endforeach; ?>

                <footer class="nav">
                    <button type="button" class="btn btn-ghost" id="back-btn" hidden>← Back</button>
                    <span class="nav-hint" id="nav-hint">Press Enter ↵ to continue</span>
                    <button type="button" class="btn btn-primary" id="next-btn">Continue →</button>
                    <button type="submit" class="btn btn-primary btn-glow" id="submit-btn" hidden>
                        <span class="btn-label">Complete my profile ✨</span><span class="spinner" aria-hidden="true"></span>
                    </button>
                </footer>
            </form>

            <section class="success" id="success" hidden tabindex="-1" aria-live="polite">
                <div class="confetti" aria-hidden="true"><?= str_repeat('<i></i>', 24) ?></div>
                <div class="success-badge" aria-hidden="true">✓</div>
                <h2>You did it, <span id="success-name"></span>!</h2>
                <p id="success-text">Your profile is complete. A thank-you email is on its way to your inbox.</p>
                <a class="btn btn-primary" href="./">Submit another response</a>
            </section>
        </main>
    </div>

    <noscript><p class="noscript">This form works best with JavaScript turned on — the selfie step needs it.</p></noscript>
</body>
</html>
