<?php

/**
 * Catálogo de materias. Una materia puede estar en VARIOS grupos (grupo_materia).
 * Aquí vive TODO el SQL, incluida la sincronización del muchos-a-muchos.
 */
class MateriaBusiness
{
    private mysqli $db;

    public function __construct(mysqli $db)
    {
        $this->db = $db;
    }

    /** @return Materia[] */
    public function listar(): array
    {
        $sql = 'SELECT m.id_materia, m.nombre,
                       (SELECT GROUP_CONCAT(g.grado ORDER BY g.grado SEPARATOR ", ")
                          FROM grupo_materia gm JOIN grupo g ON g.id_grupo = gm.id_grupo
                          WHERE gm.id_materia = m.id_materia) AS grupos_txt,
                       (SELECT GROUP_CONCAT(gm.id_grupo)
                          FROM grupo_materia gm WHERE gm.id_materia = m.id_materia) AS grupo_ids,
                       (SELECT COUNT(*) FROM grupo_materia gm WHERE gm.id_materia = m.id_materia) AS num_grupos
                FROM materia m ORDER BY m.nombre';
        $res = mysqli_query($this->db, $sql);
        $items = [];
        while ($r = mysqli_fetch_assoc($res)) {
            $r['grupo_ids'] = $r['grupo_ids'] ? array_map('intval', explode(',', $r['grupo_ids'])) : [];
            $items[] = new Materia($r);
        }
        return $items;
    }

    /** ¿Existe otra materia con el mismo nombre? */
    public function nombreDuplicado(string $nombre, int $exceptoId = 0): bool
    {
        $q = mysqli_prepare($this->db, 'SELECT 1 FROM materia WHERE nombre = ? AND id_materia <> ? LIMIT 1');
        mysqli_stmt_bind_param($q, 'si', $nombre, $exceptoId);
        mysqli_stmt_execute($q);
        $dup = (bool) mysqli_fetch_row(mysqli_stmt_get_result($q));
        mysqli_stmt_close($q);
        return $dup;
    }

    /** Crea la materia; regresa su id (0 = falló). */
    public function crear(string $nombre): int
    {
        $stmt = mysqli_prepare($this->db, 'INSERT INTO materia (nombre) VALUES (?)');
        mysqli_stmt_bind_param($stmt, 's', $nombre);
        $ok = mysqli_stmt_execute($stmt);
        $id = $ok ? mysqli_insert_id($this->db) : 0;
        mysqli_stmt_close($stmt);
        return $id;
    }

    public function editar(int $id, string $nombre): bool
    {
        $stmt = mysqli_prepare($this->db, 'UPDATE materia SET nombre = ? WHERE id_materia = ?');
        mysqli_stmt_bind_param($stmt, 'si', $nombre, $id);
        $ok = mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);
        return $ok;
    }

    /** Deja la materia vinculada EXACTAMENTE a los grupos dados. */
    public function sincronizarGrupos(int $materiaId, array $grupoIds): void
    {
        $grupoIds = array_values(array_unique(array_filter(array_map('intval', $grupoIds), fn($x) => $x > 0)));
        if ($grupoIds) {
            $in = implode(',', $grupoIds);
            mysqli_query($this->db, "DELETE FROM grupo_materia WHERE id_materia = $materiaId AND id_grupo NOT IN ($in)");
        } else {
            mysqli_query($this->db, "DELETE FROM grupo_materia WHERE id_materia = $materiaId");
        }
        $ins = mysqli_prepare($this->db, 'INSERT IGNORE INTO grupo_materia (id_grupo, id_materia) VALUES (?, ?)');
        foreach ($grupoIds as $gid) {
            mysqli_stmt_bind_param($ins, 'ii', $gid, $materiaId);
            mysqli_stmt_execute($ins);
        }
        mysqli_stmt_close($ins);
    }

    /** ¿La materia tiene calificaciones registradas? */
    public function tieneCalificaciones(int $id): bool
    {
        $chk = mysqli_prepare($this->db, 'SELECT 1 FROM calificacion WHERE materia_id = ? LIMIT 1');
        mysqli_stmt_bind_param($chk, 'i', $id);
        mysqli_stmt_execute($chk);
        $hay = (bool) mysqli_fetch_row(mysqli_stmt_get_result($chk));
        mysqli_stmt_close($chk);
        return $hay;
    }

    /** Los vínculos en grupo_materia se borran por la FK en cascada. */
    public function eliminar(int $id): void
    {
        $stmt = mysqli_prepare($this->db, 'DELETE FROM materia WHERE id_materia = ?');
        mysqli_stmt_bind_param($stmt, 'i', $id);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);
    }
}
