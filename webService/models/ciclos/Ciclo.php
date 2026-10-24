<?php
/** Model Ciclo escolar, con su estado del catálogo status. Lo carga Ciclo::fromTable() desde sp_ciclo_listar y Ciclo::fromForm() desde su formulario. */
class Ciclo extends Model
{
    public ?int    $IdCiclo       = null;
    public string  $Nombre        = '';
    public string  $FechaInicio   = '';     // AAAA-MM-DD
    public string  $FechaFin      = '';
    public bool    $Activo        = false;  // solo un ciclo está activo a la vez
    public ?string $Estatus       = null;   // clave del catálogo: activo · cerrado
    public ?string $EstatusNombre = null;
}
