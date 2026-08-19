<?php
/**
 * Action: dashboard  →  /api/dashboard?action=resumen
 * Métricas del panel de administración (solo admin).
 * Depende de: $conexion, response() y middleware/auth.
 */
require_once __DIR__ . '/../../apiService/core/conexionBDD.php';
require_once __DIR__ . '/../../apiService/middleware/auth.php';

requireAdmin();

$action = $_GET['action'] ?? '';

/** Devuelve el primer valor escalar de una consulta. */
function escalar(mysqli $conexion, string $sql, $def = 0) {
    $res = mysqli_query($conexion, $sql);
    if (!$res) return $def;
    $row = mysqli_fetch_row($res);
    return $row ? $row[0] : $def;
}

switch ($action) {

    case 'resumen':
        $alumnos  = (int)   escalar($conexion, 'SELECT COUNT(*) FROM cuenta WHERE permiso_id = 3');
        $maestros = (int)   escalar($conexion, 'SELECT COUNT(*) FROM cuenta WHERE permiso_id = 2');
        $grupos   = (int)   escalar($conexion, 'SELECT COUNT(*) FROM grupo');
        $saldo    = (float) escalar($conexion,
            "SELECT COALESCE(SUM(CASE WHEN naturaleza = 'ingreso' THEN monto ELSE -monto END), 0) FROM recibo");
        $pend     = (int)   escalar($conexion, "SELECT COUNT(*) FROM colegiatura WHERE LOWER(estatus) = 'pendiente'");
        $pagados  = (int)   escalar($conexion, "SELECT COUNT(*) FROM colegiatura WHERE LOWER(estatus) = 'pagado'");

        // Alumnos por grupo (para la gráfica)
        $porGrupo = [];
        $res = mysqli_query($conexion,
            "SELECT g.grado, COUNT(c.id_cuenta) AS total
             FROM grupo g
             LEFT JOIN cuenta c ON c.grupo_id = g.id_grupo AND c.permiso_id = 3
             GROUP BY g.id_grupo ORDER BY g.grado");
        while ($r = mysqli_fetch_assoc($res)) {
            $porGrupo[] = ['grado' => $r['grado'], 'total' => (int) $r['total']];
        }

        response(200, true, 'Métricas obtenidas.', [
            'alumnos'          => $alumnos,
            'maestros'         => $maestros,
            'grupos'           => $grupos,
            'saldo'            => $saldo,
            'pagos_pendientes' => $pend,
            'pagos_pagados'    => $pagados,
            'alumnos_por_grupo' => $porGrupo,
        ]);
        break;

    default:
        response(400, false, "Acción de dashboard no válida: '$action'.");
}
