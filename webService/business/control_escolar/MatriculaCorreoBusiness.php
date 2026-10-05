<?php
require_once dirname(__DIR__, 3) . '/apiService/core/mail.php'; // enviarCorreo() → API central

/**
 * Correo con la matrícula (clave de acceso al portal) para los tutores del alumno.
 * Sin SQL: recibe los datos ya consultados.
 */
class MatriculaCorreoBusiness
{
    private function e(string $s): string
    {
        return htmlspecialchars($s, ENT_QUOTES, 'UTF-8');
    }

    /** Dirección pública del portal (APP_URL del .env; nunca el Host de la petición). */
    private function urlPortal(): string
    {
        return rtrim($_ENV['APP_URL'] ?? 'https://colegiodelbosquelerma.com', '/') . '/inicio-sesion';
    }

    private function html(string $alumno, string $matricula): string
    {
        $url = $this->e($this->urlPortal());
        return '<div style="font-family:Arial,Helvetica,sans-serif;max-width:560px;margin:0 auto;color:#1f2233;">'
            . '<div style="background:#5b3ee0;color:#ffffff;padding:20px 24px;border-radius:12px 12px 0 0;">'
            . '<div style="font-size:20px;font-weight:bold;">Colegio del Bosque</div>'
            . '<div style="font-size:13px;opacity:.9;">Acceso al portal de familias</div>'
            . '</div>'
            . '<div style="padding:20px 24px;border:1px solid #eceafb;border-top:none;border-radius:0 0 12px 12px;">'
            . '<p style="margin:0 0 12px;font-size:14px;">Estimada familia:</p>'
            . '<p style="margin:0 0 16px;font-size:14px;">Esta es la matrícula de <strong>' . $this->e($alumno) . '</strong>. '
            . 'Con ella puede entrar al portal para consultar calificaciones y pagos.</p>'
            . '<p style="margin:0 0 16px;padding:14px;background:#efeaff;border-radius:10px;text-align:center;'
            . 'font-size:24px;font-weight:bold;letter-spacing:3px;color:#3d2aa0;">' . $this->e($matricula) . '</p>'
            . '<p style="margin:0 0 16px;font-size:14px;">Entrar al portal: <a href="' . $url . '" style="color:#5b3ee0;">' . $url . '</a></p>'
            . '<p style="margin:0;font-size:13px;color:#5b607a;">La matrícula es personal: no la comparta. '
            . 'Este correo es informativo, favor de no responder a esta dirección.</p>'
            . '</div></div>';
    }

    /**
     * Envía la matrícula a cada tutor.
     * @return array{enviados:int,errores:string[]}
     */
    public function enviar(array $correos, string $alumno, string $matricula): array
    {
        $html     = $this->html($alumno, $matricula);
        $asunto   = "Matrícula de $alumno — Colegio del Bosque";
        $enviados = 0;
        $errores  = [];
        foreach ($correos as $correo) {
            $err = enviarCorreo($correo, $asunto, $html, ['fromName' => 'Colegio del Bosque']);
            if ($err === '') $enviados++;
            else             $errores[] = $err;
        }
        return ['enviados' => $enviados, 'errores' => $errores];
    }
}
