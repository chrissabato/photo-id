<?php
// Shared notification helpers

function send_gchat(string $webhook, string $text): array {
    if (!function_exists('curl_init')) {
        return ['ok' => false, 'error' => 'cURL extension not available on this server'];
    }

    $payload = json_encode(['text' => $text]);

    $ch = curl_init($webhook);
    curl_setopt_array($ch, [
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => $payload,
        CURLOPT_HTTPHEADER     => ['Content-Type: application/json'],
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 10,
    ]);

    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curl_error = curl_error($ch);
    curl_close($ch);

    if ($curl_error) {
        return ['ok' => false, 'error' => 'cURL error: ' . $curl_error];
    }
    if ($http_code < 200 || $http_code >= 300) {
        return ['ok' => false, 'error' => "Webhook returned HTTP {$http_code}: {$response}"];
    }

    return ['ok' => true];
}

function send_email(string $to, string $from_email, string $from_name, string $subject, string $body): void {
    $headers = "From: {$from_name} <{$from_email}>\r\nContent-Type: text/plain; charset=UTF-8\r\n";
    mail($to, $subject, $body, $headers);
}
