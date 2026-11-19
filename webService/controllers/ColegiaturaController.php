<?php
/**
 * Colegiaturas: pagos, abonos, descuentos y recargos de cada alumno (solo el administrador).
 * Los pagos se generan desde los esquemas de pago (/esquemapago/aplicar).
 *   /colegiatura/index      → pantalla
 *   /colegiatura/catalogos  → ciclo activo, grupos, descuentos y estados de pago
 *   /colegiatura/listar     → todos los pagos, con recargo, total y saldo
 *   /colegiatura/pagar      → abono o pago completo de un pago (genera su recibo)
 *   /colegiatura/descuento  → aplica o quita un descuento del catálogo
 *   /colegiatura/recargo    → cambia el recargo por mes vencido de un pago
 *   /colegiatura/eliminar   → baja de un pago
 * La URL anterior /administrador/colegiaturas redirige a /colegiatura/index (tabla Rutas).
 */
class ColegiaturaController extends Controller
{
    public const MODULE = 'pagos';

    public function index(): void
    {
        $this->view('admin/colegiaturas');
    }

    public function catalogos(): void
    {
        $this->api->command('Pagos', 'Colegiatura', 'ActiveCycle');
        $result = $this->api->execute();
        if ($result->status()) $this->failure($result);
        $ciclo = $result->row() ? Ciclo::fromRow($result->row()) : null;

        $this->api->command('Pagos', 'Colegiatura', 'Groups');
        $this->api->addParameter('IdCiclo', 'I', $ciclo?->IdCiclo);
        $result = $this->api->execute();
        if ($result->status()) $this->failure($result);
        $grupos = GrupoOpcion::fromTable($result->table());

        $this->api->command('Pagos', 'Colegiatura', 'Discounts');
        $result = $this->api->execute();
        if ($result->status()) $this->failure($result);
        $descuentos = TipoDescuento::fromTable($result->table());

        $this->api->command('Pagos', 'Colegiatura', 'Statuses');
        $result = $this->api->execute();

        if (!$result->status()) {
            $this->success('Catálogos.', [
                'ciclo'      => $ciclo,
                'grupos'     => $grupos,
                'descuentos' => $descuentos,
                'estatus'    => EstatusPago::fromTable($result->table()),
            ]);
        } else {
            $this->failure($result);
        }
    }

    public function listar(): void
    {
        $this->api->command('Pagos', 'Colegiatura', 'List');
        $result = $this->api->execute();

        if (!$result->status()) {
            $pagos = Colegiatura::fromTable($result->table());
            foreach ($pagos as $pago) Recargo::completar($pago);
            $this->success('Colegiaturas obtenidas.', ['items' => $pagos]);
        } else {
            $this->failure($result);
        }
    }

    /**
     * Cobra un pago. Sin $Monto se salda completo; con $Monto es un abono (no puede pasar del saldo).
     * Cada cobro genera su recibo. Al completarse, el recargo de ese día queda congelado en el pago.
     */
    public function pagar(int $IdPago, string $TipoPago = 'Efectivo', ?string $Monto = null): void
    {
        $abono = null;
        if ($Monto !== null && $Monto !== '') {
            $abono = filter_var($Monto, FILTER_VALIDATE_FLOAT);
            $this->ensure($abono !== false && $abono > 0, 'El monto del abono no es válido.');
        }

        $pago = $this->pago($IdPago);
        $this->ensure(strtolower($pago->Estatus) !== 'pagado', 'Este pago ya está saldado.', 409);

        Recargo::completar($pago);
        $saldo = round($pago->Total - $pago->Abonado, 2);
        $this->ensure($saldo > 0.005, 'Este pago ya está cubierto.', 409);

        $abono ??= $saldo;
        $this->ensure($abono <= $saldo + 0.005, 'El abono (' . money($abono) . ') supera el saldo (' . money($saldo) . ').', 409);
        $completa = ($pago->Abonado + $abono) >= ($pago->Total - 0.005);

        $concepto = $pago->esInscripcion() ? 'Inscripción' : 'Colegiatura ' . $pago->Mes;
        $comentario = ($completa && $pago->Abonado <= 0.005) ? $concepto : 'Abono — ' . $concepto;

        $this->api->command('Pagos', 'Colegiatura', 'Pay');
        $this->api->addParameter('IdPago',     'I', $IdPago);
        $this->api->addParameter('Monto',      'N', $abono);
        $this->api->addParameter('TipoPago',   'S', $TipoPago);
        $this->api->addParameter('Comentario', 'S', $comentario);
        $this->api->addParameter('Fecha',      'S', date('Y-m-d H:i:s'));
        $this->api->addParameter('Completa',   'B', $completa);
        $this->api->addParameter('Recargo',    'N', $pago->RecargoCalc);
        $result = $this->api->execute();

        if (!$result->status()) {
            if ($completa) {
                $this->success('Pago saldado y recibo generado.', ['IdRecibo' => $result->id(), 'Completa' => true, 'Saldo' => 0]);
            }
            $resta = round($saldo - $abono, 2);
            $this->success('Abono registrado. Saldo restante: ' . money($resta) . '.', ['IdRecibo' => $result->id(), 'Completa' => false, 'Saldo' => $resta]);
        } else {
            $this->failure($result);
        }
    }

