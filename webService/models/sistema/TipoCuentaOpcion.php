<?php
/**
 * Model de un tipo de cuenta (tabla permisos: administrador, maestro, alumno, desarrollador) para el formulario de
 * cuentas. Lo carga TipoCuentaOpcion::fromTable() desde sp_permiso_listar. Dice QUÉ ES la persona, no qué puede abrir.
 */
class TipoCuentaOpcion extends Model
{
    public ?int   $IdPermiso  = null;
    public string $Nombre     = '';
    public int    $NumCuentas = 0;      // cuántas cuentas son de este tipo
}
