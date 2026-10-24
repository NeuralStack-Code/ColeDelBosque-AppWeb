<?php
/** Entidad TipoDescuento: catálogo de descuentos (tabla tipo_descuento). Procedures: webService/sql/sp/sp_catalogos.sql. */
class TipoDescuentoBusiness extends Entity
{
    public ?int   $IdDescuento = null;
    public string $Nombre      = '';
    public float  $Porcentaje  = 0.0;
    public string $AplicaA     = 'colegiatura';
    public int    $Activo      = 1;

    public function list(): array
    {
        $this->db->command('sp_tipodescuento_listar');
        return $this->db->execute();
    }

    /** Nace activo. */
    public function insert(): array
    {
        $this->db->command('sp_tipodescuento_crear');
        $this->db->addParameter('Nombre',     $this->Nombre);
        $this->db->addParameter('Porcentaje', $this->Porcentaje);
        $this->db->addParameter('AplicaA',    $this->AplicaA);
        return $this->db->execute();
    }

    /** 'notfound' si no existe. */
    public function update(): array
    {
        $this->db->command('sp_tipodescuento_editar');
        $this->db->addParameter('IdDescuento', $this->IdDescuento);
        $this->db->addParameter('Nombre',      $this->Nombre);
        $this->db->addParameter('Porcentaje',  $this->Porcentaje);
        $this->db->addParameter('AplicaA',     $this->AplicaA);
        $this->db->addParameter('Activo',      $this->Activo);
        return $this->db->execute();
    }

    /** Regresa las filas afectadas; 'en_uso' si ya hay pagos con ese descuento. */
    public function delete(): array
    {
        $this->db->command('sp_tipodescuento_eliminar');
        $this->db->addParameter('IdDescuento', $this->IdDescuento);
        return $this->db->execute();
    }
}
