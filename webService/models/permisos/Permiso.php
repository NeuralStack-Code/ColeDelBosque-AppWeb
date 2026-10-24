<?php
/** Model Permiso (rol). Lo carga Permiso::fromTable() desde sp_permiso_listar y Permiso::fromForm() desde su formulario. */
class Permiso extends Model
{
    /** Roles base del sistema (1 administrador · 2 maestro · 3 alumno · 4 desarrollador): no se borran. */
    public const LAST_SYSTEM_ROLE = 4;

    public ?int   $IdPermiso  = null;
    public string $Nombre     = '';
    public int    $NumCuentas = 0;      // cuántas cuentas tienen este rol

    public function isSystem(): bool
    {
        return $this->IdPermiso !== null && $this->IdPermiso >= 1 && $this->IdPermiso <= self::LAST_SYSTEM_ROLE;
    }
}
