<?php
/**
 * Router de PRUEBAS para el servidor integrado de PHP (lo levantan las suites con levantar()).
 *
 * Igual que el index.php raíz, pero antes pisa el .env (que es el de PRODUCCIÓN) con el entorno de pruebas
 * (PRUEBAS_ENV) y apaga la api central: sin caché de licencia y con la api caída, el sitio sigue (fail-open).
 */
if (PHP_SAPI !== 'cli-server') {
    http_response_code(403);
    exit;
}

$root = dirname(__DIR__, 2);
require_once $root . '/apiService/core/env.php';               // carga el .env (producción)…

// …y aquí lo pisa el de pruebas (sin incluir lib.php: sus funciones no deben convivir con las del sitio).
$archivo = getenv('PRUEBAS_ENV') ?: '';
if (!is_file($archivo)) { http_response_code(500); exit('Sin PRUEBAS_ENV: no se arranca contra el .env de producción.'); }
foreach (file($archivo, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $linea) {
    $linea = trim($linea);
    if ($linea === '' || $linea[0] === '#' || !str_contains($linea, '=')) continue;
    [$k, $v] = array_map('trim', explode('=', $linea, 2));
    $_ENV[$k] = trim($v, "\"'");
}
if (!in_array($_ENV['DB_HOST'] ?? '', ['127.0.0.1', 'localhost', '::1'], true) || !str_ends_with($_ENV['DB_NAME'] ?? '', '_local')) {
    http_response_code(500);
    exit('La base de pruebas debe ser local y terminar en _local.');
}
$_ENV['API_BASE_URL'] = $_ENV['API_BASE_URL'] ?? 'http://127.0.0.1:9/v1';   // nadie escucha: no sale nada

chdir($root);
$_SERVER['SCRIPT_FILENAME'] = $root . '/index.php';
return require $root . '/index.php';
