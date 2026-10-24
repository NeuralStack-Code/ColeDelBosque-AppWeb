<?php
/**
 * Los pagos que produce un esquema de pago, en orden: la inscripción (si tiene costo) y una colegiatura por mes.
 * Es lo mismo que se muestra en la vista previa (/esquemapago/desglose) y lo que se guarda al aplicarlo
 * (/esquemapago/aplicar). No toca la base.
 */
class CalendarioPagos
{
    public const MESES = ['', 'Enero', 'Febrero', 'Marzo', 'Abril', 'Mayo', 'Junio', 'Julio', 'Agosto', 'Septiembre', 'Octubre', 'Noviembre', 'Diciembre'];

    /**
     * @return array<int,array{Tipo:string,Mes:string,Anio:int,FechaVencimiento:string,Monto:float,RecargoMonto:float}>
     */
    public static function de(EsquemaPago $esquema): array
    {
        $anio      = (int) substr($esquema->PrimerMes, 0, 4);
        $mesInicio = (int) substr($esquema->PrimerMes, 5, 2);
        $dia       = $esquema->DiaVencimiento;

        $pagos = [];
        if ($esquema->MontoInscripcion > 0) {
            $pagos[] = [
                'Tipo' => 'inscripcion', 'Mes' => 'Inscripción', 'Anio' => $anio,
                'FechaVencimiento' => sprintf('%04d-%02d-%02d', $anio, $mesInicio, $dia),
                'Monto' => $esquema->MontoInscripcion, 'RecargoMonto' => 0.0,   // la inscripción no genera recargo
            ];
        }
        for ($k = 0; $k < $esquema->NumMeses; $k++) {
            $mes  = ($mesInicio - 1 + $k) % 12 + 1;
            $anioMes = $anio + intdiv($mesInicio - 1 + $k, 12);
            $pagos[] = [
                'Tipo' => 'colegiatura', 'Mes' => self::MESES[$mes], 'Anio' => $anioMes,
                'FechaVencimiento' => sprintf('%04d-%02d-%02d', $anioMes, $mes, $dia),
                'Monto' => $esquema->MontoColegiatura, 'RecargoMonto' => $esquema->RecargoMonto,
            ];
        }
        return $pagos;
    }
}
