<?php
require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/../notify.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['ok' => false, 'error' => 'Method not allowed']);
    exit;
}

$webhook = get_setting('gchat_webhook');
if (!$webhook) {
    echo json_encode(['ok' => false, 'error' => 'No webhook configured']);
    exit;
}

echo json_encode(send_gchat($webhook, 'Test message from Photo ID — notifications are working!'));
