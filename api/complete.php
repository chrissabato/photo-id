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

$results_url  = BASE_URL . "/admin/view.php?id={$gallery_id}";
$message_text = "{$identifier_name} has finished identifying photos in \"{$gallery['name']}\".\n"
              . "View results: {$results_url}";

// Email notification
$admin_email = get_setting('admin_email');
$from_email  = get_setting('from_email');
$from_name   = get_setting('from_name', 'Photo ID');

if ($admin_email && $from_email) {
    $subject = "Photo ID Complete: {$gallery['name']}";
    $body    = "Hi,\n\n{$message_text}\n\n— Photo ID System";
    $headers = "From: {$from_name} <{$from_email}>\r\nContent-Type: text/plain; charset=UTF-8\r\n";
    mail($admin_email, $subject, $body, $headers);
}

// Google Chat notification
$gchat_webhook = get_setting('gchat_webhook');
if ($gchat_webhook) {
    $payload = json_encode(['text' => $message_text]);
    $ctx = stream_context_create([
        'http' => [
            'method'  => 'POST',
            'header'  => "Content-Type: application/json\r\n",
            'content' => $payload,
            'timeout' => 10,
        ]
    ]);
    @file_get_contents($gchat_webhook, false, $ctx);
}

echo json_encode(['ok' => true]);
