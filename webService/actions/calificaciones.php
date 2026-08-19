<?php
/**
 * Action: calificaciones  →  /api/calificaciones?action=contexto|listar|guardar
 * Notas por (alumno, materia) del grupo que imparte el maestro logueado.
 *
 * Todo se acota al grupo del maestro (cuenta.grupo_id del usuario en sesión),
 * para que un maestro no pueda tocar alumnos/materias de otro grupo.
 *
 * Requiere la tabla `calificacion` (ver webService/sql/calificacion.sql).
 * Depende de: $conexion, response() y middleware/auth.
 */
require_once __DIR__ . '/../../apiService/core/conexionBDD.php';
require_once __DIR__ . '/../../apiService/middleware/auth.php';

// Solo maestros (permiso 2)
requireAuth();
if ((int) ($_SESSION['permiso'] ?? 0) !== 2) {
    response(403, false, 'Solo los maestros pueden acceder a calificaciones.');
}

/** Grupo que imparte el maestro logueado (por su usuario_id). */
function grupoDelMaestro(mysqli $conexion): int {
    $uid = (int) ($_SESSION['id'] ?? 0);
    $stmt = mysqli_prepare($conexion, 'SELECT grupo_id FROM cuenta WHERE usuario_id = ? AND permiso_id = 2 LIMIT 1');
    mysqli_stmt_bind_param($stmt, 'i', $uid);
    mysqli_stmt_execute($stmt);
    $row = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
    mysqli_stmt_close($stmt);
    return (int) ($row['grupo_id'] ?? 0);
}

/** '' → null; si no, float validado 0–10 (corta con response en error). */
function nota($v): ?float {
    if ($v === '' || $v === null) return null;
    $n = filter_var($v, FILTER_VALIDATE_FLOAT);
    if ($n === false)          response(400, false, 'Las calificaciones deben ser números.');
    if ($n < 0 || $n > 10)     response(400, false, 'Las calificaciones deben estar entre 0 y 10.');
    return (float) $n;
}

/** Verifica que la materia pertenezca al grupo del maestro. */
function materiaEnGrupo(mysqli $conexion, int $materiaId, int $grupoId): bool {
    $stmt = mysqli_prepare($conexion, 'SELECT 1 FROM materia WHERE id_materia = ? AND grupo_id = ? LIMIT 1');
    mysqli_stmt_bind_param($stmt, 'ii', $materiaId, $grupoId);
    mysqli_stmt_execute($stmt);
    $ok = (bool) mysqli_fetch_row(mysqli_stmt_get_result($stmt));
    mysqli_stmt_close($stmt);
    return $ok;
}

$action  = $_GET['action'] ?? '';
$grupoId = grupoDelMaestro($conexion);

if ($grupoId <= 0) {
    response(409, false, 'No tienes un grupo asignado. Contacta a la administración.');
}

