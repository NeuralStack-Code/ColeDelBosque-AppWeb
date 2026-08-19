<?php
/**
 * Action: colegiaturas  →  /api/colegiaturas?action=listar|crear|editar|eliminar
 * Gestión de pagos de colegiatura (solo admin).
 *
 * Esquema: colegiatura(id_pago PK, cuenta_id, fecha_pago, mes, monto, estatus)
 * Depende de: $conexion, response() y middleware/auth.
 */
require_once __DIR__ . '/../../apiService/core/conexionBDD.php';
require_once __DIR__ . '/../../apiService/middleware/auth.php';

requireAdmin();

$action = $_GET['action'] ?? '';

/** Normaliza estatus a 'pagado' | 'pendiente'. */
function estatusValido(string $e): string {
    $e = strtolower(trim($e));
    return in_array($e, ['pagado', 'pendiente'], true) ? $e : 'pendiente';
}

switch ($action) {

    case 'listar':
        $sql = 'SELECT col.id_pago, col.cuenta_id, col.mes, col.monto, col.fecha_pago, col.estatus,
                       u.nombre, u.paterno, u.materno
                FROM colegiatura col
                JOIN cuenta c  ON c.id_cuenta = col.cuenta_id
                JOIN usuario u ON u.id_usuario = c.usuario_id
                ORDER BY col.id_pago DESC';
        $res = mysqli_query($conexion, $sql);
        $items = [];
        while ($r = mysqli_fetch_assoc($res)) {
            $r['alumno'] = trim($r['nombre'] . ' ' . $r['paterno'] . ' ' . $r['materno']);
            $items[] = $r;
        }
        response(200, true, 'Pagos obtenidos.', ['items' => $items]);
        break;

    case 'crear':
        $cuentaId = (int) ($_POST['cuenta_id'] ?? 0);
        $mes      = trim($_POST['mes'] ?? '');
        $monto    = filter_var($_POST['monto'] ?? '', FILTER_VALIDATE_FLOAT);
        $fecha    = trim($_POST['fecha_pago'] ?? '');
        $estatus  = estatusValido($_POST['estatus'] ?? 'pendiente');

        if ($cuentaId <= 0)         response(400, false, 'Selecciona un alumno.');
        if ($mes === '')            response(400, false, 'Indica el mes.');
        if ($monto === false || $monto < 0) response(400, false, 'El monto no es válido.');
        if ($fecha === '')          $fecha = date('Y-m-d');

        $stmt = mysqli_prepare($conexion,
            'INSERT INTO colegiatura (cuenta_id, fecha_pago, mes, monto, estatus) VALUES (?, ?, ?, ?, ?)');
        mysqli_stmt_bind_param($stmt, 'issds', $cuentaId, $fecha, $mes, $monto, $estatus);
        if (!mysqli_stmt_execute($stmt)) response(500, false, 'No se pudo registrar el pago.');
        mysqli_stmt_close($stmt);

        response(201, true, 'Pago registrado.');
        break;

    case 'editar':
        $id      = (int) ($_POST['id_pago'] ?? 0);
        $mes     = trim($_POST['mes'] ?? '');
        $monto   = filter_var($_POST['monto'] ?? '', FILTER_VALIDATE_FLOAT);
        $fecha   = trim($_POST['fecha_pago'] ?? '');
        $estatus = estatusValido($_POST['estatus'] ?? 'pendiente');

        if ($id <= 0)               response(400, false, 'Registro no válido.');
        if ($mes === '')            response(400, false, 'Indica el mes.');
        if ($monto === false || $monto < 0) response(400, false, 'El monto no es válido.');
        if ($fecha === '')          $fecha = date('Y-m-d');

        $stmt = mysqli_prepare($conexion,
            'UPDATE colegiatura SET fecha_pago = ?, mes = ?, monto = ?, estatus = ? WHERE id_pago = ?');
        mysqli_stmt_bind_param($stmt, 'ssdsi', $fecha, $mes, $monto, $estatus, $id);
        if (!mysqli_stmt_execute($stmt)) response(500, false, 'No se pudo actualizar el pago.');
        $af = mysqli_stmt_affected_rows($stmt);
        mysqli_stmt_close($stmt);
        if ($af === 0) response(404, false, 'No se encontró el pago.');

        response(200, true, 'Pago actualizado.');
        break;

    case 'eliminar':
        $id = (int) ($_POST['id_pago'] ?? 0);
        if ($id <= 0) response(400, false, 'Registro no válido.');

        $stmt = mysqli_prepare($conexion, 'DELETE FROM colegiatura WHERE id_pago = ?');
        mysqli_stmt_bind_param($stmt, 'i', $id);
        mysqli_stmt_execute($stmt);
        $af = mysqli_stmt_affected_rows($stmt);
        mysqli_stmt_close($stmt);
        if ($af === 0) response(404, false, 'No se encontró el pago.');

        response(200, true, 'Pago eliminado.');
        break;

    default:
        response(400, false, "Acción de colegiaturas no válida: '$action'.");
}
