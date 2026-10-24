<?php
/**
 * Entidad Perfil: personas y sus cuentas de acceso, de cualquier rol (tablas usuario + cuenta).
 * La matrícula viaja ENCRIPTADA. Procedures: webService/sql/sp/sp_perfil.sql.
 */
class PerfilBusiness extends Entity
{
    public ?int    $IdCuenta        = null;
    public string  $Nombre          = '';
    public string  $Paterno         = '';
    public ?string $Materno         = null;
    public string  $Matricula       = '';   // ya encriptada
    public int     $IdPermiso       = 0;
    public int     $IdGrupo         = 0;    // 0 = sin grupo
    public string  $Estatus         = 'activo';
    public int     $IdUsuarioSesion = 0;    // quien pide una baja (nadie borra su propia cuenta)

    /** Las cuentas (todas, o las de un rol si IdPermiso > 0). */
    public function list(): array
    {
        $this->db->command('sp_perfil_listar');
        $this->db->addParameter('IdPermiso', $this->IdPermiso);
        return $this->db->execute();
    }

    /** Los grupos que se pueden asignar. */
    public function groups(): array
    {
        $this->db->command('sp_perfil_grupos');
        return $this->db->execute();
    }

    /** Los estatus que puede tener una cuenta. */
    public function statuses(): array
    {
        $this->db->command('sp_perfil_estatus');
        return $this->db->execute();
    }

    /** Alta de persona + cuenta. Regresa el Id de la cuenta; 'invalido' (rol) · 'dup' (matrícula). */
    public function insert(): array
    {
        $this->db->command('sp_perfil_crear');
        $this->db->addParameter('Nombre',    $this->Nombre);
        $this->db->addParameter('Paterno',   $this->Paterno);
        $this->db->addParameter('Materno',   $this->Materno);
        $this->db->addParameter('Matricula', $this->Matricula);
        $this->db->addParameter('IdPermiso', $this->IdPermiso);
        $this->db->addParameter('IdGrupo',   $this->IdGrupo);
        $this->db->addParameter('Estatus',   $this->Estatus);
        return $this->db->execute();
    }

    /** 'notfound' · 'invalido' (rol) · 'dup' (matrícula). */
    public function update(): array
    {
        $this->db->command('sp_perfil_editar');
        $this->db->addParameter('IdCuenta',  $this->IdCuenta);
        $this->db->addParameter('Nombre',    $this->Nombre);
        $this->db->addParameter('Paterno',   $this->Paterno);
        $this->db->addParameter('Materno',   $this->Materno);
        $this->db->addParameter('Matricula', $this->Matricula);
        $this->db->addParameter('IdPermiso', $this->IdPermiso);
        $this->db->addParameter('IdGrupo',   $this->IdGrupo);
        $this->db->addParameter('Estatus',   $this->Estatus);
        return $this->db->execute();
    }

    /** Borra la cuenta, su persona y todo lo suyo. 'notfound' · 'en_uso' (es la cuenta de quien lo pide). */
    public function delete(): array
    {
        $this->db->command('sp_perfil_eliminar');
        $this->db->addParameter('IdCuenta',        $this->IdCuenta);
        $this->db->addParameter('IdUsuarioSesion', $this->IdUsuarioSesion);
        return $this->db->execute();
    }
}
