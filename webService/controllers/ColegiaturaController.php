<?php

/**
 * Recurso: colegiaturas.  Ruta: /api/colegiaturas?action=catalogos|listar|generar|registrar_pago|descuento|eliminar
 * Esquema de pagos por ciclo (solo admin).
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

    public function generar(): void
    {
        $montoCol  = filter_var($_POST['monto_colegiatura'] ?? '', FILTER_VALIDATE_FLOAT);
        $montoIns  = filter_var($_POST['monto_inscripcion'] ?? '0', FILTER_VALIDATE_FLOAT);
        $anio      = (int) ($_POST['anio'] ?? 0);
        $mesInicio = (int) ($_POST['mes_inicio'] ?? 0);
        $numMeses  = (int) ($_POST['num_meses'] ?? 0);
        $diaVenc   = (int) ($_POST['dia_venc'] ?? 1);
        $recPct    = (float) ($_POST['recargo_pct'] ?? 0);
        $aplicarA  = ($_POST['aplicar_a'] ?? 'todos') === 'alumno' ? 'alumno' : 'todos';
        $cuentaSel = (int) ($_POST['cuenta_id'] ?? 0);

        if ($montoCol === false || $montoCol <= 0) response(400, false, 'Monto de colegiatura no válido.');
        if ($montoIns === false || $montoIns < 0)  response(400, false, 'Monto de inscripción no válido.');
        if ($anio < 2020 || $anio > 2100)          response(400, false, 'Año no válido.');
        if ($mesInicio < 1 || $mesInicio > 12)     response(400, false, 'Mes de inicio no válido.');
        if ($numMeses < 1 || $numMeses > 24)       response(400, false, 'Número de mensualidades no válido.');
        if ($diaVenc < 1 || $diaVenc > 28)         response(400, false, 'Día de vencimiento entre 1 y 28.');

        $cicloId = $this->cole->cicloActivoId();
        if ($cicloId <= 0) response(409, false, 'No hay un ciclo escolar activo. Actívalo antes de generar.');

        if ($aplicarA === 'alumno') {
            if ($cuentaSel <= 0) response(400, false, 'Selecciona un alumno.');
            if (!$this->cole->alumnoEnCicloActivo($cuentaSel, $cicloId)) {
                response(404, false, 'El alumno no está en un grupo del ciclo activo.');
            }
            $alumnos = [$cuentaSel];
        } else {
            $alumnos = $this->cole->alumnosDelCicloActivo($cicloId);
        }
        if (!$alumnos) response(409, false, 'No hay alumnos en el ciclo activo para generar el esquema.');

        $c = $this->cole->generarEsquema($alumnos, $cicloId, $montoCol, $montoIns, $anio, $mesInicio, $numMeses, $diaVenc, $recPct);

        $msg = "Esquema generado: {$c['creados']} nuevo(s)";
        if ($c['actualizados']) $msg .= ", {$c['actualizados']} actualizado(s)";
        if ($c['saltados'])     $msg .= ", {$c['saltados']} sin cambios";
        $msg .= '.';
        response(201, true, $msg);
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
        $recargo = $this->cole->recargoVigente($monto, (float) $col['recargo_pct'], $col['fecha_vencimiento'], $col['estatus']);
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

    public function eliminar(): void
    {
        $id = (int) ($_POST['id_pago'] ?? 0);
        if ($id <= 0) response(400, false, 'Registro no válido.');
        if ($this->cole->eliminar($id) === 0) response(404, false, 'No se encontró el registro.');
        response(200, true, 'Registro eliminado.');
    }
}
