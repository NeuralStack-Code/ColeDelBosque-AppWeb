<?php
/** Entidad EsquemaPago (montos y meses a cobrar de un ciclo escolar). Solo variables. */
class EsquemaPago
{
    public ?int    $id_esquema        = null;
    public ?int    $ciclo_id          = null;
    public ?string $ciclo_nombre      = null;
    public ?string $nombre            = null;
    public ?float  $monto_inscripcion = null;
    public ?float  $monto_colegiatura = null;
    public ?string $primer_mes        = null;   // Y-m-01
    public ?int    $num_meses         = null;
    public ?int    $dia_vencimiento   = null;
    public ?float  $recargo_monto     = null;   // pesos por cada mes vencido

    public function __construct(array $fila = [])
    {
        foreach ($fila as $col => $val) {
            if (property_exists($this, $col)) $this->$col = $val;
        }
    }
}
