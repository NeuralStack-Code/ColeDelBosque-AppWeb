<?php

/**
 * Recurso: colegiaturas.  Ruta: /api/colegiaturas?action=catalogos|listar|registrar_pago|descuento|recargo|eliminar
 * Pagos, abonos y descuentos de las colegiaturas (solo admin). Se generan desde esquemas-pago.
 */
class ColegiaturaController
{
    private ColegiaturaBusiness $cole;

    public function __construct(mysqli $conexion)
    {
        requireAdmin();
        $this->cole = new ColegiaturaBusiness($conexion);
    }

    public function catalogos(): void
    {
        response(200, true, 'Catálogos.', $this->cole->catalogos());
    }

    public function listar(): void
    {
        response(200, true, 'Colegiaturas obtenidas.', ['items' => $this->cole->listar()]);
    }

    public function registrar_pago(): void
    {
        $id   = (int) ($_POST['id_pago'] ?? 0);
        $pago = trim($_POST['tipo_pago'] ?? 'Efectivo');
        $abonoIn = (isset($_POST['monto']) && $_POST['monto'] !== '')
            ? filter_var($_POST['monto'], FILTER_VALIDATE_FLOAT) : null;
        if ($id <= 0) response(400, false, 'Registro no válido.');
        if ($abonoIn !== null && ($abonoIn === false || $abonoIn <= 0)) response(400, false, 'El monto del abono no es válido.');

        $col = $this->cole->colegiaturaParaPago($id);
        if (!$col) response(404, false, 'No se encontró la colegiatura.');
        if (strtolower($col['estatus']) === 'pagado') response(409, false, 'Este pago ya está saldado.');

        $monto   = (float) $col['monto'];
        $desc    = (float) $col['descuento'];
        $recargo = $this->cole->recargoVigente((float) $col['recargo_monto'], $col['fecha_vencimiento'], $col['estatus']);
        $total   = max(0, $monto + $recargo - $desc);
        $cuentaId = (int) $col['cuenta_id'];
        $esInscripcion = $col['tipo'] === 'inscripcion';

        $abonado = $this->cole->abonadoDe($id);
        $saldo   = round($total - $abonado, 2);
        if ($saldo <= 0.005) response(409, false, 'Este pago ya está cubierto.');

        $abono = $abonoIn ?? $saldo;
        if ($abono > $saldo + 0.005) {
            response(409, false, 'El abono ($' . number_format($abono, 2) . ') supera el saldo ($' . number_format($saldo, 2) . ').');
        }
        $completa = ($abonado + $abono) >= ($total - 0.005);

        $tipoNombre = $esInscripcion ? 'Inscripción' : 'Colegiatura';
        $tipoId     = $this->cole->tipoReciboId($tipoNombre);

        $base = $esInscripcion ? 'Inscripción' : ('Colegiatura ' . $col['mes']);
        $com  = ($completa && $abonado <= 0.005) ? $base : ('Abono — ' . $base);

        $reciboId = $this->cole->crearReciboAbono($tipoId, $cuentaId, $id, $abono, $pago, $com);
        if ($reciboId <= 0) response(500, false, 'No se pudo generar el recibo.');

        if ($completa) {
            $this->cole->marcarPagado($id, $recargo, $reciboId);
            response(200, true, 'Pago saldado y recibo generado.', ['recibo_id' => $reciboId, 'completa' => true, 'saldo' => 0]);
        } else {
            $this->cole->marcarParcial($id, $reciboId);
            $saldoRest = round($saldo - $abono, 2);
            response(200, true, 'Abono registrado. Saldo restante: $' . number_format($saldoRest, 2) . '.',
                ['recibo_id' => $reciboId, 'completa' => false, 'saldo' => $saldoRest]);
        }
    }

    public function descuento(): void
    {
        $id   = (int) ($_POST['id_pago'] ?? 0);
        $tdId = (int) ($_POST['tipo_descuento_id'] ?? 0); // 0 = quitar descuento
        if ($id <= 0) response(400, false, 'Registro no válido.');

        $col = $this->cole->colegiaturaParaDescuento($id);
        if (!$col) response(404, false, 'No se encontró el registro.');
        if (strtolower($col['estatus']) === 'pagado') response(409, false, 'No se puede: el pago ya está registrado.');

        if ($tdId <= 0) {
            $this->cole->quitarDescuento($id);
            response(200, true, 'Descuento quitado.');
        }

        $desc = $this->cole->tipoDescuentoActivo($tdId);
        if (!$desc) response(404, false, 'Descuento no encontrado o inactivo.');

        $tipoCol = $col['tipo'] === 'inscripcion' ? 'inscripcion' : 'colegiatura';
        if ($desc['aplica_a'] !== $tipoCol) response(409, false, 'Ese descuento no aplica a este tipo de pago.');

        $descMonto = round((float) $col['monto'] * (float) $desc['porcentaje'] / 100, 2);
        $concepto  = function_exists('mb_substr') ? mb_substr($desc['nombre'], 0, 60) : substr($desc['nombre'], 0, 60);

        $this->cole->aplicarDescuento($id, $descMonto, $tdId, $concepto);
        response(200, true, "Descuento aplicado: {$desc['nombre']} (−$" . number_format($descMonto, 2) . ').');
    }

    /** Recargo en pesos por cada mes vencido (0 = sin recargo). */
    public function recargo(): void
    {
        $id    = (int) ($_POST['id_pago'] ?? 0);
        $monto = filter_var($_POST['recargo_monto'] ?? '', FILTER_VALIDATE_FLOAT);
        if ($id <= 0) response(400, false, 'Registro no válido.');
        if ($monto === false || $monto < 0) response(400, false, 'El recargo no es válido.');
        if (!$this->cole->fijarRecargo($id, (float) $monto)) response(409, false, 'No se puede: el pago no existe o ya está saldado.');
        response(200, true, $monto > 0 ? 'Recargo aplicado.' : 'Recargo quitado.');
    }

    public function eliminar(): void
    {
        $id = (int) ($_POST['id_pago'] ?? 0);
        if ($id <= 0) response(400, false, 'Registro no válido.');
        if ($this->cole->eliminar($id) === 0) response(404, false, 'No se encontró el registro.');
        response(200, true, 'Registro eliminado.');
    }
}
