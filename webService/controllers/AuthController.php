<?php

/**
 * Recurso: auth.  Ruta: /api/auth?action=login|logout
 * Autenticación por matrícula (permiso: 1 admin · 2 maestro · 3 padre · 4 dev).
 */
class AuthController
{
    private AuthBusiness $auth;

    public function __construct(mysqli $conexion)
    {
        $this->auth = new AuthBusiness($conexion);
    }

    public function login(): void
    {
        $matricula = trim($_POST['matricula'] ?? '');
        if ($matricula === '') response(400, false, 'Ingresa tu matrícula.');

        $row = $this->auth->autenticar($matricula);
        if (!$row) response(401, false, 'ID incorrecto. Verifica tu matrícula.');

        $permiso = (int) $row['permiso_id'];
        $_SESSION['usuario'] = trim($row['nombre'] . ' ' . $row['paterno']);
        $_SESSION['permiso'] = $permiso;
        $_SESSION['id']      = (int) $row['usuario_id'];

        $destinos = [
            1 => BASE_URL . '/administrador',
            2 => BASE_URL . '/maestro',
            3 => BASE_URL . '/padre',
            4 => BASE_URL . '/desarrollador',
        ];
        $redirect = $destinos[$permiso] ?? BASE_URL . '/';

        response(200, true, 'Bienvenido, ' . $_SESSION['usuario'] . '.', ['redirect' => $redirect]);
    }

    public function logout(): void
    {
        $_SESSION = [];
        session_destroy();
        response(200, true, 'Sesión cerrada.', ['redirect' => BASE_URL . '/inicio-sesion']);
    }
}
