<?php

declare(strict_types=1);

// Router for the PHP built-in server used by HttpClientTest
$path = parse_url(is_string($_SERVER['REQUEST_URI'] ?? null) ? $_SERVER['REQUEST_URI'] : '/', PHP_URL_PATH);

header('Content-Type: application/json');

if ($path === '/echo') {
    echo json_encode([
        'method' => $_SERVER['REQUEST_METHOD'] ?? null,
        'contentType' => $_SERVER['CONTENT_TYPE'] ?? null,
        'post' => $_POST,
    ]);
} elseif ($path === '/client-error') {
    http_response_code(400);
    echo '{"success":false,"error-codes":["invalid-input-secret"]}';
} else {
    http_response_code(500);
}
