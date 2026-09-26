<?php

/** Catálogo de roles (tabla permisos). Aquí vive el SQL. */
class PermisoBusiness
{
    private mysqli $db;

    public function __construct(mysqli $db)
    {
        $this->db = $db;
    }

    /** @return Permiso[] */
    public function listar(): array
    {
        $res = mysqli_query($this->db,
            'SELECT p.id_permiso, p.nombre,
                    (SELECT COUNT(*) FROM cuenta c WHERE c.permiso_id = p.id_permiso) AS num_cuentas
             FROM permisos p ORDER BY p.id_permiso');
        $items = [];
        while ($r = mysqli_fetch_assoc($res)) $items[] = new Permiso($r);
        return $items;
    }

    public function crear(string $nombre): bool
    {
        $stmt = mysqli_prepare($this->db, 'INSERT INTO permisos (nombre) VALUES (?)');
        mysqli_stmt_bind_param($stmt, 's', $nombre);
        $ok = mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);
        return $ok;
    }

    public function editar(int $id, string $nombre): bool
    {
        $stmt = mysqli_prepare($this->db, 'UPDATE permisos SET nombre = ? WHERE id_permiso = ?');
        mysqli_stmt_bind_param($stmt, 'si', $nombre, $id);
        $ok = mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);
        return $ok;
    }

    /** ¿Hay cuentas usando este rol? */
    public function tieneCuentas(int $id): bool
    {
        $chk = mysqli_prepare($this->db, 'SELECT 1 FROM cuenta WHERE permiso_id = ? LIMIT 1');
        mysqli_stmt_bind_param($chk, 'i', $id);
        mysqli_stmt_execute($chk);
        $hay = (bool) mysqli_fetch_row(mysqli_stmt_get_result($chk));
        mysqli_stmt_close($chk);
        return $hay;
    }

    public function eliminar(int $id): void
    {
        $stmt = mysqli_prepare($this->db, 'DELETE FROM permisos WHERE id_permiso = ?');
        mysqli_stmt_bind_param($stmt, 'i', $id);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);
    }
}
