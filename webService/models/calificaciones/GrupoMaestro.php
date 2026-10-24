<?php
/**
 * Model de un grupo donde captura la maestra: el suyo (todas sus materias) o uno de sus niveles de inglés (solo Inglés).
 * Lo carga GrupoMaestro::fromTable() desde sp_calificacion_grupos.
 */
class GrupoMaestro extends Model
{
    public ?int   $IdGrupo    = null;
    public string $Grado      = '';
    public bool   $SoloIngles = false;
}
