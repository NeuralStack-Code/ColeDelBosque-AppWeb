<?php
/**
 * Action: control-escolar  →  /api/control-escolar?action=...
 * CRUD de Alumnos, Maestros y Grupos/Materias.
 *
 * Esquema (según diagrama Hostinger):
 *   usuario(id_usuario PK, nombre, paterno, materno)
 *   cuenta(id_cuenta PK, matricula, usuario_id, grupo_id, permiso_id)   permiso: 2=maestro, 3=alumno/padre
 *   grupo(id_grupo PK, grado, maestra_id)
 *   materia(id_materia PK, nombre, calificaciones, grupo_id)
 *
 * La matrícula se guarda ENCRIPTADA (AES-256-CBC, core/crypto.php).
 *
 * NOTAS de migración (el código anterior tenía inconsistencias):
 *   - `grupo` NO tiene columna `nombre`; el nombre del grupo es `grado`.
 *   - La maestra del grupo se guarda en `grupo.maestra_id` (según el diagrama).
 *     El código viejo además la enlazaba por `cuenta.grupo_id`; aquí uso
 *     `grupo.maestra_id` como fuente de verdad. Si tu modelo usa `cuenta.grupo_id`,
 *     avísame y lo cambio.
 *
 * Depende de: $conexion, response(), crypto, y middleware/auth.
 */
require_once __DIR__ . '/../../apiService/core/conexionBDD.php';
require_once __DIR__ . '/../../apiService/core/crypto.php';
require_once __DIR__ . '/../../apiService/middleware/auth.php';

requireAdmin();

$action = $_GET['action'] ?? '';

/* ============================ Helpers ============================ */

/** Valida nombre/apellidos (no vacíos, no numéricos). Corta con response() si falla. */
function validarNombres(string $nombre, string $paterno, string $materno): void {
    foreach (['nombre' => $nombre, 'apellido paterno' => $paterno, 'apellido materno' => $materno] as $campo => $val) {
        if ($val === '')        response(400, false, "El $campo no puede estar vacío.");
        if (is_numeric($val))   response(400, false, "Revisa el $campo.");
    }
}

/** La matrícula debe ser 3 mayúsculas + 6 números (AAA######). */
function matriculaValida(string $m): bool {
    return (bool) preg_match('/^[A-Z]{3}[0-9]{6}$/', $m);
}

/** Alta de persona (alumno=permiso 3, maestro=permiso 2): crea usuario + cuenta. */
function crearPersona(mysqli $conexion, int $permiso): void {
    $nombre  = trim($_POST['nombre']  ?? '');
    $paterno = trim($_POST['paterno'] ?? '');
    $materno = trim($_POST['materno'] ?? '');
    $grupo   = (int) ($_POST['grado'] ?? 0);
    $mat     = trim($_POST['matricula'] ?? '');

    if ($grupo <= 0)              response(400, false, 'Selecciona un grupo válido.');
    validarNombres($nombre, $paterno, $materno);
    if (!matriculaValida($mat))   response(400, false, 'La matrícula debe tener 3 mayúsculas y 6 números (AAA######).');

    $matEnc = encrypt($mat);

    // ¿Matrícula duplicada?
    $chk = mysqli_prepare($conexion, 'SELECT 1 FROM cuenta WHERE matricula = ? LIMIT 1');
    mysqli_stmt_bind_param($chk, 's', $matEnc);
    mysqli_stmt_execute($chk);
    if (mysqli_fetch_row(mysqli_stmt_get_result($chk))) {
        response(409, false, 'La matrícula ya existe.');
    }
    mysqli_stmt_close($chk);

    // usuario
    $u = mysqli_prepare($conexion, 'INSERT INTO usuario (nombre, paterno, materno) VALUES (?, ?, ?)');
    mysqli_stmt_bind_param($u, 'sss', $nombre, $paterno, $materno);
    if (!mysqli_stmt_execute($u)) response(500, false, 'No se pudo crear el usuario.');
    $usuarioId = mysqli_insert_id($conexion);
    mysqli_stmt_close($u);

    // cuenta (nace activa)
    $stActivo = statusId($conexion, 'alumno', 'activo');
    $c = mysqli_prepare($conexion, 'INSERT INTO cuenta (matricula, usuario_id, grupo_id, permiso_id, status_id) VALUES (?, ?, ?, ?, ?)');
    mysqli_stmt_bind_param($c, 'siiii', $matEnc, $usuarioId, $grupo, $permiso, $stActivo);
    if (!mysqli_stmt_execute($c)) {
        // rollback del usuario si falla la cuenta
        mysqli_query($conexion, 'DELETE FROM usuario WHERE id_usuario = ' . (int) $usuarioId);
        response(500, false, 'No se pudo crear la cuenta.');
    }
    mysqli_stmt_close($c);

    $etiqueta = $permiso === 2 ? 'maestro' : 'alumno';
    response(201, true, "¡Se ha registrado un nuevo $etiqueta!");
}

