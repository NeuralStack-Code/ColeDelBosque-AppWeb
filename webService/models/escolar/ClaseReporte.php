<?php
/** Model de una clase capturada en el reporte semanal (grupo + materia + día). Lo carga ClaseReporte::fromTable() desde sp_reporte_clases y fromRow() desde sp_reporte_clase. */
class ClaseReporte extends Model
{
    public ?int    $IdClase     = null;
    public ?string $Fecha       = null;   // AAAA-MM-DD
    public ?int    $IdMateria   = null;
    public ?string $Materia     = null;
    public string  $Tema        = '';
    public int     $Incidencias = 0;      // alumnos con alguna incidencia
}
