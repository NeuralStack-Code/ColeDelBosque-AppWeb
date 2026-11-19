<?php
/**
 * Genera la semilla del catálogo de permisos a partir del CÓDIGO (ReflectionClass) + apiService/tools/catalogo/ajustes.php.
 *
 *   php apiService/tools/catalogo/generar_semilla.php            → escribe webService/sql/catalogo/02_catalogo.sql
 *   php apiService/tools/catalogo/generar_semilla.php --stdout   → la imprime
 *
 * Entran todos los controllers que heredan de Controller. No abre la base: solo lee el código.
 * La semilla se puede correr cuantas veces se quiera: registra (alta o actualización) y al final
 * desactiva lo que ya no existe en el código.
 * El archivo se genera dentro de webService/sql/ (como los procedures, se comparte por fuera del repo).
 */
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }

$root = dirname(__DIR__, 3);
require_once $root . '/apiService/core/autoload.php';

require_once __DIR__ . '/lib.php';

$ajustes = require __DIR__ . '/ajustes.php';
$catalogo = leerCodigo($root, $ajustes);

$sql = armarSql($catalogo, $ajustes);
if (in_array('--stdout', $argv, true)) {
    echo $sql;
} else {
    $destino = $root . '/webService/sql/catalogo/02_catalogo.sql';
    if (!is_dir(dirname($destino))) mkdir(dirname($destino), 0775, true);
    file_put_contents($destino, $sql);
    fwrite(STDERR, "Semilla escrita en webService/sql/catalogo/02_catalogo.sql\n");
}
foreach ($catalogo['avisos'] as $aviso) fwrite(STDERR, "  ! $aviso\n");
fwrite(STDERR, sprintf("  %d componentes, %d vistas, %d métodos.\n",
    count($catalogo['componentes']), count($catalogo['vistas']), count($catalogo['metodos'])));

/* ───────────────────────────────────────────────────────────────────────────────────────── */

function q(?string $s): string
{
    return $s === null ? 'NULL' : "'" . str_replace(["\\", "'"], ["\\\\", "''"], $s) . "'";
}

function armarSql(array $c, array $ajustes): string
{
    $cuerpo   = [];
    $vigentes = [];
    $objetos  = 0;

    $cuerpo[] = '-- CALL sp_objeto_registrar(Componente, Clase, Tipo, Padre, Clave, Nombre, Icono, Orden, SoloPost, Acceso, Sensible, PaginaAnterior, IdUsuarioRegistra, IdUsuarioCambia)';
    $cuerpo[] = '-- Desde la terminal no hay Command que llene la auditoría: se pasa 0 (sistema) explícito.';

    $cuerpo[] = '';
    $cuerpo[] = '-- Componentes: qué archivo, módulo y base atiende cada uno (el router los carga desde aquí)';
    foreach ($c['componentes'] as $clave => $comp) {
        $cuerpo[] = sprintf("CALL sp_componente_registrar(%s, %s, %s, %s, %s, 'portal', 0, 0);",
            q($clave), q($comp['clase']), q($comp['archivo']), q($comp['modulo']), q($comp['conexion']));
    }

    $cuerpo[] = '';
    $cuerpo[] = '-- Grupos';
    foreach ($ajustes['grupos'] as $clave => [$nombre, $icono, $orden]) {
        $cuerpo[] = sprintf("CALL sp_objeto_registrar('', '', 1, '', %s, %s, %s, %d, 0, 'sesion', 0, NULL, 0, 0);",
            q($clave), q($nombre), q($icono), $orden);
        $vigentes[] = ":$clave";
        $objetos++;
    }

    $cuerpo[] = '';
    $cuerpo[] = '-- Vistas';
    foreach ($c['vistas'] as $v) {
        $cuerpo[] = sprintf("CALL sp_objeto_registrar(%s, %s, 2, %s, %s, %s, %s, %d, %d, %s, %d, %s, 0, 0);",
            q($v['componente']), q($v['clase']), q($v['grupo']), q($v['metodo']), q($v['nombre']), q($v['icono']),
            $v['orden'], $v['soloPost'], q($v['acceso']), $v['sensible'], q($v['pagina']));
        $vigentes[] = "{$v['componente']}:{$v['metodo']}";
        $objetos++;
    }

    $cuerpo[] = '';
    $cuerpo[] = '-- Métodos';
    foreach ($c['metodos'] as $m) {
        $cuerpo[] = sprintf("CALL sp_objeto_registrar(%s, %s, 3, %s, %s, %s, '', %d, %d, %s, %d, NULL, 0, 0);",
            q($m['componente']), q($m['clase']), q($m['padre']), q($m['metodo']), q($m['nombre']),
            $m['orden'], $m['soloPost'], q($m['acceso']), $m['sensible']);
        $vigentes[] = "{$m['componente']}:{$m['metodo']}";
        $objetos++;
    }

    $cuerpo[] = '';
    $cuerpo[] = '-- Usos (una vista que llama métodos de otro lugar)';
    foreach ($ajustes['usos'] as $vista => $usados) {
        [$vc, $vm] = explode('/', $vista, 2);
        foreach ($usados as $u) {
            [$uc, $um] = explode('/', $u, 2);
            $cuerpo[] = sprintf('CALL sp_objeto_uso_registrar(%s, %s, %s, %s, 0);', q($vc), q($vm), q($uc), q($um));
        }
    }

    $cuerpo[] = '';
    $cuerpo[] = '-- Lo que ya no está en el código queda inactivo (no se borra: conserva sus asignaciones)';
    $cuerpo[] = sprintf("CALL sp_objeto_retirar(%s, 'portal', 0);", q(json_encode($vigentes, JSON_UNESCAPED_UNICODE)));

    // La huella sale del cuerpo (sin la fecha del encabezado): el mismo código da la misma huella.
    $huella = huellaCatalogo($c, $ajustes);

    $sql = [];
    $sql[] = '-- ============================================================================================';
    $sql[] = '-- Semilla del catálogo de permisos. GENERADA por apiService/tools/catalogo/generar_semilla.php';
    $sql[] = '-- ' . date('Y-m-d H:i') . ' · NO editar a mano: cambiar el código o apiService/tools/catalogo/ajustes.php y regenerar.';
    $sql[] = '-- Requiere: las tablas y procedures del catálogo (01_tablas_catalogo.sql y sp_catalogo.sql)';
    $sql[] = "-- Huella: $huella";
    $sql[] = '-- Sin information_schema (phpMyAdmin #1109). Se puede correr más de una vez.';
    $sql[] = '-- ============================================================================================';
    $sql[] = 'SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci;';
    $sql[] = '';
    array_push($sql, ...$cuerpo);
    $sql[] = '';
    $sql[] = '-- Versión cargada (+ su renglón en BitacoraPermisos)';
    $sql[] = sprintf("CALL sp_catalogo_version_registrar('%s', %d, 'semilla', 0);", $huella, $objetos);
    $sql[] = '';
    return implode("\n", $sql);
}
