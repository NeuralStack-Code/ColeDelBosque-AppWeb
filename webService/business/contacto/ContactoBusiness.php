<?php
require_once dirname(__DIR__, 3) . '/apiService/core/mail.php'; // enviarCorreo() → API central

/**
 * Envío del formulario de contacto por la API central (correo centralizado).
 * NO usa SMTP local ni MAIL_* del producto. Lanza \Throwable si falla (el
 * ContactoController lo atrapa y responde 500).
 */
class ContactoBusiness
{
    public function enviar(string $nombre, string $tel, string $coment): void
    {
        // Destino de los mensajes (no es credencial SMTP): configurable por el producto.
        $destino = $_ENV['MAIL_TO'] ?? ($_ENV['MAIL_FROM'] ?? '');

        $texto = "Has recibido un mensaje desde la página web del Colegio del Bosque.\n\n"
            . "Nombre:     {$nombre}\n"
            . "Teléfono:   {$tel}\n"
            . "Comentario: {$coment}\n\n"
            . "Enviado el: " . date('d/m/Y H:i');
        $html = nl2br(htmlspecialchars($texto, ENT_QUOTES, 'UTF-8'));

        $err = enviarCorreo($destino, 'Nuevo mensaje de contacto — Colegio del Bosque', $html, [
            'altBody' => $texto,
        ]);
        if ($err !== '') {
            throw new \RuntimeException($err);
        }
    }
}
