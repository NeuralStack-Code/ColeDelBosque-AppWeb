<?php
/**
 * Ingresos/egresos de la escuela (tabla recibo). Aquí vive el SQL.
 */
class ReciboBusiness
{
    private mysqli $db;

    public function __construct(mysqli $db)
    {
        $this->db = $db;
    }

    /** Tipos de recibo para el formulario. */
    public function tipos(): array
    {
        $tipos = [];
        $res = mysqli_query($this->db, 'SELECT id_tipo, nombre, naturaleza FROM tipo_recibo ORDER BY nombre');
        while ($r = mysqli_fetch_assoc($res)) $tipos[] = $r;
        return $tipos;
    }

    /** Personas por permiso (3=alumno, 2=docente) para el select de destinatario. */
    public function personas(int $permiso): array
    {
        $stmt = mysqli_prepare($this->db,
            'SELECT c.id_cuenta, u.nombre, u.paterno, u.materno
             FROM cuenta c JOIN usuario u ON u.id_usuario = c.usuario_id
             WHERE c.permiso_id = ? ORDER BY u.paterno, u.nombre');
        mysqli_stmt_bind_param($stmt, 'i', $permiso);
        mysqli_stmt_execute($stmt);
        $res = mysqli_stmt_get_result($stmt);
        $out = [];
        while ($r = mysqli_fetch_assoc($res)) {
            $out[] = ['id_cuenta' => $r['id_cuenta'], 'nombre' => trim("$r[nombre] $r[paterno] $r[materno]")];
        }
        mysqli_stmt_close($stmt);
        return $out;
    }

    /** Lista de recibos + saldo acumulado. @return array{items:array,saldo:float} */
    public function listar(): array
    {
        $sql = 'SELECT r.id_recibo, r.tipo_recibo_id, r.naturaleza, r.destinatario_tipo, r.cuenta_id,
                       r.monto, r.tipo_pago, r.comentario, r.fecha,
                       t.nombre AS tipo_nombre,
                       u.nombre, u.paterno, u.materno
                FROM recibo r
                LEFT JOIN tipo_recibo t ON t.id_tipo = r.tipo_recibo_id
                LEFT JOIN cuenta c      ON c.id_cuenta = r.cuenta_id
                LEFT JOIN usuario u     ON u.id_usuario = c.usuario_id
                ORDER BY r.fecha DESC, r.id_recibo DESC';
        $res = mysqli_query($this->db, $sql);
        $items = []; $saldo = 0.0;
        while ($r = mysqli_fetch_assoc($res)) {
            $r['destinatario'] = $r['destinatario_tipo'] === 'escuela'
                ? 'Escuela'
                : trim("$r[nombre] $r[paterno] $r[materno]");
            $saldo += ($r['naturaleza'] === 'ingreso' ? 1 : -1) * (float) $r['monto'];
            $items[] = $r;
        }
        return ['items' => $items, 'saldo' => $saldo];
    }

    /** ¿La cuenta existe con ese permiso (3=alumno, 2=docente)? */
    public function cuentaValida(int $cuenta, int $permiso): bool
    {
        $chk = mysqli_prepare($this->db, 'SELECT 1 FROM cuenta WHERE id_cuenta = ? AND permiso_id = ? LIMIT 1');
        mysqli_stmt_bind_param($chk, 'ii', $cuenta, $permiso);
        mysqli_stmt_execute($chk);
        $ok = (bool) mysqli_fetch_row(mysqli_stmt_get_result($chk));
        mysqli_stmt_close($chk);
        return $ok;
    }

    public function crear(?int $tipo, string $nat, string $dtipo, ?int $cuenta, float $monto, ?string $pago, ?string $com, string $fecha): bool
    {
        $stmt = mysqli_prepare($this->db,
            'INSERT INTO recibo (tipo_recibo_id, naturaleza, destinatario_tipo, cuenta_id, monto, tipo_pago, comentario, fecha)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?)');
        mysqli_stmt_bind_param($stmt, 'issidsss', $tipo, $nat, $dtipo, $cuenta, $monto, $pago, $com, $fecha);
        $ok = mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);
        return $ok;
    }

    public function editar(int $id, ?int $tipo, string $nat, string $dtipo, ?int $cuenta, float $monto, ?string $pago, ?string $com, string $fecha): bool
    {
        $stmt = mysqli_prepare($this->db,
            'UPDATE recibo SET tipo_recibo_id=?, naturaleza=?, destinatario_tipo=?, cuenta_id=?, monto=?, tipo_pago=?, comentario=?, fecha=? WHERE id_recibo=?');
        mysqli_stmt_bind_param($stmt, 'issidsssi', $tipo, $nat, $dtipo, $cuenta, $monto, $pago, $com, $fecha, $id);
        $ok = mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);
        return $ok;
    }

    /** @return int filas afectadas (0 = no existía). */
    public function eliminar(int $id): int
    {
        $stmt = mysqli_prepare($this->db, 'DELETE FROM recibo WHERE id_recibo = ?');
        mysqli_stmt_bind_param($stmt, 'i', $id);
        mysqli_stmt_execute($stmt);
        $af = mysqli_stmt_affected_rows($stmt);
        mysqli_stmt_close($stmt);
        return $af;
    }
}
