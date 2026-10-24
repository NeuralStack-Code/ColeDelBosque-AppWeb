<?php
/**
 * Model EsquemaPago: los montos y meses que se cobran en un ciclo escolar.
 * Lo carga EsquemaPago::fromTable() desde sp_esquema_listar, fromRow() desde sp_esquema_obtener y fromForm() desde su formulario.
 */
class EsquemaPago extends Model
{
    public ?int    $IdEsquema        = null;
    public ?int    $IdCiclo          = null;
    public ?string $CicloNombre      = null;
    public string  $Nombre           = '';
    public float   $MontoInscripcion = 0.0;
    public float   $MontoColegiatura = 0.0;
    public string  $PrimerMes        = '';     // AAAA-MM-01 (del formulario llega AAAA-MM)
    public int     $NumMeses         = 0;
    public int     $DiaVencimiento   = 0;
    public float   $RecargoMonto     = 0.0;    // pesos por cada mes vencido
}
