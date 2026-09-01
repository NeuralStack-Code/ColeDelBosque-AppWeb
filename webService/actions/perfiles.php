<?php
/**
 * Action: perfiles  →  /api/perfiles?action=catalogos|listar|crear|editar|eliminar
 * Panel del desarrollador (permiso 4): alta/edición/baja de cuentas de CUALQUIER rol
 * (admin, maestro, alumno, desarrollador). La matrícula se guarda ENCRIPTADA.
 *
 * cuenta(id_cuenta, matricula, usuario_id, grupo_id, permiso_id, status_id)
 */
require_once __DIR__ . '/../../apiService/core/conexionBDD.php';
require_once __DIR__ . '/../../apiService/core/crypto.php';
require_once __DIR__ . '/../../apiService/middleware/auth.php';

requireDev();

$action = $_GET['action'] ?? '';

/** Roles con grupo (maestro=2, alumno=3). Admin/dev no llevan grupo. */
function rolLlevaGrupo(int $permiso): bool { return $permiso === 2 || $permiso === 3; }

switch ($action) {

    // ---- Catálogos para el formulario ----
    case 'catalogos':
        $permisos = [];
        $res = mysqli_query($conexion, 'SELECT id_permiso, nombre FROM permisos ORDER BY id_permiso');
        while ($r = mysqli_fetch_assoc($res)) $permisos[] = $r;

        $grupos = [];
        $res = mysqli_query($conexion,
            'SELECT g.id_grupo, g.grado, ci.nombre AS ciclo FROM grupo g
             LEFT JOIN ciclo ci ON ci.id_ciclo = g.ciclo_id
             WHERE g.id_grupo <> 0 ORDER BY g.nivel, g.grado');
        while ($r = mysqli_fetch_assoc($res)) $grupos[] = $r;

        $estatus = [];
        $res = mysqli_query($conexion, "SELECT clave, nombre FROM status WHERE ambito = 'alumno' ORDER BY orden");
        while ($r = mysqli_fetch_assoc($res)) $estatus[] = $r;

        response(200, true, 'Catálogos.', ['permisos' => $permisos, 'grupos' => $grupos, 'estatus' => $estatus]);
        break;

    // ---- Listar todas las cuentas ----
    case 'listar':
        $filtro = (int) ($_GET['permiso_id'] ?? 0);
        $sql = 'SELECT c.id_cuenta, c.matricula, c.permiso_id, p.nombre AS permiso_nombre,
                       c.grupo_id, g.grado, st.clave AS estatus, st.nombre AS estatus_nombre,
                       u.id_usuario, u.nombre, u.paterno, u.materno
                FROM cuenta c
                JOIN usuario u   ON u.id_usuario = c.usuario_id
                LEFT JOIN permisos p ON p.id_permiso = c.permiso_id
                LEFT JOIN grupo g    ON g.id_grupo = c.grupo_id
                LEFT JOIN status st  ON st.id_status = c.status_id'
                . ($filtro > 0 ? ' WHERE c.permiso_id = ' . $filtro : '') . '
                ORDER BY c.permiso_id, u.paterno, u.nombre';
        $res = mysqli_query($conexion, $sql);
        $items = [];
        while ($r = mysqli_fetch_assoc($res)) {
            $r['matricula'] = decrypt($r['matricula']);
            $r['nombre_completo'] = trim("$r[nombre] $r[paterno] $r[materno]");
            $items[] = $r;
        }
        response(200, true, 'Perfiles obtenidos.', ['items' => $items]);
        break;

    // ---- Crear cuenta de cualquier rol ----
    case 'crear':
        $nombre   = trim($_POST['nombre'] ?? '');
        $paterno  = trim($_POST['paterno'] ?? '');
        $materno  = trim($_POST['materno'] ?? '');
        $mat      = trim($_POST['matricula'] ?? '');
        $permiso  = (int) ($_POST['permiso_id'] ?? 0);
        $grupo    = rolLlevaGrupo($permiso) ? (int) ($_POST['grupo_id'] ?? 0) : 0;
        $claveEst = trim($_POST['estatus'] ?? 'activo');

        if ($nombre === '' || $paterno === '') response(400, false, 'Nombre y apellido paterno son obligatorios.');
        if (strlen($mat) < 4)                  response(400, false, 'La matrícula debe tener al menos 4 caracteres.');
        // ¿Permiso válido?
        $chkP = mysqli_prepare($conexion, 'SELECT 1 FROM permisos WHERE id_permiso = ? LIMIT 1');
        mysqli_stmt_bind_param($chkP, 'i', $permiso);
        mysqli_stmt_execute($chkP);
        if (!mysqli_fetch_row(mysqli_stmt_get_result($chkP))) response(400, false, 'Rol (permiso) no válido.');
        mysqli_stmt_close($chkP);

        $matEnc = encrypt($mat);
        $chk = mysqli_prepare($conexion, 'SELECT 1 FROM cuenta WHERE matricula = ? LIMIT 1');
        mysqli_stmt_bind_param($chk, 's', $matEnc);
        mysqli_stmt_execute($chk);
        if (mysqli_fetch_row(mysqli_stmt_get_result($chk))) response(409, false, 'La matrícula ya existe.');
        mysqli_stmt_close($chk);

        $statusId = statusId($conexion, 'alumno', in_array($claveEst, ['activo', 'baja', 'egresado']) ? $claveEst : 'activo');

        $u = mysqli_prepare($conexion, 'INSERT INTO usuario (nombre, paterno, materno) VALUES (?, ?, ?)');
        mysqli_stmt_bind_param($u, 'sss', $nombre, $paterno, $materno);
        if (!mysqli_stmt_execute($u)) response(500, false, 'No se pudo crear el usuario.');
        $usuarioId = mysqli_insert_id($conexion);
        mysqli_stmt_close($u);

        $c = mysqli_prepare($conexion, 'INSERT INTO cuenta (matricula, usuario_id, grupo_id, permiso_id, status_id) VALUES (?, ?, ?, ?, ?)');
        mysqli_stmt_bind_param($c, 'siiii', $matEnc, $usuarioId, $grupo, $permiso, $statusId);
        if (!mysqli_stmt_execute($c)) {
            mysqli_query($conexion, 'DELETE FROM usuario WHERE id_usuario = ' . (int) $usuarioId);
            response(500, false, 'No se pudo crear la cuenta.');
        }
        mysqli_stmt_close($c);
        response(201, true, 'Perfil creado.');
        break;

    // ---- Editar cuenta ----
    case 'editar':
        $id       = (int) ($_POST['id_cuenta'] ?? 0);
        $nombre   = trim($_POST['nombre'] ?? '');
        $paterno  = trim($_POST['paterno'] ?? '');
        $materno  = trim($_POST['materno'] ?? '');
        $mat      = trim($_POST['matricula'] ?? '');
        $permiso  = (int) ($_POST['permiso_id'] ?? 0);
        $grupo    = rolLlevaGrupo($permiso) ? (int) ($_POST['grupo_id'] ?? 0) : 0;
        $claveEst = trim($_POST['estatus'] ?? 'activo');

        if ($id <= 0)                          response(400, false, 'Registro no válido.');
        if ($nombre === '' || $paterno === '') response(400, false, 'Nombre y apellido paterno son obligatorios.');
        if (strlen($mat) < 4)                  response(400, false, 'La matrícula debe tener al menos 4 caracteres.');

        $q = mysqli_prepare($conexion, 'SELECT usuario_id FROM cuenta WHERE id_cuenta = ? LIMIT 1');
        mysqli_stmt_bind_param($q, 'i', $id);
        mysqli_stmt_execute($q);
        $row = mysqli_fetch_assoc(mysqli_stmt_get_result($q));
        mysqli_stmt_close($q);
        if (!$row) response(404, false, 'No se encontró la cuenta.');
        $usuarioId = (int) $row['usuario_id'];

        // Matrícula única (excluyendo esta cuenta)
        $matEnc = encrypt($mat);
        $chk = mysqli_prepare($conexion, 'SELECT 1 FROM cuenta WHERE matricula = ? AND id_cuenta <> ? LIMIT 1');
        mysqli_stmt_bind_param($chk, 'si', $matEnc, $id);
        mysqli_stmt_execute($chk);
        if (mysqli_fetch_row(mysqli_stmt_get_result($chk))) response(409, false, 'La matrícula ya existe en otra cuenta.');
        mysqli_stmt_close($chk);

        $statusId = statusId($conexion, 'alumno', in_array($claveEst, ['activo', 'baja', 'egresado']) ? $claveEst : 'activo');

        $u = mysqli_prepare($conexion, 'UPDATE usuario SET nombre = ?, paterno = ?, materno = ? WHERE id_usuario = ?');
        mysqli_stmt_bind_param($u, 'sssi', $nombre, $paterno, $materno, $usuarioId);
        mysqli_stmt_execute($u);
        mysqli_stmt_close($u);

        $c = mysqli_prepare($conexion, 'UPDATE cuenta SET matricula = ?, grupo_id = ?, permiso_id = ?, status_id = ? WHERE id_cuenta = ?');
        mysqli_stmt_bind_param($c, 'siiii', $matEnc, $grupo, $permiso, $statusId, $id);
        if (!mysqli_stmt_execute($c)) response(500, false, 'No se pudo actualizar la cuenta.');
        mysqli_stmt_close($c);
        response(200, true, 'Perfil actualizado.');
        break;

    // ---- Eliminar cuenta ----
    case 'eliminar':
        $id = (int) ($_POST['id_cuenta'] ?? 0);
        if ($id <= 0) response(400, false, 'Registro no válido.');

        $q = mysqli_prepare($conexion, 'SELECT usuario_id FROM cuenta WHERE id_cuenta = ? LIMIT 1');
        mysqli_stmt_bind_param($q, 'i', $id);
        mysqli_stmt_execute($q);
        $row = mysqli_fetch_assoc(mysqli_stmt_get_result($q));
        mysqli_stmt_close($q);
        if (!$row) response(404, false, 'No se encontró el registro.');
        $usuarioId = (int) $row['usuario_id'];

        // No permitir que el desarrollador se elimine a sí mismo
        if ($usuarioId === (int) ($_SESSION['id'] ?? 0)) response(409, false, 'No puedes eliminar tu propia cuenta.');

        // Borrado en cascada (recibos, colegiaturas, calificaciones) para no chocar con las FK
        try {
            mysqli_begin_transaction($conexion);
            foreach ([
                'DELETE FROM recibo       WHERE cuenta_id = ?',
                'DELETE FROM colegiatura  WHERE cuenta_id = ?',
                'DELETE FROM calificacion WHERE cuenta_id = ?',
                'UPDATE grupo SET maestra_id = NULL WHERE maestra_id = ?',
                'DELETE FROM cuenta       WHERE id_cuenta = ?',
            ] as $sql) {
                $st = mysqli_prepare($conexion, $sql);
                mysqli_stmt_bind_param($st, 'i', $id);
                mysqli_stmt_execute($st);
                mysqli_stmt_close($st);
            }
            $st = mysqli_prepare($conexion, 'DELETE FROM usuario WHERE id_usuario = ?');
            mysqli_stmt_bind_param($st, 'i', $usuarioId);
            mysqli_stmt_execute($st);
            mysqli_stmt_close($st);
            mysqli_commit($conexion);
        } catch (\Throwable $e) {
            mysqli_rollback($conexion);
            response(500, false, 'No se pudo eliminar: hay datos relacionados que lo impiden.');
        }
        response(200, true, 'Perfil y sus datos relacionados eliminados.');
        break;

    default:
        response(400, false, "Acción de perfiles no válida: '$action'.");
}