switch ($action) {

    // ---------- CONTEXTO: grupo + materias + alumnos ----------
    case 'contexto':
        // Materias del grupo
        $ms = mysqli_prepare($conexion, 'SELECT id_materia, nombre FROM materia WHERE grupo_id = ? ORDER BY nombre');
        mysqli_stmt_bind_param($ms, 'i', $grupoId);
        mysqli_stmt_execute($ms);
        $res = mysqli_stmt_get_result($ms);
        $materias = [];
        while ($r = mysqli_fetch_assoc($res)) $materias[] = $r;
        mysqli_stmt_close($ms);

        // Grado del grupo
        $gs = mysqli_prepare($conexion, 'SELECT grado FROM grupo WHERE id_grupo = ? LIMIT 1');
        mysqli_stmt_bind_param($gs, 'i', $grupoId);
        mysqli_stmt_execute($gs);
        $grado = mysqli_fetch_assoc(mysqli_stmt_get_result($gs))['grado'] ?? '';
        mysqli_stmt_close($gs);

        response(200, true, 'Contexto obtenido.', [
            'grupo_id' => $grupoId,
            'grado'    => $grado,
            'materias' => $materias,
        ]);
        break;

    // ---------- LISTAR: alumnos + sus notas en una materia ----------
    case 'listar':
        $materiaId = (int) ($_GET['materia_id'] ?? 0);
        if ($materiaId <= 0 || !materiaEnGrupo($conexion, $materiaId, $grupoId)) {
            response(400, false, 'Materia no válida para tu grupo.');
        }

        $sql = 'SELECT c.id_cuenta, u.nombre, u.paterno, u.materno,
                       cal.t1, cal.t2, cal.t3, cal.t4, cal.examen, cal.reporte
                FROM cuenta c
                JOIN usuario u ON u.id_usuario = c.usuario_id
                LEFT JOIN calificacion cal ON cal.cuenta_id = c.id_cuenta AND cal.materia_id = ?
                WHERE c.grupo_id = ? AND c.permiso_id = 3
                ORDER BY u.paterno, u.nombre';
        $stmt = mysqli_prepare($conexion, $sql);
        mysqli_stmt_bind_param($stmt, 'ii', $materiaId, $grupoId);
        mysqli_stmt_execute($stmt);
        $res = mysqli_stmt_get_result($stmt);
        $alumnos = [];
        while ($r = mysqli_fetch_assoc($res)) {
            $r['nombre_completo'] = trim($r['nombre'] . ' ' . $r['paterno'] . ' ' . $r['materno']);
            $alumnos[] = $r;
        }
        mysqli_stmt_close($stmt);

        response(200, true, 'Calificaciones obtenidas.', ['alumnos' => $alumnos]);
        break;

    // ---------- GUARDAR: upsert de una nota (alumno + materia) ----------
    case 'guardar':
        $cuentaId  = (int) ($_POST['cuenta_id']  ?? 0);
        $materiaId = (int) ($_POST['materia_id'] ?? 0);
        if ($cuentaId <= 0)  response(400, false, 'Alumno no válido.');
        if ($materiaId <= 0 || !materiaEnGrupo($conexion, $materiaId, $grupoId)) {
            response(400, false, 'Materia no válida para tu grupo.');
        }

        // El alumno debe pertenecer al grupo del maestro
        $chk = mysqli_prepare($conexion, 'SELECT 1 FROM cuenta WHERE id_cuenta = ? AND grupo_id = ? AND permiso_id = 3 LIMIT 1');
        mysqli_stmt_bind_param($chk, 'ii', $cuentaId, $grupoId);
        mysqli_stmt_execute($chk);
        if (!mysqli_fetch_row(mysqli_stmt_get_result($chk))) {
            response(403, false, 'Ese alumno no pertenece a tu grupo.');
        }
        mysqli_stmt_close($chk);

        $t1 = nota($_POST['t1'] ?? '');
        $t2 = nota($_POST['t2'] ?? '');
        $t3 = nota($_POST['t3'] ?? '');
        $t4 = nota($_POST['t4'] ?? '');
        $ex = nota($_POST['examen'] ?? '');
        $rep = trim($_POST['reporte'] ?? '');
        $rep = ($rep === '') ? null : $rep;

        $sql = 'INSERT INTO calificacion (cuenta_id, materia_id, t1, t2, t3, t4, examen, reporte)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?)
                ON DUPLICATE KEY UPDATE
                    t1 = VALUES(t1), t2 = VALUES(t2), t3 = VALUES(t3), t4 = VALUES(t4),
                    examen = VALUES(examen), reporte = VALUES(reporte)';
        $stmt = mysqli_prepare($conexion, $sql);
        mysqli_stmt_bind_param($stmt, 'iiddddds', $cuentaId, $materiaId, $t1, $t2, $t3, $t4, $ex, $rep);
        if (!mysqli_stmt_execute($stmt)) {
            response(500, false, 'No se pudo guardar la calificación.');
        }
        mysqli_stmt_close($stmt);

        response(200, true, 'Calificación guardada.');
        break;

    default:
        response(400, false, "Acción de calificaciones no válida: '$action'.");
}
