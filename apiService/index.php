<?php
/**
 * Router de los controllers:  /<componente>/<método>  →  <Componente>Controller::<método>()   (sin método = index)
 * También acepta el prefijo /api/ (/api/ciclo/listar). No hay mapa de rutas: el catálogo de permisos de la base
 * (core/Catalog.php) dice si existe, si se permite y qué archivo lo atiende. Sin registro → 404, sin tocar el disco.
 */
// Los errores se registran (error_log) pero NUNCA se imprimen (romperían el JSON).
ini_set('display_errors', '0');
error_reporting(E_ALL);

// El .htaccess manda /api DIRECTO aquí (sin pasar por el index.php raíz): el arranque se carga también acá.
require_once __DIR__ . '/core/autoload.php';
startSecureSession();                               // si llegó por el index.php raíz, ya está abierta
sendSecurityHeaders();
require_once __DIR__ . '/middleware/cors.php';
require_once __DIR__ . '/core/config.php';

header('Content-Type: application/json; charset=utf-8');
// Nunca cachear las respuestas: cada alta, cambio o baja debe reflejarse de inmediato al volver a consultar.
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');

// Defensa CSRF: lo que escribe solo se acepta desde este mismo sitio (core/security.php).
requireSameOrigin();

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
$resource = strtolower($resource);

try {
    if (!preg_match('/^[a-z][a-z0-9_]*$/', $resource) || !Loader::hasController($resource)) {
        notFound("Ruta '$route' no encontrada.");                                   // sin tocar la base
    }
    $action = $action !== '' ? $action : 'index';
    if (!preg_match('/^[A-Za-z][A-Za-z0-9_]*$/', $action)) notFound('Acción no encontrada.');

    $db = conexionPrincipal();                       // el catálogo vive en la base del colegio
    Loader::activate('framework');                   // sus business (Objeto, Catalogo, PerfilAcceso)
    $decision = Catalog::resolve($db, $resource, $action);
    Catalog::enforce($decision);

    $class = Loader::controller($decision['clase'], $decision['archivo']);
    if (!is_subclass_of($class, Controller::class)) throw new RuntimeException("$class no hereda de Controller.");
    Loader::activate($decision['modulo']);           // sus business y models (webService/business|models/<módulo>)
    $controller = new $class($decision['conexion'] === 'ninguna' ? null : $db);

    // Solo son endpoints los métodos públicos que escribe el propio controller (los del framework no).
    $reflection = method_exists($controller, $action) ? new ReflectionMethod($controller, $action) : null;
    if ($reflection === null || !$reflection->isPublic() || $reflection->isStatic() || $reflection->isConstructor()
        || $reflection->getDeclaringClass()->isAbstract() || $reflection->getName() === 'authorize') {
        notFound('Acción no encontrada.');
    }
    $reflection->invokeArgs($controller, actionArguments($controller, $reflection->getName()));   // lo que mandó el navegador, como parámetros
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

/** 404: la página webService/views/404.php si lo pidió el navegador, JSON si lo pidió JavaScript. */
function notFound(string $message): never {
    if (str_contains($_SERVER['HTTP_ACCEPT'] ?? '', 'text/html')) {
        http_response_code(404);
        header('Content-Type: text/html; charset=utf-8');
        require dirname(__DIR__) . '/webService/views/404.php';
        exit;
    }
    response(404, false, $message);
}

/** Respuesta JSON estándar: {success, status, code, message, data}. */
function response(int $code, bool $success, string $message, array $data = []): never {
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
