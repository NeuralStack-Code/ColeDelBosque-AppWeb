<?php
/**
 * Entidad Materia (con los grupos vinculados, tal como los lee el listado).
 * Solo variables. `grupo_ids` es un arreglo de ids de grupo (muchos-a-muchos).
 */
class Materia
{
    public ?int    $id_materia = null;
    public ?string $nombre     = null;
    public ?string $grupos_txt = null;
    public array   $grupo_ids  = [];
    public ?int    $num_grupos = null;

    public function __construct(array $fila = [])
    {
        foreach ($fila as $col => $val) {
            if (property_exists($this, $col)) $this->$col = $val;
        }
    }
}
