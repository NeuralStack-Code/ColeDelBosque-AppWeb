<?php
/**
 * Action: permisos  →  /api/permisos?action=listar|crear|editar|eliminar
 * Catálogo de roles (solo desarrollador, permiso 4).
 * permisos(id_permiso, nombre)
 */
require_once __DIR__ . '/../../apiService/core/conexionBDD.php';
require_once __DIR__ . '/../../apiService/middleware/auth.php';

requireDev();

$action = $_GET['action'] ?? '';

switch ($action) {

    case 'listar':
        $res = mysqli_query($conexion,
            'SELECT p.id_permiso, p.nombre,
                    (SELECT COUNT(*) FROM cuenta c WHERE c.permiso_id = p.id_permiso) AS num_cuentas
             FROM permisos p ORDER BY p.id_permiso');
        $items = [];
        while ($r = mysqli_fetch_assoc($res)) $items[] = $r;
        response(200, true, 'Permisos obtenidos.', ['items' => $items]);
        break;

    case 'crear':
        $nombre = trim($_POST['nombre'] ?? '');
        if ($nombre === '') response(400, false, 'El nombre del rol es obligatorio.');
        $stmt = mysqli_prepare($conexion, 'INSERT INTO permisos (nombre) VALUES (?)');
        mysqli_stmt_bind_param($stmt, 's', $nombre);
        if (!mysqli_stmt_execute($stmt)) response(500, false, 'No se pudo crear el rol.');
        mysqli_stmt_close($stmt);
        response(201, true, 'Rol creado.');
        break;

    case 'editar':
        $id     = (int) ($_POST['id_permiso'] ?? 0);
        $nombre = trim($_POST['nombre'] ?? '');
        if ($id <= 0)       response(400, false, 'Registro no válido.');
        if ($nombre === '') response(400, false, 'El nombre del rol es obligatorio.');
        $stmt = mysqli_prepare($conexion, 'UPDATE permisos SET nombre = ? WHERE id_permiso = ?');
        mysqli_stmt_bind_param($stmt, 'si', $nombre, $id);
        if (!mysqli_stmt_execute($stmt)) response(500, false, 'No se pudo actualizar el rol.');
        mysqli_stmt_close($stmt);
        response(200, true, 'Rol actualizado.');
        break;

    case 'eliminar':
        $id = (int) ($_POST['id_permiso'] ?? 0);
        if ($id <= 0) response(400, false, 'Registro no válido.');
        if ($id >= 1 && $id <= 4) response(409, false, 'No se pueden eliminar los roles base del sistema.');

        $chk = mysqli_prepare($conexion, 'SELECT 1 FROM cuenta WHERE permiso_id = ? LIMIT 1');
        mysqli_stmt_bind_param($chk, 'i', $id);
        mysqli_stmt_execute($chk);
        if (mysqli_fetch_row(mysqli_stmt_get_result($chk))) response(409, false, 'No se puede eliminar: hay cuentas con este rol.');
        mysqli_stmt_close($chk);

        $stmt = mysqli_prepare($conexion, 'DELETE FROM permisos WHERE id_permiso = ?');
        mysqli_stmt_bind_param($stmt, 'i', $id);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);
        response(200, true, 'Rol eliminado.');
        break;

    default:
        response(400, false, "Acción de permisos no válida: '$action'.");
}
