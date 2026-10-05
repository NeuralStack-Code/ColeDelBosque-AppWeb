<?php

/**
 * Recurso: esquemas-pago.  Ruta: /api/esquemas-pago?action=catalogos|listar|desglose|crear|editar|eliminar|aplicar
 * Panel de esquemas de pago por ciclo escolar (solo admin).
 */
class EsquemaPagoController
{
    private EsquemaPagoBusiness $esquemas;
    private ColegiaturaBusiness $cole;      // genera las colegiaturas al aplicar

    public function __construct(mysqli $conexion)
    {
        requireAdmin();
        $this->esquemas = new EsquemaPagoBusiness($conexion);
        $this->cole     = new ColegiaturaBusiness($conexion);
    }

    /** Lee y valida el formulario contra las fechas del ciclo → EsquemaPago listo para guardar. */
    private function datos(): EsquemaPago
    {
        $cicloId  = (int) ($_POST['ciclo_id'] ?? 0);
        $nombre   = trim($_POST['nombre'] ?? '');
        $montoIns = filter_var($_POST['monto_inscripcion'] ?? '0', FILTER_VALIDATE_FLOAT);
        $montoCol = filter_var($_POST['monto_colegiatura'] ?? '', FILTER_VALIDATE_FLOAT);
        $primer   = trim($_POST['primer_mes'] ?? '');        // Y-m
        $numMeses = (int) ($_POST['num_meses'] ?? 0);
        $diaVenc  = (int) ($_POST['dia_vencimiento'] ?? 0);
        $recMonto = filter_var($_POST['recargo_monto'] ?? '0', FILTER_VALIDATE_FLOAT);

        if ($nombre === '')                        response(400, false, 'El nombre es obligatorio.');
        if (iconv_strlen($nombre) > 80)            response(400, false, 'El nombre no puede pasar de 80 caracteres.');
        if ($montoCol === false || $montoCol <= 0) response(400, false, 'Monto de colegiatura no válido.');
        if ($montoIns === false || $montoIns < 0)  response(400, false, 'Monto de inscripción no válido.');
        if ($recMonto === false || $recMonto < 0) response(400, false, 'Recargo no válido.');
        if ($diaVenc < 1 || $diaVenc > 28)         response(400, false, 'Día de vencimiento entre 1 y 28.');

        $ciclo = $this->esquemas->ciclo($cicloId);
        if (!$ciclo) response(400, false, 'Selecciona un ciclo escolar.');
        if (empty($ciclo['fecha_inicio']) || empty($ciclo['fecha_fin'])) {
            response(409, false, 'El ciclo no tiene fechas de inicio y fin. Complétalas en Ciclo escolar.');
        }

        // Las mensualidades deben caer dentro de los meses del ciclo
        $mes = DateTime::createFromFormat('!Y-m', $primer);
        if (!$mes || $mes->format('Y-m') !== $primer) response(400, false, 'Selecciona el mes de la primera mensualidad.');
        $ini = new DateTime(substr($ciclo['fecha_inicio'], 0, 7) . '-01');
        $fin = new DateTime(substr($ciclo['fecha_fin'], 0, 7) . '-01');
        if ($mes < $ini || $mes > $fin) response(400, false, 'La primera mensualidad debe estar dentro del ciclo escolar.');

        $disponibles = ((int) $fin->format('Y') - (int) $mes->format('Y')) * 12 + ((int) $fin->format('n') - (int) $mes->format('n')) + 1;
        if ($numMeses < 1)            response(400, false, 'Indica cuántas mensualidades se cobran.');
        if ($numMeses > $disponibles) response(400, false, "Desde ese mes el ciclo solo tiene $disponibles mensualidad(es).");

        return new EsquemaPago([
            'ciclo_id'          => $cicloId,
            'nombre'            => $nombre,
            'monto_inscripcion' => (float) $montoIns,
            'monto_colegiatura' => (float) $montoCol,
            'primer_mes'        => $mes->format('Y-m-01'),
            'num_meses'         => $numMeses,
            'dia_vencimiento'   => $diaVenc,
            'recargo_monto'     => (float) $recMonto,
        ]);
    }

