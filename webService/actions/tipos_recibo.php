<?php
/**
 * Action: tipos-recibo  →  /api/tipos-recibo?action=listar|crear|editar|eliminar
 * Catálogo de tipos de recibo (solo admin).
 * Esquema: tipo_recibo(id_tipo, nombre, naturaleza ENUM('ingreso','gasto'))
 */
require_once __DIR__ . '/../../apiService/core/conexionBDD.php';
require_once __DIR__ . '/../../apiService/middleware/auth.php';

requireAdmin();

$action = $_GET['action'] ?? '';

function naturalezaValida(string $n): string {
    return $n === 'gasto' ? 'gasto' : 'ingreso';
}

switch ($action) {

    case 'listar':
        $res = mysqli_query($conexion, 'SELECT id_tipo, nombre, naturaleza FROM tipo_recibo ORDER BY nombre');
        $items = [];
        while ($r = mysqli_fetch_assoc($res)) $items[] = $r;
        response(200, true, 'Tipos obtenidos.', ['items' => $items]);
        break;

    case 'crear':
        $nombre = trim($_POST['nombre'] ?? '');
        $nat    = naturalezaValida($_POST['naturaleza'] ?? 'ingreso');
        if ($nombre === '') response(400, false, 'El nombre es obligatorio.');

        $stmt = mysqli_prepare($conexion, 'INSERT INTO tipo_recibo (nombre, naturaleza) VALUES (?, ?)');
        mysqli_stmt_bind_param($stmt, 'ss', $nombre, $nat);
        if (!mysqli_stmt_execute($stmt)) response(500, false, 'No se pudo crear el tipo.');
        mysqli_stmt_close($stmt);
        response(201, true, 'Tipo de recibo creado.');
        break;

    case 'editar':
        $id     = (int) ($_POST['id_tipo'] ?? 0);
        $nombre = trim($_POST['nombre'] ?? '');
        $nat    = naturalezaValida($_POST['naturaleza'] ?? 'ingreso');
        if ($id <= 0)       response(400, false, 'Registro no válido.');
        if ($nombre === '') response(400, false, 'El nombre es obligatorio.');

        $stmt = mysqli_prepare($conexion, 'UPDATE tipo_recibo SET nombre = ?, naturaleza = ? WHERE id_tipo = ?');
        mysqli_stmt_bind_param($stmt, 'ssi', $nombre, $nat, $id);
        if (!mysqli_stmt_execute($stmt)) response(500, false, 'No se pudo actualizar el tipo.');
        mysqli_stmt_close($stmt);
        response(200, true, 'Tipo de recibo actualizado.');
        break;

    case 'eliminar':
        $id = (int) ($_POST['id_tipo'] ?? 0);
        if ($id <= 0) response(400, false, 'Registro no válido.');

        // ¿Hay recibos usando este tipo?
        $chk = mysqli_prepare($conexion, 'SELECT 1 FROM recibo WHERE tipo_recibo_id = ? LIMIT 1');
        mysqli_stmt_bind_param($chk, 'i', $id);
        mysqli_stmt_execute($chk);
        if (mysqli_fetch_row(mysqli_stmt_get_result($chk))) {
            response(409, false, 'No se puede eliminar: hay recibos con este tipo.');
        }
        mysqli_stmt_close($chk);

        $stmt = mysqli_prepare($conexion, 'DELETE FROM tipo_recibo WHERE id_tipo = ?');
        mysqli_stmt_bind_param($stmt, 'i', $id);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);
        response(200, true, 'Tipo de recibo eliminado.');
        break;

    default:
        response(400, false, "Acción de tipos-recibo no válida: '$action'.");
}
