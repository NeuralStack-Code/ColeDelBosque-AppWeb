<?php
/**
 * Notas por (alumno, materia, ciclo activo). Aquí vive el SQL. Todo acotado al
 * grupo del maestro (el controller valida el contexto).
 */
class CalificacionBusiness
{
    private mysqli $db;

    public function __construct(mysqli $db)
    {
        $this->db = $db;
    }

    /** Ciclo escolar activo (o null). */
    public function cicloActivo(): ?array
    {
        $res = mysqli_query($this->db,
            "SELECT id_ciclo, nombre FROM ciclo WHERE status_id = (SELECT id_status FROM status WHERE ambito='ciclo' AND clave='activo') LIMIT 1");
        return $res ? mysqli_fetch_assoc($res) : null;
    }

    /** Grupo que imparte el maestro logueado. */
    public function grupoDelMaestro(int $usuarioId): int
    {
        $stmt = mysqli_prepare($this->db, 'SELECT grupo_id FROM cuenta WHERE usuario_id = ? AND permiso_id = 2 LIMIT 1');
        mysqli_stmt_bind_param($stmt, 'i', $usuarioId);
        mysqli_stmt_execute($stmt);
        $row = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
        mysqli_stmt_close($stmt);
        return (int) ($row['grupo_id'] ?? 0);
    }

    public function materiaEnGrupo(int $materiaId, int $grupoId): bool
    {
        $stmt = mysqli_prepare($this->db, 'SELECT 1 FROM grupo_materia WHERE id_materia = ? AND id_grupo = ? LIMIT 1');
        mysqli_stmt_bind_param($stmt, 'ii', $materiaId, $grupoId);
        mysqli_stmt_execute($stmt);
        $ok = (bool) mysqli_fetch_row(mysqli_stmt_get_result($stmt));
        mysqli_stmt_close($stmt);
        return $ok;
    }

    /** Materias que se imparten en el grupo. */
    public function materiasDeGrupo(int $grupoId): array
    {
        $ms = mysqli_prepare($this->db,
            'SELECT m.id_materia, m.nombre FROM grupo_materia gm
             JOIN materia m ON m.id_materia = gm.id_materia
             WHERE gm.id_grupo = ? ORDER BY m.nombre');
        mysqli_stmt_bind_param($ms, 'i', $grupoId);
        mysqli_stmt_execute($ms);
        $res = mysqli_stmt_get_result($ms);
        $materias = [];
        while ($r = mysqli_fetch_assoc($res)) $materias[] = $r;
        mysqli_stmt_close($ms);
        return $materias;
    }

    public function gradoDeGrupo(int $grupoId): string
    {
        $gs = mysqli_prepare($this->db, 'SELECT grado FROM grupo WHERE id_grupo = ? LIMIT 1');
        mysqli_stmt_bind_param($gs, 'i', $grupoId);
        mysqli_stmt_execute($gs);
        $grado = mysqli_fetch_assoc(mysqli_stmt_get_result($gs))['grado'] ?? '';
        mysqli_stmt_close($gs);
        return $grado;
    }

    public function alumnoEnGrupo(int $cuentaId, int $grupoId): bool
    {
        $chk = mysqli_prepare($this->db, 'SELECT 1 FROM cuenta WHERE id_cuenta = ? AND grupo_id = ? AND permiso_id = 3 LIMIT 1');
        mysqli_stmt_bind_param($chk, 'ii', $cuentaId, $grupoId);
        mysqli_stmt_execute($chk);
        $ok = (bool) mysqli_fetch_row(mysqli_stmt_get_result($chk));
        mysqli_stmt_close($chk);
        return $ok;
    }

    /** Alumnos del grupo con sus notas de la materia/ciclo. */
    public function alumnosConNotas(int $materiaId, int $cicloId, int $grupoId): array
    {
        $sql = 'SELECT c.id_cuenta, u.nombre, u.paterno, u.materno,
                       cal.p1, cal.p2, cal.p3, cal.calif_final, cal.reporte
                FROM cuenta c
                JOIN usuario u ON u.id_usuario = c.usuario_id
                LEFT JOIN calificacion cal
                       ON cal.cuenta_id = c.id_cuenta AND cal.materia_id = ? AND cal.ciclo_id = ?
                WHERE c.grupo_id = ? AND c.permiso_id = 3
                ORDER BY u.paterno, u.nombre';
        $stmt = mysqli_prepare($this->db, $sql);
        mysqli_stmt_bind_param($stmt, 'iii', $materiaId, $cicloId, $grupoId);
        mysqli_stmt_execute($stmt);
        $res = mysqli_stmt_get_result($stmt);
        $alumnos = [];
        while ($r = mysqli_fetch_assoc($res)) {
            $r['nombre_completo'] = trim("$r[nombre] $r[paterno] $r[materno]");
            $alumnos[] = $r;
        }
        mysqli_stmt_close($stmt);
        return $alumnos;
    }

    /** Inserta o actualiza la calificación (con estatus de captura/aprobación). */
    public function guardar(int $cuentaId, int $materiaId, int $cicloId, ?float $p1, ?float $p2, ?float $p3, ?float $fin, ?string $rep): bool
    {
        $stCaptura = statusId($this->db, 'captura', 'capturada');
        $stAprob   = $fin === null ? null : statusId($this->db, 'aprobacion', $fin >= 6 ? 'aprobado' : 'reprobado');

        $sql = 'INSERT INTO calificacion (cuenta_id, materia_id, ciclo_id, p1, p2, p3, calif_final, reporte, status_captura_id, status_aprobacion_id)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                ON DUPLICATE KEY UPDATE
                    p1 = VALUES(p1), p2 = VALUES(p2), p3 = VALUES(p3),
                    calif_final = VALUES(calif_final), reporte = VALUES(reporte),
                    status_captura_id = VALUES(status_captura_id), status_aprobacion_id = VALUES(status_aprobacion_id)';
        $stmt = mysqli_prepare($this->db, $sql);
        mysqli_stmt_bind_param($stmt, 'iiiddddsii', $cuentaId, $materiaId, $cicloId, $p1, $p2, $p3, $fin, $rep, $stCaptura, $stAprob);
        $ok = mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);
        return $ok;
    }
}
