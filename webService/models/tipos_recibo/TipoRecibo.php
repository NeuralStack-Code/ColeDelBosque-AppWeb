<?php
/** Entidad TipoRecibo. Solo variables. */
class TipoRecibo
{
    public ?int    $id_tipo    = null;
    public ?string $nombre     = null;
    public ?string $naturaleza = null;   // ingreso | gasto

    public function __construct(array $fila = [])
    {
        foreach ($fila as $col => $val) {
            if (property_exists($this, $col)) $this->$col = $val;
        }
    }
}
