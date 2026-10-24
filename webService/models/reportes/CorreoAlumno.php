<?php
/**
 * Model de un alumno con los correos de sus tutores (llegan encriptados; el controller los descifra).
 * Lo carga CorreoAlumno::fromRow() desde sp_reporte_alumno y sp_alumno_correo.
 */
class CorreoAlumno extends Model
{
    public ?int    $IdCuenta       = null;
    public string  $NombreCompleto = '';
    public ?string $Matricula      = null;   // solo sp_alumno_correo (encriptada)
    public ?string $CorreoTutor    = null;
    public ?string $CorreoTutor2   = null;

    /** Los correos (ya descifrados), sin vacíos ni repetidos. */
    public function correos(): array
    {
        return array_values(array_unique(array_filter([$this->CorreoTutor, $this->CorreoTutor2], fn($c) => $c !== null && $c !== '')));
    }
}
