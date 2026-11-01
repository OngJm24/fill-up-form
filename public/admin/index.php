<?php
declare(strict_types=1);

require __DIR__ . '/../../src/bootstrap.php';
require __DIR__ . '/../../src/form.php';

require_admin();
send_security_headers();
header('Cache-Control: no-store');

$rows = db()->query(
    'SELECT s.*, (SELECT COUNT(*) FROM mood_photos m WHERE m.submission_id = s.id) AS mood_count
     FROM submissions s ORDER BY s.submitted_at DESC'
)->fetch_all(MYSQLI_ASSOC);

$total = count($rows);
$thisWeek = count(array_filter($rows, fn($r) => strtotime((string)$r['submitted_at']) >= strtotime('-7 days')));
$withMoods = count(array_filter($rows, fn($r) => (int)$r['mood_count'] > 0));

// Data for the detail panel. Photos are referenced through the auth-checked photo.php.
$payload = array_map(function ($r) {
    $r['selfie_url'] = $r['selfie'] ? 'photo.php?f=' . rawurlencode(basename($r['selfie'])) : '';
    unset($r['selfie']);
    return $r;
}, $rows);

function initials(string $name): string
{
    $parts = preg_split('/\s+/u', trim($name)) ?: [];
    $first = mb_substr($parts[0] ?? '?', 0, 1);
    $last = count($parts) > 1 ? mb_substr(end($parts), 0, 1) : '';
    return mb_strtoupper($first . $last);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title>Submissions · Admin</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../assets/app.css">
    <script src="../assets/admin.js" defer></script>
</head>
<body class="admin-page">
    <header class="admin-top">
        <div class="brand brand-dark">
            <span class="brand-mark" aria-hidden="true">✦</span>
            <span>Profile Journey · Admin</span>
        </div>
        <form method="post" action="logout.php">
            <?= csrf_field() ?>
            <button type="submit" class="btn btn-ghost btn-sm">Sign out</button>
        </form>
    </header>

    <main class="admin-main">
        <section class="stats" aria-label="Summary">
            <div class="stat"><span class="stat-num"><?= $total ?></span><span class="stat-label">Total submissions</span></div>
            <div class="stat"><span class="stat-num"><?= $thisWeek ?></span><span class="stat-label">In the last 7 days</span></div>
            <div class="stat"><span class="stat-num"><?= $withMoods ?></span><span class="stat-label">Included mood photos</span></div>
        </section>

        <section class="panel">
            <div class="panel-head">
                <h1>Submissions</h1>
                <input type="search" id="search" class="search" placeholder="Search name, email, phone…" aria-label="Search submissions">
            </div>

            <?php if (!$rows): ?>
                <p class="empty">No submissions yet. Share the form link to get started.</p>
            <?php else: ?>
                <div class="table-wrap">
                    <table class="table">
                        <thead>
                            <tr><th>Person</th><th class="hide-sm">Mobile</th><th class="hide-sm">Submitted</th><th class="hide-xs">Moods</th><th><span class="sr-only">Actions</span></th></tr>
                        </thead>
                        <tbody id="rows">
                        <?php foreach ($rows as $r): ?>
                            <tr data-search="<?= e(mb_strtolower($r['name'] . ' ' . $r['email'] . ' ' . $r['phone'])) ?>">
                                <td>
                                    <div class="person">
                                        <span class="avatar" aria-hidden="true"><?= e(initials($r['name'])) ?></span>
                                        <span><b><?= e($r['name']) ?></b><small><?= e($r['email']) ?></small></span>
                                    </div>
                                </td>
                                <td class="hide-sm"><?= e($r['phone']) ?> <span class="tag"><?= e($r['sim_card']) ?></span></td>
                                <td class="hide-sm"><?= e(date('M j, Y · g:i A', strtotime((string)$r['submitted_at']))) ?></td>
                                <td class="hide-xs"><?= (int)$r['mood_count'] ?>/4</td>
                                <td class="right"><button type="button" class="btn btn-soft btn-sm" data-view="<?= (int)$r['id'] ?>">View</button></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                    <p class="empty" id="no-results" hidden>No submissions match your search.</p>
                </div>
            <?php endif; ?>
        </section>
    </main>

    <dialog id="detail" class="detail" aria-labelledby="d-name">
        <div class="detail-inner">
            <button type="button" class="icon-btn detail-close" data-close aria-label="Close">✕</button>
            <div class="detail-hero">
                <img id="d-selfie" class="detail-selfie" alt="" hidden>
                <h2 id="d-name"></h2>
                <p id="d-email" class="muted"></p>
            </div>
            <div id="d-sections"></div>
            <h3 class="detail-h">🎭 Moods</h3>
            <div id="d-moods" class="mood-list"></div>
        </div>
    </dialog>

    <script type="application/json" id="submissions-data"><?= json_encode($payload, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE) ?></script>
</body>
</html>
