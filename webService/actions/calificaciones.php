<?php
/**
 * Action: calificaciones  →  /api/calificaciones?action=contexto|listar|guardar
 * Notas por (alumno, materia, CICLO ACTIVO). 3 periodos + final (promedio editable).
 * Solo maestros (permiso 2). Todo acotado al grupo del maestro.
 */
require_once __DIR__ . '/../../apiService/core/conexionBDD.php';
require_once __DIR__ . '/../../apiService/middleware/auth.php';

requireAuth();
if ((int) ($_SESSION['permiso'] ?? 0) !== 2) {
    response(403, false, 'Solo los maestros pueden acceder a calificaciones.');
}

/** Ciclo escolar activo. */
function cicloActivo(mysqli $conexion): ?array {
    $res = mysqli_query($conexion,
        "SELECT id_ciclo, nombre FROM ciclo WHERE status_id = (SELECT id_status FROM status WHERE ambito='ciclo' AND clave='activo') LIMIT 1");
    return $res ? mysqli_fetch_assoc($res) : null;
}

/** Grupo que imparte el maestro logueado. */
function grupoDelMaestro(mysqli $conexion): int {
    $uid = (int) ($_SESSION['id'] ?? 0);
    $stmt = mysqli_prepare($conexion, 'SELECT grupo_id FROM cuenta WHERE usuario_id = ? AND permiso_id = 2 LIMIT 1');
    mysqli_stmt_bind_param($stmt, 'i', $uid);
    mysqli_stmt_execute($stmt);
    $row = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
    mysqli_stmt_close($stmt);
    return (int) ($row['grupo_id'] ?? 0);
}

/** '' → null; si no, float validado 0–10. */
function nota($v): ?float {
    if ($v === '' || $v === null) return null;
    $n = filter_var($v, FILTER_VALIDATE_FLOAT);
    if ($n === false)      response(400, false, 'Las calificaciones deben ser números.');
    if ($n < 0 || $n > 10) response(400, false, 'Las calificaciones deben estar entre 0 y 10.');
    return (float) $n;
}

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
$ciclo   = cicloActivo($conexion);

if ($grupoId <= 0) response(409, false, 'No tienes un grupo asignado. Contacta a la administración.');
if (!$ciclo)       response(409, false, 'No hay un ciclo escolar activo. Pide al administrador que active uno.');
$cicloId = (int) $ciclo['id_ciclo'];

switch ($action) {

    case 'contexto':
        $ms = mysqli_prepare($conexion, 'SELECT id_materia, nombre FROM materia WHERE grupo_id = ? ORDER BY nombre');
        mysqli_stmt_bind_param($ms, 'i', $grupoId);
        mysqli_stmt_execute($ms);
        $res = mysqli_stmt_get_result($ms);
        $materias = [];
        while ($r = mysqli_fetch_assoc($res)) $materias[] = $r;
        mysqli_stmt_close($ms);

        $gs = mysqli_prepare($conexion, 'SELECT grado FROM grupo WHERE id_grupo = ? LIMIT 1');
        mysqli_stmt_bind_param($gs, 'i', $grupoId);
        mysqli_stmt_execute($gs);
        $grado = mysqli_fetch_assoc(mysqli_stmt_get_result($gs))['grado'] ?? '';
        mysqli_stmt_close($gs);

        response(200, true, 'Contexto obtenido.', [
            'grupo_id' => $grupoId, 'grado' => $grado,
            'materias' => $materias, 'ciclo' => $ciclo['nombre'],
        ]);
        break;

    case 'listar':
        $materiaId = (int) ($_GET['materia_id'] ?? 0);
        if ($materiaId <= 0 || !materiaEnGrupo($conexion, $materiaId, $grupoId)) {
            response(400, false, 'Materia no válida para tu grupo.');
        }
        $sql = 'SELECT c.id_cuenta, u.nombre, u.paterno, u.materno,
                       cal.p1, cal.p2, cal.p3, cal.calif_final, cal.reporte
                FROM cuenta c
                JOIN usuario u ON u.id_usuario = c.usuario_id
                LEFT JOIN calificacion cal
                       ON cal.cuenta_id = c.id_cuenta AND cal.materia_id = ? AND cal.ciclo_id = ?
                WHERE c.grupo_id = ? AND c.permiso_id = 3
                ORDER BY u.paterno, u.nombre';
        $stmt = mysqli_prepare($conexion, $sql);
        mysqli_stmt_bind_param($stmt, 'iii', $materiaId, $cicloId, $grupoId);
        mysqli_stmt_execute($stmt);
        $res = mysqli_stmt_get_result($stmt);
        $alumnos = [];
        while ($r = mysqli_fetch_assoc($res)) {
            $r['nombre_completo'] = trim("$r[nombre] $r[paterno] $r[materno]");
            $alumnos[] = $r;
        }
        mysqli_stmt_close($stmt);
        response(200, true, 'Calificaciones obtenidas.', ['alumnos' => $alumnos]);
        break;

    case 'guardar':
        $cuentaId  = (int) ($_POST['cuenta_id']  ?? 0);
        $materiaId = (int) ($_POST['materia_id'] ?? 0);
        if ($cuentaId <= 0) response(400, false, 'Alumno no válido.');
        if ($materiaId <= 0 || !materiaEnGrupo($conexion, $materiaId, $grupoId)) {
            response(400, false, 'Materia no válida para tu grupo.');
        }
        $chk = mysqli_prepare($conexion, 'SELECT 1 FROM cuenta WHERE id_cuenta = ? AND grupo_id = ? AND permiso_id = 3 LIMIT 1');
        mysqli_stmt_bind_param($chk, 'ii', $cuentaId, $grupoId);
        mysqli_stmt_execute($chk);
        if (!mysqli_fetch_row(mysqli_stmt_get_result($chk))) response(403, false, 'Ese alumno no pertenece a tu grupo.');
        mysqli_stmt_close($chk);

        $p1  = nota($_POST['p1'] ?? '');
        $p2  = nota($_POST['p2'] ?? '');
        $p3  = nota($_POST['p3'] ?? '');
        $fin = nota($_POST['final'] ?? '');  // vacío = se calcula el promedio al mostrar
        $rep = trim($_POST['reporte'] ?? '');
        $rep = ($rep === '') ? null : $rep;

        // Estatus de captura (el maestro está capturando) y de aprobación (según el final)
        $stCaptura = statusId($conexion, 'captura', 'capturada');
        $stAprob   = $fin === null ? null : statusId($conexion, 'aprobacion', $fin >= 6 ? 'aprobado' : 'reprobado');

        $sql = 'INSERT INTO calificacion (cuenta_id, materia_id, ciclo_id, p1, p2, p3, calif_final, reporte, status_captura_id, status_aprobacion_id)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                ON DUPLICATE KEY UPDATE
                    p1 = VALUES(p1), p2 = VALUES(p2), p3 = VALUES(p3),
                    calif_final = VALUES(calif_final), reporte = VALUES(reporte),
                    status_captura_id = VALUES(status_captura_id), status_aprobacion_id = VALUES(status_aprobacion_id)';
        $stmt = mysqli_prepare($conexion, $sql);
        mysqli_stmt_bind_param($stmt, 'iiiddddsii', $cuentaId, $materiaId, $cicloId, $p1, $p2, $p3, $fin, $rep, $stCaptura, $stAprob);
        if (!mysqli_stmt_execute($stmt)) response(500, false, 'No se pudo guardar la calificación.');
        mysqli_stmt_close($stmt);
        response(200, true, 'Calificación guardada.');
        break;

    default:
        response(400, false, "Acción de calificaciones no válida: '$action'.");
}
