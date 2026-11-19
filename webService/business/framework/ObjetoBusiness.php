<?php
/**
 * Entidad Objeto: el catálogo de permisos (base del colegio). Procedures: webService/sql/sp/sp_catalogo.sql.
 * Las lecturas no auditan. register(), registerUse() y retire() las usa la semilla del catálogo:
 * sus procedures declaran pIdUsuarioRegistra / pIdUsuarioCambia y el business NO los pasa (los llena Command).
 */
class ObjetoBusiness extends Entity
{
    public int    $IdCuenta   = 0;
    public string $Componente = '';     // 'ciclo'
    public string $Metodo     = '';     // 'vistaPrevia' (nombre exacto del método)

    // Para registrar un nodo (sp_objeto_registrar)
    public string  $Clase          = '';
    public int     $Tipo           = 3;        // 1 grupo · 2 vista · 3 método
    public string  $Padre          = '';
    public string  $Clave          = '';
    public string  $Nombre         = '';
    public string  $Icono          = '';
    public int     $Orden          = 0;
    public int     $SoloPost       = 1;
    public string  $Acceso         = 'perfil';
    public int     $Sensible       = 0;
    public ?string $PaginaAnterior = null;
    // Para un uso entre vistas (sp_objeto_uso_registrar) y para retirar (sp_objeto_retirar)
    public string  $UsaComponente  = '';
    public string  $UsaClave       = '';
    public string  $Vigentes       = '[]';     // JSON ["ciclo:index", ":escolar", …]
    public string  $Sistema        = 'portal'; // retire() solo toca lo de ese sistema (en el colegio siempre 'portal')

    /** Una fila: Existe, IdObjeto, Tipo, SoloPost, Acceso, Permitido. */
    public function authorize(): array
    {
        $this->db->command('sp_objeto_autorizar');
        $this->db->addParameter('IdCuenta',   $this->IdCuenta);
        $this->db->addParameter('Componente', $this->Componente);
        $this->db->addParameter('Metodo',     $this->Metodo);
        return $this->db->execute();
    }

    /** Filas (Metodo) con lo que la cuenta tiene concedido de este componente. */
    public function granted(): array
    {
        $this->db->command('sp_objeto_concedidos');
        $this->db->addParameter('IdCuenta',   $this->IdCuenta);
        $this->db->addParameter('Componente', $this->Componente);
        return $this->db->execute();
    }

    /** Grupos y vistas que la cuenta puede abrir (el menú). */
    public function menu(): array
    {
        $this->db->command('sp_objeto_menu');
        $this->db->addParameter('IdCuenta', $this->IdCuenta);
        return $this->db->execute();
    }

    /** Todo el catálogo. */
    public function list(): array
    {
        $this->db->command('sp_objeto_listar');
        return $this->db->execute();
    }

    /** Alta o actualización de un nodo (un nodo que llega idéntico no cuenta como cambio). Regresa Id. */
    public function register(): array
    {
        $this->db->command('sp_objeto_registrar');
        $this->db->addParameter('Componente',     $this->Componente);
        $this->db->addParameter('Clase',          $this->Clase);
        $this->db->addParameter('Tipo',           $this->Tipo);
        $this->db->addParameter('Padre',          $this->Padre);
        $this->db->addParameter('Clave',          $this->Clave);
        $this->db->addParameter('Nombre',         $this->Nombre);
        $this->db->addParameter('Icono',          $this->Icono);
        $this->db->addParameter('Orden',          $this->Orden);
        $this->db->addParameter('SoloPost',       $this->SoloPost);
        $this->db->addParameter('Acceso',         $this->Acceso);
        $this->db->addParameter('Sensible',       $this->Sensible);
        $this->db->addParameter('PaginaAnterior', $this->PaginaAnterior);
        return $this->db->execute();
    }

    /** La vista Componente/Clave usa el método UsaComponente/UsaClave. */
    public function registerUse(): array
    {
        $this->db->command('sp_objeto_uso_registrar');
        $this->db->addParameter('Componente',     $this->Componente);
        $this->db->addParameter('Clave',          $this->Clave);
        $this->db->addParameter('UsaComponente',  $this->UsaComponente);
        $this->db->addParameter('UsaClave',       $this->UsaClave);
        return $this->db->execute();
    }

    /** Desactiva lo que no esté en la lista de vigentes. Regresa Afectadas. */
    public function retire(): array
    {
        $this->db->command('sp_objeto_retirar');
        $this->db->addParameter('Vigentes', $this->Vigentes);
        $this->db->addParameter('Sistema',  $this->Sistema);
        return $this->db->execute();
    }
}
