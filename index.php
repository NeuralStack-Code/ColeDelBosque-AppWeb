<?php
// Los errores van al log, nunca a la pantalla (mostrarían rutas internas y responderían 200 en lugar de 500).
ini_set('display_errors', '0');

// Archivos estáticos: el built-in server los sirve directo si el router retorna false
if (php_sapi_name() === 'cli-server') {
    $requestPath = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
    $staticFile  = __DIR__ . $requestPath;
    // Lo mismo que bloquea el .htaccess en Apache: código interno, apiService/storage/ privado y cualquier .php que no sea una entrada.
    if (preg_match('#^/(sql|docs|apiService/(core|middleware|storage|tests|tools)|webService/(controllers|business|models|libs|views|sql))(/|$)#i', $requestPath)
        || (preg_match('#\.php$#i', $requestPath) && !in_array($requestPath, ['/index.php', '/apiService/index.php'], true))) {
        http_response_code(403); exit;
    }
    if ($requestPath !== '/index.php' && is_file($staticFile)) return false;
}

// Arranque: el core y el cargador propio (apiService/core/autoload.php + Loader.php). Sin Composer ni vendor/.
require_once __DIR__ . '/apiService/core/autoload.php';

startSecureSession();
sendSecurityHeaders();
require_once __DIR__ . '/apiService/core/config.php';
require_once __DIR__ . '/apiService/core/checkLicense.php';
checkLicense();

$uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);

// API → apiService/index.php (el .htaccess lo maneja en producción; aquí cubre el dev server)
if (preg_match('#^/?api(/|$)#', $uri)) {
    require __DIR__ . '/apiService/index.php';
    exit;
}

// Quitar prefijo de ruta si la app está en subdirectorio
$basePath = rtrim(parse_url(BASE_URL, PHP_URL_PATH) ?? '', '/');
if ($basePath !== '' && strpos($uri, $basePath) === 0) {
    $uri = substr($uri, strlen($basePath));
}

$route = trim($uri, '/');
$route = strtok($route, '?');
if ($route === false) $route = '';

// Las rutas viven en la base (tabla Rutas, ver core/Rutas.php): páginas sueltas del sitio y URLs que mandan a otra.
// Todo lo demás es /<componente>/<método>: lo despacha el router de la api, que le pregunta al catálogo qué existe
// y si el perfil de la cuenta lo permite (y responde 404 si no está registrado). Aquí no se registra ninguna ruta.
$destino = Rutas::resolver($route);
if ($destino !== null && $destino['Tipo'] === 'redireccion') Rutas::redirigir($destino);
if ($destino !== null && $destino['Tipo'] === 'vista') {
    $base = BASE_URL;
    require Rutas::vista($destino);
} else {
    require __DIR__ . '/apiService/index.php';
}
