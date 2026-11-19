<?php
/** Model Sesion: quién acaba de entrar. Lo carga Sesion::fromRow() desde sp_auth_autenticar. */
class Sesion extends Model
{
    public ?int   $IdCuenta  = null;
    public ?int   $IdUsuario = null;
    public int    $IdPermiso = 0;      // tipo de cuenta (libs/TipoCuenta.php): no da permisos
    public string $Nombre    = '';
    public string $Paterno   = '';

    /** Nombre con el que se le saluda y que se muestra en su panel. */
    public function displayName(): string
    {
        return trim($this->Nombre . ' ' . $this->Paterno);
    }
}
