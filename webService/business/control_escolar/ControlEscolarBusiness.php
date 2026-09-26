<?php
require_once dirname(__DIR__, 3) . '/apiService/core/crypto.php';

/**
 * CRUD de Alumnos, Maestros y Grupos/Materias. La matrícula se guarda ENCRIPTADA.
 * Aquí vive TODO el SQL (incluida la sincronización grupo_materia m-a-m).
 */
class ControlEscolarBusiness
{
    private mysqli $db;

    public function __construct(mysqli $db)
    {
        $this->db = $db;
    }

    /* ---------------- Personas (alumnos=3, maestros=2) ---------------- */

    public function matriculaExiste(string $matricula): bool
    {
        $enc = encrypt($matricula);
        $chk = mysqli_prepare($this->db, 'SELECT 1 FROM cuenta WHERE matricula = ? LIMIT 1');
        mysqli_stmt_bind_param($chk, 's', $enc);
        mysqli_stmt_execute($chk);
        $hay = (bool) mysqli_fetch_row(mysqli_stmt_get_result($chk));
        mysqli_stmt_close($chk);
        return $hay;
    }

    /** Crea usuario + cuenta (nace activa). Rollback del usuario si falla la cuenta. */
    public function crearPersona(string $nombre, string $paterno, string $materno, int $grupo, string $matricula, int $permiso): bool
    {
        $matEnc = encrypt($matricula);

        $u = mysqli_prepare($this->db, 'INSERT INTO usuario (nombre, paterno, materno) VALUES (?, ?, ?)');
        mysqli_stmt_bind_param($u, 'sss', $nombre, $paterno, $materno);
        if (!mysqli_stmt_execute($u)) { mysqli_stmt_close($u); return false; }
        $usuarioId = mysqli_insert_id($this->db);
        mysqli_stmt_close($u);

        $stActivo = statusId($this->db, 'alumno', 'activo');
        $c = mysqli_prepare($this->db, 'INSERT INTO cuenta (matricula, usuario_id, grupo_id, permiso_id, status_id) VALUES (?, ?, ?, ?, ?)');
        mysqli_stmt_bind_param($c, 'siiii', $matEnc, $usuarioId, $grupo, $permiso, $stActivo);
        $ok = mysqli_stmt_execute($c);
        mysqli_stmt_close($c);
        if (!$ok) {
            mysqli_query($this->db, 'DELETE FROM usuario WHERE id_usuario = ' . (int) $usuarioId);
            return false;
        }
        return true;
    }

    public function obtenerUsuarioId(int $idCuenta): ?int
    {
        $q = mysqli_prepare($this->db, 'SELECT usuario_id FROM cuenta WHERE id_cuenta = ? LIMIT 1');
        mysqli_stmt_bind_param($q, 'i', $idCuenta);
        mysqli_stmt_execute($q);
        $row = mysqli_fetch_assoc(mysqli_stmt_get_result($q));
        mysqli_stmt_close($q);
        return $row ? (int) $row['usuario_id'] : null;
    }

    /** Actualiza usuario y cuenta (matrícula siempre; grupo solo si grado > 0). */
    public function editarPersona(int $id, int $usuarioId, string $nombre, string $paterno, string $materno, int $grado, string $matricula): bool
    {
        $u = mysqli_prepare($this->db, 'UPDATE usuario SET nombre = ?, paterno = ?, materno = ? WHERE id_usuario = ?');
        mysqli_stmt_bind_param($u, 'sssi', $nombre, $paterno, $materno, $usuarioId);
        mysqli_stmt_execute($u);
        mysqli_stmt_close($u);

        $matEnc = encrypt($matricula);
        if ($grado > 0) {
            $c = mysqli_prepare($this->db, 'UPDATE cuenta SET matricula = ?, grupo_id = ? WHERE id_cuenta = ?');
            mysqli_stmt_bind_param($c, 'sii', $matEnc, $grado, $id);
        } else {
            $c = mysqli_prepare($this->db, 'UPDATE cuenta SET matricula = ? WHERE id_cuenta = ?');
            mysqli_stmt_bind_param($c, 'si', $matEnc, $id);
        }
        $ok = mysqli_stmt_execute($c);
        mysqli_stmt_close($c);
        return $ok;
    }