    public function catalogos(): void
    {
        response(200, true, 'Catálogos.', [
            'ciclos'  => $this->esquemas->ciclos(),
            'grupos'  => $this->esquemas->grupos(),
            'alumnos' => $this->esquemas->alumnos(),
        ]);
    }

    public function listar(): void
    {
        response(200, true, 'Esquemas obtenidos.', ['items' => $this->esquemas->listar()]);
    }

    /** Pagos que genera el esquema por alumno (lo mismo que guardará aplicar()). */
    private function calendario(EsquemaPago $e): array
    {
        return $this->cole->calendarioEsquema(
            (float) $e->monto_colegiatura, (float) $e->monto_inscripcion,
            (int) substr($e->primer_mes, 0, 4), (int) substr($e->primer_mes, 5, 2),
            (int) $e->num_meses, (int) $e->dia_vencimiento, (float) $e->recargo_monto
        );
    }

    /** Vista previa: cada pago del esquema, mes por mes, y el total por alumno. */
    public function desglose(): void
    {
        $e = $this->esquemas->obtener((int) ($_GET['id_esquema'] ?? 0));
        if (!$e) response(404, false, 'No se encontró el esquema.');
        $pagos = $this->calendario($e);
        response(200, true, 'Desglose obtenido.', [
            'pagos' => $pagos,
            'total' => round(array_sum(array_column($pagos, 'monto')), 2),
        ]);
    }

    public function crear(): void
    {
        if (!$this->esquemas->crear($this->datos())) response(500, false, 'No se pudo crear el esquema.');
        response(201, true, 'Esquema de pago creado.');
    }

    public function editar(): void
    {
        $id = (int) ($_POST['id_esquema'] ?? 0);
        if ($id <= 0 || !$this->esquemas->obtener($id)) response(404, false, 'No se encontró el esquema.');
        $e = $this->datos();
        $e->id_esquema = $id;
        if (!$this->esquemas->editar($e)) response(500, false, 'No se pudo actualizar el esquema.');
        response(200, true, 'Esquema actualizado. Vuelve a aplicarlo para llevar los cambios a los pagos pendientes.');
    }

    public function eliminar(): void
    {
        $id = (int) ($_POST['id_esquema'] ?? 0);
        if ($id <= 0) response(400, false, 'Registro no válido.');
        if ($this->esquemas->eliminar($id) === 0) response(404, false, 'No se encontró el esquema.');
        response(200, true, 'Esquema eliminado. Las colegiaturas ya generadas se conservan.');
    }

    /** Genera inscripción + mensualidades del esquema para todos los alumnos del ciclo, un grupo o un alumno. */
    public function aplicar(): void
    {
        $e = $this->esquemas->obtener((int) ($_POST['id_esquema'] ?? 0));
        if (!$e) response(404, false, 'No se encontró el esquema.');
        $cicloId = (int) $e->ciclo_id;

        switch ($_POST['aplicar_a'] ?? 'todos') {
            case 'grupo':
                $grupoId = (int) ($_POST['grupo_id'] ?? 0);
                if ($grupoId <= 0 || !$this->esquemas->grupoEnCiclo($grupoId, $cicloId)) response(400, false, 'Selecciona un grupo del ciclo del esquema.');
                $alumnos = $this->esquemas->alumnosDeGrupo($grupoId);
                break;
            case 'alumno':
                $cuentaId = (int) ($_POST['cuenta_id'] ?? 0);
                if ($cuentaId <= 0) response(400, false, 'Selecciona un alumno.');
                if (!$this->cole->alumnoEnCicloActivo($cuentaId, $cicloId)) response(404, false, 'El alumno no está en un grupo del ciclo del esquema.');
                $alumnos = [$cuentaId];
                break;
            default:
                $alumnos = $this->cole->alumnosDelCicloActivo($cicloId);
        }
        if (!$alumnos) response(409, false, 'No hay alumnos a quienes aplicar el esquema.');

        $c = $this->cole->generarEsquema($alumnos, $cicloId, $this->calendario($e));

        $msg = 'Esquema aplicado a ' . count($alumnos) . " alumno(s): {$c['creados']} pago(s) nuevo(s)";
        if ($c['actualizados']) $msg .= ", {$c['actualizados']} actualizado(s)";
        if ($c['saltados'])     $msg .= ", {$c['saltados']} sin cambios";
        response(201, true, $msg . '.');
    }
}
