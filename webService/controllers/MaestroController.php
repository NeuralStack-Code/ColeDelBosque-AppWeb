<?php
/**
 * Panel del maestro (/maestro): los accesos a calificaciones y reportes. Antes era una página suelta con guard.php;
 * ahora la sirve un controller para que el catálogo decida quién la abre (el perfil Maestro la concede).
 */
class MaestroController extends Controller
{
    public const MODULE = 'escolar';

    public function index(): void
    {
        $this->view('maestro/home', ['nombre' => $this->user() ?: 'Maestro']);
    }
}
