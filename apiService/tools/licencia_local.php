<?php
/**
 * Deja una licencia SIMULADA en apiService/storage/license_cache.json para el entorno local y la CI, donde no hay
 * KEY_TOKEN de verdad: así el sitio no depende de la api (ni de la api falsa) para no entrar en mantenimiento.
 *
 *   php apiService/tools/licencia_local.php            la escribe solo si no hay una licencia válida en caché
 *   php apiService/tools/licencia_local.php --forzar   la escribe siempre
 *
 * El .env del colegio es el de PRODUCCIÓN: esto lee el entorno de pruebas (.env.pruebas en la raíz o PRUEBAS_ENV) y
 * se niega a correr si su base no es local (_local). Nunca toca la licencia de un servidor real.
 * El archivo está en .gitignore (no viaja al repo ni al servidor).
 */
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }

$root    = dirname(__DIR__, 2);
$entorno = getenv('PRUEBAS_ENV') ?: $root . '/.env.pruebas';
$db      = '';
if (is_file($entorno)) {
    foreach (file($entorno, FILE_IGNORE_NEW_LINES) as $linea) {
        if (preg_match('/^\s*DB_NAME\s*=\s*["\']?([^"\'\s]*)/', $linea, $m)) $db = $m[1];
    }
}
if (!str_ends_with($db, '_local')) {
    fwrite(STDERR, "Solo para entornos locales: el entorno de pruebas ($entorno) debe tener DB_NAME terminado en _local.\n");
    exit(1);
}

$archivo = $root . '/apiService/storage/license_cache.json';
$actual  = is_file($archivo) ? json_decode((string) file_get_contents($archivo), true) : null;
if (!in_array('--forzar', $argv, true) && is_array($actual) && ($actual['valid'] ?? false) === true) {
    echo "Ya hay una licencia válida en caché: no se toca.\n";
    exit(0);
}

if (!is_dir(dirname($archivo))) mkdir(dirname($archivo), 0775, true);
file_put_contents($archivo, json_encode([
    'valid'            => true,
    'ts'               => time() + 10 * 365 * 86400,   // no vence mientras dure el entorno local
    'checked_at'       => date('Y-m-d H:i:s'),
    'proyecto'         => 'entorno-local',
    'plan'             => null,
    'fecha_expiracion' => null,
    'message'          => 'Licencia simulada para el entorno local / CI.',
], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
echo "Licencia simulada escrita en apiService/storage/license_cache.json\n";
