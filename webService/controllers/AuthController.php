<?php

/**
 * Acceso a la plataforma. Se entra con la matrícula (no hay contraseña).
 *   /auth/entrar  → pantalla de inicio de sesión · POST inicia sesión
 *   /auth/salir   → cierra la sesión
 * La URL anterior /inicio-sesion redirige a /auth/entrar (tabla Rutas).
 */
class AuthController extends Controller
{
    public const MODULE = 'sitio';
    /** GET: la pantalla de entrar (si ya entró, a su panel) · POST: inicia sesión con la matrícula. Pública. */
    public function entrar(string $Matricula = ''): void
    {
        if ($this->method === 'GET') {
            if ($this->user() !== null) $this->redirect($this->home());   // ya entró: a su panel
            $this->view('site/inicio_sesion');
            return;
        }

        $this->ensure($Matricula !== '', 'Ingresa tu matrícula.');

        $this->api->command('Sitio', 'Auth', 'Authenticate');
        $this->api->addParameter('Matricula', 'S', Cifrado::encrypt($Matricula));   // se encripta y se compara: la base nunca la tiene en claro
        $result = $this->api->execute();
        if ($result->status()) $this->failure($result);
        $this->ensure($result->row() !== null, 'ID incorrecto. Verifica tu matrícula.', 401);
        $sesion = Sesion::fromRow($result->row());

        // Sus perfiles (catálogo de permisos): el primero en importancia da su pantalla de inicio y el nombre del panel.
        $this->api->command('Framework', 'PerfilAcceso', 'AccountProfiles');
        $this->api->addParameter('IdCuenta', 'I', (int) $sesion->IdCuenta);
        $result = $this->api->execute();
        if ($result->status()) $this->failure($result);
        $principal = null;
        foreach ($result->table() as $perfil) {
            if ((int) $perfil['Activo'] === 1) { $principal = $perfil; break; }
        }

        session_regenerate_id(true);
        $_SESSION = [];
        $_SESSION['usuario']   = $sesion->displayName();
        $_SESSION['id']        = $sesion->IdUsuario;            // la persona (auditoría, sus grupos, sus recibos)
        $_SESSION['id_cuenta'] = $sesion->IdCuenta;             // la cuenta (sus perfiles)
        $_SESSION['perfil']    = (string) ($principal['Nombre'] ?? '');
        $_SESSION['inicio']    = (string) ($principal['Inicio'] ?? '');

        $this->success('Bienvenido, ' . $sesion->displayName() . '.', ['redirect' => $this->home()]);
    }

    public function salir(): void
    {
        $_SESSION = [];
        session_destroy();
        $this->success('Sesión cerrada.', ['redirect' => '/auth/entrar']);
    }
}
