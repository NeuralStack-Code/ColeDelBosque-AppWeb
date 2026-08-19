<?php
/**
 * Action: materias  →  /api/materias?action=listar|crear|editar|eliminar
 * Catálogo global de materias (solo admin). Cada materia pertenece a un grupo.
 * Esquema: materia(id_materia, nombre, calificaciones, grupo_id)
 */
require_once __DIR__ . '/../../apiService/core/conexionBDD.php';
require_once __DIR__ . '/../../apiService/middleware/auth.php';

requireAdmin();

$action = $_GET['action'] ?? '';

switch ($action) {

    case 'listar':
        $sql = 'SELECT m.id_materia, m.nombre, m.grupo_id, g.grado
                FROM materia m
                LEFT JOIN grupo g ON g.id_grupo = m.grupo_id
                ORDER BY g.grado, m.nombre';
        $res = mysqli_query($conexion, $sql);
        $items = [];
        while ($r = mysqli_fetch_assoc($res)) $items[] = $r;
        response(200, true, 'Materias obtenidas.', ['items' => $items]);
        break;

    case 'crear':
        $nombre = trim($_POST['nombre'] ?? '');
        $grupo  = (int) ($_POST['grupo_id'] ?? 0);
        if ($nombre === '') response(400, false, 'El nombre es obligatorio.');
        if ($grupo <= 0)    response(400, false, 'Selecciona un grupo.');

        $stmt = mysqli_prepare($conexion, 'INSERT INTO materia (nombre, grupo_id) VALUES (?, ?)');
        mysqli_stmt_bind_param($stmt, 'si', $nombre, $grupo);
        if (!mysqli_stmt_execute($stmt)) response(500, false, 'No se pudo crear la materia.');
        mysqli_stmt_close($stmt);
        response(201, true, 'Materia creada.');
        break;

    case 'editar':
        $id     = (int) ($_POST['id_materia'] ?? 0);
        $nombre = trim($_POST['nombre'] ?? '');
        $grupo  = (int) ($_POST['grupo_id'] ?? 0);
        if ($id <= 0)       response(400, false, 'Registro no válido.');
        if ($nombre === '') response(400, false, 'El nombre es obligatorio.');
        if ($grupo <= 0)    response(400, false, 'Selecciona un grupo.');

        $stmt = mysqli_prepare($conexion, 'UPDATE materia SET nombre = ?, grupo_id = ? WHERE id_materia = ?');
        mysqli_stmt_bind_param($stmt, 'sii', $nombre, $grupo, $id);
        if (!mysqli_stmt_execute($stmt)) response(500, false, 'No se pudo actualizar la materia.');
        mysqli_stmt_close($stmt);
        response(200, true, 'Materia actualizada.');
        break;

    case 'eliminar':
        $id = (int) ($_POST['id_materia'] ?? 0);
        if ($id <= 0) response(400, false, 'Registro no válido.');

        // ¿Hay calificaciones ligadas?
        $chk = mysqli_prepare($conexion, 'SELECT 1 FROM calificacion WHERE materia_id = ? LIMIT 1');
        mysqli_stmt_bind_param($chk, 'i', $id);
        mysqli_stmt_execute($chk);
        if (mysqli_fetch_row(mysqli_stmt_get_result($chk))) {
            response(409, false, 'No se puede eliminar: la materia tiene calificaciones registradas.');
        }
        mysqli_stmt_close($chk);

        $stmt = mysqli_prepare($conexion, 'DELETE FROM materia WHERE id_materia = ?');
        mysqli_stmt_bind_param($stmt, 'i', $id);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);
        response(200, true, 'Materia eliminada.');
        break;

    default:
        response(400, false, "Acción de materias no válida: '$action'.");
}
