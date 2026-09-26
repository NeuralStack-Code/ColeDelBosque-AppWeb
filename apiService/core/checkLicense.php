<?php
/**
 * Valida la licencia consultando la API central en runtime (SIN cron por sitio).
 * Cachea el resultado por LICENSE_TTL para no llamar a la API en cada request.
 * Fail-open: si la API no responde, se conserva el último caché (una caída de la
 * API no tumba el sitio).
 */
require_once __DIR__ . '/apiClient.php';

define('LICENSE_CACHE_FILE', __DIR__ . '/../../storage/license_cache.json');
define('LICENSE_TTL', 43200); // 12 horas

function checkLicense(): void {
    $cache   = leerLicenseCache();
    $vencido = !$cache || (time() - (int) ($cache['ts'] ?? 0) > LICENSE_TTL);

    if ($vencido) {
        $r = apiGet('keys/validate');
        if ($r['status'] !== 0) {                 // la API respondió (válida o inválida)
            $cache = [
                'valid'            => $r['ok'],
                'ts'               => time(),
                'checked_at'       => date('Y-m-d H:i:s'),
                'proyecto'         => $r['data']['proyecto']         ?? null,
                'plan'             => $r['data']['plan']             ?? null,
                'fecha_expiracion' => $r['data']['fecha_expiracion'] ?? null,
                'message'          => $r['message'],
            ];
            guardarLicenseCache($cache);
        } else {
            log_license_warning('No se pudo contactar la API de licencias; se usa el último caché.');
        }
    }

    if ($cache && isset($cache['valid']) && !$cache['valid']) {
        showMaintenance($cache['message'] ?? 'Licencia inválida o expirada.');
    }
}

function leerLicenseCache(): ?array {
    if (!file_exists(LICENSE_CACHE_FILE)) return null;
    $c = json_decode(file_get_contents(LICENSE_CACHE_FILE), true);
    return (is_array($c) && isset($c['valid'])) ? $c : null;
}

function guardarLicenseCache(array $cache): void {
    $dir = dirname(LICENSE_CACHE_FILE);
    if (!is_dir($dir)) @mkdir($dir, 0755, true);
    @file_put_contents(LICENSE_CACHE_FILE, json_encode($cache, JSON_PRETTY_PRINT));
}

function showMaintenance(string $motivo): void {
    http_response_code(503);
    require __DIR__ . '/../../webService/views/mantenimiento.php';
    exit;
}

function log_license_warning(string $msg): void {
    $dir = __DIR__ . '/../../storage/logs';
    if (!is_dir($dir)) @mkdir($dir, 0755, true);
    @file_put_contents($dir . '/license.log', '[' . date('Y-m-d H:i:s') . '] WARNING: ' . $msg . PHP_EOL, FILE_APPEND);
}
