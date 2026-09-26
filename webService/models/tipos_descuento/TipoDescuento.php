<?php
/** Entidad TipoDescuento (con estatus del catálogo). Solo variables. */
class TipoDescuento
{
    public ?int    $id_descuento = null;
    public ?string $nombre       = null;
    public ?float  $porcentaje   = null;
    public ?string $aplica_a     = null;   // colegiatura | inscripcion
    public ?int    $activo       = null;

    public function __construct(array $fila = [])
    {
        foreach ($fila as $col => $val) {
            if (property_exists($this, $col)) $this->$col = $val;
        }
    }
}
