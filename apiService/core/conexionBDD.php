<?php
require_once __DIR__ . '/env.php';

$conexion = mysqli_init();
mysqli_options($conexion, MYSQLI_OPT_INT_AND_FLOAT_NATIVE, 1);

if (!mysqli_real_connect(
    $conexion,
    $_ENV['DB_HOST'],
    $_ENV['DB_USER'],
    $_ENV['DB_PASSWORD'],
    $_ENV['DB_NAME']
)) {
    error_log('DB error: ' . mysqli_connect_error());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Error de conexión.']);
    exit;
}

mysqli_set_charset($conexion, 'utf8mb4');

// Helper del catálogo de estatus (statusId): disponible en todos los actions
require_once __DIR__ . '/status.php';