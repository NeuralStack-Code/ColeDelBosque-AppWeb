<?php
/** Entidad ReporteColumna: las columnas del reporte semanal (tabla reporte_columna). Procedures: webService/sql/sp/sp_reporte_columna.sql. */
class ReporteColumnaBusiness extends Entity
{
    public ?int    $IdColumna   = null;
    public string  $Nombre      = '';
    public string  $Tipo        = 'casilla';
    public ?string $Etiqueta    = null;
    public int     $Orden       = 0;
    public bool    $Activo      = true;
    public bool    $SoloActivas = false;

    public function list(): array
    {
        $this->db->command('sp_reporte_columna_listar');
        $this->db->addParameter('SoloActivas', $this->SoloActivas);
        return $this->db->execute();
    }

    /** Regresa el Id nuevo. */
    public function insert(): array
    {
        $this->db->command('sp_reporte_columna_crear');
        $this->fields();
        return $this->db->execute();
    }

    /** 'notfound' si no existe. */
    public function update(): array
    {
        $this->db->command('sp_reporte_columna_editar');
        $this->db->addParameter('IdColumna', $this->IdColumna);
        $this->fields();
        return $this->db->execute();
    }

    /** Regresa las filas afectadas; 'en_uso' si ya hay incidencias con ella. */
    public function delete(): array
    {
        $this->db->command('sp_reporte_columna_eliminar');
        $this->db->addParameter('IdColumna', $this->IdColumna);
        return $this->db->execute();
    }

    private function fields(): void
    {
        $this->db->addParameter('Nombre',   $this->Nombre);
        $this->db->addParameter('Tipo',     $this->Tipo);
        $this->db->addParameter('Etiqueta', $this->Etiqueta);
        $this->db->addParameter('Orden',    $this->Orden);
        $this->db->addParameter('Activo',   $this->Activo);
    }
}
