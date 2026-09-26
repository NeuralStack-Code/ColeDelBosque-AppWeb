<?php
/** Entidad Permiso (rol). Solo variables. */
class Permiso
{
    public ?int    $id_permiso  = null;
    public ?string $nombre      = null;
    public ?int    $num_cuentas = null;

    public function __construct(array $fila = [])
    {
        foreach ($fila as $col => $val) {
            if (property_exists($this, $col)) $this->$col = $val;
        }
    }
}
