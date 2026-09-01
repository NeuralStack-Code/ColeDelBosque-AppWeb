<?php
/**
 * Action: tablas  →  /api/tablas?action=listar|ver
 * Visor de solo lectura de las tablas de la BD (solo desarrollador, permiso 4).
 * El nombre de tabla se valida contra la lista real (whitelist) para evitar inyección.
 */
require_once __DIR__ . '/../../apiService/core/conexionBDD.php';
require_once __DIR__ . '/../../apiService/core/crypto.php';
require_once __DIR__ . '/../../apiService/middleware/auth.php';

requireDev();

$action = $_GET['action'] ?? '';

/** Lista real de tablas de la BD (whitelist). */
function tablasBD(mysqli $conexion): array {
    $t = [];
    $res = mysqli_query($conexion, 'SHOW TABLES');
    while ($r = mysqli_fetch_row($res)) $t[] = $r[0];
    return $t;
}

switch ($action) {

    case 'listar':
        $items = [];
        foreach (tablasBD($conexion) as $t) {
            $c = mysqli_fetch_row(mysqli_query($conexion, 'SELECT COUNT(*) FROM `' . $t . '`'))[0] ?? 0;
            $items[] = ['tabla' => $t, 'filas' => (int) $c];
        }
        response(200, true, 'Tablas obtenidas.', ['items' => $items]);
        break;

    case 'ver':
        $tabla = trim($_GET['tabla'] ?? '');
        if (!in_array($tabla, tablasBD($conexion), true)) response(400, false, 'Tabla no válida.');
        $limite = min(500, max(1, (int) ($_GET['limite'] ?? 200)));

        $res = mysqli_query($conexion, 'SELECT * FROM `' . $tabla . '` LIMIT ' . $limite);
        $columnas = [];
        foreach (mysqli_fetch_fields($res) as $f) $columnas[] = $f->name;
        $desencriptar = in_array('matricula', $columnas, true);

        $filas = [];
        while ($r = mysqli_fetch_assoc($res)) {
            if ($desencriptar && !empty($r['matricula'])) {
                $dec = decrypt($r['matricula']);
                if ($dec !== '' && $dec !== null) $r['matricula'] = $dec;
            }
            $filas[] = $r;
        }
        response(200, true, 'Datos de la tabla.', ['tabla' => $tabla, 'columnas' => $columnas, 'filas' => $filas]);
        break;

    default:
        response(400, false, "Acción de tablas no válida: '$action'.");
}
