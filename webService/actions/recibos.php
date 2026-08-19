<?php
/**
 * Action: recibos  →  /api/recibos?action=catalogos|listar|crear|editar|eliminar
 * Ingresos/egresos de la escuela (solo admin).
 * Esquema: recibo(id_recibo, tipo_recibo_id, naturaleza, destinatario_tipo, cuenta_id, monto, comentario, fecha)
 */
require_once __DIR__ . '/../../apiService/core/conexionBDD.php';
require_once __DIR__ . '/../../apiService/middleware/auth.php';

requireAdmin();

$action = $_GET['action'] ?? '';

function natValida(string $n): string { return $n === 'gasto' ? 'gasto' : 'ingreso'; }
function destValido(string $d): string { return in_array($d, ['alumno', 'docente', 'escuela'], true) ? $d : 'escuela'; }

/** Lista personas por permiso (3=alumno, 2=docente) para el select de destinatario. */
function personas(mysqli $conexion, int $permiso): array {
    $stmt = mysqli_prepare($conexion,
        'SELECT c.id_cuenta, u.nombre, u.paterno, u.materno
         FROM cuenta c JOIN usuario u ON u.id_usuario = c.usuario_id
         WHERE c.permiso_id = ? ORDER BY u.paterno, u.nombre');
    mysqli_stmt_bind_param($stmt, 'i', $permiso);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    $out = [];
    while ($r = mysqli_fetch_assoc($res)) {
        $out[] = ['id_cuenta' => $r['id_cuenta'], 'nombre' => trim("$r[nombre] $r[paterno] $r[materno]")];
    }
    mysqli_stmt_close($stmt);
    return $out;
}

switch ($action) {

    // ---- Catálogos para el formulario ----
    case 'catalogos':
        $tipos = [];
        $res = mysqli_query($conexion, 'SELECT id_tipo, nombre, naturaleza FROM tipo_recibo ORDER BY nombre');
        while ($r = mysqli_fetch_assoc($res)) $tipos[] = $r;
        response(200, true, 'Catálogos.', [
            'tipos'    => $tipos,
            'alumnos'  => personas($conexion, 3),
            'docentes' => personas($conexion, 2),
        ]);
        break;

    // ---- Listar recibos + saldo ----
    case 'listar':
        $sql = 'SELECT r.id_recibo, r.tipo_recibo_id, r.naturaleza, r.destinatario_tipo, r.cuenta_id,
                       r.monto, r.tipo_pago, r.comentario, r.fecha,
                       t.nombre AS tipo_nombre,
                       u.nombre, u.paterno, u.materno
                FROM recibo r
                LEFT JOIN tipo_recibo t ON t.id_tipo = r.tipo_recibo_id
                LEFT JOIN cuenta c      ON c.id_cuenta = r.cuenta_id
                LEFT JOIN usuario u     ON u.id_usuario = c.usuario_id
                ORDER BY r.fecha DESC, r.id_recibo DESC';
        $res = mysqli_query($conexion, $sql);
        $items = []; $saldo = 0.0;
        while ($r = mysqli_fetch_assoc($res)) {
            $r['destinatario'] = $r['destinatario_tipo'] === 'escuela'
                ? 'Escuela'
                : trim("$r[nombre] $r[paterno] $r[materno]");
            $saldo += ($r['naturaleza'] === 'ingreso' ? 1 : -1) * (float) $r['monto'];
            $items[] = $r;
        }
        response(200, true, 'Recibos obtenidos.', ['items' => $items, 'saldo' => $saldo]);
        break;

    // ---- Crear ----
    case 'crear':
        [$tipo, $nat, $dtipo, $cuenta, $monto, $pago, $com, $fecha] = leerRecibo($conexion);
        $stmt = mysqli_prepare($conexion,
            'INSERT INTO recibo (tipo_recibo_id, naturaleza, destinatario_tipo, cuenta_id, monto, tipo_pago, comentario, fecha)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?)');
        mysqli_stmt_bind_param($stmt, 'issidsss', $tipo, $nat, $dtipo, $cuenta, $monto, $pago, $com, $fecha);
        if (!mysqli_stmt_execute($stmt)) response(500, false, 'No se pudo registrar el recibo.');
        mysqli_stmt_close($stmt);
        response(201, true, 'Recibo registrado.');
        break;

    // ---- Editar ----
    case 'editar':
        $id = (int) ($_POST['id_recibo'] ?? 0);
        if ($id <= 0) response(400, false, 'Registro no válido.');
        [$tipo, $nat, $dtipo, $cuenta, $monto, $pago, $com, $fecha] = leerRecibo($conexion);
        $stmt = mysqli_prepare($conexion,
            'UPDATE recibo SET tipo_recibo_id=?, naturaleza=?, destinatario_tipo=?, cuenta_id=?, monto=?, tipo_pago=?, comentario=?, fecha=? WHERE id_recibo=?');
        mysqli_stmt_bind_param($stmt, 'issidsssi', $tipo, $nat, $dtipo, $cuenta, $monto, $pago, $com, $fecha, $id);
        if (!mysqli_stmt_execute($stmt)) response(500, false, 'No se pudo actualizar el recibo.');
        mysqli_stmt_close($stmt);
        response(200, true, 'Recibo actualizado.');
        break;

    // ---- Eliminar ----
    case 'eliminar':
        $id = (int) ($_POST['id_recibo'] ?? 0);
        if ($id <= 0) response(400, false, 'Registro no válido.');
        $stmt = mysqli_prepare($conexion, 'DELETE FROM recibo WHERE id_recibo = ?');
        mysqli_stmt_bind_param($stmt, 'i', $id);
        mysqli_stmt_execute($stmt);
        $af = mysqli_stmt_affected_rows($stmt);
        mysqli_stmt_close($stmt);
        if ($af === 0) response(404, false, 'No se encontró el recibo.');
        response(200, true, 'Recibo eliminado.');
        break;

    default:
        response(400, false, "Acción de recibos no válida: '$action'.");
}

/** Lee y valida los campos POST de un recibo. Corta con response() si algo falla. */
function leerRecibo(mysqli $conexion): array {
    $tipo  = (int) ($_POST['tipo_recibo_id'] ?? 0) ?: null;
    $nat   = natValida($_POST['naturaleza'] ?? 'ingreso');
    $dtipo = destValido($_POST['destinatario_tipo'] ?? 'escuela');
    $monto = filter_var($_POST['monto'] ?? '', FILTER_VALIDATE_FLOAT);
    $pago  = trim($_POST['tipo_pago'] ?? '');
    $pago  = ($pago === '') ? null : $pago;
    $com   = trim($_POST['comentario'] ?? '');
    $com   = ($com === '') ? null : $com;
    $fecha = trim($_POST['fecha'] ?? '');
    $fecha = ($fecha === '') ? date('Y-m-d H:i:s') : str_replace('T', ' ', $fecha);

    if ($monto === false || $monto <= 0) response(400, false, 'El monto debe ser mayor a 0.');

    // Destinatario: escuela = sin persona; alumno/docente = requiere cuenta válida
    $cuenta = null;
    if ($dtipo !== 'escuela') {
        $cuenta = (int) ($_POST['cuenta_id'] ?? 0);
        if ($cuenta <= 0) response(400, false, 'Selecciona el ' . $dtipo . ' destinatario.');
        $permiso = $dtipo === 'alumno' ? 3 : 2;
        $chk = mysqli_prepare($conexion, 'SELECT 1 FROM cuenta WHERE id_cuenta = ? AND permiso_id = ? LIMIT 1');
        mysqli_stmt_bind_param($chk, 'ii', $cuenta, $permiso);
        mysqli_stmt_execute($chk);
        if (!mysqli_fetch_row(mysqli_stmt_get_result($chk))) response(400, false, 'El destinatario no es un ' . $dtipo . ' válido.');
        mysqli_stmt_close($chk);
    }
    return [$tipo, $nat, $dtipo, $cuenta, $monto, $pago, $com, $fecha];
}
