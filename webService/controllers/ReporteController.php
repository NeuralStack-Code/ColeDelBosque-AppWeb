<?php

/**
 * Recurso: reportes.  Ruta: /api/reportes?action=contexto|semana|clase|guardar_clase|eliminar_clase|vista_previa|enviar
 * Reporte semanal a papás. Solo maestros (permiso 2), acotado a su grupo.
 */
class ReporteController
{
    private ReporteBusiness        $rep;
    private ReporteColumnaBusiness $cols;
    private ReporteCorreoBusiness  $correo;
    private CalificacionBusiness   $cal;      // contexto del maestro (grupo, ciclo, materias)
    private int   $grupoId;
    private int   $cicloId;
    private array $ciclo;

    public function __construct(mysqli $conexion)
    {
        requireAuth();
        if ((int) ($_SESSION['permiso'] ?? 0) !== 2) {
            response(403, false, 'Solo los maestros pueden acceder a los reportes.');
        }
        $this->rep    = new ReporteBusiness($conexion);
        $this->cols   = new ReporteColumnaBusiness($conexion);
        $this->correo = new ReporteCorreoBusiness();
        $this->cal    = new CalificacionBusiness($conexion);

        $this->grupoId = $this->cal->grupoDelMaestro((int) ($_SESSION['id'] ?? 0));
        $ciclo         = $this->cal->cicloActivo();
        if ($this->grupoId <= 0) response(409, false, 'No tienes un grupo asignado. Contacta a la administración.');
        if (!$ciclo)             response(409, false, 'No hay un ciclo escolar activo. Pide al administrador que active uno.');
        $this->ciclo   = $ciclo;
        $this->cicloId = (int) $ciclo['id_ciclo'];
    }

    /* ---------------- Helpers ---------------- */

    /** Valida Y-m-d (vacío → hoy). */
    private function fecha(string $v): DateTime
    {
        if ($v === '') return new DateTime('today', new DateTimeZone('America/Mexico_City'));
        $d = DateTime::createFromFormat('!Y-m-d', $v);
        if (!$d || $d->format('Y-m-d') !== $v) response(400, false, 'Fecha no válida.');
        return $d;
    }

    /** [lunes, viernes] (Y-m-d) de la semana que contiene la fecha. */
    private function semanaDe(DateTime $d): array
    {
        $lunes = (clone $d)->modify('monday this week');
        return [$lunes->format('Y-m-d'), (clone $lunes)->modify('+4 days')->format('Y-m-d')];
    }

    private function materiaValida(int $materiaId): void
    {
        if ($materiaId <= 0 || !$this->cal->materiaEnGrupo($materiaId, $this->grupoId)) {
            response(400, false, 'Materia no válida para tu grupo.');
        }
    }

    private function alumnoValido(int $cuentaId): void
    {
        if ($cuentaId <= 0 || !$this->cal->alumnoEnGrupo($cuentaId, $this->grupoId)) {
            response(403, false, 'Ese alumno no pertenece a tu grupo.');
        }
    }

    /** Datos del correo de un alumno: [html, asunto, lunes]. Corta si no hay clases. */
    private function armarCorreo(int $cuentaId, DateTime $fecha): array
    {
        [$lunes, $viernes] = $this->semanaDe($fecha);
        $clases = $this->rep->reporteAlumno($cuentaId, $this->grupoId, $lunes, $viernes);
        if (!$clases) response(409, false, 'No hay clases capturadas en esa semana.');

        $alumno = $this->rep->nombreAlumno($cuentaId);
        $html   = $this->correo->html($alumno, $this->cal->gradoDeGrupo($this->grupoId), $this->cols->listar(true), $clases, $lunes, $viernes);
        $asunto = "Reporte semanal de $alumno — " . $this->correo->fechaCorta($lunes) . ' al ' . $this->correo->fechaCorta($viernes);
        return [$html, $asunto, $lunes];
    }

    /* ---------------- Acciones ---------------- */

    public function contexto(): void
    {
        response(200, true, 'Contexto obtenido.', [
            'grado'    => $this->cal->gradoDeGrupo($this->grupoId),
            'materias' => $this->cal->materiasDeGrupo($this->grupoId),
            'columnas' => $this->cols->listar(true),
            'ciclo'    => $this->ciclo['nombre'],
        ]);
    }

    /** Semana (lunes–viernes) con sus clases y el estado de envío por alumno. */
    public function semana(): void
    {
        [$lunes, $viernes] = $this->semanaDe($this->fecha(trim($_GET['inicio'] ?? '')));

        $dias = [];
        for ($i = 0; $i < 5; $i++) $dias[] = date('Y-m-d', strtotime("$lunes +$i days"));

        $envios  = $this->rep->enviosDeSemana($this->grupoId, $lunes);
        $alumnos = $this->rep->alumnosDeGrupo($this->grupoId);
        foreach ($alumnos as &$a) $a['enviado_en'] = $envios[$a['id_cuenta']] ?? null;
        unset($a);

        response(200, true, 'Semana obtenida.', [
            'inicio'  => $lunes,
            'dias'    => $dias,
            'clases'  => $this->rep->clasesDeSemana($this->grupoId, $lunes, $viernes),
            'alumnos' => $alumnos,
        ]);
    }

