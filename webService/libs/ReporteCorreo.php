<?php
/**
 * Correo del reporte semanal que reciben los papás: una tabla por día con cada clase y sus incidencias.
 * Lo manda ReporteController::enviar por la api central (y lo muestra vistaPrevia). No toca la base.
 */
class ReporteCorreo
{
    private const DIAS = [1 => 'Lunes', 'Martes', 'Miércoles', 'Jueves', 'Viernes', 'Sábado', 'Domingo'];

    /** AAAA-MM-DD → DD/MM/AAAA */
    public static function fechaCorta(string $fecha): string
    {
        return date('d/m/Y', strtotime($fecha));
    }

    public static function asunto(string $alumno, string $lunes, string $viernes): string
    {
        return "Reporte semanal de $alumno — " . self::fechaCorta($lunes) . ' al ' . self::fechaCorta($viernes);
    }

    /**
     * Las filas de sp_reporte_semana_alumno (una por clase y columna con incidencia) agrupadas por clase:
     * [['Fecha', 'Materia', 'Tema', 'Marcas' => [IdColumna => ['Marcado', 'Nota']]], …], en orden.
     */
    public static function clases(array $filas): array
    {
        $clases = [];
        foreach ($filas as $fila) {
            $id = (int) $fila['IdClase'];
            $clases[$id] ??= ['Fecha' => $fila['Fecha'], 'Materia' => $fila['Materia'], 'Tema' => $fila['Tema'], 'Marcas' => []];
            if ($fila['IdColumna'] !== null) {
                $clases[$id]['Marcas'][(int) $fila['IdColumna']] = ['Marcado' => (int) $fila['Marcado'], 'Nota' => $fila['Nota']];
            }
        }
        return array_values($clases);
    }

    /**
     * @param ReporteColumna[] $columnas columnas activas, en orden
     * @param array            $clases   salida de clases()
     */
    public static function html(string $alumno, string $grado, array $columnas, array $clases, string $lunes, string $viernes): string
    {
        $th = 'padding:8px 10px;font-size:11px;text-transform:uppercase;text-align:left;color:#ffffff;background:#3d2aa0;';
        $td = 'padding:8px 10px;font-size:13px;color:#1f2233;border-top:1px solid #ffffff;vertical-align:top;';

        $porDia = [];
        foreach ($clases as $clase) $porDia[$clase['Fecha']][] = $clase;

        $cuerpo = '';
        foreach ($porDia as $fecha => $lista) {
            $dia = self::DIAS[(int) date('N', strtotime($fecha))];
            $cuerpo .= '<p style="margin:24px 0 8px;font-size:15px;font-weight:bold;color:#3d2aa0;">' . $dia . '</p>';
            $cuerpo .= '<table width="100%" cellpadding="0" cellspacing="0" style="border-collapse:collapse;"><tr>'
                . '<th style="' . $th . '">Fecha</th><th style="' . $th . '">Materia</th><th style="' . $th . '">Tema</th>';
            foreach ($columnas as $columna) $cuerpo .= '<th style="' . $th . '">' . e($columna->Nombre) . '</th>';
            $cuerpo .= '</tr>';

            foreach ($lista as $i => $clase) {
                $fondo = $i % 2 === 0 ? '#efeaff' : '#f7f6fd';
                $cuerpo .= '<tr style="background:' . $fondo . ';">'
                    . '<td style="' . $td . 'white-space:nowrap;">' . self::fechaCorta($clase['Fecha']) . '</td>'
                    . '<td style="' . $td . 'font-weight:bold;">' . e($clase['Materia']) . '</td>'
                    . '<td style="' . $td . '">' . e($clase['Tema']) . '</td>';
                foreach ($columnas as $columna) {
                    $texto = self::celda($columna, $clase['Marcas'][$columna->IdColumna] ?? null);
                    // Rojo solo para casillas (falta, retardo…); las notas pueden ser positivas
                    $resalte = $texto === '' ? '' : ($columna->esTexto() ? 'font-weight:bold;' : 'font-weight:bold;color:#b91c1c;');
                    $cuerpo .= '<td style="' . $td . $resalte . '">' . $texto . '</td>';
                }
                $cuerpo .= '</tr>';
            }
            $cuerpo .= '</table>';
        }

        return '<div style="font-family:Arial,Helvetica,sans-serif;max-width:860px;margin:0 auto;color:#1f2233;">'
            . '<div style="background:#5b3ee0;color:#ffffff;padding:20px 24px;border-radius:12px 12px 0 0;">'
            . '<div style="font-size:20px;font-weight:bold;">Colegio del Bosque</div>'
            . '<div style="font-size:13px;opacity:.9;">Reporte semanal · ' . self::fechaCorta($lunes) . ' al ' . self::fechaCorta($viernes) . '</div>'
            . '</div>'
            . '<div style="padding:20px 24px;border:1px solid #eceafb;border-top:none;border-radius:0 0 12px 12px;">'
            . '<p style="margin:0 0 4px;font-size:14px;">Estimada familia:</p>'
            . '<p style="margin:0;font-size:14px;">Le compartimos el reporte de la semana de <strong>' . e($alumno) . '</strong>'
            . ($grado !== '' ? ' (' . e($grado) . ')' : '') . '.</p>'
            . $cuerpo
            . '<p style="margin:24px 0 0;font-size:13px;">Enviándole un cordial saludo, estamos para servirle.</p>'
            . '<p style="margin:16px 0 0;font-size:11px;color:#5b607a;">Este correo es informativo, favor de no responder a esta dirección.</p>'
            . '</div></div>';
    }

    /** Lo que lee el papá en la celda de una columna (vacío = sin incidencia). */
    private static function celda(ReporteColumna $columna, ?array $marca): string
    {
        if (!$marca) return '';
        if ($columna->esTexto()) return e($marca['Nota'] ?? '');
        return $marca['Marcado'] ? e($columna->Etiqueta ?: 'Sí') : '';
    }
}
