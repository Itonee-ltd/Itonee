<?php
// Simple Paystack transaction verification endpoint.
// Reads PAYSTACK_SECRET_KEY from environment (.env file optional for local dev).

// Load .env file if present (minimal parser to keep dependencies out)
$envPath = dirname(__DIR__) . '/.env';
if (file_exists($envPath)) {
    $lines = file($envPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        $trimmed = trim($line);
        if ($trimmed === '' || strpos($trimmed, '#') === 0) {
            continue;
        }

        [$name, $value] = array_pad(explode('=', $line, 2), 2, '');
        if ($name !== '') {
            putenv("{$name}={$value}");
            $_ENV[$name] = $value;
        }
    }
}

header('Content-Type: application/json');
$reference = $_GET['reference'] ?? null;
if (!$reference) {
    http_response_code(400);
    echo json_encode(['status' => false, 'message' => 'reference is required']);
    exit;
}

$secretKey = getenv('PAYSTACK_SECRET_KEY');
if (!$secretKey) {
    http_response_code(500);
    echo json_encode(['status' => false, 'message' => 'PAYSTACK_SECRET_KEY not set']);
    exit;
}

$url = 'https://api.paystack.co/transaction/verify/' . urlencode($reference);
$curl = curl_init($url);

curl_setopt_array($curl, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_TIMEOUT => 30,
    CURLOPT_HTTPHEADER => [
        'Authorization: Bearer ' . $secretKey,
        'Cache-Control: no-cache',
    ],
]);

$response = curl_exec($curl);
$error = curl_error($curl);
$httpCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);

curl_close($curl);

if ($error) {
    http_response_code(502);
    echo json_encode(['status' => false, 'message' => 'cURL error: ' . $error]);
    exit;
}

http_response_code($httpCode ?: 200);
echo $response ?: json_encode(['status' => false, 'message' => 'Empty response from Paystack']);
