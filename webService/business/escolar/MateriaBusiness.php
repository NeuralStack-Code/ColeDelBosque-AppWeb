<?php
/**
 * Entidad Materia: el catálogo de materias y los grupos donde se imparten (tablas materia + grupo_materia).
 * Procedures: webService/sql/sp/sp_materia.sql.
 */
class MateriaBusiness extends Entity
{
    public ?int   $IdMateria = null;
    public string $Nombre    = '';
    public string $Grupos    = '';   // ids de sus grupos separados por coma: "69,70,71"

    /** Cada materia con sus grupos. */
    public function list(): array
    {
        $this->db->command('sp_materia_listar');
        return $this->db->execute();
    }

    /** Alta con sus grupos. Regresa el Id nuevo; 'dup' si ya hay una con ese nombre. */
    public function insert(): array
    {
        $this->db->command('sp_materia_crear');
        $this->db->addParameter('Nombre', $this->Nombre);
        $this->db->addParameter('Grupos', $this->Grupos);
        return $this->db->execute();
    }

    /** Cambia el nombre y deja la materia exactamente en esos grupos. 'notfound' · 'dup'. */
    public function update(): array
    {
        $this->db->command('sp_materia_editar');
        $this->db->addParameter('IdMateria', $this->IdMateria);
        $this->db->addParameter('Nombre',    $this->Nombre);
        $this->db->addParameter('Grupos',    $this->Grupos);
        return $this->db->execute();
    }

    /** Regresa las filas afectadas; 'en_uso' si ya tiene calificaciones. */
    public function delete(): array
    {
        $this->db->command('sp_materia_eliminar');
        $this->db->addParameter('IdMateria', $this->IdMateria);
        return $this->db->execute();
    }
}
