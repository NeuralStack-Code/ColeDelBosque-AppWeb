<?php
require_once dirname(__DIR__, 3) . '/apiService/core/crypto.php';

/**
 * Esquemas de pago por ciclo escolar (tabla esquema_pago) y los catálogos que
 * necesita su panel. Aquí vive el SQL. Generar las colegiaturas lo hace
 * ColegiaturaBusiness::generarEsquema().
 */
class EsquemaPagoBusiness
{
    private mysqli $db;

    public function __construct(mysqli $db)
    {
        $this->db = $db;
    }

    /** @return EsquemaPago[] */
    public function listar(): array
    {
        $res = mysqli_query($this->db,
            'SELECT e.id_esquema, e.ciclo_id, c.nombre AS ciclo_nombre, e.nombre, e.monto_inscripcion, e.monto_colegiatura,
                    e.primer_mes, e.num_meses, e.dia_vencimiento, e.recargo_monto
             FROM esquema_pago e JOIN ciclo c ON c.id_ciclo = e.ciclo_id
             ORDER BY c.fecha_inicio DESC, e.nombre');
        $items = [];
        while ($r = mysqli_fetch_assoc($res)) $items[] = new EsquemaPago($r);
        return $items;
    }

    public function obtener(int $id): ?EsquemaPago
    {
        $stmt = mysqli_prepare($this->db,
            'SELECT id_esquema, ciclo_id, nombre, monto_inscripcion, monto_colegiatura, primer_mes, num_meses, dia_vencimiento, recargo_monto
             FROM esquema_pago WHERE id_esquema = ? LIMIT 1');
        mysqli_stmt_bind_param($stmt, 'i', $id);
        mysqli_stmt_execute($stmt);
        $row = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
        mysqli_stmt_close($stmt);
        return $row ? new EsquemaPago($row) : null;
    }

    public function crear(EsquemaPago $e): bool
    {
        $stmt = mysqli_prepare($this->db,
            'INSERT INTO esquema_pago (ciclo_id, nombre, monto_inscripcion, monto_colegiatura, primer_mes, num_meses, dia_vencimiento, recargo_monto)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?)');
        mysqli_stmt_bind_param($stmt, 'isddsiid', $e->ciclo_id, $e->nombre, $e->monto_inscripcion, $e->monto_colegiatura,
            $e->primer_mes, $e->num_meses, $e->dia_vencimiento, $e->recargo_monto);
        $ok = mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);
        return $ok;
    }

    public function editar(EsquemaPago $e): bool
    {
        $stmt = mysqli_prepare($this->db,
            'UPDATE esquema_pago SET ciclo_id = ?, nombre = ?, monto_inscripcion = ?, monto_colegiatura = ?, primer_mes = ?,
                    num_meses = ?, dia_vencimiento = ?, recargo_monto = ?
             WHERE id_esquema = ?');
        mysqli_stmt_bind_param($stmt, 'isddsiidi', $e->ciclo_id, $e->nombre, $e->monto_inscripcion, $e->monto_colegiatura,
            $e->primer_mes, $e->num_meses, $e->dia_vencimiento, $e->recargo_monto, $e->id_esquema);
        $ok = mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);
        return $ok;
    }

    /** @return int filas afectadas (0 = no existía). No toca las colegiaturas ya generadas. */
    public function eliminar(int $id): int
    {
        $stmt = mysqli_prepare($this->db, 'DELETE FROM esquema_pago WHERE id_esquema = ?');
        mysqli_stmt_bind_param($stmt, 'i', $id);
        mysqli_stmt_execute($stmt);
        $af = mysqli_stmt_affected_rows($stmt);
        mysqli_stmt_close($stmt);
        return $af;
    }

    /** Fechas del ciclo (o null si no existe). */
    public function ciclo(int $id): ?array
    {
        $stmt = mysqli_prepare($this->db, 'SELECT id_ciclo, nombre, fecha_inicio, fecha_fin FROM ciclo WHERE id_ciclo = ? LIMIT 1');
        mysqli_stmt_bind_param($stmt, 'i', $id);
        mysqli_stmt_execute($stmt);
        $row = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
        mysqli_stmt_close($stmt);
        return $row ?: null;
    }

    public function ciclos(): array
    {
        $res = mysqli_query($this->db,
            'SELECT c.id_ciclo, c.nombre, c.fecha_inicio, c.fecha_fin, (s.clave = "activo") AS activo
             FROM ciclo c LEFT JOIN status s ON s.id_status = c.status_id
             ORDER BY c.fecha_inicio DESC');
        $ciclos = [];
        while ($r = mysqli_fetch_assoc($res)) $ciclos[] = $r;
        return $ciclos;
    }

    /** Grupos con su ciclo (para elegir a quién aplicar). */
    public function grupos(): array
    {
        $res = mysqli_query($this->db,
            'SELECT id_grupo, grado, ciclo_id FROM grupo WHERE id_grupo <> 0 AND ciclo_id IS NOT NULL ORDER BY nivel, grado');
        $grupos = [];
        while ($r = mysqli_fetch_assoc($res)) $grupos[] = $r;
        return $grupos;
    }

    /** Alumnos con su grupo y ciclo (para elegir a quién aplicar); matrícula desencriptada. */
    public function alumnos(): array
    {
        $res = mysqli_query($this->db,
            'SELECT c.id_cuenta, c.matricula, c.grupo_id, g.grado, g.ciclo_id, u.nombre, u.paterno, u.materno
             FROM cuenta c
             JOIN usuario u ON u.id_usuario = c.usuario_id
             JOIN grupo g   ON g.id_grupo = c.grupo_id
             WHERE c.permiso_id = 3 AND g.ciclo_id IS NOT NULL
             ORDER BY u.paterno, u.nombre');
        $alumnos = [];
        while ($r = mysqli_fetch_assoc($res)) {
            $alumnos[] = [
                'id_cuenta' => $r['id_cuenta'],
                'nombre'    => trim("$r[nombre] $r[paterno] $r[materno]"),
                'matricula' => decrypt($r['matricula']),
                'grupo_id'  => $r['grupo_id'],
                'grado'     => $r['grado'],
                'ciclo_id'  => $r['ciclo_id'],
            ];
        }
        return $alumnos;
    }

    /** ¿El grupo pertenece al ciclo? */
    public function grupoEnCiclo(int $grupoId, int $cicloId): bool
    {
        $chk = mysqli_prepare($this->db, 'SELECT 1 FROM grupo WHERE id_grupo = ? AND ciclo_id = ? LIMIT 1');
        mysqli_stmt_bind_param($chk, 'ii', $grupoId, $cicloId);
        mysqli_stmt_execute($chk);
        $ok = (bool) mysqli_fetch_row(mysqli_stmt_get_result($chk));
        mysqli_stmt_close($chk);
        return $ok;
    }

    /** @return int[] id_cuenta de los alumnos del grupo. */
    public function alumnosDeGrupo(int $grupoId): array
    {
        $stmt = mysqli_prepare($this->db, 'SELECT id_cuenta FROM cuenta WHERE grupo_id = ? AND permiso_id = 3');
        mysqli_stmt_bind_param($stmt, 'i', $grupoId);
        mysqli_stmt_execute($stmt);
        $res = mysqli_stmt_get_result($stmt);
        $ids = [];
        while ($r = mysqli_fetch_row($res)) $ids[] = (int) $r[0];
        mysqli_stmt_close($stmt);
        return $ids;
    }
}