/** Edición de persona: actualiza usuario y cuenta (matrícula, y grupo si grado != 0). */
function editarPersona(mysqli $conexion): void {
    $id      = (int) ($_POST['id_cuenta'] ?? 0);
    $nombre  = trim($_POST['nombre']  ?? '');
    $paterno = trim($_POST['paterno'] ?? '');
    $materno = trim($_POST['materno'] ?? '');
    $grado   = (int) ($_POST['grado'] ?? 0);   // 0 = no cambiar grupo
    $mat     = trim($_POST['matricula'] ?? '');

    if ($id <= 0)                 response(400, false, 'Registro no válido.');
    validarNombres($nombre, $paterno, $materno);
    if (!matriculaValida($mat))   response(400, false, 'La matrícula debe tener 3 mayúsculas y 6 números (AAA######).');

    // usuario_id de la cuenta
    $q = mysqli_prepare($conexion, 'SELECT usuario_id FROM cuenta WHERE id_cuenta = ? LIMIT 1');
    mysqli_stmt_bind_param($q, 'i', $id);
    mysqli_stmt_execute($q);
    $row = mysqli_fetch_assoc(mysqli_stmt_get_result($q));
    mysqli_stmt_close($q);
    if (!$row) response(404, false, 'No se encontró la cuenta.');
    $usuarioId = (int) $row['usuario_id'];

    // usuario
    $u = mysqli_prepare($conexion, 'UPDATE usuario SET nombre = ?, paterno = ?, materno = ? WHERE id_usuario = ?');
    mysqli_stmt_bind_param($u, 'sssi', $nombre, $paterno, $materno, $usuarioId);
    mysqli_stmt_execute($u);
    mysqli_stmt_close($u);

    // cuenta (matrícula siempre; grupo solo si grado != 0)
    $matEnc = encrypt($mat);
    if ($grado > 0) {
        $c = mysqli_prepare($conexion, 'UPDATE cuenta SET matricula = ?, grupo_id = ? WHERE id_cuenta = ?');
        mysqli_stmt_bind_param($c, 'sii', $matEnc, $grado, $id);
    } else {
        $c = mysqli_prepare($conexion, 'UPDATE cuenta SET matricula = ? WHERE id_cuenta = ?');
        mysqli_stmt_bind_param($c, 'si', $matEnc, $id);
    }
    if (!mysqli_stmt_execute($c)) response(500, false, 'No se pudo actualizar la cuenta.');
    mysqli_stmt_close($c);

    response(200, true, 'Se ha actualizado la información.');
}

/** Baja de persona: elimina cuenta y su usuario. */
function eliminarPersona(mysqli $conexion): void {
    $id = (int) ($_POST['id_cuenta'] ?? 0);
    if ($id <= 0) response(400, false, 'Registro no válido.');

    $q = mysqli_prepare($conexion, 'SELECT usuario_id FROM cuenta WHERE id_cuenta = ? LIMIT 1');
    mysqli_stmt_bind_param($q, 'i', $id);
    mysqli_stmt_execute($q);
    $row = mysqli_fetch_assoc(mysqli_stmt_get_result($q));
    mysqli_stmt_close($q);
    if (!$row) response(404, false, 'No se encontró el registro.');
    $usuarioId = (int) $row['usuario_id'];

    $d1 = mysqli_prepare($conexion, 'DELETE FROM cuenta WHERE id_cuenta = ?');
    mysqli_stmt_bind_param($d1, 'i', $id);
    mysqli_stmt_execute($d1);
    mysqli_stmt_close($d1);

    $d2 = mysqli_prepare($conexion, 'DELETE FROM usuario WHERE id_usuario = ?');
    mysqli_stmt_bind_param($d2, 'i', $usuarioId);
    mysqli_stmt_execute($d2);
    mysqli_stmt_close($d2);

    response(200, true, 'Se ha eliminado el registro.');
}

