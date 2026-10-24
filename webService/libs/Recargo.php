<?php
/**
 * Lo que se cobra de un pago (colegiatura o inscripción): recargo por mes vencido, total y saldo.
 * Lo usan ColegiaturaController (panel del administrador) y PadreController (portal del alumno): los dos
 * tienen que mostrar exactamente las mismas cantidades. No toca la base.
 */
class Recargo
{
    /**
     * Recargo de hoy de un pago que no está pagado y ya venció: una cantidad fija por cada mes vencido
     * (al menos uno en cuanto vence). Pagado, sin recargo o sin fecha de vencimiento → 0.
     */
    public static function vigente(float $recargoMes, ?string $vencimiento, string $estatus): float
    {
        if (strtolower($estatus) === 'pagado' || $recargoMes <= 0 || !$vencimiento) return 0.0;
        try {
            $hoy = new DateTime('today');
            $vence = new DateTime($vencimiento);
        } catch (Exception $e) {
            return 0.0;
        }
        if ($vence >= $hoy) return 0.0;
        $meses = ((int) $hoy->format('Y') - (int) $vence->format('Y')) * 12 + ((int) $hoy->format('n') - (int) $vence->format('n'));
        return round($recargoMes * max(1, $meses), 2);
    }

    /**
     * Completa las cantidades de un pago: su recargo (el congelado al pagarse, o el de hoy), total y saldo.
     * El total nunca baja de 0 aunque el descuento sea mayor.
     */
    public static function completar(Colegiatura $pago): void
    {
        $pago->RecargoCalc = strtolower($pago->Estatus) === 'pagado'
            ? $pago->Recargo
            : self::vigente($pago->RecargoMonto, $pago->FechaVencimiento, $pago->Estatus);
        $pago->Total = max(0, $pago->Monto + $pago->RecargoCalc - $pago->Descuento);
        $pago->Saldo = max(0, round($pago->Total - $pago->Abonado, 2));
    }
}
