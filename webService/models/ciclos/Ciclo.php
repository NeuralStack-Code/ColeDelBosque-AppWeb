<?php
/**
 * Entidad Ciclo (con los campos de estatus del catálogo, tal como los lee el
 * listado). SOLO variables: sin lógica ni SQL. Se hidrata desde una fila.
 */
class Ciclo
{
    public ?int    $id_ciclo       = null;
    public ?string $nombre         = null;
    public ?string $fecha_inicio   = null;
    public ?string $fecha_fin      = null;
    public ?int    $activo         = null;
    public ?string $estatus        = null;
    public ?string $estatus_nombre = null;

    public function __construct(array $fila = [])
    {
        foreach ($fila as $col => $val) {
            if (property_exists($this, $col)) $this->$col = $val;
        }
    }
}
