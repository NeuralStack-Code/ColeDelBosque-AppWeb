<?php
/**
 * Entidad Cuenta: personas y sus cuentas de acceso, de cualquier tipo (tablas usuario + cuenta).
 * La matrícula viaja ENCRIPTADA. Procedures: webService/sql/sp/sp_cuenta.sql. Los perfiles de cada cuenta (lo que
 * puede abrir) son del catálogo de permisos: PerfilAccesoBusiness (módulo framework).
 */
class CuentaBusiness extends Entity
{
    public ?int    $IdCuenta        = null;
    public string  $Nombre          = '';
    public string  $Paterno         = '';
    public ?string $Materno         = null;
    public string  $Matricula       = '';   // ya encriptada
    public int     $IdPermiso       = 0;    // tipo de cuenta (libs/TipoCuenta.php)
    public int     $IdGrupo         = 0;    // 0 = sin grupo
    public string  $Estatus         = 'activo';
    public int     $IdUsuarioSesion = 0;    // quien pide una baja (nadie borra su propia cuenta)

    /** Las cuentas (todas, o las de un tipo si IdPermiso > 0). */
    public function list(): array
    {
        $this->db->command('sp_cuenta_listar');
        $this->db->addParameter('IdPermiso', $this->IdPermiso);
        return $this->db->execute();
    }

    /** Los grupos que se pueden asignar. */
    public function groups(): array
    {
        $this->db->command('sp_cuenta_grupos');
        return $this->db->execute();
    }

    /** Los estatus que puede tener una cuenta. */
    public function statuses(): array
    {
        $this->db->command('sp_cuenta_estatus');
        return $this->db->execute();
    }

    /** Alta de persona + cuenta (con el perfil predeterminado de su tipo). Regresa el Id de la cuenta; 'invalido' (tipo) · 'dup' (matrícula). */
    public function insert(): array
    {
        $this->db->command('sp_cuenta_crear');
        $this->db->addParameter('Nombre',    $this->Nombre);
        $this->db->addParameter('Paterno',   $this->Paterno);
        $this->db->addParameter('Materno',   $this->Materno);
        $this->db->addParameter('Matricula', $this->Matricula);
        $this->db->addParameter('IdPermiso', $this->IdPermiso);
        $this->db->addParameter('IdGrupo',   $this->IdGrupo);
        $this->db->addParameter('Estatus',   $this->Estatus);
        return $this->db->execute();
    }

    /** 'notfound' · 'invalido' (tipo) · 'dup' (matrícula). */
    public function update(): array
    {
        $this->db->command('sp_cuenta_editar');
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
        $this->db->command('sp_cuenta_eliminar');
        $this->db->addParameter('IdCuenta',        $this->IdCuenta);
        $this->db->addParameter('IdUsuarioSesion', $this->IdUsuarioSesion);
        return $this->db->execute();
    }

    /** Los tipos de cuenta (tabla permisos), con cuántas cuentas tiene cada uno. */
    public function types(): array
    {
        $this->db->command('sp_permiso_listar');
        return $this->db->execute();
    }
}
