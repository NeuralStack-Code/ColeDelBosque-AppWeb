<?php
// Evita el "Notice: session already active" cuando se entra vía el router raíz
if (session_status() !== PHP_SESSION_ACTIVE) session_start();

require_once __DIR__ . '/middleware/cors.php';
require_once __DIR__ . '/core/config.php';

header('Content-Type: application/json; charset=utf-8');

$uri   = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$base  = rtrim(BASE_URL, '/');
$route = trim(preg_replace('#^' . preg_quote($base, '#') . '/?api/?#', '', $uri), '/');
$route = strtok($route, '?');

$routes = [
    'auth'              => dirname(__DIR__) . '/webService/actions/auth.php',
    'contacto'          => dirname(__DIR__) . '/webService/actions/contacto.php',
    'control-escolar'   => dirname(__DIR__) . '/webService/actions/control_escolar.php',
    'calificaciones'    => dirname(__DIR__) . '/webService/actions/calificaciones.php',
    'colegiaturas'      => dirname(__DIR__) . '/webService/actions/colegiaturas.php',
    'padre'             => dirname(__DIR__) . '/webService/actions/padre.php',
    'dashboard'         => dirname(__DIR__) . '/webService/actions/dashboard.php',
    'recibos'           => dirname(__DIR__) . '/webService/actions/recibos.php',
    'tipos-recibo'      => dirname(__DIR__) . '/webService/actions/tipos_recibo.php',
    'materias'          => dirname(__DIR__) . '/webService/actions/materias.php',
];

if (array_key_exists($route, $routes)) {
    require $routes[$route];
} else {
    http_response_code(404);
    echo json_encode(['success' => false, 'message' => "Ruta '$route' no encontrada."]);
}

function response(int $code, bool $success, string $message, array $extra = []): void {
    http_response_code($code);
    echo json_encode(array_merge(['success' => $success, 'message' => $message], $extra));
    exit;
}