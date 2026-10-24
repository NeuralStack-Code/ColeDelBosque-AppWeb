<?php
require_once dirname(__DIR__, 2) . '/apiService/core/mail.php';   // enviarCorreo() → api central

/**
 * Contacto: cualquiera puede escribirle al colegio, sin cuenta.
 *   /contacto/index   → pantalla de contacto
 *   /contacto/enviar  → manda el mensaje por correo al colegio
 * No guarda nada en la base: el mensaje sale por la api central (correo centralizado, sin SMTP local).
 */
class ContactoController extends Controller
{
    /** No usa la base. */
    public const CONNECTION = 'ninguna';

    protected array $publicActions = ['index', 'enviar'];

    public function index(): void
    {
        // El administrador y el desarrollador trabajan en su panel.
        if (in_array($this->roleId(), [self::ADMIN, self::DEVELOPER], true)) $this->redirect(self::homeOf($this->roleId()));
        $this->view('site/contacto');
    }

    public function enviar(string $Nombre = '', string $Telefono = '', string $Comentario = ''): void
    {
        $this->ensure($Nombre !== '' && $Telefono !== '' && $Comentario !== '', 'Completa todos los campos.');
        $this->ensure((bool) preg_match('/^.{1,100}$/us', $Nombre) && (bool) preg_match('/^.{1,30}$/us', $Telefono),
                      'El nombre admite máximo 100 caracteres y el teléfono 30.');
        $this->ensure((bool) preg_match('/^.{1,3000}$/us', $Comentario), 'El comentario admite máximo 3000 caracteres.');

        // A quién le llega (no es una credencial): lo define el proyecto en su .env.
        $to    = $_ENV['MAIL_TO'] ?? ($_ENV['MAIL_FROM'] ?? '');
        $error = $to === '' ? 'Falta MAIL_TO en el .env.' : enviarCorreo(
            $to, CorreoContacto::SUBJECT, CorreoContacto::body($Nombre, $Telefono, $Comentario),
            ['altBody' => CorreoContacto::text($Nombre, $Telefono, $Comentario)]);

        if ($error === '') {
            $this->success('Se ha enviado tu mensaje. Nos comunicaremos contigo en breve.');
        } else {
            error_log('[Colegio contacto] ' . $error);
            response(500, false, 'No se pudo enviar el mensaje. Intenta más tarde.');
        }
    }
}
