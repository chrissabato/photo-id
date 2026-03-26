<?php
require_once __DIR__ . '/../db.php';

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

$result = send_gchat($webhook, 'Test message from Photo ID — notifications are working!');
echo json_encode($result);

function send_gchat(string $webhook, string $text): array {
    $payload = json_encode(['text' => $text]);
    $ctx = stream_context_create([
        'http' => [
            'method'  => 'POST',
            'header'  => "Content-Type: application/json\r\n",
            'content' => $payload,
            'timeout' => 10,
        ]
    ]);
    $response = @file_get_contents($webhook, false, $ctx);
    if ($response === false) {
        return ['ok' => false, 'error' => 'Could not reach webhook URL'];
    }
    return ['ok' => true];
}
