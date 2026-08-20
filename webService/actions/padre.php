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

/** Recargo vigente para una colegiatura no pagada y vencida. */
function recargoVigente(float $monto, float $pct, ?string $venc, string $estatus): float {
    if (strtolower($estatus) === 'pagado' || $pct <= 0 || !$venc) return 0.0;
    try { $hoy = new DateTime('today'); $v = new DateTime($venc); }
    catch (Exception $e) { return 0.0; }
    if ($v >= $hoy) return 0.0;
    $meses = ((int)$hoy->format('Y') - (int)$v->format('Y')) * 12 + ((int)$hoy->format('n') - (int)$v->format('n'));
    if ($meses < 1) $meses = 1;
    return round($monto * $pct / 100 * $meses, 2);
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

        // Ciclo activo
        $cicloRow = mysqli_fetch_assoc(mysqli_query($conexion,
            "SELECT id_ciclo, nombre FROM ciclo WHERE status_id = (SELECT id_status FROM status WHERE ambito='ciclo' AND clave='activo') LIMIT 1"));
        $cicloId  = (int) ($cicloRow['id_ciclo'] ?? 0);

        // Calificaciones por materia del grupo (ciclo activo) — 3 periodos + final
        $calif = [];
        $sql = 'SELECT m.nombre AS materia, cal.p1, cal.p2, cal.p3, cal.calif_final, cal.reporte
                FROM materia m
                LEFT JOIN calificacion cal ON cal.materia_id = m.id_materia AND cal.cuenta_id = ? AND cal.ciclo_id = ?
                WHERE m.grupo_id = ?
                ORDER BY m.nombre';
        $st = mysqli_prepare($conexion, $sql);
        mysqli_stmt_bind_param($st, 'iii', $cuentaId, $cicloId, $grupoId);
        mysqli_stmt_execute($st);
        $res = mysqli_stmt_get_result($st);
        while ($r = mysqli_fetch_assoc($res)) {
            $notas = array_filter([$r['p1'], $r['p2'], $r['p3']], fn($v) => $v !== null && $v !== '');
            $r['promedio'] = $r['calif_final'] !== null
                ? (float) $r['calif_final']
                : ($notas ? round(array_sum($notas) / count($notas), 1) : null);
            $calif[] = $r;
        }
        mysqli_stmt_close($st);

        // Colegiaturas (con recargo/descuento/total)
        $pagos = [];
        $p = mysqli_prepare($conexion,
            'SELECT col.id_pago, col.mes, col.tipo, col.monto, s.clave AS estatus, col.fecha_pago, col.fecha_vencimiento,
                    col.recargo_pct, col.recargo, col.descuento, col.concepto_descuento, col.recibo_id,
                    (SELECT COALESCE(SUM(rr.monto),0) FROM recibo rr WHERE rr.colegiatura_id = col.id_pago) AS abonado
             FROM colegiatura col LEFT JOIN status s ON s.id_status = col.status_id
             WHERE col.cuenta_id = ? ORDER BY col.tipo DESC, col.fecha_vencimiento ASC');
        mysqli_stmt_bind_param($p, 'i', $cuentaId);
        mysqli_stmt_execute($p);
        $res = mysqli_stmt_get_result($p);
        while ($r = mysqli_fetch_assoc($res)) {
            $monto = (float) $r['monto'];
            $desc  = (float) $r['descuento'];
            $recargo = strtolower($r['estatus']) === 'pagado'
                ? (float) $r['recargo']
                : recargoVigente($monto, (float) $r['recargo_pct'], $r['fecha_vencimiento'], $r['estatus']);
            $abonado = (float) $r['abonado'];
            $total   = max(0, $monto + $recargo - $desc);
            $r['recargo_calc'] = $recargo;
            $r['abonado']      = $abonado;
            $r['total']        = $total;
            $r['saldo']        = max(0, round($total - $abonado, 2));
            $pagos[] = $r;
        }
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
