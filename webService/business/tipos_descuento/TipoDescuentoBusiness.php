<?php

/** Catálogo de tipos de descuento (tabla tipo_descuento). Aquí vive el SQL. */
class TipoDescuentoBusiness
{
    private mysqli $db;

    public function __construct(mysqli $db)
    {
        $this->db = $db;
    }

    /** @return TipoDescuento[] */
    public function listar(): array
    {
        $res = mysqli_query($this->db,
            'SELECT t.id_descuento, t.nombre, t.porcentaje, t.aplica_a, (s.clave = "activo") AS activo
             FROM tipo_descuento t LEFT JOIN status s ON s.id_status = t.status_id
             ORDER BY t.aplica_a, t.nombre');
        $items = [];
        while ($r = mysqli_fetch_assoc($res)) $items[] = new TipoDescuento($r);
        return $items;
    }

    public function crear(string $nombre, float $pct, string $aplica): bool
    {
        $stActivo = statusId($this->db, 'descuento', 'activo');
        $stmt = mysqli_prepare($this->db, 'INSERT INTO tipo_descuento (nombre, porcentaje, aplica_a, status_id) VALUES (?, ?, ?, ?)');
        mysqli_stmt_bind_param($stmt, 'sdsi', $nombre, $pct, $aplica, $stActivo);
        $ok = mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);
        return $ok;
    }

    public function editar(int $id, string $nombre, float $pct, string $aplica, int $activo): bool
    {
        $stId = statusId($this->db, 'descuento', $activo === 1 ? 'activo' : 'inactivo');
        $stmt = mysqli_prepare($this->db,
            'UPDATE tipo_descuento SET nombre = ?, porcentaje = ?, aplica_a = ?, status_id = ? WHERE id_descuento = ?');
        mysqli_stmt_bind_param($stmt, 'sdsii', $nombre, $pct, $aplica, $stId, $id);
        $ok = mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);
        return $ok;
    }

    /** ¿Hay colegiaturas usando este descuento? */
    public function tieneColegiaturas(int $id): bool
    {
        $chk = mysqli_prepare($this->db, 'SELECT 1 FROM colegiatura WHERE tipo_descuento_id = ? LIMIT 1');
        mysqli_stmt_bind_param($chk, 'i', $id);
        mysqli_stmt_execute($chk);
        $hay = (bool) mysqli_fetch_row(mysqli_stmt_get_result($chk));
        mysqli_stmt_close($chk);
        return $hay;
    }

    public function eliminar(int $id): void
    {
        $stmt = mysqli_prepare($this->db, 'DELETE FROM tipo_descuento WHERE id_descuento = ?');
        mysqli_stmt_bind_param($stmt, 'i', $id);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);
    }
}
