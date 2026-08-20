<?php
/**
 * Action: ciclos  →  /api/ciclos?action=listar|crear|editar|activar|eliminar
 * Ciclos escolares (solo admin).
 * ciclo(id_ciclo, nombre, fecha_inicio, fecha_fin, activo)
 */
require_once __DIR__ . '/../../apiService/core/conexionBDD.php';
require_once __DIR__ . '/../../apiService/middleware/auth.php';

requireAdmin();

$action = $_GET['action'] ?? '';

switch ($action) {

    case 'listar':
        $res = mysqli_query($conexion,
            'SELECT c.id_ciclo, c.nombre, c.fecha_inicio, c.fecha_fin,
                    (s.clave = "activo") AS activo, s.clave AS estatus, s.nombre AS estatus_nombre
             FROM ciclo c LEFT JOIN status s ON s.id_status = c.status_id
             ORDER BY c.fecha_inicio DESC');
        $items = [];
        while ($r = mysqli_fetch_assoc($res)) $items[] = $r;
        response(200, true, 'Ciclos obtenidos.', ['items' => $items]);
        break;

    case 'crear':
        $nombre = trim($_POST['nombre'] ?? '');
        $ini    = trim($_POST['fecha_inicio'] ?? '');
        $fin    = trim($_POST['fecha_fin'] ?? '');
        if ($nombre === '')        response(400, false, 'El nombre es obligatorio.');
        if ($ini === '' || $fin === '') response(400, false, 'Indica fecha de inicio y fin.');
        if ($fin < $ini)           response(400, false, 'La fecha fin no puede ser anterior a la de inicio.');

        $cerrado = statusId($conexion, 'ciclo', 'cerrado'); // nace cerrado hasta que se active
        $stmt = mysqli_prepare($conexion, 'INSERT INTO ciclo (nombre, fecha_inicio, fecha_fin, status_id) VALUES (?, ?, ?, ?)');
        mysqli_stmt_bind_param($stmt, 'sssi', $nombre, $ini, $fin, $cerrado);
        if (!mysqli_stmt_execute($stmt)) response(500, false, 'No se pudo crear el ciclo.');
        mysqli_stmt_close($stmt);
        response(201, true, 'Ciclo creado.');
        break;

    case 'editar':
        $id     = (int) ($_POST['id_ciclo'] ?? 0);
        $nombre = trim($_POST['nombre'] ?? '');
        $ini    = trim($_POST['fecha_inicio'] ?? '');
        $fin    = trim($_POST['fecha_fin'] ?? '');
        if ($id <= 0)              response(400, false, 'Registro no válido.');
        if ($nombre === '')        response(400, false, 'El nombre es obligatorio.');
        if ($ini === '' || $fin === '') response(400, false, 'Indica fecha de inicio y fin.');
        if ($fin < $ini)           response(400, false, 'La fecha fin no puede ser anterior a la de inicio.');

        $stmt = mysqli_prepare($conexion, 'UPDATE ciclo SET nombre = ?, fecha_inicio = ?, fecha_fin = ? WHERE id_ciclo = ?');
        mysqli_stmt_bind_param($stmt, 'sssi', $nombre, $ini, $fin, $id);
        if (!mysqli_stmt_execute($stmt)) response(500, false, 'No se pudo actualizar el ciclo.');
        mysqli_stmt_close($stmt);
        response(200, true, 'Ciclo actualizado.');
        break;

    case 'activar':
        $id = (int) ($_POST['id_ciclo'] ?? 0);
        if ($id <= 0) response(400, false, 'Registro no válido.');
        // Un solo ciclo activo a la vez
        $cerrado  = statusId($conexion, 'ciclo', 'cerrado');
        $activoId = statusId($conexion, 'ciclo', 'activo');
        mysqli_query($conexion, 'UPDATE ciclo SET status_id = ' . (int) $cerrado);
        $stmt = mysqli_prepare($conexion, 'UPDATE ciclo SET status_id = ? WHERE id_ciclo = ?');
        mysqli_stmt_bind_param($stmt, 'ii', $activoId, $id);
        mysqli_stmt_execute($stmt);
        $af = mysqli_stmt_affected_rows($stmt);
        mysqli_stmt_close($stmt);
        response(200, true, 'Ciclo activado.');
        break;

    case 'eliminar':
        $id = (int) ($_POST['id_ciclo'] ?? 0);
        if ($id <= 0) response(400, false, 'Registro no válido.');
        $stmt = mysqli_prepare($conexion, 'DELETE FROM ciclo WHERE id_ciclo = ?');
        mysqli_stmt_bind_param($stmt, 'i', $id);
        mysqli_stmt_execute($stmt);
        $af = mysqli_stmt_affected_rows($stmt);
        mysqli_stmt_close($stmt);
        if ($af === 0) response(404, false, 'No se encontró el ciclo.');
        response(200, true, 'Ciclo eliminado.');
        break;

    default:
        response(400, false, "Acción de ciclos no válida: '$action'.");
}