/** Lista cuentas por permiso (2=maestros, 3=alumnos) con matrícula desencriptada. */
function listarPersonas(mysqli $conexion, int $permiso): void {
    $sql = 'SELECT c.id_cuenta, c.matricula, c.grupo_id, g.grado,
                   u.id_usuario, u.nombre, u.paterno, u.materno
            FROM cuenta c
            JOIN usuario u ON u.id_usuario = c.usuario_id
            LEFT JOIN grupo g ON g.id_grupo = c.grupo_id
            WHERE c.permiso_id = ?
            ORDER BY u.paterno, u.nombre';
    $stmt = mysqli_prepare($conexion, $sql);
    mysqli_stmt_bind_param($stmt, 'i', $permiso);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);

    $items = [];
    while ($row = mysqli_fetch_assoc($res)) {
        $row['matricula'] = decrypt($row['matricula']); // mostrar en claro
        $items[] = $row;
    }
    mysqli_stmt_close($stmt);
    response(200, true, 'Listado obtenido.', ['items' => $items]);
}

/* ============================ Router de acciones ============================ */

switch ($action) {

    /* ---------- ALUMNOS (permiso 3) ---------- */
    case 'alumnos_listar':  listarPersonas($conexion, 3); break;
    case 'alumno_crear':    crearPersona($conexion, 3);   break;
    case 'alumno_editar':   editarPersona($conexion);     break;
    case 'alumno_eliminar': eliminarPersona($conexion);   break;

    /* ---------- MAESTROS (permiso 2) ---------- */
    case 'maestros_listar':  listarPersonas($conexion, 2); break;
    case 'maestro_crear':    crearPersona($conexion, 2);   break;
    case 'maestro_editar':   editarPersona($conexion);     break;
    case 'maestro_eliminar': eliminarPersona($conexion);   break;

    /* ---------- GRUPOS ---------- */
    case 'grupos_listar':
        $cicloFiltro = (int) ($_GET['ciclo_id'] ?? 0);
        // id_grupo = 0 es un bucket fantasma "Sin grupo": nunca debe ofrecerse como grupo real.
        $where = ' WHERE g.id_grupo <> 0' . ($cicloFiltro > 0 ? ' AND g.ciclo_id = ' . $cicloFiltro : '');
        $sql = 'SELECT g.id_grupo, g.grado, g.maestra_id, g.ciclo_id, g.nivel, ci.nombre AS ciclo_nombre,
                       (SELECT COUNT(*) FROM materia m WHERE m.grupo_id = g.id_grupo) AS num_materias,
                       (SELECT COUNT(*) FROM cuenta cu WHERE cu.grupo_id = g.id_grupo AND cu.permiso_id = 3) AS num_alumnos
                FROM grupo g
                LEFT JOIN ciclo ci ON ci.id_ciclo = g.ciclo_id'
                . $where . '
                ORDER BY g.nivel, g.grado';
        $res = mysqli_query($conexion, $sql);
        $grupos = [];
        while ($row = mysqli_fetch_assoc($res)) $grupos[] = $row;
        response(200, true, 'Grupos obtenidos.', ['items' => $grupos]);
        break;

    case 'grupo_detalle':
        $gid = (int) ($_GET['id_grupo'] ?? 0);
        if ($gid <= 0) response(400, false, 'Grupo no válido.');

        // Datos del grupo
        $gs = mysqli_prepare($conexion, 'SELECT id_grupo, grado, maestra_id, ciclo_id, nivel FROM grupo WHERE id_grupo = ? LIMIT 1');
        mysqli_stmt_bind_param($gs, 'i', $gid);
        mysqli_stmt_execute($gs);
        $grupo = mysqli_fetch_assoc(mysqli_stmt_get_result($gs));
        mysqli_stmt_close($gs);
        if (!$grupo) response(404, false, 'No se encontró el grupo.');

        // Ciclos disponibles (para el select)
        $ciclos = [];
        $resC = mysqli_query($conexion,
            'SELECT c.id_ciclo, c.nombre, (s.clave = "activo") AS activo
             FROM ciclo c LEFT JOIN status s ON s.id_status = c.status_id ORDER BY c.fecha_inicio DESC');
        while ($r = mysqli_fetch_assoc($resC)) $ciclos[] = $r;

        // Materias del grupo
        $ms = mysqli_prepare($conexion, 'SELECT id_materia, nombre FROM materia WHERE grupo_id = ? ORDER BY nombre');
        mysqli_stmt_bind_param($ms, 'i', $gid);
        mysqli_stmt_execute($ms);
        $res = mysqli_stmt_get_result($ms);
        $materias = [];
        while ($r = mysqli_fetch_assoc($res)) $materias[] = $r;
        mysqli_stmt_close($ms);

        // Maestros disponibles (para asignar maestra) — maestra_id = cuenta.id_cuenta
        $res = mysqli_query($conexion,
            'SELECT c.id_cuenta, u.nombre, u.paterno
             FROM cuenta c JOIN usuario u ON u.id_usuario = c.usuario_id
             WHERE c.permiso_id = 2 ORDER BY u.paterno, u.nombre');
        $maestros = [];
        while ($r = mysqli_fetch_assoc($res)) {
            $maestros[] = ['id_cuenta' => $r['id_cuenta'], 'nombre' => trim($r['nombre'] . ' ' . $r['paterno'])];
        }

        response(200, true, 'Detalle del grupo.', [
            'grupo'    => $grupo,
            'materias' => $materias,
            'maestros' => $maestros,
            'ciclos'   => $ciclos,
        ]);
        break;

    case 'grupo_crear':
        $grado    = trim($_POST['grado'] ?? '');
        $cicloId  = (int) ($_POST['ciclo_id'] ?? 0) ?: null;
        $nivel    = ($_POST['nivel'] ?? '') === '' ? null : (int) $_POST['nivel'];
        $materias = json_decode($_POST['materias'] ?? '[]', true) ?: [];
        if ($grado === '')     response(400, false, 'Indica el grado del grupo.');
        if (empty($materias))  response(400, false, 'Agrega al menos una materia.');

        // ¿grado ya existe en ese ciclo? (el mismo grado puede repetirse en otro ciclo)
        if ($cicloId) {
            $chk = mysqli_prepare($conexion, 'SELECT 1 FROM grupo WHERE grado = ? AND ciclo_id = ? LIMIT 1');
            mysqli_stmt_bind_param($chk, 'si', $grado, $cicloId);
        } else {
            $chk = mysqli_prepare($conexion, 'SELECT 1 FROM grupo WHERE grado = ? AND ciclo_id IS NULL LIMIT 1');
            mysqli_stmt_bind_param($chk, 's', $grado);
        }
        mysqli_stmt_execute($chk);
        if (mysqli_fetch_row(mysqli_stmt_get_result($chk))) response(409, false, 'Ya existe ese grupo en el ciclo seleccionado.');
        mysqli_stmt_close($chk);

        $g = mysqli_prepare($conexion, 'INSERT INTO grupo (grado, ciclo_id, nivel) VALUES (?, ?, ?)');
        mysqli_stmt_bind_param($g, 'sii', $grado, $cicloId, $nivel);
        if (!mysqli_stmt_execute($g)) response(500, false, 'No se pudo crear el grupo.');
        $grupoId = mysqli_insert_id($conexion);
        mysqli_stmt_close($g);

        $ins = mysqli_prepare($conexion, 'INSERT INTO materia (nombre, grupo_id) VALUES (?, ?)');
        foreach ($materias as $nombreMat) {
            $nombreMat = trim((string) $nombreMat);
            if ($nombreMat === '') continue;
            mysqli_stmt_bind_param($ins, 'si', $nombreMat, $grupoId);
            if (!mysqli_stmt_execute($ins)) {
                mysqli_query($conexion, 'DELETE FROM materia WHERE grupo_id = ' . (int) $grupoId);
                mysqli_query($conexion, 'DELETE FROM grupo WHERE id_grupo = ' . (int) $grupoId);
                response(500, false, 'No se pudieron guardar las materias.');
            }
        }
        mysqli_stmt_close($ins);
        response(201, true, '¡Se ha creado el grupo y sus materias!');
        break;

    case 'grupo_editar':
        $grupoId          = (int) ($_POST['id_grupo'] ?? 0);
        $nuevoGrado       = trim($_POST['grado'] ?? '');            // '' = no renombrar
        $maestraId        = $_POST['maestra_id'] ?? null;          // null = no cambiar
        $materiasAdd      = json_decode($_POST['materias'] ?? '[]', true) ?: [];
        $materiasEliminar = json_decode($_POST['materiasEliminar'] ?? '[]', true) ?: [];
        if ($grupoId <= 0) response(400, false, 'Grupo no válido.');
        $cambios = 0;

        if ($nuevoGrado !== '') {
            $s = mysqli_prepare($conexion, 'UPDATE grupo SET grado = ? WHERE id_grupo = ?');
            mysqli_stmt_bind_param($s, 'si', $nuevoGrado, $grupoId);
            mysqli_stmt_execute($s); mysqli_stmt_close($s); $cambios++;
        }
        if ($maestraId !== null && $maestraId !== '') {
            $mid = (int) $maestraId;
            $s = mysqli_prepare($conexion, 'UPDATE grupo SET maestra_id = ? WHERE id_grupo = ?');
            mysqli_stmt_bind_param($s, 'ii', $mid, $grupoId);
            mysqli_stmt_execute($s); mysqli_stmt_close($s); $cambios++;
        }
        if (isset($_POST['nivel']) && $_POST['nivel'] !== '') {
            $niv = (int) $_POST['nivel'];
            $s = mysqli_prepare($conexion, 'UPDATE grupo SET nivel = ? WHERE id_grupo = ?');
            mysqli_stmt_bind_param($s, 'ii', $niv, $grupoId);
            mysqli_stmt_execute($s); mysqli_stmt_close($s); $cambios++;
        }
        if (isset($_POST['ciclo_id']) && $_POST['ciclo_id'] !== '') {
            $cid = (int) $_POST['ciclo_id'];
            $s = mysqli_prepare($conexion, 'UPDATE grupo SET ciclo_id = ? WHERE id_grupo = ?');
            mysqli_stmt_bind_param($s, 'ii', $cid, $grupoId);
            mysqli_stmt_execute($s); mysqli_stmt_close($s); $cambios++;
        }
        if (!empty($materiasAdd)) {
            $ins = mysqli_prepare($conexion, 'INSERT INTO materia (nombre, grupo_id) VALUES (?, ?)');
            foreach ($materiasAdd as $nombreMat) {
                $nombreMat = trim((string) $nombreMat);
                if ($nombreMat === '') continue;
                mysqli_stmt_bind_param($ins, 'si', $nombreMat, $grupoId);
                mysqli_stmt_execute($ins); $cambios++;
            }
            mysqli_stmt_close($ins);
        }
        if (!empty($materiasEliminar)) {
            $del = mysqli_prepare($conexion, 'DELETE FROM materia WHERE id_materia = ?');
            foreach ($materiasEliminar as $m) {
                $idMat = (int) (is_array($m) ? ($m['id'] ?? 0) : $m);
                if ($idMat <= 0) continue;
                mysqli_stmt_bind_param($del, 'i', $idMat);
                mysqli_stmt_execute($del); $cambios++;
            }
            mysqli_stmt_close($del);
        }

        if ($cambios === 0) response(400, false, 'No hay cambios que aplicar.');
        response(200, true, 'Se han aplicado los cambios al grupo.');
        break;

    case 'grupo_eliminar':
        $grupoId = (int) ($_POST['id_grupo'] ?? 0);
        if ($grupoId <= 0) response(400, false, 'Grupo no válido.');

        // "Sin grupo" (grupo_id = 0) es un comodín de migración: al disolver el grupo,
        // sus alumnos quedan ahí (marcados en rojo) hasta reasignarlos a otro grupo.
        $cnt = mysqli_prepare($conexion, 'SELECT COUNT(*) FROM cuenta WHERE grupo_id = ? AND permiso_id = 3');
        mysqli_stmt_bind_param($cnt, 'i', $grupoId);
        mysqli_stmt_execute($cnt);
        $nAlumnos = (int) (mysqli_fetch_row(mysqli_stmt_get_result($cnt))[0] ?? 0);
        mysqli_stmt_close($cnt);

        $d1 = mysqli_prepare($conexion, 'DELETE FROM materia WHERE grupo_id = ?');
        mysqli_stmt_bind_param($d1, 'i', $grupoId);
        mysqli_stmt_execute($d1);
        mysqli_stmt_close($d1);

        // Mandar alumnos y maestros del grupo a "Sin grupo" (comodín)
        $up = mysqli_prepare($conexion, 'UPDATE cuenta SET grupo_id = 0 WHERE grupo_id = ?');
        mysqli_stmt_bind_param($up, 'i', $grupoId);
        mysqli_stmt_execute($up);
        mysqli_stmt_close($up);

        $d2 = mysqli_prepare($conexion, 'DELETE FROM grupo WHERE id_grupo = ?');
        mysqli_stmt_bind_param($d2, 'i', $grupoId);
        mysqli_stmt_execute($d2);
        $afectadas = mysqli_stmt_affected_rows($d2);
        mysqli_stmt_close($d2);

        if ($afectadas === 0) response(404, false, 'No se encontró el grupo.');
        $msg = 'Se ha eliminado el grupo y sus materias.';
        if ($nAlumnos > 0) $msg .= " $nAlumnos alumno(s) quedaron en «Sin grupo» — reasígnalos pronto.";
        response(200, true, $msg);
        break;

    default:
        response(400, false, "Acción de control-escolar no válida: '$action'.");
}
