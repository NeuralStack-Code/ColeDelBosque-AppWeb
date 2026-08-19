<?php
/**
 * Action: contacto  →  /api/contacto  (POST)
 * Envía el mensaje del formulario de contacto por correo (SMTP vía .env).
 * Depende de: response() y $_ENV (definidos/cargados por el router y env.php).
 */
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require_once __DIR__ . '/../libs/PHPMailer/src/Exception.php';
require_once __DIR__ . '/../libs/PHPMailer/src/PHPMailer.php';
require_once __DIR__ . '/../libs/PHPMailer/src/SMTP.php';

$nombre = trim($_POST['nombre'] ?? '');
$tel    = trim($_POST['tel']    ?? '');
$coment = trim($_POST['coment'] ?? '');

if ($nombre === '' || $tel === '' || $coment === '') {
    response(400, false, 'Completa todos los campos.');
}

$mail = new PHPMailer(true);

try {
    $mail->isSMTP();
    $mail->Host       = $_ENV['MAIL_HOST'] ?? '';
    $mail->SMTPAuth   = true;
    $mail->Username   = $_ENV['MAIL_USERNAME'] ?? '';
    $mail->Password   = $_ENV['MAIL_PASSWORD'] ?? '';
    $mail->SMTPSecure = (($_ENV['MAIL_ENCRYPTION'] ?? 'ssl') === 'tls')
        ? PHPMailer::ENCRYPTION_STARTTLS
        : PHPMailer::ENCRYPTION_SMTPS;
    $mail->Port       = (int) ($_ENV['MAIL_PORT'] ?? 465);
    $mail->CharSet    = 'UTF-8';

    $mail->setFrom($_ENV['MAIL_FROM'] ?? '', $_ENV['MAIL_FROM_NAME'] ?? 'Colegio del Bosque');
    $mail->addAddress($_ENV['MAIL_TO'] ?? ($_ENV['MAIL_FROM'] ?? ''));

    $mail->isHTML(false);
    $mail->Subject = 'Nuevo mensaje de contacto — Colegio del Bosque';
    $mail->Body =
        "Has recibido un mensaje desde la página web del Colegio del Bosque.\r\n\r\n" .
        "Nombre:     $nombre\r\n" .
        "Teléfono:   $tel\r\n" .
        "Comentario: $coment\r\n\r\n" .
        "Enviado el: " . date('d/m/Y H:i');

    $mail->send();
    response(200, true, 'Se ha enviado tu mensaje. Nos comunicaremos contigo en breve.');

} catch (Exception $e) {
    error_log('Mail error (contacto): ' . $mail->ErrorInfo);
    response(500, false, 'No se pudo enviar el mensaje. Intenta más tarde.');
}
