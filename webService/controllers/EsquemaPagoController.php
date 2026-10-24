<?php
require_once dirname(__DIR__, 2) . '/apiService/core/crypto.php';   // decrypt(): la matrícula se guarda encriptada

/**
 * Esquemas de pago por ciclo escolar (solo el administrador): cuánto y cuántos meses se cobra, y a quiénes.
 *   /esquemapago/index      → pantalla
 *   /esquemapago/catalogos  → ciclos, grupos y alumnos para el formulario
 *   /esquemapago/listar     → los esquemas
 *   /esquemapago/desglose   → vista previa: cada pago que genera, mes por mes
 *   /esquemapago/guardar    → alta o cambio
 *   /esquemapago/eliminar   → baja (los pagos ya generados se conservan)
 *   /esquemapago/aplicar    → genera inscripción y mensualidades a todo el ciclo, a un grupo o a un alumno
 * La URL anterior /administrador/esquemas-pago redirige a /esquemapago/index (index.php raíz).
 */
class EsquemaPagoController extends Controller
{
    protected int $role = self::ADMIN;
    protected array $readActions = ['index', 'listar', 'catalogos', 'desglose'];

    public function index(): void
    {
        $this->view('admin/esquemas_pago');
    }

    public function catalogos(): void
    {
        $this->api->command('Ciclos', 'Ciclo', 'List');
        $result = $this->api->execute();
        if ($result->status()) $this->failure($result);
        $ciclos = Ciclo::fromTable($result->table());

        $this->api->command('Esquemas_pago', 'EsquemaPago', 'Groups');
        $result = $this->api->execute();
        if ($result->status()) $this->failure($result);
        $grupos = GrupoOpcion::fromTable($result->table());

        $this->api->command('Esquemas_pago', 'EsquemaPago', 'Students');
        $result = $this->api->execute();

        if (!$result->status()) {
            $alumnos = AlumnoOpcion::fromTable($result->table());
            foreach ($alumnos as $alumno) $alumno->Matricula = (string) decrypt($alumno->Matricula);
            $this->success('Catálogos.', ['ciclos' => $ciclos, 'grupos' => $grupos, 'alumnos' => $alumnos]);
        } else {
            $this->failure($result);
        }
    }

    public function listar(): void
    {
        $this->api->command('Esquemas_pago', 'EsquemaPago', 'List');
        $result = $this->api->execute();

        if (!$result->status()) {
            $this->success('Esquemas obtenidos.', ['items' => EsquemaPago::fromTable($result->table())]);
        } else {
            $this->failure($result);
        }
    }

    /** Vista previa: cada pago del esquema, mes por mes, y el total por alumno (lo mismo que guardará aplicar). */
    public function desglose(int $IdEsquema): void
    {
        $pagos = CalendarioPagos::de($this->esquema($IdEsquema));
        $this->success('Desglose obtenido.', [
            'pagos' => $pagos,
            'total' => round(array_sum(array_column($pagos, 'Monto')), 2),
        ]);
    }

    /**
     * Sin IdEsquema es un alta; con él, un cambio. Las mensualidades tienen que caer dentro de los meses del
     * ciclo: $esquema->PrimerMes llega como AAAA-MM y se guarda como el día 1 de ese mes.
     */
    public function guardar(EsquemaPago $esquema): void
    {
        $this->ensure($esquema->Nombre !== '', 'El nombre es obligatorio.');
        $this->ensure((bool) preg_match('/^.{1,80}$/us', $esquema->Nombre), 'El nombre no puede pasar de 80 caracteres.');
        $this->ensure($esquema->MontoColegiatura > 0, 'Monto de colegiatura no válido.');
        $this->ensure($esquema->MontoInscripcion >= 0, 'Monto de inscripción no válido.');
        $this->ensure($esquema->RecargoMonto >= 0, 'Recargo no válido.');
        $this->ensure($esquema->DiaVencimiento >= 1 && $esquema->DiaVencimiento <= 28, 'Día de vencimiento entre 1 y 28.');

        $ciclo = $this->ciclo((int) $esquema->IdCiclo);
        $this->ensure($ciclo !== null, 'Selecciona un ciclo escolar.');
        $this->ensure($ciclo->FechaInicio !== '' && $ciclo->FechaFin !== '', 'El ciclo no tiene fechas de inicio y fin. Complétalas en Ciclo escolar.', 409);

        $mes = DateTime::createFromFormat('!Y-m', $esquema->PrimerMes);
        $this->ensure($mes && $mes->format('Y-m') === $esquema->PrimerMes, 'Selecciona el mes de la primera mensualidad.');
        $inicio = new DateTime(substr($ciclo->FechaInicio, 0, 7) . '-01');
        $fin    = new DateTime(substr($ciclo->FechaFin, 0, 7) . '-01');
        $this->ensure($mes >= $inicio && $mes <= $fin, 'La primera mensualidad debe estar dentro del ciclo escolar.');

        $disponibles = ((int) $fin->format('Y') - (int) $mes->format('Y')) * 12 + ((int) $fin->format('n') - (int) $mes->format('n')) + 1;
        $this->ensure($esquema->NumMeses >= 1, 'Indica cuántas mensualidades se cobran.');
        $this->ensure($esquema->NumMeses <= $disponibles, "Desde ese mes el ciclo solo tiene $disponibles mensualidad(es).");

        if (!$esquema->IdEsquema) {
            $this->api->command('Esquemas_pago', 'EsquemaPago', 'Insert');
        } else {
            $this->api->command('Esquemas_pago', 'EsquemaPago', 'Update');
            $this->api->addParameter('IdEsquema', 'I', $esquema->IdEsquema);
        }
        $this->api->addParameter('IdCiclo',          'I', $esquema->IdCiclo);
        $this->api->addParameter('Nombre',           'S', $esquema->Nombre);
        $this->api->addParameter('MontoInscripcion', 'N', $esquema->MontoInscripcion);
        $this->api->addParameter('MontoColegiatura', 'N', $esquema->MontoColegiatura);
        $this->api->addParameter('PrimerMes',        'D', $mes->format('Y-m-01'));
        $this->api->addParameter('NumMeses',         'I', $esquema->NumMeses);
        $this->api->addParameter('DiaVencimiento',   'I', $esquema->DiaVencimiento);
        $this->api->addParameter('RecargoMonto',     'N', $esquema->RecargoMonto);
        $result = $this->api->execute();

        if (!$result->status()) {
            if (!$esquema->IdEsquema) $this->success('Esquema de pago creado.', ['IdEsquema' => $result->id()], 201);
            $this->success('Esquema actualizado. Vuelve a aplicarlo para llevar los cambios a los pagos pendientes.');
        } else {
            $this->failure($result);
        }
    }

