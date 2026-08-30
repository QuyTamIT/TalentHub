<?php
declare(strict_types=1);
require __DIR__ . '/bin/bootstrap.php';
$url = (string) getenv('TALENTHUB_AI_API_URL');
$key = (string) getenv('TALENTHUB_AI_API_KEY');
$body = json_encode([
    'contents' => [[
        'role' => 'user',
        'parts' => [['text' => 'ping']],
    ]],
], JSON_UNESCAPED_SLASHES);
$ch = curl_init($url);
curl_setopt_array($ch, [
    CURLOPT_POST => true,
    CURLOPT_POSTFIELDS => $body,
    CURLOPT_HTTPHEADER => ['Content-Type: application/json', 'x-goog-api-key: ' . $key],
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_HEADER => true,
    CURLOPT_TIMEOUT => 30,
]);
$raw = curl_exec($ch);
$errno = curl_errno($ch);
$error = curl_error($ch);
$status = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
$headerSize = (int) curl_getinfo($ch, CURLINFO_HEADER_SIZE);
$response = is_string($raw) ? substr($raw, $headerSize) : '';
echo json_encode([
    'status' => $status,
    'curl_errno' => $errno,
    'curl_error' => $error,
    'body_prefix' => substr($response, 0, 500),
], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE), PHP_EOL;
curl_close($ch);
