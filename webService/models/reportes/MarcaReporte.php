<?php
/** Model de una incidencia de un alumno en una clase (casilla marcada o nota). Lo carga MarcaReporte::fromTable() desde sp_reporte_marcas. */
class MarcaReporte extends Model
{
    public ?int    $IdCuenta  = null;
    public ?int    $IdColumna = null;
    public bool    $Marcado   = false;
    public ?string $Nota      = null;
}
