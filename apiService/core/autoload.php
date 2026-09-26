<?php
/**
 * Autocarga del proyecto.
 * - Usa Composer si vendor/ existe (classmap optimizado).
 * - Fallback en runtime: resuelve las clases propias (controllers/business/models)
 *   SIN Composer. Así solo programas: no hay que correr comandos.
 * - Carga auth.php (funciones requireAuth/requireAdmin/etc.) para que estén
 *   disponibles en todos los controllers sin require manual.
 *
 * Idempotente: se incluye desde el index.php raíz y desde apiService/index.php.
 */
if (defined('CB_AUTOLOAD_READY')) return;
define('CB_AUTOLOAD_READY', true);

$raiz   = dirname(__DIR__, 2);
$vendor = $raiz . '/vendor/autoload.php';
if (is_file($vendor)) require_once $vendor;   // opcional: solo si Composer generó vendor/

require_once __DIR__ . '/../middleware/auth.php';  // funciones de sesión (no autocargables)

spl_autoload_register(static function (string $clase) use ($raiz): void {
    if (strpos($clase, '\\') !== false) return;   // namespaced → Composer/lib

    static $mapa = null;
    if ($mapa === null) {
        $mapa = [];
        foreach (['webService/controllers', 'webService/business', 'webService/models'] as $rel) {
            $dir = $raiz . '/' . $rel;
            if (!is_dir($dir)) continue;
            $it = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS)
            );
            foreach ($it as $f) {
                if ($f->isFile() && substr($f->getFilename(), -4) === '.php') {
                    $mapa[substr($f->getFilename(), 0, -4)] = $f->getPathname();
                }
            }
        }
    }
    if (isset($mapa[$clase])) require $mapa[$clase];
});