    /** Borra cuenta + datos relacionados (recibos, colegiaturas, calificaciones) y el usuario. */
    public function eliminarCuentaEnCascada(int $idCuenta, int $usuarioId): bool
    {
        try {
            mysqli_begin_transaction($this->db);
            foreach ([
                'DELETE FROM recibo       WHERE cuenta_id = ?',
                'DELETE FROM colegiatura  WHERE cuenta_id = ?',
                'DELETE FROM calificacion WHERE cuenta_id = ?',
                'UPDATE grupo SET maestra_id = NULL WHERE maestra_id = ?',
                'DELETE FROM cuenta       WHERE id_cuenta = ?',
            ] as $sql) {
                $st = mysqli_prepare($this->db, $sql);
                mysqli_stmt_bind_param($st, 'i', $idCuenta);
                mysqli_stmt_execute($st);
                mysqli_stmt_close($st);
            }
            $st = mysqli_prepare($this->db, 'DELETE FROM usuario WHERE id_usuario = ?');
            mysqli_stmt_bind_param($st, 'i', $usuarioId);
            mysqli_stmt_execute($st);
            mysqli_stmt_close($st);
            mysqli_commit($this->db);
            return true;
        } catch (\Throwable $e) {
            mysqli_rollback($this->db);
            return false;
        }
    }

    /** Cuentas por permiso, con matrícula desencriptada. */
    public function listarPersonas(int $permiso): array
    {
        $sql = 'SELECT c.id_cuenta, c.matricula, c.grupo_id, g.grado,
                       u.id_usuario, u.nombre, u.paterno, u.materno
                FROM cuenta c
                JOIN usuario u ON u.id_usuario = c.usuario_id
                LEFT JOIN grupo g ON g.id_grupo = c.grupo_id
                WHERE c.permiso_id = ?
                ORDER BY u.paterno, u.nombre';
        $stmt = mysqli_prepare($this->db, $sql);
        mysqli_stmt_bind_param($stmt, 'i', $permiso);
        mysqli_stmt_execute($stmt);
        $res = mysqli_stmt_get_result($stmt);
        $items = [];
        while ($row = mysqli_fetch_assoc($res)) {
            $row['matricula'] = decrypt($row['matricula']);
            $items[] = $row;
        }
        mysqli_stmt_close($stmt);
        return $items;
    }

    /* ---------------- Grupos / Materias ---------------- */

    public function gruposListar(int $cicloFiltro): array
    {
        $where = ' WHERE g.id_grupo <> 0' . ($cicloFiltro > 0 ? ' AND g.ciclo_id = ' . $cicloFiltro : '');
        $sql = 'SELECT g.id_grupo, g.grado, g.maestra_id, g.ciclo_id, g.nivel, ci.nombre AS ciclo_nombre,
                       (SELECT COUNT(*) FROM grupo_materia gm WHERE gm.id_grupo = g.id_grupo) AS num_materias,
                       (SELECT COUNT(*) FROM cuenta cu WHERE cu.grupo_id = g.id_grupo AND cu.permiso_id = 3) AS num_alumnos
                FROM grupo g
                LEFT JOIN ciclo ci ON ci.id_ciclo = g.ciclo_id'
                . $where . '
                ORDER BY g.nivel, g.grado';
        $res = mysqli_query($this->db, $sql);
        $grupos = [];
        while ($row = mysqli_fetch_assoc($res)) $grupos[] = $row;
        return $grupos;
    }

    public function grupoBasico(int $gid): ?array
    {
        $gs = mysqli_prepare($this->db, 'SELECT id_grupo, grado, maestra_id, ciclo_id, nivel FROM grupo WHERE id_grupo = ? LIMIT 1');
        mysqli_stmt_bind_param($gs, 'i', $gid);
        mysqli_stmt_execute($gs);
        $grupo = mysqli_fetch_assoc(mysqli_stmt_get_result($gs));
        mysqli_stmt_close($gs);
        return $grupo ?: null;
    }

