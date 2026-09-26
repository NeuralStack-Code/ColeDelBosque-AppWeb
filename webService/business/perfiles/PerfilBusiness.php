<?php
require_once dirname(__DIR__, 3) . '/apiService/core/crypto.php';

/**
 * Panel del desarrollador: cuentas de cualquier rol. La matrícula se guarda
 * ENCRIPTADA (AES). Aquí vive TODO el SQL.
 */
class PerfilBusiness
{
    private mysqli $db;

    public function __construct(mysqli $db)
    {
        $this->db = $db;
    }

    public function permisos(): array
    {
        $out = [];
        $res = mysqli_query($this->db, 'SELECT id_permiso, nombre FROM permisos ORDER BY id_permiso');
        while ($r = mysqli_fetch_assoc($res)) $out[] = $r;
        return $out;
    }

    public function grupos(): array
    {
        $out = [];
        $res = mysqli_query($this->db,
            'SELECT g.id_grupo, g.grado, ci.nombre AS ciclo FROM grupo g
             LEFT JOIN ciclo ci ON ci.id_ciclo = g.ciclo_id
             WHERE g.id_grupo <> 0 ORDER BY g.nivel, g.grado');
        while ($r = mysqli_fetch_assoc($res)) $out[] = $r;
        return $out;
    }

    public function estatusAlumno(): array
    {
        $out = [];
        $res = mysqli_query($this->db, "SELECT clave, nombre FROM status WHERE ambito = 'alumno' ORDER BY orden");
        while ($r = mysqli_fetch_assoc($res)) $out[] = $r;
        return $out;
    }

    /** @return Cuenta[] */
    public function listar(int $filtro = 0): array
    {
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
        $res = mysqli_query($this->db, $sql);
        $items = [];
        while ($r = mysqli_fetch_assoc($res)) {
            $r['matricula'] = decrypt($r['matricula']);
            $r['nombre_completo'] = trim("$r[nombre] $r[paterno] $r[materno]");
            $items[] = new Cuenta($r);
        }
        return $items;
    }

    public function permisoExiste(int $permiso): bool
    {
        $chk = mysqli_prepare($this->db, 'SELECT 1 FROM permisos WHERE id_permiso = ? LIMIT 1');
        mysqli_stmt_bind_param($chk, 'i', $permiso);
        mysqli_stmt_execute($chk);
        $ok = (bool) mysqli_fetch_row(mysqli_stmt_get_result($chk));
        mysqli_stmt_close($chk);
        return $ok;
    }

    /** ¿La matrícula (en claro) ya existe? Excluye una cuenta al editar. */
    public function matriculaExiste(string $matricula, int $exceptoId = 0): bool
    {
        $enc = encrypt($matricula);
        if ($exceptoId > 0) {
            $chk = mysqli_prepare($this->db, 'SELECT 1 FROM cuenta WHERE matricula = ? AND id_cuenta <> ? LIMIT 1');
            mysqli_stmt_bind_param($chk, 'si', $enc, $exceptoId);
        } else {
            $chk = mysqli_prepare($this->db, 'SELECT 1 FROM cuenta WHERE matricula = ? LIMIT 1');
            mysqli_stmt_bind_param($chk, 's', $enc);
        }
        mysqli_stmt_execute($chk);
        $ok = (bool) mysqli_fetch_row(mysqli_stmt_get_result($chk));
        mysqli_stmt_close($chk);
        return $ok;
    }

    private function statusAlumno(string $clave): ?int
    {
        return statusId($this->db, 'alumno', in_array($clave, ['activo', 'baja', 'egresado']) ? $clave : 'activo');
    }

    /** Crea usuario + cuenta. Rollback del usuario si la cuenta falla. */
    public function crear(string $nombre, string $paterno, string $materno, string $matricula, int $permiso, int $grupo, string $claveEst): bool
    {
        $matEnc   = encrypt($matricula);
        $statusId = $this->statusAlumno($claveEst);

        $u = mysqli_prepare($this->db, 'INSERT INTO usuario (nombre, paterno, materno) VALUES (?, ?, ?)');
        mysqli_stmt_bind_param($u, 'sss', $nombre, $paterno, $materno);
        if (!mysqli_stmt_execute($u)) { mysqli_stmt_close($u); return false; }
        $usuarioId = mysqli_insert_id($this->db);
        mysqli_stmt_close($u);

        $c = mysqli_prepare($this->db, 'INSERT INTO cuenta (matricula, usuario_id, grupo_id, permiso_id, status_id) VALUES (?, ?, ?, ?, ?)');
        mysqli_stmt_bind_param($c, 'siiii', $matEnc, $usuarioId, $grupo, $permiso, $statusId);
        $ok = mysqli_stmt_execute($c);
        mysqli_stmt_close($c);
        if (!$ok) {
            mysqli_query($this->db, 'DELETE FROM usuario WHERE id_usuario = ' . (int) $usuarioId);
            return false;
        }
        return true;
    }

    /** usuario_id de una cuenta (o null si no existe). */
    public function obtenerUsuarioId(int $idCuenta): ?int
    {
        $q = mysqli_prepare($this->db, 'SELECT usuario_id FROM cuenta WHERE id_cuenta = ? LIMIT 1');
        mysqli_stmt_bind_param($q, 'i', $idCuenta);
        mysqli_stmt_execute($q);
        $row = mysqli_fetch_assoc(mysqli_stmt_get_result($q));
        mysqli_stmt_close($q);
        return $row ? (int) $row['usuario_id'] : null;
    }

    public function editar(int $id, int $usuarioId, string $nombre, string $paterno, string $materno, string $matricula, int $permiso, int $grupo, string $claveEst): bool
    {
        $matEnc   = encrypt($matricula);
        $statusId = $this->statusAlumno($claveEst);

        $u = mysqli_prepare($this->db, 'UPDATE usuario SET nombre = ?, paterno = ?, materno = ? WHERE id_usuario = ?');
        mysqli_stmt_bind_param($u, 'sssi', $nombre, $paterno, $materno, $usuarioId);
        mysqli_stmt_execute($u);
        mysqli_stmt_close($u);

        $c = mysqli_prepare($this->db, 'UPDATE cuenta SET matricula = ?, grupo_id = ?, permiso_id = ?, status_id = ? WHERE id_cuenta = ?');
        mysqli_stmt_bind_param($c, 'siiii', $matEnc, $grupo, $permiso, $statusId, $id);
        $ok = mysqli_stmt_execute($c);
        mysqli_stmt_close($c);
        return $ok;
    }

    /** Borra la cuenta y sus datos relacionados en una transacción. */
    public function eliminarCascada(int $idCuenta, int $usuarioId): bool
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
}
