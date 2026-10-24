<?php
/** Model de un alumno con sus notas en una materia (pantalla de la maestra). Lo carga AlumnoCalificacion::fromTable() desde sp_calificacion_listar. */
class AlumnoCalificacion extends Model
{
    public ?int    $IdCuenta       = null;
    public string  $NombreCompleto = '';
    public ?float  $P1             = null;
    public ?float  $P2             = null;
    public ?float  $P3             = null;
    public ?float  $CalifFinal     = null;
    public ?string $Reporte        = null;
}
