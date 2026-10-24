<?php
/** Correo que le llega al colegio cuando alguien escribe en el formulario de contacto (lo manda ContactoController::enviar por la api central). */
class CorreoContacto
{
    public const SUBJECT = 'Nuevo mensaje de contacto — Colegio del Bosque';

    /** El mensaje sin formato (también es el cuerpo alterno del correo). */
    public static function text(string $nombre, string $telefono, string $comentario): string
    {
        // El proyecto no fija zona horaria: la hora del mensaje va en la del colegio.
        $fecha = (new DateTime('now', new DateTimeZone('America/Mexico_City')))->format('d/m/Y H:i');

        return "Has recibido un mensaje desde la página web del Colegio del Bosque.\n\n"
             . "Nombre:     {$nombre}\n"
             . "Teléfono:   {$telefono}\n"
             . "Comentario: {$comentario}\n\n"
             . "Enviado el: {$fecha}";
    }

    public static function body(string $nombre, string $telefono, string $comentario): string
    {
        return nl2br(e(self::text($nombre, $telefono, $comentario)));
    }
}
