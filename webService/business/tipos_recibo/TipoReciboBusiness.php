<?php
/** Entidad TipoRecibo: catálogo de tipos de recibo (tabla tipo_recibo). Procedures: webService/sql/sp/sp_catalogos.sql. */
class TipoReciboBusiness extends Entity
{
    public ?int   $IdTipo     = null;
    public string $Nombre     = '';
    public string $Naturaleza = 'ingreso';

    public function list(): array
    {
        $this->db->command('sp_tiporecibo_listar');
        return $this->db->execute();
    }

    public function insert(): array
    {
        $this->db->command('sp_tiporecibo_crear');
        $this->db->addParameter('Nombre',     $this->Nombre);
        $this->db->addParameter('Naturaleza', $this->Naturaleza);
        return $this->db->execute();
    }

    /** 'notfound' si no existe. */
    public function update(): array
    {
        $this->db->command('sp_tiporecibo_editar');
        $this->db->addParameter('IdTipo',     $this->IdTipo);
        $this->db->addParameter('Nombre',     $this->Nombre);
        $this->db->addParameter('Naturaleza', $this->Naturaleza);
        return $this->db->execute();
    }

    /** Regresa las filas afectadas; 'en_uso' si ya hay recibos de ese tipo. */
    public function delete(): array
    {
        $this->db->command('sp_tiporecibo_eliminar');
        $this->db->addParameter('IdTipo', $this->IdTipo);
        return $this->db->execute();
    }
}
