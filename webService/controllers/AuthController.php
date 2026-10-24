<?php
require_once dirname(__DIR__, 2) . '/apiService/core/crypto.php';   // encrypt(): la matrícula se guarda encriptada

/**
 * Acceso a la plataforma. Se entra con la matrícula (no hay contraseña).
 *   /auth/entrar  → pantalla de inicio de sesión · POST inicia sesión
 *   /auth/salir   → cierra la sesión
 * La URL anterior /inicio-sesion redirige a /auth/entrar (index.php raíz).
 */
class AuthController extends Controller
{
    /** Aquí todavía no hay sesión que exigir: la pantalla acepta GET y todo lo demás es POST. */
    public function authorize(string $action): void
    {
        if ($action === 'entrar' && $this->method === 'GET') return;
        $this->requirePost();
    }

    public function entrar(string $Matricula = ''): void
    {
        if ($this->method === 'GET') {
            if ($this->user() !== null) $this->redirect(self::homeOf($this->roleId()));   // ya entró: a su panel
            $this->view('site/inicio_sesion');
            return;
        }

        $this->ensure($Matricula !== '', 'Ingresa tu matrícula.');

        $this->api->command('Auth', 'Auth', 'Authenticate');
        $this->api->addParameter('Matricula', 'S', encrypt($Matricula));   // se encripta y se compara: la base nunca la tiene en claro
        $result = $this->api->execute();

        if (!$result->status()) {
            $this->ensure($result->row() !== null, 'ID incorrecto. Verifica tu matrícula.', 401);
            $sesion = Sesion::fromRow($result->row());

            session_regenerate_id(true);
            $_SESSION['usuario'] = $sesion->displayName();
            $_SESSION['permiso'] = $sesion->IdPermiso;
            $_SESSION['id']      = $sesion->IdUsuario;

            $this->success('Bienvenido, ' . $sesion->displayName() . '.', ['redirect' => self::homeOf($sesion->IdPermiso)]);
        } else {
            $this->failure($result);
        }
    }

    public function salir(): void
    {
        $_SESSION = [];
        session_destroy();
        $this->success('Sesión cerrada.', ['redirect' => '/auth/entrar']);
    }
}
