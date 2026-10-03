<?php

/** Catálogo de columnas del reporte semanal (tabla reporte_columna). Aquí vive el SQL. */
class ReporteColumnaBusiness
{
    private mysqli $db;

    public function __construct(mysqli $db)
    {
        $this->db = $db;
    }

    /** @return ReporteColumna[] */
    public function listar(bool $soloActivas = false): array
    {
        $res = mysqli_query($this->db,
            'SELECT id_columna, nombre, tipo, etiqueta, orden, activo FROM reporte_columna'
            . ($soloActivas ? ' WHERE activo = 1' : '')
            . ' ORDER BY orden, id_columna');
        $items = [];
        while ($r = mysqli_fetch_assoc($res)) $items[] = new ReporteColumna($r);
        return $items;
    }

    public function crear(string $nombre, string $tipo, ?string $etiqueta, int $orden, int $activo): bool
    {
        $stmt = mysqli_prepare($this->db, 'INSERT INTO reporte_columna (nombre, tipo, etiqueta, orden, activo) VALUES (?, ?, ?, ?, ?)');
        mysqli_stmt_bind_param($stmt, 'sssii', $nombre, $tipo, $etiqueta, $orden, $activo);
        $ok = mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);
        return $ok;
    }

    public function editar(int $id, string $nombre, string $tipo, ?string $etiqueta, int $orden, int $activo): bool
    {
        $stmt = mysqli_prepare($this->db, 'UPDATE reporte_columna SET nombre = ?, tipo = ?, etiqueta = ?, orden = ?, activo = ? WHERE id_columna = ?');
        mysqli_stmt_bind_param($stmt, 'sssiii', $nombre, $tipo, $etiqueta, $orden, $activo, $id);
        $ok = mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);
        return $ok;
    }

    /** ¿Ya hay incidencias capturadas en esta columna? */
    public function enUso(int $id): bool
    {
        $chk = mysqli_prepare($this->db, 'SELECT 1 FROM reporte_marca WHERE columna_id = ? LIMIT 1');
        mysqli_stmt_bind_param($chk, 'i', $id);
        mysqli_stmt_execute($chk);
        $hay = (bool) mysqli_fetch_row(mysqli_stmt_get_result($chk));
        mysqli_stmt_close($chk);
        return $hay;
    }

    public function eliminar(int $id): void
    {
        $stmt = mysqli_prepare($this->db, 'DELETE FROM reporte_columna WHERE id_columna = ?');
        mysqli_stmt_bind_param($stmt, 'i', $id);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);
    }
}
