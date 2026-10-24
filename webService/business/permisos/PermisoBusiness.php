<?php
/** Entidad Permiso: catálogo de roles (tabla permisos). Procedures: webService/sql/sp/sp_catalogos.sql. */
class PermisoBusiness extends Entity
{
    public ?int   $IdPermiso = null;
    public string $Nombre    = '';

    /** Cada rol con cuántas cuentas lo tienen. */
    public function list(): array
    {
        $this->db->command('sp_permiso_listar');
        return $this->db->execute();
    }

    public function insert(): array
    {
        $this->db->command('sp_permiso_crear');
        $this->db->addParameter('Nombre', $this->Nombre);
        return $this->db->execute();
    }

    /** 'notfound' si no existe. */
    public function update(): array
    {
        $this->db->command('sp_permiso_editar');
        $this->db->addParameter('IdPermiso', $this->IdPermiso);
        $this->db->addParameter('Nombre',    $this->Nombre);
        return $this->db->execute();
    }

    /** Regresa las filas afectadas; 'en_uso' si hay cuentas con ese rol. */
    public function delete(): array
    {
        $this->db->command('sp_permiso_eliminar');
        $this->db->addParameter('IdPermiso', $this->IdPermiso);
        return $this->db->execute();
    }
}
