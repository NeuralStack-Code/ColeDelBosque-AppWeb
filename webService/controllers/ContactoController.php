<?php

/**
 * Recurso: contacto.  Ruta: /api/contacto (POST, sin action → index).
 * Envía el mensaje del formulario de contacto por correo.
 */
class ContactoController
{
    private ContactoBusiness $contacto;

    public function __construct(?mysqli $conexion = null)
    {
        $this->contacto = new ContactoBusiness();
    }

    public function index(): void
    {
        $nombre = trim($_POST['nombre'] ?? '');
        $tel    = trim($_POST['tel']    ?? '');
        $coment = trim($_POST['coment'] ?? '');

        if ($nombre === '' || $tel === '' || $coment === '') {
            response(400, false, 'Completa todos los campos.');
        }

        try {
            $this->contacto->enviar($nombre, $tel, $coment);
            response(200, true, 'Se ha enviado tu mensaje. Nos comunicaremos contigo en breve.');
        } catch (\Throwable $e) {
            error_log('Mail error (contacto): ' . $e->getMessage());
            response(500, false, 'No se pudo enviar el mensaje. Intenta más tarde.');
        }
    }
}
