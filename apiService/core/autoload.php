<?php
/**
 * Arranque del proyecto: el core (siempre) y el cargador propio (core/Loader.php). Sin Composer ni vendor/.
 *
 * Las clases de webService no se cargan por adelantado: el router carga el controller que le dice el catálogo
 * y activa su módulo (Loader::activate), y solo entonces se buscan sus business y models.
 *
 * Idempotente: se incluye desde el index.php raíz Y desde apiService/index.php. El guard evita doble registro.
 */
if (defined('CB_AUTOLOAD_READY')) return;
define('CB_AUTOLOAD_READY', true);

require_once __DIR__ . '/procedure.php';      // sp(): el único punto que toca la base
require_once __DIR__ . '/Command.php';        // procedure con parámetros por nombre + estado uniforme
require_once __DIR__ . '/Entity.php';         // base de los business (solo datos)
require_once __DIR__ . '/Model.php';          // base de los models (cargan las tablas que trae el business)
require_once __DIR__ . '/ServerApi.php';      // por donde los controllers le hablan al business (+ Result)
require_once __DIR__ . '/Controller.php';     // base de los controllers + actionArguments() + e()
require_once __DIR__ . '/Catalog.php';        // catálogo de permisos: qué existe, quién lo usa y qué archivo lo atiende
require_once __DIR__ . '/security.php';       // sesión endurecida, cabeceras y defensa CSRF (funciones)
require_once __DIR__ . '/conexionBDD.php';    // conexionPrincipal(): la base del colegio, una vez por petición
require_once __DIR__ . '/Rutas.php';          // páginas sueltas y redirecciones del sitio desde la base
require_once __DIR__ . '/Loader.php';         // cargador propio

Loader::boot(dirname(__DIR__, 2));

// storage/ pasó a apiService/storage/ (estructura 1.4.0). Un servidor que todavía tiene la carpeta vieja (caché de
// licencia, logs) la mueve sola en su primera petición; después esto es un is_dir().
if (is_dir(dirname(__DIR__, 2) . '/storage')) moverStorageViejo(dirname(__DIR__, 2) . '/storage', __DIR__ . '/../storage');

/** Mueve el contenido de $viejo a $nuevo sin pisar nada que ya exista allá, y borra $viejo si queda vacío. */
function moverStorageViejo(string $viejo, string $nuevo): void
{
    if (!is_dir($nuevo)) @mkdir($nuevo, 0755, true);
    foreach (scandir($viejo) ?: [] as $nombre) {
        if ($nombre === '.' || $nombre === '..') continue;
        $de = "$viejo/$nombre";
        $a  = "$nuevo/$nombre";
        if (!file_exists($a))                    @rename($de, $a);
        elseif (is_dir($de) && is_dir($a))       moverStorageViejo($de, $a);
        else                                     @unlink($de);   // ya existe allá (la copia nueva manda)
    }
    @rmdir($viejo);   // solo si quedó vacía; si algo no se pudo mover, se intenta en la siguiente petición
}
