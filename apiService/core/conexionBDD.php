<?php
require_once __DIR__ . '/env.php';

/**
 * La conexión a la base del colegio, abierta una sola vez por petición. La usan el router (para el controller y el
 * catálogo de permisos) y Rutas. Si la base no responde lanza RuntimeException: el router la convierte en 500.
 */
function conexionPrincipal(): mysqli
{
    static $conexion = null;
    if ($conexion !== null) return $conexion;

    $db = mysqli_init();
    mysqli_options($db, MYSQLI_OPT_INT_AND_FLOAT_NATIVE, 1);
    mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);   // los procedures avisan con excepciones (procedure.php)
    try {
        mysqli_real_connect($db, $_ENV['DB_HOST'] ?? '', $_ENV['DB_USER'] ?? '', $_ENV['DB_PASSWORD'] ?? '', $_ENV['DB_NAME'] ?? '',
                            isset($_ENV['DB_PORT']) ? (int) $_ENV['DB_PORT'] : null);
    } catch (mysqli_sql_exception $e) {
        throw new RuntimeException('DB error: ' . $e->getMessage());
    }
    mysqli_set_charset($db, 'utf8mb4');
    return $conexion = $db;
}
