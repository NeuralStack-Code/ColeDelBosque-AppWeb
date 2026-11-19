<?php
/**
 * Model Cuenta: una persona con su cuenta de acceso (cualquier tipo) y sus perfiles. Lo carga Cuenta::fromTable() desde
 * sp_cuenta_listar y Cuenta::fromForm() desde su formulario. La matrícula llega encriptada de la base:
 * el controller la deja legible antes de mandarla a la pantalla.
 */
class Cuenta extends Model
{
    public ?int    $IdCuenta       = null;
    public string  $Matricula      = '';
    public int     $IdPermiso      = 0;      // tipo de cuenta (libs/TipoCuenta.php): no da permisos
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
    public array   $Perfiles       = [];     // [IdPerfil, …] del catálogo de permisos; lo llena el controller

    protected function onLoad(array $row): void
    {
        $this->NombreCompleto = trim($this->Nombre . ' ' . $this->Paterno . ' ' . $this->Materno);
        if ($this->Estatus === '') $this->Estatus = 'activo';
    }

    /** Solo maestros y alumnos pertenecen a un grupo. */
    public function hasGroup(): bool
    {
        return $this->IdPermiso === TipoCuenta::MAESTRO || $this->IdPermiso === TipoCuenta::ALUMNO;
    }
}
