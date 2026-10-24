<?php
/**
 * Model de la cuenta del alumno en sesión (portal del alumno). Lo carga CuentaAlumno::fromRow() desde sp_padre_cuenta.
 * Los correos llegan encriptados: el controller los descifra.
 */
class CuentaAlumno extends Model
{
    public ?int    $IdCuenta     = null;
    public ?int    $IdGrupo      = null;
    public ?string $Grado        = null;
    public ?string $CorreoTutor  = null;
    public ?string $CorreoTutor2 = null;
}
