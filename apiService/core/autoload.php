<?php
/**
 * Autocarga del proyecto.
 * - Usa Composer si vendor/ existe (classmap optimizado).
 * - Carga el core del framework (lo entrega la api central: php apiService/core/update.php).
 * - Fallback en runtime: resuelve las clases propias (controllers/business/models/libs)
 *   SIN Composer. Así solo programas: no hay que correr comandos.
 *
 * Idempotente: se incluye desde el index.php raíz y desde apiService/index.php.
 */
if (defined('CB_AUTOLOAD_READY')) return;
define('CB_AUTOLOAD_READY', true);

$raiz   = dirname(__DIR__, 2);
$vendor = $raiz . '/vendor/autoload.php';
if (is_file($vendor)) require_once $vendor;   // opcional: solo si Composer generó vendor/

require_once __DIR__ . '/procedure.php';      // sp(): el único punto que toca la base
require_once __DIR__ . '/Command.php';        // procedure con parámetros por nombre + estado uniforme
require_once __DIR__ . '/Entity.php';         // base de los business (solo datos)
require_once __DIR__ . '/Model.php';          // base de los models (cargan las tablas que trae el business)
require_once __DIR__ . '/ServerApi.php';      // por donde los controllers le hablan al business (+ Result)
require_once __DIR__ . '/Controller.php';     // base de los controllers + routeController() + e()


spl_autoload_register(static function (string $clase) use ($raiz): void {
    if (strpos($clase, '\\') !== false) return;   // namespaced → Composer/lib

    static $mapa = null;
    if ($mapa === null) {
        $mapa = [];
        foreach (['webService/controllers', 'webService/business', 'webService/models', 'webService/libs'] as $rel) {
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
