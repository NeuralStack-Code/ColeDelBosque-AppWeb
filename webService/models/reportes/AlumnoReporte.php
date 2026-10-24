<?php
/**
 * Model de un alumno en el reporte semanal: si tiene a quién mandarle el reporte y cuándo se le mandó el de la semana.
 * Lo carga AlumnoReporte::fromTable() desde sp_reporte_alumnos (EnviadoEn lo llena el controller con sp_reporte_envios).
 */
class AlumnoReporte extends Model
{
    public ?int    $IdCuenta       = null;
    public string  $NombreCompleto = '';
    public bool    $TieneCorreo    = false;
    public ?string $EnviadoEn      = null;   // AAAA-MM-DD HH:MM:SS
}