    /** Aplica un descuento del catálogo al pago ($IdDescuento = 0 lo quita). El descuento es un % del monto. */
    public function descuento(int $IdPago, int $IdDescuento = 0): void
    {
        $pago = $this->pago($IdPago);
        $this->ensure(strtolower($pago->Estatus) !== 'pagado', 'No se puede: el pago ya está registrado.', 409);

        $this->api->command('Pagos', 'Colegiatura', 'ApplyDiscount');
        $this->api->addParameter('IdPago', 'I', $IdPago);

        if ($IdDescuento <= 0) {
            $this->api->addParameter('IdTipoDescuento', 'I', null);
            $result = $this->api->execute();
            if ($result->status()) $this->failure($result);
            $this->success('Descuento quitado.');
        }

        $descuento = $this->tipoDescuento($IdDescuento);
        $this->ensure($descuento->AplicaA === ($pago->esInscripcion() ? 'inscripcion' : 'colegiatura'), 'Ese descuento no aplica a este tipo de pago.', 409);
        $monto = round($pago->Monto * $descuento->Porcentaje / 100, 2);
        preg_match('/^.{0,60}/us', $descuento->Nombre, $concepto);   // el concepto que se guarda admite 60 caracteres

        $this->api->command('Pagos', 'Colegiatura', 'ApplyDiscount');
        $this->api->addParameter('IdPago',            'I', $IdPago);
        $this->api->addParameter('Descuento',         'N', $monto);
        $this->api->addParameter('IdTipoDescuento',   'I', $IdDescuento);
        $this->api->addParameter('ConceptoDescuento', 'S', $concepto[0]);
        $result = $this->api->execute();

        if (!$result->status()) {
            $this->success("Descuento aplicado: {$descuento->Nombre} (−" . money($monto) . ').');
        } else {
            $this->failure($result);
        }
    }

    /** Recargo en pesos por cada mes vencido (0 = sin recargo). */
    public function recargo(int $IdPago, ?string $RecargoMonto = null): void
    {
        $monto = filter_var($RecargoMonto ?? '', FILTER_VALIDATE_FLOAT);
        $this->ensure($monto !== false && $monto >= 0, 'El recargo no es válido.');

        $this->api->command('Pagos', 'Colegiatura', 'Surcharge');
        $this->api->addParameter('IdPago',       'I', $IdPago);
        $this->api->addParameter('RecargoMonto', 'N', $monto);
        $result = $this->api->execute();

        if (!$result->status()) {
            $this->success($monto > 0 ? 'Recargo aplicado.' : 'Recargo quitado.');
        } else {
            $this->failure($result);
        }
    }

    public function eliminar(int $IdPago): void
    {
        $this->api->command('Pagos', 'Colegiatura', 'Delete');
        $this->api->addParameter('IdPago', 'I', $IdPago);
        $result = $this->api->execute();

        if (!$result->status()) {
            $this->ensure($result->affected() > 0, 'No se encontró el registro.', 404);
            $this->success('Registro eliminado.');
        } else {
            $this->failure($result);
        }
    }

    /** El pago con lo abonado; corta con 404 si no existe. */
    private function pago(int $IdPago): Colegiatura
    {
        $this->api->command('Pagos', 'Colegiatura', 'Get');
        $this->api->addParameter('IdPago', 'I', $IdPago);
        $result = $this->api->execute();
        if ($result->status()) $this->failure($result);
        $this->ensure($result->row() !== null, 'No se encontró la colegiatura.', 404);
        return Colegiatura::fromRow($result->row());
    }

    /** Un descuento activo del catálogo; corta con 404 si no existe o está inactivo. */
    private function tipoDescuento(int $IdDescuento): TipoDescuento
    {
        $this->api->command('Pagos', 'Colegiatura', 'Discount');
        $this->api->addParameter('IdDescuento', 'I', $IdDescuento);
        $result = $this->api->execute();
        if ($result->status()) $this->failure($result);
        $this->ensure($result->row() !== null, 'Descuento no encontrado o inactivo.', 404);
        return TipoDescuento::fromRow($result->row());
    }
}
