<?php
require_once __DIR__ . '/../db.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'error' => 'Method not allowed']);
    exit;
}

$raw = file_get_contents('php://input');
$data = json_decode($raw, true);

if (!$data) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'Invalid JSON']);
    exit;
}

$gallery_id      = (int)($data['gallery_id']      ?? 0);
$token           = trim($data['token']            ?? '');
$identifier_name = trim($data['identifier_name'] ?? '');
$identifications = $data['identifications']       ?? [];

if (!$gallery_id || $token === '' || $identifier_name === '' || !is_array($identifications)) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'Missing required fields']);
    exit;
}

$db = get_db();

// Verify gallery + token
$gallery = $db->prepare("SELECT * FROM galleries WHERE id = ? AND token = ?");
$gallery->execute([$gallery_id, $token]);
$gallery = $gallery->fetch();

if (!$gallery) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'error' => 'Invalid gallery or token']);
    exit;
}

// Save identifications
$stmt = $db->prepare("
    INSERT INTO identifications (photo_id, gallery_id, identifier_name, people)
    VALUES (?, ?, ?, ?)
");

foreach ($identifications as $item) {
    $photo_id = (int)($item['photo_id'] ?? 0);
    $people   = trim($item['people']    ?? '');
    if (!$photo_id) continue;
    $stmt->execute([$photo_id, $gallery_id, $identifier_name, $people]);
}

// Mark gallery complete (only first completion sets the timestamp)
if (!$gallery['completed_at']) {
    $db->prepare("UPDATE galleries SET completed_at = datetime('now') WHERE id = ?")
       ->execute([$gallery_id]);
}

// Send email notification
$subject = "Photo ID Complete: {$gallery['name']}";
$body    = "Hi,\n\n"
         . "{$identifier_name} has finished identifying photos in the gallery \"{$gallery['name']}\".\n\n"
         . "View results: " . BASE_URL . "/admin/view.php?id={$gallery_id}\n\n"
         . "— Photo ID System";

$headers = "From: " . FROM_NAME . " <" . FROM_EMAIL . ">\r\n"
         . "Content-Type: text/plain; charset=UTF-8\r\n";

mail(ADMIN_EMAIL, $subject, $body, $headers);

echo json_encode(['ok' => true]);
