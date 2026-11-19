<?php
/**
 * Model Persona: la cuenta de un alumno o un maestro, con su nombre y su grupo. Lo carga Persona::fromTable() desde
 * sp_persona_listar y fromForm() desde los formularios de alumnos y maestros. La matrícula y los correos llegan
 * encriptados de la base: el controller los descifra.
 */
class Persona extends Model
{
    public ?int    $IdCuenta      = null;
    public ?int    $IdUsuario     = null;
    public string  $Matricula     = '';
    public ?int    $IdGrupo       = null;   // 0 = sin grupo
    public ?string $Grado         = null;
    public string  $Nombre        = '';
    public string  $Paterno       = '';
    public ?string $Materno       = null;   // nunca es obligatorio
    public ?string $CorreoTutor   = null;   // alumnos: a dónde llega el reporte semanal y su matrícula
    public ?string $CorreoTutor2  = null;
    public ?string $NivelesIngles = null;   // maestros: 'basico,medio' (null = no da inglés)

    public function nombreCompleto(): string
    {
        return trim("{$this->Nombre} {$this->Paterno} " . ($this->Materno ?? ''));
    }
}
