<?php
/**
 * Model de un alumno como opción de una lista (a quién aplicar un esquema de pago), con su grupo y ciclo.
 * Lo carga AlumnoOpcion::fromTable() desde sp_esquema_alumnos. La matrícula llega encriptada: el controller la descifra.
 */
class AlumnoOpcion extends Model
{
    public ?int    $IdCuenta  = null;
    public string  $Nombre    = '';
    public string  $Matricula = '';
    public ?int    $IdGrupo   = null;
    public ?string $Grado     = null;
    public ?int    $IdCiclo   = null;
}
