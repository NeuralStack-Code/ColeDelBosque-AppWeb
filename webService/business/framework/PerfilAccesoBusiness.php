<?php
/**
 * Entidad PerfilAcceso: los perfiles del catálogo de permisos y su asignación a cuentas (base del colegio).
 * Se llama PerfilAcceso (igual que en NeuralStack Code) para no confundirlo con los datos de una persona. Procedures: webService/sql/sp/sp_catalogo.sql.
 * Las altas y cambios declaran pIdUsuarioRegistra / pIdUsuarioCambia y el business NO los pasa: Command los
 * llena con quien actúa. Con eso el procedure aplica "nadie da lo que no tiene".
 */
class PerfilAccesoBusiness extends Entity
{
    public ?int   $IdPerfil    = null;
    public string $Nombre      = '';
    public string $Descripcion = '';
    public int    $EsAdmin     = 0;
    public int    $Activo      = 1;
    public string $Objetos     = '[]';   // JSON [IdObjeto, …] para saveGrants()
    public string $Inicio      = '';     // pantalla de inicio de quien tiene este perfil ('/maestro')
    public int    $IdCuenta    = 0;
    public string $Perfiles    = '[]';   // JSON [IdPerfil, …] para assign()

    /** Todos los perfiles, con cuántos usuarios y objetos tiene cada uno. */
    public function list(): array
    {
        $this->db->command('sp_catalogo_perfil_listar');
        return $this->db->execute();
    }

    /** Filas (IdPerfil, IdObjeto) de lo que concede este perfil; sin IdPerfil, las de todos. */
    public function grants(): array
    {
        $this->db->command('sp_catalogo_perfil_objetos');
        $this->db->addParameter('IdPerfil', (int) $this->IdPerfil);
        return $this->db->execute();
    }

    public function insert(): array
    {
        $this->db->command('sp_catalogo_perfil_crear');
        $this->db->addParameter('Nombre',      $this->Nombre);
        $this->db->addParameter('Descripcion', $this->Descripcion);
        $this->db->addParameter('EsAdmin',     $this->EsAdmin);
        $this->db->addParameter('Activo',      $this->Activo);
        $this->db->addParameter('Inicio',      $this->Inicio);
        return $this->db->execute();
    }

    public function update(): array
    {
        $this->db->command('sp_catalogo_perfil_actualizar');
        $this->db->addParameter('IdPerfil',    $this->IdPerfil);
        $this->db->addParameter('Nombre',      $this->Nombre);
        $this->db->addParameter('Descripcion', $this->Descripcion);
        $this->db->addParameter('EsAdmin',     $this->EsAdmin);
        $this->db->addParameter('Activo',      $this->Activo);
        $this->db->addParameter('Inicio',      $this->Inicio);
        return $this->db->execute();
    }

    public function delete(): array
    {
        $this->db->command('sp_catalogo_perfil_eliminar');
        $this->db->addParameter('IdPerfil', $this->IdPerfil);
        return $this->db->execute();
    }

    /** Reemplaza lo que concede este perfil por los objetos del JSON (el procedure valida quién lo pide). */
    public function saveGrants(): array
    {
        $this->db->command('sp_catalogo_perfil_objetos_guardar');
        $this->db->addParameter('IdPerfil', $this->IdPerfil);
        $this->db->addParameter('Objetos',  $this->Objetos);
        return $this->db->execute();
    }

    /** Filas (IdCuenta, IdPerfil, Nombre, EsAdmin, Activo, Inicio) de los perfiles de una cuenta; con 0, los de todas. */
    public function accountProfiles(): array
    {
        $this->db->command('sp_catalogo_cuenta_perfiles');
        $this->db->addParameter('IdCuenta', $this->IdCuenta);
        return $this->db->execute();
    }

    /** Reemplaza los perfiles de la cuenta por los del JSON (el procedure valida quién lo pide). */
    public function assign(): array
    {
        $this->db->command('sp_catalogo_cuenta_perfiles_asignar');
        $this->db->addParameter('IdCuenta', $this->IdCuenta);
        $this->db->addParameter('Perfiles',  $this->Perfiles);
        return $this->db->execute();
    }
}
