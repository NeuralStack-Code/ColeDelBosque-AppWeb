<?php
/**
 * Action: padre  →  /api/padre?action=resumen
 * Vista de solo lectura para el padre/alumno (permiso 3):
 * sus calificaciones (por materia) y sus colegiaturas.
 *
 * Depende de: $conexion, response() y middleware/auth.
 */
require_once __DIR__ . '/../../apiService/core/conexionBDD.php';
require_once __DIR__ . '/../../apiService/middleware/auth.php';

requireAuth();
if ((int) ($_SESSION['permiso'] ?? 0) !== 3) {
    response(403, false, 'Sección disponible solo para alumnos/padres.');
}

$action = $_GET['action'] ?? '';

switch ($action) {

    case 'resumen':
        $uid = (int) ($_SESSION['id'] ?? 0);

        // Cuenta del alumno
        $c = mysqli_prepare($conexion,
            'SELECT id_cuenta, grupo_id FROM cuenta WHERE usuario_id = ? AND permiso_id = 3 LIMIT 1');
        mysqli_stmt_bind_param($c, 'i', $uid);
        mysqli_stmt_execute($c);
        $cuenta = mysqli_fetch_assoc(mysqli_stmt_get_result($c));
        mysqli_stmt_close($c);
        if (!$cuenta) response(404, false, 'No se encontró tu cuenta.');

        $cuentaId = (int) $cuenta['id_cuenta'];
        $grupoId  = (int) $cuenta['grupo_id'];

        // Grado
        $grado = '';
        if ($grupoId > 0) {
            $g = mysqli_prepare($conexion, 'SELECT grado FROM grupo WHERE id_grupo = ? LIMIT 1');
            mysqli_stmt_bind_param($g, 'i', $grupoId);
            mysqli_stmt_execute($g);
            $grado = mysqli_fetch_assoc(mysqli_stmt_get_result($g))['grado'] ?? '';
            mysqli_stmt_close($g);
        }

        // Calificaciones por materia del grupo
        $calif = [];
        $sql = 'SELECT m.nombre AS materia, cal.t1, cal.t2, cal.t3, cal.t4, cal.examen, cal.reporte
                FROM materia m
                LEFT JOIN calificacion cal ON cal.materia_id = m.id_materia AND cal.cuenta_id = ?
                WHERE m.grupo_id = ?
                ORDER BY m.nombre';
        $st = mysqli_prepare($conexion, $sql);
        mysqli_stmt_bind_param($st, 'ii', $cuentaId, $grupoId);
        mysqli_stmt_execute($st);
        $res = mysqli_stmt_get_result($st);
        while ($r = mysqli_fetch_assoc($res)) $calif[] = $r;
        mysqli_stmt_close($st);

        // Colegiaturas
        $pagos = [];
        $p = mysqli_prepare($conexion,
            'SELECT id_pago, mes, monto, fecha_pago, estatus FROM colegiatura WHERE cuenta_id = ? ORDER BY id_pago DESC');
        mysqli_stmt_bind_param($p, 'i', $cuentaId);
        mysqli_stmt_execute($p);
        $res = mysqli_stmt_get_result($p);
        while ($r = mysqli_fetch_assoc($res)) $pagos[] = $r;
        mysqli_stmt_close($p);

        response(200, true, 'Resumen obtenido.', [
            'grado'          => $grado,
            'calificaciones' => $calif,
            'pagos'          => $pagos,
        ]);
        break;

    default:
        response(400, false, "Acción de padre no válida: '$action'.");
}
