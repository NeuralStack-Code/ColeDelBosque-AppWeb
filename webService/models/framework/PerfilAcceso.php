<?php
/** Model de un perfil del catálogo de permisos (sp_catalogo_perfil_listar) con los objetos que concede. */
class PerfilAcceso extends Model
{
    public ?int   $IdPerfil    = null;
    public string $Nombre      = '';
    public string $Descripcion = '';
    public bool   $EsAdmin     = false;     // concede todo sin filas en PerfilObjeto
    public bool   $Activo      = true;
    public string $Inicio      = '';        // pantalla de inicio de quien lo tiene ('/maestro')
    public int    $Cuentas     = 0;
    public int    $Objetos     = 0;
    public array  $Concede     = [];        // IdObjeto que concede; lo llena assignGrants()

    /** Le pone a cada perfil de la lista lo que concede, a partir de la tabla (IdPerfil, IdObjeto). */
    public static function assignGrants(array $perfiles, array $tabla): void
    {
        $porPerfil = [];
        foreach ($tabla as $fila) $porPerfil[(int) $fila['IdPerfil']][] = (int) $fila['IdObjeto'];
        foreach ($perfiles as $perfil) $perfil->Concede = $porPerfil[$perfil->IdPerfil] ?? [];
    }
}
