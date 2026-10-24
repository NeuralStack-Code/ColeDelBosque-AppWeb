<?php
/**
 * Model Perfil: una persona con su cuenta de acceso (cualquier rol). Lo carga Perfil::fromTable() desde
 * sp_perfil_listar y Perfil::fromForm() desde su formulario. La matrícula llega encriptada de la base:
 * el controller la deja legible antes de mandarla a la pantalla.
 */
class Perfil extends Model
{
    public ?int    $IdCuenta       = null;
    public string  $Matricula      = '';
    public int     $IdPermiso      = 0;      // 1 administrador · 2 maestro · 3 alumno · 4 desarrollador
    public ?string $PermisoNombre  = null;
    public int     $IdGrupo        = 0;      // 0 = sin grupo
    public ?string $Grado          = null;
    public string  $Estatus        = 'activo';   // clave del catálogo status (ámbito alumno)
    public ?string $EstatusNombre  = null;
    public ?int    $IdUsuario      = null;
    public string  $Nombre         = '';
    public string  $Paterno        = '';
    public ?string $Materno        = null;   // nunca es obligatorio
    public string  $NombreCompleto = '';

    protected function onLoad(array $row): void
    {
        $this->NombreCompleto = trim($this->Nombre . ' ' . $this->Paterno . ' ' . $this->Materno);
        if ($this->Estatus === '') $this->Estatus = 'activo';
    }

    /** Solo maestros y alumnos pertenecen a un grupo. */
    public function hasGroup(): bool
    {
        return $this->IdPermiso === 2 || $this->IdPermiso === 3;
    }
}
