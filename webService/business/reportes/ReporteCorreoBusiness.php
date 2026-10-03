<?php
require_once dirname(__DIR__, 3) . '/apiService/core/mail.php'; // enviarCorreo() → API central

/**
 * Correo del reporte semanal: arma el HTML (una tabla por día) y lo envía por la
 * API central. Sin SQL: recibe los datos ya consultados.
 */
class ReporteCorreoBusiness
{
    private const DIAS = [1 => 'Lunes', 'Martes', 'Miércoles', 'Jueves', 'Viernes', 'Sábado', 'Domingo'];

    private function e(?string $s): string
    {
        return htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8');
    }

    /** Y-m-d → d/m/Y */
    public function fechaCorta(string $fecha): string
    {
        return date('d/m/Y', strtotime($fecha));
    }

    /** Lo que lee el papá en la celda de una columna (vacío = sin incidencia). */
    private function celda(ReporteColumna $col, ?array $marca): string
    {
        if (!$marca) return '';
        if ($col->tipo === 'texto') return $this->e($marca['nota'] ?? '');
        return $marca['marcado'] ? $this->e($col->etiqueta ?: 'Sí') : '';
    }

    /**
     * @param ReporteColumna[] $columnas columnas activas, en orden
     * @param array            $clases   salida de ReporteBusiness::reporteAlumno()
     */
    public function html(string $alumno, string $grado, array $columnas, array $clases, string $lunes, string $viernes): string
    {
        $th = 'padding:8px 10px;font-size:11px;text-transform:uppercase;text-align:left;color:#ffffff;background:#3d2aa0;';
        $td = 'padding:8px 10px;font-size:13px;color:#1f2233;border-top:1px solid #ffffff;vertical-align:top;';

        $porDia = [];
        foreach ($clases as $c) $porDia[$c['fecha']][] = $c;

        $cuerpo = '';
        foreach ($porDia as $fecha => $lista) {
            $dia = self::DIAS[(int) date('N', strtotime($fecha))];
            $cuerpo .= '<p style="margin:24px 0 8px;font-size:15px;font-weight:bold;color:#3d2aa0;">' . $dia . '</p>';
            $cuerpo .= '<table width="100%" cellpadding="0" cellspacing="0" style="border-collapse:collapse;"><tr>'
                . '<th style="' . $th . '">Fecha</th><th style="' . $th . '">Materia</th><th style="' . $th . '">Tema</th>';
            foreach ($columnas as $col) $cuerpo .= '<th style="' . $th . '">' . $this->e($col->nombre) . '</th>';
            $cuerpo .= '</tr>';

            foreach ($lista as $i => $c) {
                $fondo = $i % 2 === 0 ? '#efeaff' : '#f7f6fd';
                $cuerpo .= '<tr style="background:' . $fondo . ';">'
                    . '<td style="' . $td . 'white-space:nowrap;">' . $this->fechaCorta($c['fecha']) . '</td>'
                    . '<td style="' . $td . 'font-weight:bold;">' . $this->e($c['materia']) . '</td>'
                    . '<td style="' . $td . '">' . $this->e($c['tema']) . '</td>';
                foreach ($columnas as $col) {
                    $txt = $this->celda($col, $c['marcas'][$col->id_columna] ?? null);
                    // Rojo solo para casillas (falta, retardo…); las notas pueden ser positivas
                    $resalte = $txt === '' ? '' : ($col->tipo === 'casilla' ? 'font-weight:bold;color:#b91c1c;' : 'font-weight:bold;');
                    $cuerpo .= '<td style="' . $td . $resalte . '">' . $txt . '</td>';
                }
                $cuerpo .= '</tr>';
            }
            $cuerpo .= '</table>';
        }

        return '<div style="font-family:Arial,Helvetica,sans-serif;max-width:860px;margin:0 auto;color:#1f2233;">'
            . '<div style="background:#5b3ee0;color:#ffffff;padding:20px 24px;border-radius:12px 12px 0 0;">'
            . '<div style="font-size:20px;font-weight:bold;">Colegio del Bosque</div>'
            . '<div style="font-size:13px;opacity:.9;">Reporte semanal · ' . $this->fechaCorta($lunes) . ' al ' . $this->fechaCorta($viernes) . '</div>'
            . '</div>'
            . '<div style="padding:20px 24px;border:1px solid #eceafb;border-top:none;border-radius:0 0 12px 12px;">'
            . '<p style="margin:0 0 4px;font-size:14px;">Estimada familia:</p>'
            . '<p style="margin:0;font-size:14px;">Le compartimos el reporte de la semana de <strong>' . $this->e($alumno) . '</strong>'
            . ($grado !== '' ? ' (' . $this->e($grado) . ')' : '') . '.</p>'
            . $cuerpo
            . '<p style="margin:24px 0 0;font-size:13px;">Enviándole un cordial saludo, estamos para servirle.</p>'
            . '<p style="margin:16px 0 0;font-size:11px;color:#5b607a;">Este correo es informativo, favor de no responder a esta dirección.</p>'
            . '</div></div>';
    }

    /**
     * Envía el mismo correo a cada tutor.
     * @return array{enviados:int,errores:string[]}
     */
    public function enviar(array $correos, string $asunto, string $html): array
    {
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
