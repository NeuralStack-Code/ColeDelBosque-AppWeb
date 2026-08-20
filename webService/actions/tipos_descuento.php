<?php
/**
 * Action: tipos-descuento  →  /api/tipos-descuento?action=listar|crear|editar|eliminar
 * Catálogo de tipos de descuento (solo admin). Nada hardcodeado: el % vive aquí.
 * Esquema: tipo_descuento(id_descuento, nombre, porcentaje, aplica_a ENUM('colegiatura','inscripcion'), activo)
 */
require_once __DIR__ . '/../../apiService/core/conexionBDD.php';
require_once __DIR__ . '/../../apiService/middleware/auth.php';

requireAdmin();

$action = $_GET['action'] ?? '';

/** aplica_a solo puede ser colegiatura o inscripción. */
function aplicaValida(string $a): string {
    return $a === 'inscripcion' ? 'inscripcion' : 'colegiatura';
}

switch ($action) {

    case 'listar':
        $res = mysqli_query($conexion,
            'SELECT t.id_descuento, t.nombre, t.porcentaje, t.aplica_a, (s.clave = "activo") AS activo
             FROM tipo_descuento t LEFT JOIN status s ON s.id_status = t.status_id
             ORDER BY t.aplica_a, t.nombre');
        $items = [];
        while ($r = mysqli_fetch_assoc($res)) $items[] = $r;
        response(200, true, 'Tipos de descuento obtenidos.', ['items' => $items]);
        break;

    case 'crear':
        $nombre = trim($_POST['nombre'] ?? '');
        $pct    = filter_var($_POST['porcentaje'] ?? '', FILTER_VALIDATE_FLOAT);
        $aplica = aplicaValida($_POST['aplica_a'] ?? 'colegiatura');
        if ($nombre === '')                     response(400, false, 'El nombre es obligatorio.');
        if ($pct === false || $pct < 0 || $pct > 100) response(400, false, 'El porcentaje debe estar entre 0 y 100.');

        $stActivo = statusId($conexion, 'descuento', 'activo');
        $stmt = mysqli_prepare($conexion, 'INSERT INTO tipo_descuento (nombre, porcentaje, aplica_a, status_id) VALUES (?, ?, ?, ?)');
        mysqli_stmt_bind_param($stmt, 'sdsi', $nombre, $pct, $aplica, $stActivo);
        if (!mysqli_stmt_execute($stmt)) response(500, false, 'No se pudo crear el descuento.');
        mysqli_stmt_close($stmt);
        response(201, true, 'Tipo de descuento creado.');
        break;

    case 'editar':
        $id     = (int) ($_POST['id_descuento'] ?? 0);
        $nombre = trim($_POST['nombre'] ?? '');
        $pct    = filter_var($_POST['porcentaje'] ?? '', FILTER_VALIDATE_FLOAT);
        $aplica = aplicaValida($_POST['aplica_a'] ?? 'colegiatura');
        $activo = (int) ($_POST['activo'] ?? 1) === 0 ? 0 : 1;
        if ($id <= 0)       response(400, false, 'Registro no válido.');
        if ($nombre === '') response(400, false, 'El nombre es obligatorio.');
        if ($pct === false || $pct < 0 || $pct > 100) response(400, false, 'El porcentaje debe estar entre 0 y 100.');

        $stId = statusId($conexion, 'descuento', $activo === 1 ? 'activo' : 'inactivo');
        $stmt = mysqli_prepare($conexion,
            'UPDATE tipo_descuento SET nombre = ?, porcentaje = ?, aplica_a = ?, status_id = ? WHERE id_descuento = ?');
        mysqli_stmt_bind_param($stmt, 'sdsii', $nombre, $pct, $aplica, $stId, $id);
        if (!mysqli_stmt_execute($stmt)) response(500, false, 'No se pudo actualizar el descuento.');
        mysqli_stmt_close($stmt);
        response(200, true, 'Tipo de descuento actualizado.');
        break;

    case 'eliminar':
        $id = (int) ($_POST['id_descuento'] ?? 0);
        if ($id <= 0) response(400, false, 'Registro no válido.');

        // ¿Hay colegiaturas usando este descuento?
        $chk = mysqli_prepare($conexion, 'SELECT 1 FROM colegiatura WHERE tipo_descuento_id = ? LIMIT 1');
        mysqli_stmt_bind_param($chk, 'i', $id);
        mysqli_stmt_execute($chk);
        if (mysqli_fetch_row(mysqli_stmt_get_result($chk))) {
            response(409, false, 'No se puede eliminar: hay pagos con este descuento aplicado. Puedes desactivarlo.');
        }
        mysqli_stmt_close($chk);

        $stmt = mysqli_prepare($conexion, 'DELETE FROM tipo_descuento WHERE id_descuento = ?');
        mysqli_stmt_bind_param($stmt, 'i', $id);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);
        response(200, true, 'Tipo de descuento eliminado.');
        break;

    default:
        response(400, false, "Acción de tipos-descuento no válida: '$action'.");
}
