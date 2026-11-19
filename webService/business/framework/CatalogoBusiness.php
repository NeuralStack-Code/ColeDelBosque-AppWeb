<?php
/**
 * Entidad Catalogo: versiones del catálogo de permisos (base del colegio). Procedures: webService/sql/sp/sp_catalogo.sql.
 * register(): el procedure declara pIdUsuarioRegistra y el business NO lo pasa: Command lo llena con el actor
 * que fijó el Controller.
 */
class CatalogoBusiness extends Entity
{
    public string $Huella  = '';
    public int    $Objetos = 0;
    public string $Origen  = 'pantalla';

    /** Revisión del catálogo: cambia con cada cambio de permisos (el menú en caché se compara contra ella). */
    public function revision(): array
    {
        $this->db->command('sp_catalogo_revision');
        return $this->db->execute();
    }

    /** La última versión cargada (tabla vacía si nunca se ha cargado). */
    public function lastVersion(): array
    {
        $this->db->command('sp_catalogo_version_ultima');
        return $this->db->execute();
    }

    /** Registra una versión cargada (+ su renglón en BitacoraPermisos). Regresa Id. */
    public function register(): array
    {
        $this->db->command('sp_catalogo_version_registrar');
        $this->db->addParameter('Huella',  $this->Huella);
        $this->db->addParameter('Objetos', $this->Objetos);
        $this->db->addParameter('Origen',  $this->Origen);
        return $this->db->execute();
    }
}
