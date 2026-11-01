<?php
declare(strict_types=1);

require __DIR__ . '/../../src/bootstrap.php';

require_admin(true);
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
if (!$id) {
    http_response_code(400);
    exit(json_encode(['error' => 'Missing id']));
}

$stmt = db()->prepare('SELECT mood, photo_path, reason FROM mood_photos WHERE submission_id = ? ORDER BY id');
$stmt->bind_param('i', $id);
$stmt->execute();

$moods = [];
foreach ($stmt->get_result()->fetch_all(MYSQLI_ASSOC) as $row) {
    $moods[] = [
        'mood'   => $row['mood'],
        'photo'  => 'photo.php?f=' . rawurlencode(basename($row['photo_path'])),
        'reason' => $row['reason'],
    ];
}
echo json_encode($moods);