    /** Tema e incidencias de la clase (materia + día); vacía si aún no se captura. */
    public function clase(): void
    {
        $materiaId = (int) ($_GET['materia_id'] ?? 0);
        $fecha     = $this->fecha(trim($_GET['fecha'] ?? ''))->format('Y-m-d');
        $this->materiaValida($materiaId);

        $clase = $this->rep->claseObtener($this->grupoId, $materiaId, $fecha);
        response(200, true, 'Clase obtenida.', [
            'id_clase' => $clase ? (int) $clase['id_clase'] : null,
            'tema'     => $clase['tema'] ?? '',
            'marcas'   => $clase ? $this->rep->marcasDeClase((int) $clase['id_clase']) : [],
        ]);
    }

    public function guardar_clase(): void
    {
        $materiaId = (int) ($_POST['materia_id'] ?? 0);
        $fecha     = $this->fecha(trim($_POST['fecha'] ?? ''));
        $tema      = trim($_POST['tema'] ?? '');
        $this->materiaValida($materiaId);
        if ((int) $fecha->format('N') > 5) response(400, false, 'Solo se capturan clases de lunes a viernes.');
        if (iconv_strlen($tema) > 255)        response(400, false, 'El tema no puede pasar de 255 caracteres.');

        $tipos = [];
        foreach ($this->cols->listar(true) as $col) $tipos[$col->id_columna] = $col->tipo;
        $alumnos = array_column($this->rep->alumnosDeGrupo($this->grupoId), 'id_cuenta');

        $marcas = [];
        foreach (json_decode($_POST['marcas'] ?? '[]', true) ?: [] as $m) {
            $cuentaId  = (int) ($m['cuenta_id']  ?? 0);
            $columnaId = (int) ($m['columna_id'] ?? 0);
            if (!in_array($cuentaId, $alumnos, true)) response(403, false, 'Hay un alumno que no pertenece a tu grupo.');
            if (!isset($tipos[$columnaId]))           response(400, false, 'Hay una columna que ya no está disponible. Recarga la página.');

            if ($tipos[$columnaId] === 'texto') {
                $nota = trim((string) ($m['nota'] ?? ''));
                if ($nota === '') continue;
                if (iconv_strlen($nota) > 255) response(400, false, 'Las notas no pueden pasar de 255 caracteres.');
                $marcas["$cuentaId-$columnaId"] = ['cuenta_id' => $cuentaId, 'columna_id' => $columnaId, 'marcado' => 0, 'nota' => $nota];
            } elseif (!empty($m['marcado'])) {
                $marcas["$cuentaId-$columnaId"] = ['cuenta_id' => $cuentaId, 'columna_id' => $columnaId, 'marcado' => 1, 'nota' => null];
            }
        }

        if (!$this->rep->guardarClase($this->grupoId, $materiaId, $this->cicloId, $fecha->format('Y-m-d'), $tema, array_values($marcas))) {
            response(500, false, 'No se pudo guardar la clase.');
        }
        response(200, true, 'Clase guardada.');
    }

    public function eliminar_clase(): void
    {
        $id = (int) ($_POST['id_clase'] ?? 0);
        if ($id <= 0) response(400, false, 'Registro no válido.');
        if ($this->rep->eliminarClase($id, $this->grupoId) === 0) response(404, false, 'No se encontró la clase.');
        response(200, true, 'Clase eliminada.');
    }

    /** HTML del correo tal como lo recibirá el papá. */
    public function vista_previa(): void
    {
        $cuentaId = (int) ($_GET['cuenta_id'] ?? 0);
        $this->alumnoValido($cuentaId);
        [$html, $asunto] = $this->armarCorreo($cuentaId, $this->fecha(trim($_GET['inicio'] ?? '')));
        response(200, true, 'Vista previa generada.', ['asunto' => $asunto, 'html' => $html]);
    }

    /** Envía el reporte de UN alumno (la vista los manda uno por uno). */
    public function enviar(): void
    {
        $cuentaId = (int) ($_POST['cuenta_id'] ?? 0);
        $this->alumnoValido($cuentaId);

        $correos = $this->rep->correosTutor($cuentaId);
        if (!$correos) response(409, false, 'El alumno no tiene correo de tutor registrado.');

        [$html, $asunto, $lunes] = $this->armarCorreo($cuentaId, $this->fecha(trim($_POST['inicio'] ?? '')));

        $r = $this->correo->enviar($correos, $asunto, $html);
        if ($r['enviados'] === 0) {
            error_log('Reporte semanal no enviado: ' . implode(' | ', $r['errores']));
            response(502, false, 'No se pudo enviar el correo.');
        }
        // Hora del colegio, sin depender de la zona horaria del servidor ni de MySQL
        $enviadoEn = (new DateTime('now', new DateTimeZone('America/Mexico_City')))->format('Y-m-d H:i:s');
        $this->rep->registrarEnvio($cuentaId, $lunes, $enviadoEn, (int) ($_SESSION['id'] ?? 0), $r['enviados']);

        $msg = $r['errores'] ? 'Reporte enviado, pero uno de los correos falló.' : 'Reporte enviado.';
        response(200, true, $msg, ['enviado_en' => $enviadoEn]);
    }
}
