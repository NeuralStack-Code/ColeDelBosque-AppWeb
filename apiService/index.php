<?php
/**
 * Router de los controllers:  /<controller>/<método>  →  <Controller>Controller::<método>()   (sin método = index)
 * También acepta el prefijo /api/ (/api/ciclo/listar). No hay mapa de rutas: un controller que hereda
 * de Controller ya tiene sus URLs, y cada método público es un endpoint.
 */
// Los errores se registran (error_log) pero NUNCA se imprimen (romperían el JSON).
ini_set('display_errors', '0');
error_reporting(E_ALL);

// Evita el "Notice: session already active" cuando se entra vía el router raíz
if (session_status() !== PHP_SESSION_ACTIVE) session_start();

require_once __DIR__ . '/core/autoload.php';   // core del framework + clases (classmap+fallback)
require_once __DIR__ . '/middleware/cors.php';
require_once __DIR__ . '/core/config.php';

header('Content-Type: application/json; charset=utf-8');
// Nunca cachear las respuestas: cada alta, cambio o baja debe reflejarse de inmediato al volver a consultar.
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');

// Errores FATALES no atrapables -> respuesta JSON limpia (nunca stack trace al cliente).
register_shutdown_function(function () {
    $e = error_get_last();
    if ($e && in_array($e['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR], true)) {
        error_log("[FATAL] {$e['message']} en {$e['file']}:{$e['line']}");
        if (!headers_sent()) { http_response_code(500); header('Content-Type: application/json; charset=utf-8'); }
        echo json_encode(['success' => false, 'status' => 'error', 'code' => 500, 'message' => 'Error interno del servidor.', 'data' => null]);
    }
});

$uri   = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$base  = rtrim(BASE_URL, '/');
$route = trim(preg_replace('#^' . preg_quote($base, '#') . '/?(api(/|$))?#', '', $uri), '/');
[$resource, $action] = array_pad(explode('/', $route, 2), 2, '');

try {
    if ($action === '') $action = 'index';

    $class = routeController($resource);
    if ($class === null) response(404, false, "Ruta '$route' no encontrada.");

    $controller = new $class(connectionFor($class::CONNECTION));

    // Solo son endpoints los métodos públicos que escribe el propio controller (los del framework no).
    $reflection = method_exists($controller, $action) ? new ReflectionMethod($controller, $action) : null;
    if ($reflection === null || !$reflection->isPublic() || $reflection->isStatic() || $reflection->isConstructor()
        || $reflection->getDeclaringClass()->isAbstract()) {
        response(404, false, 'Acción no encontrada.');
    }

    $controller->authorize($action);                                          // guardia único (core/Controller.php)
    $reflection->invokeArgs($controller, actionArguments($controller, $action)); // lo que mandó el navegador, como parámetros
} catch (Throwable $ex) {
    error_log("[ERROR] {$ex->getMessage()} en {$ex->getFile()}:{$ex->getLine()}");
    // Si lo que se pidió era una pantalla (el navegador navegando), el aviso va como texto, no como JSON.
    if (str_contains($_SERVER['HTTP_ACCEPT'] ?? '', 'text/html')) {
        http_response_code(500);
        header('Content-Type: text/html; charset=utf-8');
        exit('No pudimos cargar esta página. Intenta de nuevo en un momento.');
    }
    response(500, false, 'Error interno del servidor.');
}

/** Abre la conexión que pide el controller (o null si no usa la base). */
function connectionFor(string $type): ?mysqli
{
    if ($type === 'principal') {
        require __DIR__ . '/core/conexionBDD.php';   // $conexion
        return $conexion;
    }
    return null;
}

/** Respuesta JSON estándar: {success, status, code, message, data}. */
function response(int $code, bool $success, string $message, array $data = []): void {
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode([
        'success' => $success,                       // compatibilidad
        'status'  => $success ? 'success' : 'error',
        'code'    => $code,
        'message' => $message,
        'data'    => (object) $data,                 // el payload SIEMPRE va aquí
    ]);
    exit;
}
