<?php

/**
 * Recurso: recibos.  Ruta: /api/recibos?action=catalogos|listar|crear|editar|eliminar
 * Ingresos/egresos de la escuela (solo admin).
 */
class ReciboController
{
    private ReciboBusiness $recibos;

    public function __construct(mysqli $conexion)
    {
        requireAdmin();
        $this->recibos = new ReciboBusiness($conexion);
    }

    private function natValida(string $n): string
    {
        return $n === 'gasto' ? 'gasto' : 'ingreso';
    }

    private function destValido(string $d): string
    {
        return in_array($d, ['alumno', 'docente', 'escuela'], true) ? $d : 'escuela';
    }

    /** Lee y valida los campos POST de un recibo. Corta con response() si algo falla. */
    private function leerRecibo(): array
    {
        $tipo  = (int) ($_POST['tipo_recibo_id'] ?? 0) ?: null;
        $nat   = $this->natValida($_POST['naturaleza'] ?? 'ingreso');
        $dtipo = $this->destValido($_POST['destinatario_tipo'] ?? 'escuela');
        $monto = filter_var($_POST['monto'] ?? '', FILTER_VALIDATE_FLOAT);
        $pago  = trim($_POST['tipo_pago'] ?? '');
        $pago  = ($pago === '') ? null : $pago;
        $com   = trim($_POST['comentario'] ?? '');
        $com   = ($com === '') ? null : $com;
        $fecha = trim($_POST['fecha'] ?? '');
        $fecha = ($fecha === '') ? date('Y-m-d H:i:s') : str_replace('T', ' ', $fecha);

        if ($monto === false || $monto <= 0) response(400, false, 'El monto debe ser mayor a 0.');

        $cuenta = null;
        if ($dtipo !== 'escuela') {
            $cuenta = (int) ($_POST['cuenta_id'] ?? 0);
            if ($cuenta <= 0) response(400, false, 'Selecciona el ' . $dtipo . ' destinatario.');
            $permiso = $dtipo === 'alumno' ? 3 : 2;
            if (!$this->recibos->cuentaValida($cuenta, $permiso)) {
                response(400, false, 'El destinatario no es un ' . $dtipo . ' válido.');
            }
        }
        return [$tipo, $nat, $dtipo, $cuenta, $monto, $pago, $com, $fecha];
    }

    public function catalogos(): void
    {
        response(200, true, 'Catálogos.', [
            'tipos'    => $this->recibos->tipos(),
            'alumnos'  => $this->recibos->personas(3),
            'docentes' => $this->recibos->personas(2),
        ]);
    }

    public function listar(): void
    {
        $data = $this->recibos->listar();
        response(200, true, 'Recibos obtenidos.', ['items' => $data['items'], 'saldo' => $data['saldo']]);
    }

    public function crear(): void
    {
        [$tipo, $nat, $dtipo, $cuenta, $monto, $pago, $com, $fecha] = $this->leerRecibo();
        if (!$this->recibos->crear($tipo, $nat, $dtipo, $cuenta, $monto, $pago, $com, $fecha)) {
            response(500, false, 'No se pudo registrar el recibo.');
        }
        response(201, true, 'Recibo registrado.');
    }

    public function editar(): void
    {
        $id = (int) ($_POST['id_recibo'] ?? 0);
        if ($id <= 0) response(400, false, 'Registro no válido.');
        [$tipo, $nat, $dtipo, $cuenta, $monto, $pago, $com, $fecha] = $this->leerRecibo();
        if (!$this->recibos->editar($id, $tipo, $nat, $dtipo, $cuenta, $monto, $pago, $com, $fecha)) {
            response(500, false, 'No se pudo actualizar el recibo.');
        }
        response(200, true, 'Recibo actualizado.');
    }

    public function eliminar(): void
    {
        $id = (int) ($_POST['id_recibo'] ?? 0);
        if ($id <= 0) response(400, false, 'Registro no válido.');
        if ($this->recibos->eliminar($id) === 0) response(404, false, 'No se encontró el recibo.');
        response(200, true, 'Recibo eliminado.');
    }
}
