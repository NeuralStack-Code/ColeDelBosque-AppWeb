<?php
/**
 * Panel del desarrollador (/desarrollador): cuentas, perfiles y permisos, y el visor de tablas. Antes era una página
 * suelta con guard.php; ahora la sirve un controller y el catálogo decide quién la abre (el perfil Desarrollador).
 */
class DesarrolladorController extends Controller
{
    public const MODULE = 'sistema';

    public function index(): void
    {
        $this->view('dev/home', ['nombre' => $this->user() ?: 'Desarrollador']);
    }
}
