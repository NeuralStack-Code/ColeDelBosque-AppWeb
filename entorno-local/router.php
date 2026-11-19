<?php
/**
 * Router del entorno local: corre el sitio del colegio contra la base LOCAL, nunca contra producción.
 *
 *   php -S localhost:8787 -t "<carpeta del código del colegio>" "<esta carpeta>/router.php"
 *
 * El .env del código apunta a PRODUCCIÓN. Este router lo carga y, antes de que lo use nadie, pisa la base, las llaves y
 * la api con lo de entorno-local/.env.local (copia de .env.local.example). Se niega a arrancar si la base no es local.
 */
$app = rtrim(str_replace('\\', '/', $_SERVER['DOCUMENT_ROOT'] ?? ''), '/');
if ($app === '' || !is_file($app . '/apiService/core/env.php')) {
    http_response_code(500);
    exit("entorno-local: arranca con -t apuntando a la carpeta del código del colegio.\n");
}

$archivo = __DIR__ . '/.env.local';
if (!is_file($archivo)) {
    http_response_code(500);
    exit("entorno-local: falta entorno-local/.env.local (cópialo de .env.local.example).\n");
}

require_once $app . '/apiService/core/env.php';      // carga el .env del código (producción)...
foreach (file($archivo, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $linea) {   // ...y aquí se pisa
    $linea = trim($linea);
    if ($linea === '' || $linea[0] === '#' || !str_contains($linea, '=')) continue;
    [$clave, $valor] = array_map('trim', explode('=', $linea, 2));
    $_ENV[$clave] = trim($valor, "\"'");
}

// Candados: solo una base local cuyo nombre termine en _local.
$host = $_ENV['DB_HOST'] ?? '';
if (!in_array($host, ['127.0.0.1', 'localhost', '::1'], true) || !str_ends_with($_ENV['DB_NAME'] ?? '', '_local')) {
    http_response_code(500);
    exit("entorno-local: DB_HOST debe ser 127.0.0.1/localhost y DB_NAME terminar en _local. No se toca otra base.\n");
}

// Archivos reales (CSS, JS, imágenes): los sirve el servidor de PHP directo.
$ruta = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH) ?: '/';
if ($ruta !== '/' && is_file($app . $ruta) && !str_ends_with($ruta, '.php')) {
    return false;
}

chdir($app);
$_SERVER['SCRIPT_FILENAME'] = $app . '/index.php';
return require $app . '/index.php';
