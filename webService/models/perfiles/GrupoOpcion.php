<?php
/** Model de un grupo como opción de una lista. Lo carga GrupoOpcion::fromTable() desde sp_perfil_grupos, sp_colegiatura_grupos y sp_esquema_grupos. */
class GrupoOpcion extends Model
{
    public ?int    $IdGrupo = null;
    public string  $Grado   = '';
    public ?string $Ciclo   = null;   // nombre de su ciclo escolar
    public ?int    $IdCiclo = null;
}
