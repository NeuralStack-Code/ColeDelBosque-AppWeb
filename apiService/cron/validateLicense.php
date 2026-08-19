<?php
/**
 * Cron job: valida la licencia contra NeuralStack.
 * Configurar en hPanel → Cron Jobs:
 *   Comando:    php /ruta/absoluta/api/cron/validateLicense.php
 *   Frecuencia: 0 6 1 * *  (día 1 de cada mes, 6am)
 */

define('APP_ROOT', dirname(__DIR__, 2));
require_once APP_ROOT . '/apiService/core/config.php';

define('LICENSE_CACHE_FILE', APP_ROOT . '/storage/license_cache.json');
define('NSC_VALIDATE_URL',   'https://api.neuralstackcode.com.mx/v1/keys/validate');

$key = KEY_TOKEN;
$ctx = stream_context_create([
    'http' => [
        'method'        => 'GET',
        'header'        => "X-API-KEY: $key\r\nAccept: application/json\r\n",
        'timeout'       => 15,
        'ignore_errors' => true,
    ],
]);

$body = @file_get_contents(NSC_VALIDATE_URL, false, $ctx);

if ($body === false) {
    log_cron('ERROR: No se pudo contactar a NeuralStack. Caché sin cambios.');
    exit(1);
}

$data = json_decode($body, true);
if (!$data || !isset($data['success'])) {
    log_cron('ERROR: Respuesta inválida de NeuralStack.');
    exit(1);
}

$cache = [
    'valid'            => $data['success'] === true,
    'checked_at'       => date('Y-m-d H:i:s'),
    'proyecto'         => $data['proyecto']         ?? null,
    'plan'             => $data['plan']             ?? null,
    'fecha_expiracion' => $data['fecha_expiracion'] ?? null,
    'message'          => $data['message']          ?? '',
];

$dir = dirname(LICENSE_CACHE_FILE);
if (!is_dir($dir)) mkdir($dir, 0755, true);
file_put_contents(LICENSE_CACHE_FILE, json_encode($cache, JSON_PRETTY_PRINT));

$status = $cache['valid'] ? 'VÁLIDA' : 'INVÁLIDA';
log_cron("Licencia $status. Proyecto: {$cache['proyecto']}. Expira: {$cache['fecha_expiracion']}");
exit(0);

function log_cron(string $msg): void {
    $dir  = APP_ROOT . '/storage/logs';
    if (!is_dir($dir)) mkdir($dir, 0755, true);
    $line = '[' . date('Y-m-d H:i:s') . '] ' . $msg . PHP_EOL;
    file_put_contents($dir . '/license.log', $line, FILE_APPEND);
    echo $line;
}