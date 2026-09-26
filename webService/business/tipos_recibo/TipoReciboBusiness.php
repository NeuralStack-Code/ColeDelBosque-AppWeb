<?php

/** Catálogo de tipos de recibo (tabla tipo_recibo). Aquí vive el SQL. */
class TipoReciboBusiness
{
    private mysqli $db;

    public function __construct(mysqli $db)
    {
        $this->db = $db;
    }

    /** @return TipoRecibo[] */
    public function listar(): array
    {
        $res = mysqli_query($this->db, 'SELECT id_tipo, nombre, naturaleza FROM tipo_recibo ORDER BY nombre');
        $items = [];
        while ($r = mysqli_fetch_assoc($res)) $items[] = new TipoRecibo($r);
        return $items;
    }

    public function crear(string $nombre, string $naturaleza): bool
    {
        $stmt = mysqli_prepare($this->db, 'INSERT INTO tipo_recibo (nombre, naturaleza) VALUES (?, ?)');
        mysqli_stmt_bind_param($stmt, 'ss', $nombre, $naturaleza);
        $ok = mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);
        return $ok;
    }

    public function editar(int $id, string $nombre, string $naturaleza): bool
    {
        $stmt = mysqli_prepare($this->db, 'UPDATE tipo_recibo SET nombre = ?, naturaleza = ? WHERE id_tipo = ?');
        mysqli_stmt_bind_param($stmt, 'ssi', $nombre, $naturaleza, $id);
        $ok = mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);
        return $ok;
    }

    /** ¿Hay recibos usando este tipo? */
    public function tieneRecibos(int $id): bool
    {
        $chk = mysqli_prepare($this->db, 'SELECT 1 FROM recibo WHERE tipo_recibo_id = ? LIMIT 1');
        mysqli_stmt_bind_param($chk, 'i', $id);
        mysqli_stmt_execute($chk);
        $hay = (bool) mysqli_fetch_row(mysqli_stmt_get_result($chk));
        mysqli_stmt_close($chk);
        return $hay;
    }

    public function eliminar(int $id): void
    {
        $stmt = mysqli_prepare($this->db, 'DELETE FROM tipo_recibo WHERE id_tipo = ?');
        mysqli_stmt_bind_param($stmt, 'i', $id);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);
    }
}
