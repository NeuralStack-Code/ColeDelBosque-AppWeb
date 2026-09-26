<?php
/**
 * Envío de correo a través de la API central de NeuralStack (v1/mail/send).
 *
 * El producto YA NO manda SMTP: delega en la api (correo centralizado). El producto
 * no necesita MAIL_HOST/USER/PASSWORD; solo KEY_TOKEN + API_BASE_URL (apiClient).
 *
 * Devuelve '' si se envió, o el mensaje de error (para log).
 */
require_once __DIR__ . '/apiClient.php';

/**
 * @param array $opts Opcional: fromName, altBody, replyTo, replyToName.
 */
function enviarCorreo(string $to, string $subject, string $htmlBody, array $opts = []): string
{
    $r = apiPost('mail/send', array_merge([
        'to'      => $to,
        'subject' => $subject,
        'body'    => $htmlBody,
    ], $opts));

    if ($r['ok']) return '';

    return $r['message'] !== ''
        ? $r['message']
        : 'No se pudo enviar el correo (status ' . $r['status'] . ').';
}