    public function ciclosParaSelect(): array
    {
        $ciclos = [];
        $resC = mysqli_query($this->db,
            'SELECT c.id_ciclo, c.nombre, (s.clave = "activo") AS activo
             FROM ciclo c LEFT JOIN status s ON s.id_status = c.status_id ORDER BY c.fecha_inicio DESC');
        while ($r = mysqli_fetch_assoc($resC)) $ciclos[] = $r;
        return $ciclos;
    }

    public function materiasDeGrupo(int $gid): array
    {
        $ms = mysqli_prepare($this->db,
            'SELECT m.id_materia, m.nombre FROM grupo_materia gm
             JOIN materia m ON m.id_materia = gm.id_materia
             WHERE gm.id_grupo = ? ORDER BY m.nombre');
        mysqli_stmt_bind_param($ms, 'i', $gid);
        mysqli_stmt_execute($ms);
        $res = mysqli_stmt_get_result($ms);
        $materias = [];
        while ($r = mysqli_fetch_assoc($res)) $materias[] = $r;
        mysqli_stmt_close($ms);
        return $materias;
    }

    public function catalogoMaterias(): array
    {
        $catalogo = [];
        $res = mysqli_query($this->db, 'SELECT id_materia, nombre FROM materia ORDER BY nombre');
        while ($r = mysqli_fetch_assoc($res)) $catalogo[] = $r;
        return $catalogo;
    }

    public function maestrosParaSelect(): array
    {
        $res = mysqli_query($this->db,
            'SELECT c.id_cuenta, u.nombre, u.paterno
             FROM cuenta c JOIN usuario u ON u.id_usuario = c.usuario_id
             WHERE c.permiso_id = 2 ORDER BY u.paterno, u.nombre');
        $maestros = [];
        while ($r = mysqli_fetch_assoc($res)) {
            $maestros[] = ['id_cuenta' => $r['id_cuenta'], 'nombre' => trim($r['nombre'] . ' ' . $r['paterno'])];
        }
        return $maestros;
    }

    /** ¿Ya existe ese grado en ese ciclo? (el mismo grado puede repetirse en otro ciclo) */
    public function gradoExisteEnCiclo(string $grado, ?int $cicloId): bool
    {
        if ($cicloId) {
            $chk = mysqli_prepare($this->db, 'SELECT 1 FROM grupo WHERE grado = ? AND ciclo_id = ? LIMIT 1');
            mysqli_stmt_bind_param($chk, 'si', $grado, $cicloId);
        } else {
            $chk = mysqli_prepare($this->db, 'SELECT 1 FROM grupo WHERE grado = ? AND ciclo_id IS NULL LIMIT 1');
            mysqli_stmt_bind_param($chk, 's', $grado);
        }
        mysqli_stmt_execute($chk);
        $hay = (bool) mysqli_fetch_row(mysqli_stmt_get_result($chk));
        mysqli_stmt_close($chk);
        return $hay;
    }

    /** Crea el grupo; regresa su id (0 = falló). */
    public function grupoCrear(string $grado, ?int $cicloId, ?int $nivel): int
    {
        $g = mysqli_prepare($this->db, 'INSERT INTO grupo (grado, ciclo_id, nivel) VALUES (?, ?, ?)');
        mysqli_stmt_bind_param($g, 'sii', $grado, $cicloId, $nivel);
        $ok = mysqli_stmt_execute($g);
        $id = $ok ? mysqli_insert_id($this->db) : 0;
        mysqli_stmt_close($g);
        return $id;
    }

    /** Deja el grupo vinculado EXACTAMENTE a los ids de materia dados. */
    public function sincronizarMateriasGrupo(int $grupoId, array $ids): void
    {
        $ids = array_values(array_unique(array_filter(array_map('intval', $ids), fn($x) => $x > 0)));
        if ($ids) {
            $in = implode(',', $ids);
            mysqli_query($this->db, "DELETE FROM grupo_materia WHERE id_grupo = $grupoId AND id_materia NOT IN ($in)");
        } else {
            mysqli_query($this->db, "DELETE FROM grupo_materia WHERE id_grupo = $grupoId");
        }
        $ins = mysqli_prepare($this->db, 'INSERT IGNORE INTO grupo_materia (id_grupo, id_materia) VALUES (?, ?)');
        foreach ($ids as $mid) {
            mysqli_stmt_bind_param($ins, 'ii', $grupoId, $mid);
            mysqli_stmt_execute($ins);
        }
        mysqli_stmt_close($ins);
    }

    /** id de materia por nombre; la crea si no existe (catálogo compartido). */
    public function materiaIdPorNombre(string $nombre): int
    {
        $nombre = trim($nombre);
        if ($nombre === '') return 0;
        $q = mysqli_prepare($this->db, 'SELECT id_materia FROM materia WHERE nombre = ? LIMIT 1');
        mysqli_stmt_bind_param($q, 's', $nombre);
        mysqli_stmt_execute($q);
        $row = mysqli_fetch_row(mysqli_stmt_get_result($q));
        mysqli_stmt_close($q);
        if ($row) return (int) $row[0];
        $ins = mysqli_prepare($this->db, 'INSERT INTO materia (nombre) VALUES (?)');
        mysqli_stmt_bind_param($ins, 's', $nombre);
        mysqli_stmt_execute($ins);
        $id = mysqli_insert_id($this->db);
        mysqli_stmt_close($ins);
        return $id;
    }

    /** Combina ids seleccionados + nombres nuevos (creándolos) en un set de ids. */
    public function resolverMaterias(array $ids, array $nombresNuevos): array
    {
        $out = array_map('intval', $ids);
        foreach ($nombresNuevos as $nom) {
            $nid = $this->materiaIdPorNombre((string) $nom);
            if ($nid > 0) $out[] = $nid;
        }
        return array_values(array_unique(array_filter($out, fn($x) => $x > 0)));
    }

    public function grupoSetGrado(int $grupoId, string $grado): void
    {
        $s = mysqli_prepare($this->db, 'UPDATE grupo SET grado = ? WHERE id_grupo = ?');
        mysqli_stmt_bind_param($s, 'si', $grado, $grupoId);
        mysqli_stmt_execute($s);
        mysqli_stmt_close($s);
    }

    public function grupoSetMaestra(int $grupoId, int $maestraId): void
    {
        $s = mysqli_prepare($this->db, 'UPDATE grupo SET maestra_id = ? WHERE id_grupo = ?');
        mysqli_stmt_bind_param($s, 'ii', $maestraId, $grupoId);
        mysqli_stmt_execute($s);
        mysqli_stmt_close($s);
    }

    public function grupoSetNivel(int $grupoId, int $nivel): void
    {
        $s = mysqli_prepare($this->db, 'UPDATE grupo SET nivel = ? WHERE id_grupo = ?');
        mysqli_stmt_bind_param($s, 'ii', $nivel, $grupoId);
        mysqli_stmt_execute($s);
        mysqli_stmt_close($s);
    }

    public function grupoSetCiclo(int $grupoId, int $cicloId): void
    {
        $s = mysqli_prepare($this->db, 'UPDATE grupo SET ciclo_id = ? WHERE id_grupo = ?');
        mysqli_stmt_bind_param($s, 'ii', $cicloId, $grupoId);
        mysqli_stmt_execute($s);
        mysqli_stmt_close($s);
    }

    public function contarAlumnosDeGrupo(int $gid): int
    {
        $cnt = mysqli_prepare($this->db, 'SELECT COUNT(*) FROM cuenta WHERE grupo_id = ? AND permiso_id = 3');
        mysqli_stmt_bind_param($cnt, 'i', $gid);
        mysqli_stmt_execute($cnt);
        $n = (int) (mysqli_fetch_row(mysqli_stmt_get_result($cnt))[0] ?? 0);
        mysqli_stmt_close($cnt);
        return $n;
    }

    /** Manda alumnos y maestros del grupo a "Sin grupo" (comodín, grupo_id = 0). */
    public function moverAlumnosASinGrupo(int $gid): void
    {
        $up = mysqli_prepare($this->db, 'UPDATE cuenta SET grupo_id = 0 WHERE grupo_id = ?');
        mysqli_stmt_bind_param($up, 'i', $gid);
        mysqli_stmt_execute($up);
        mysqli_stmt_close($up);
    }

    /** @return int filas afectadas (0 = no existía). Los grupo_materia caen por FK. */
    public function grupoEliminar(int $gid): int
    {
        $d2 = mysqli_prepare($this->db, 'DELETE FROM grupo WHERE id_grupo = ?');
        mysqli_stmt_bind_param($d2, 'i', $gid);
        mysqli_stmt_execute($d2);
        $af = mysqli_stmt_affected_rows($d2);
        mysqli_stmt_close($d2);
        return $af;
    }
}
