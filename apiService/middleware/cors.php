<?php

$origin  = $_SERVER['HTTP_ORIGIN'] ?? '';
$allowed = [
    'https://colegiodelbosquelerma.com',
    'https://www.colegiodelbosquelerma.com',
    'http://localhost',
    'http://127.0.0.1',
];

if (in_array($origin, $allowed)) {
    header("Access-Control-Allow-Origin: $origin");
}

header('Access-Control-Allow-Credentials: true');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-API-KEY');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}