<?php
// Archivos estáticos: el built-in server los sirve directo si el router retorna false
if (php_sapi_name() === 'cli-server') {
    $requestPath = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
    $staticFile  = __DIR__ . $requestPath;
    if ($requestPath !== '/index.php' && is_file($staticFile)) return false;
}

session_start();
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
    'contacto'          => __DIR__ . '/webService/views/site/contacto.php',
    'oferta-educativa'  => __DIR__ . '/webService/views/site/oferta_educativa.php',
    'actividades'       => __DIR__ . '/webService/views/site/actividades.php',
    'inicio-sesion'     => __DIR__ . '/webService/views/site/inicio_sesion.php',

    //Maestro (permiso_id = 2)
    'maestro'                 => __DIR__ . '/webService/views/maestro/home.php',
    'maestro/calificaciones'  => __DIR__ . '/webService/views/maestro/calificaciones.php',

    //Padre / alumno (permiso_id = 3)
    'padre'            => __DIR__ . '/webService/views/padre/home.php',

    //Compartida (admin + padre): recibo imprimible
    'recibo'           => __DIR__ . '/webService/views/recibo.php',

    //Admin (permiso_id = 1)
    'administrador'               => __DIR__ . '/webService/views/admin/home.php',
    'administrador/alumnos'       => __DIR__ . '/webService/views/admin/alumnos.php',
    'administrador/maestros'      => __DIR__ . '/webService/views/admin/maestros.php',
    'administrador/grupos'        => __DIR__ . '/webService/views/admin/grupos.php',
    'administrador/colegiaturas'  => __DIR__ . '/webService/views/admin/colegiaturas.php',
    'administrador/recibos'       => __DIR__ . '/webService/views/admin/recibos.php',
    'administrador/tipos-recibo'  => __DIR__ . '/webService/views/admin/tipos_recibo.php',
    'administrador/tipos-descuento' => __DIR__ . '/webService/views/admin/tipos_descuento.php',
    'administrador/materias'      => __DIR__ . '/webService/views/admin/materias.php',
    'administrador/ciclos'        => __DIR__ . '/webService/views/admin/ciclos.php',

    //Desarrollador (permiso_id = 4)
    'desarrollador'            => __DIR__ . '/webService/views/dev/home.php',
    'desarrollador/perfiles'   => __DIR__ . '/webService/views/dev/perfiles.php',
    'desarrollador/permisos'   => __DIR__ . '/webService/views/dev/permisos.php',
    'desarrollador/tablas'     => __DIR__ . '/webService/views/dev/tablas.php',
];

// Admin encerrado en su panel: solo puede navegar rutas 'administrador' y 'administrador/*' (+ recibo)
if (isset($_SESSION['permiso']) && (int)$_SESSION['permiso'] === 1) {
    if ($route !== 'administrador' && strpos($route, 'administrador/') !== 0 && $route !== 'recibo') {
        header('Location: ' . BASE_URL . '/administrador');
        exit;
    }
}

// Desarrollador encerrado en su panel: solo 'desarrollador' y 'desarrollador/*'
if (isset($_SESSION['permiso']) && (int)$_SESSION['permiso'] === 4) {
    if ($route !== 'desarrollador' && strpos($route, 'desarrollador/') !== 0) {
        header('Location: ' . BASE_URL . '/desarrollador');
        exit;
    }
}

if (array_key_exists($route, $routes)) {
    require $routes[$route];
} else {
    http_response_code(404);
    require __DIR__ . '/webService/views/404.php';
}