    public function eliminar(int $IdEsquema): void
    {
        $this->api->command('Esquemas_pago', 'EsquemaPago', 'Delete');
        $this->api->addParameter('IdEsquema', 'I', $IdEsquema);
        $result = $this->api->execute();

        if (!$result->status()) {
            $this->ensure($result->affected() > 0, 'No se encontró el esquema.', 404);
            $this->success('Esquema eliminado. Las colegiaturas ya generadas se conservan.');
        } else {
            $this->failure($result);
        }
    }

    /**
     * Genera inscripción + mensualidades del esquema a todos los alumnos de su ciclo ($AplicarA = todos),
     * a los de un grupo (grupo + $IdGrupo) o a uno (alumno + $IdCuenta). Los pagos ya pagados no se tocan;
     * los pendientes se actualizan si cambió el monto o el recargo.
     */
    public function aplicar(int $IdEsquema, string $AplicarA = 'todos', int $IdGrupo = 0, int $IdCuenta = 0): void
    {
        $esquema = $this->esquema($IdEsquema);
        if ($AplicarA === 'grupo')  $this->ensure($IdGrupo > 0, 'Selecciona un grupo del ciclo del esquema.');
        if ($AplicarA === 'alumno') $this->ensure($IdCuenta > 0, 'Selecciona un alumno.');

        $this->api->command('Esquemas_pago', 'EsquemaPago', 'Recipients');
        $this->api->addParameter('IdCiclo',  'I', $esquema->IdCiclo);
        $this->api->addParameter('AplicarA', 'S', in_array($AplicarA, ['grupo', 'alumno'], true) ? $AplicarA : 'todos');
        $this->api->addParameter('IdGrupo',  'I', $IdGrupo);
        $this->api->addParameter('IdCuenta', 'I', $IdCuenta);
        $result = $this->api->execute();
        if ($result->status()) $this->failure($result);
        $alumnos = array_map('intval', array_column($result->table(), 'IdCuenta'));
        $this->ensure($alumnos !== [], 'No hay alumnos a quienes aplicar el esquema.', 409);

        $cuenta = ['creado' => 0, 'actualizado' => 0, 'sin_cambios' => 0];
        $calendario = CalendarioPagos::de($esquema);
        foreach ($alumnos as $idCuenta) {
            foreach ($calendario as $pago) {
                $this->api->command('Colegiaturas', 'Colegiatura', 'Generate');
                $this->api->addParameter('IdCuenta',         'I', $idCuenta);
                $this->api->addParameter('IdCiclo',          'I', $esquema->IdCiclo);
                $this->api->addParameter('Tipo',             'S', $pago['Tipo']);
                $this->api->addParameter('Mes',              'S', $pago['Mes']);
                $this->api->addParameter('Anio',             'I', $pago['Anio']);
                $this->api->addParameter('FechaVencimiento', 'D', $pago['FechaVencimiento']);
                $this->api->addParameter('Monto',            'N', $pago['Monto']);
                $this->api->addParameter('RecargoMonto',     'N', $pago['RecargoMonto']);
                $result = $this->api->execute();
                if ($result->status()) $this->failure($result);
                $cuenta[$result->row()['Resultado'] ?? 'sin_cambios']++;
            }
        }

        $mensaje = 'Esquema aplicado a ' . count($alumnos) . " alumno(s): {$cuenta['creado']} pago(s) nuevo(s)";
        if ($cuenta['actualizado']) $mensaje .= ", {$cuenta['actualizado']} actualizado(s)";
        if ($cuenta['sin_cambios']) $mensaje .= ", {$cuenta['sin_cambios']} sin cambios";
        $this->success($mensaje . '.', [], 201);
    }

    /** El esquema; corta con 404 si no existe. */
    private function esquema(int $IdEsquema): EsquemaPago
    {
        $this->api->command('Esquemas_pago', 'EsquemaPago', 'Get');
        $this->api->addParameter('IdEsquema', 'I', $IdEsquema);
        $result = $this->api->execute();
        if ($result->status()) $this->failure($result);
        $this->ensure($result->row() !== null, 'No se encontró el esquema.', 404);
        return EsquemaPago::fromRow($result->row());
    }

    /** El ciclo con sus fechas, o null si no existe. */
    private function ciclo(int $IdCiclo): ?Ciclo
    {
        $this->api->command('Ciclos', 'Ciclo', 'Get');
        $this->api->addParameter('IdCiclo', 'I', $IdCiclo);
        $result = $this->api->execute();
        if ($result->status()) $this->failure($result);
        return $result->row() ? Ciclo::fromRow($result->row()) : null;
    }
}
