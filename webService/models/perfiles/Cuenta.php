<?php
/**
 * Entidad Cuenta (perfil) tal como la lee el listado del panel dev.
 * Solo variables. `matricula` viaja YA desencriptada.
 */
class Cuenta
{
    public ?int    $id_cuenta       = null;
    public ?string $matricula       = null;
    public ?int    $permiso_id      = null;
    public ?string $permiso_nombre  = null;
    public ?int    $grupo_id        = null;
    public ?string $grado           = null;
    public ?string $estatus         = null;
    public ?string $estatus_nombre  = null;
    public ?int    $id_usuario      = null;
    public ?string $nombre          = null;
    public ?string $paterno         = null;
    public ?string $materno         = null;
    public ?string $nombre_completo = null;

    public function __construct(array $fila = [])
    {
        foreach ($fila as $col => $val) {
            if (property_exists($this, $col)) $this->$col = $val;
        }
    }
}
