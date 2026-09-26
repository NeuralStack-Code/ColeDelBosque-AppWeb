<?php

/**
 * Lógica de datos de los ciclos escolares. Aquí vive TODO el SQL.
 * Usa statusId() (catálogo status) — disponible al cargar conexionBDD.
 */
class CicloBusiness
{
    private mysqli $db;

    public function __construct(mysqli $db)
    {
        $this->db = $db;
    }

    /** @return Ciclo[] */
    public function listar(): array
    {
        $res = mysqli_query($this->db,
            'SELECT c.id_ciclo, c.nombre, c.fecha_inicio, c.fecha_fin,
                    (s.clave = "activo") AS activo, s.clave AS estatus, s.nombre AS estatus_nombre
             FROM ciclo c LEFT JOIN status s ON s.id_status = c.status_id
             ORDER BY c.fecha_inicio DESC');
        $items = [];
        while ($r = mysqli_fetch_assoc($res)) $items[] = new Ciclo($r);
        return $items;
    }

    /** Crea un ciclo (nace cerrado hasta que se active). */
    public function crear(string $nombre, string $ini, string $fin): bool
    {
        $cerrado = statusId($this->db, 'ciclo', 'cerrado');
        $stmt = mysqli_prepare($this->db, 'INSERT INTO ciclo (nombre, fecha_inicio, fecha_fin, status_id) VALUES (?, ?, ?, ?)');
        mysqli_stmt_bind_param($stmt, 'sssi', $nombre, $ini, $fin, $cerrado);
        $ok = mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);
        return $ok;
    }

    public function editar(int $id, string $nombre, string $ini, string $fin): bool
    {
        $stmt = mysqli_prepare($this->db, 'UPDATE ciclo SET nombre = ?, fecha_inicio = ?, fecha_fin = ? WHERE id_ciclo = ?');
        mysqli_stmt_bind_param($stmt, 'sssi', $nombre, $ini, $fin, $id);
        $ok = mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);
        return $ok;
    }

    /** Activa un ciclo (uno solo activo a la vez: cierra los demás). */
    public function activar(int $id): void
    {
        $cerrado  = statusId($this->db, 'ciclo', 'cerrado');
        $activoId = statusId($this->db, 'ciclo', 'activo');
        mysqli_query($this->db, 'UPDATE ciclo SET status_id = ' . (int) $cerrado);
        $stmt = mysqli_prepare($this->db, 'UPDATE ciclo SET status_id = ? WHERE id_ciclo = ?');
        mysqli_stmt_bind_param($stmt, 'ii', $activoId, $id);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);
    }

    /** @return int filas afectadas (0 = no existía). */
    public function eliminar(int $id): int
    {
        $stmt = mysqli_prepare($this->db, 'DELETE FROM ciclo WHERE id_ciclo = ?');
        mysqli_stmt_bind_param($stmt, 'i', $id);
        mysqli_stmt_execute($stmt);
        $af = mysqli_stmt_affected_rows($stmt);
        mysqli_stmt_close($stmt);
        return $af;
    }
}
