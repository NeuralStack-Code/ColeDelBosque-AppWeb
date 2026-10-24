<?php
/** Entidad Ciclo: los ciclos escolares (tabla ciclo). Procedures: webService/sql/sp/sp_ciclo.sql. */
class CicloBusiness extends Entity
{
    public ?int   $IdCiclo     = null;
    public string $Nombre      = '';
    public string $FechaInicio = '';   // AAAA-MM-DD
    public string $FechaFin    = '';

    public function list(): array
    {
        $this->db->command('sp_ciclo_listar');
        return $this->db->execute();
    }

    /** Nace cerrado. Regresa el Id nuevo. */
    public function insert(): array
    {
        $this->db->command('sp_ciclo_crear');
        $this->db->addParameter('Nombre',      $this->Nombre);
        $this->db->addParameter('FechaInicio', $this->FechaInicio);
        $this->db->addParameter('FechaFin',    $this->FechaFin);
        return $this->db->execute();
    }

    /** 'notfound' si no existe. */
    public function update(): array
    {
        $this->db->command('sp_ciclo_editar');
        $this->db->addParameter('IdCiclo',     $this->IdCiclo);
        $this->db->addParameter('Nombre',      $this->Nombre);
        $this->db->addParameter('FechaInicio', $this->FechaInicio);
        $this->db->addParameter('FechaFin',    $this->FechaFin);
        return $this->db->execute();
    }

    /** Lo activa y cierra los demás. 'notfound' si no existe. */
    public function activate(): array
    {
        $this->db->command('sp_ciclo_activar');
        $this->db->addParameter('IdCiclo', $this->IdCiclo);
        return $this->db->execute();
    }

    /** Regresa las filas afectadas (0 = no existía). */
    public function delete(): array
    {
        $this->db->command('sp_ciclo_eliminar');
        $this->db->addParameter('IdCiclo', $this->IdCiclo);
        return $this->db->execute();
    }

    /** El ciclo activo (tabla vacía si no hay ninguno). */
    public function active(): array
    {
        $this->db->command('sp_ciclo_activo');
        return $this->db->execute();
    }

    /** Un ciclo con sus fechas (tabla vacía si no existe). */
    public function get(): array
    {
        $this->db->command('sp_ciclo_obtener');
        $this->db->addParameter('IdCiclo', $this->IdCiclo);
        return $this->db->execute();
    }
}
