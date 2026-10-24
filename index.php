<?php
// Archivos estáticos: el built-in server los sirve directo si el router retorna false
if (php_sapi_name() === 'cli-server') {
    $requestPath = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
    $staticFile  = __DIR__ . $requestPath;
    if ($requestPath !== '/index.php' && is_file($staticFile)) return false;
}

session_start();
require_once __DIR__ . '/apiService/core/autoload.php';
require_once __DIR__ . '/apiService/core/config.php';
require_once __DIR__ . '/apiService/core/checkLicense.php';
checkLicense();

$uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);

// API → apiService/index.php (en producción lo maneja .htaccess)
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

$routes = [
    ''                  => __DIR__ . '/webService/views/home.php',
    'index.php'         => __DIR__ . '/webService/views/home.php',
    'oferta-educativa'  => __DIR__ . '/webService/views/site/oferta_educativa.php',
    'actividades'       => __DIR__ . '/webService/views/site/actividades.php',

    //Maestro (permiso_id = 2)
    'maestro'                 => __DIR__ . '/webService/views/maestro/home.php',


    //Desarrollador (permiso_id = 4)
    'desarrollador'            => __DIR__ . '/webService/views/dev/home.php',
];

// Pantallas que ya son /<controller>/<método>. Las URLs anteriores redirigen (301) conservando el query string.
$moved = [
    'inicio-sesion'        => '/auth/entrar',
    'administrador/ciclos' => '/ciclo/index',
    'administrador'                 => '/dashboard/index',
    'administrador/tipos-recibo'    => '/tiporecibo/index',
    'administrador/tipos-descuento' => '/tipodescuento/index',
    'desarrollador/permisos'        => '/permiso/index',
    'desarrollador/tablas'          => '/tablas/index',
    'administrador/materias'        => '/materia/index',
    'desarrollador/perfiles'        => '/perfil/index',
    'padre'                         => '/padre/index',
    'administrador/colegiaturas'    => '/colegiatura/index',
    'administrador/esquemas-pago'   => '/esquemapago/index',
    'administrador/recibos'         => '/recibo/index',
    'recibo'                        => '/recibo/imprimir',
    'administrador/reporte-columnas' => '/reportecolumna/index',
    'maestro/calificaciones'        => '/calificacion/index',
    'maestro/reportes'              => '/reporte/index',
    'administrador/alumnos'         => '/alumno/index',
    'administrador/maestros'        => '/docente/index',
    'administrador/grupos'          => '/grupo/index',
];
if (isset($moved[$route])) {
    $query = $_SERVER['QUERY_STRING'] ?? '';
    header('Location: ' . BASE_URL . $moved[$route] . ($query !== '' ? '?' . $query : ''), true, 301);
    exit;
}

// Los candados de abajo son de las páginas sueltas de $routes. Las pantallas que ya son de un controller
// revisan el rol ellas mismas (Controller::authorize), y sus métodos los usa el panel de cada rol.
$isPage = array_key_exists($route, $routes);

// Admin encerrado en su panel: solo puede navegar rutas 'administrador' y 'administrador/*' (+ recibo)
if ($isPage && isset($_SESSION['permiso']) && (int)$_SESSION['permiso'] === 1) {
    if ($route !== 'administrador' && strpos($route, 'administrador/') !== 0 && $route !== 'recibo') {
        header('Location: ' . BASE_URL . '/dashboard/index');
        exit;
    }
}

// Desarrollador encerrado en su panel: solo 'desarrollador' y 'desarrollador/*'
if ($isPage && isset($_SESSION['permiso']) && (int)$_SESSION['permiso'] === 4) {
    if ($route !== 'desarrollador' && strpos($route, 'desarrollador/') !== 0) {
        header('Location: ' . BASE_URL . '/desarrollador');
        exit;
    }
}

// 1) Páginas sueltas de $routes.  2) /<controller>/<método> (controllers que heredan de Controller;
//    lo despacha el router de apiService).  3) 404.
if ($isPage) {
    require $routes[$route];
} elseif (($class = routeController(explode('/', $route)[0])) !== null
          && method_exists($class, explode('/', $route, 2)[1] ?? 'index')) {
    require __DIR__ . '/apiService/index.php';
} else {
    http_response_code(404);
    require __DIR__ . '/webService/views/404.php';
}