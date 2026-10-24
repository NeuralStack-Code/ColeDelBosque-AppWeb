<?php
require_once dirname(__DIR__, 2) . '/apiService/core/crypto.php';   // decrypt(): los correos de los tutores van encriptados
require_once dirname(__DIR__, 2) . '/apiService/core/mail.php';     // enviarCorreo() → api central

/**
 * Reporte semanal a papás (solo maestros), acotado a sus grupos: el propio (todas las materias) y los de sus niveles
 * de inglés (solo Inglés). Cada método recibe el grupo en que se trabaja (IdGrupo).
 *   /reporte/index         → pantalla
 *   /reporte/contexto      → grupos de la maestra, materias del grupo y columnas activas del reporte
 *   /reporte/semana        → la semana (lunes a viernes) con sus clases y el estado de envío por alumno
 *   /reporte/clase         → tema e incidencias de una clase (materia + día)
 *   /reporte/guardarClase  → guarda la clase y reemplaza sus incidencias
 *   /reporte/eliminarClase → borra una clase con sus incidencias
 *   /reporte/vistaPrevia   → el correo tal como lo recibirá el papá
 *   /reporte/enviar        → manda el reporte de UN alumno (la pantalla los manda uno por uno)
 * La URL anterior /maestro/reportes redirige a /reporte/index (index.php raíz).
 */
class ReporteController extends Controller
{
    use ContextoMaestro;

    /** Hora del colegio, sin depender de la zona horaria del servidor ni de la base. */
    private const ZONA = 'America/Mexico_City';

    protected int $role = self::TEACHER;
    protected array $readActions = ['index', 'contexto', 'semana', 'clase', 'vistaPrevia'];

    public function index(): void
    {
        $this->view('maestro/reportes', ['nombre' => $this->user()]);
    }

    public function contexto(int $IdGrupo = 0): void
    {
        $this->cargarContexto($IdGrupo);
        $this->success('Contexto obtenido.', [
            'IdGrupo'  => $this->grupo->IdGrupo,
            'grupos'   => $this->grupos,
            'grado'    => $this->grupo->Grado,
            'materias' => $this->materias(),
            'columnas' => $this->columnas(),
            'ciclo'    => $this->ciclo->Nombre,
        ]);
    }

    /** La semana que contiene $Inicio (vacío = hoy): sus 5 días, las clases capturadas y a quién ya se le envió. */
    public function semana(int $IdGrupo = 0, string $Inicio = ''): void
    {
        $this->cargarContexto($IdGrupo);
        [$lunes, $viernes] = $this->semanaDe($this->fecha($Inicio));

        $dias = [];
        for ($i = 0; $i < 5; $i++) $dias[] = date('Y-m-d', strtotime("$lunes +$i days"));

        $this->api->command('Reportes', 'Reporte', 'Sends');
        $this->api->addParameter('IdGrupo', 'I', $this->grupo->IdGrupo);
        $this->api->addParameter('Semana',  'D', $lunes);
        $result = $this->api->execute();
        if ($result->status()) $this->failure($result);
        $envios = array_column($result->table(), 'EnviadoEn', 'IdCuenta');

        $alumnos = $this->alumnos();
        foreach ($alumnos as $alumno) $alumno->EnviadoEn = $envios[$alumno->IdCuenta] ?? null;

        $this->api->command('Reportes', 'Reporte', 'Classes');
        $this->api->addParameter('IdGrupo', 'I', $this->grupo->IdGrupo);
        $this->api->addParameter('Desde',   'D', $lunes);
        $this->api->addParameter('Hasta',   'D', $viernes);
        $result = $this->api->execute();

        if (!$result->status()) {
            $this->success('Semana obtenida.', ['inicio' => $lunes, 'dias' => $dias, 'clases' => ClaseReporte::fromTable($result->table()), 'alumnos' => $alumnos]);
        } else {
            $this->failure($result);
        }
    }

    /** Tema e incidencias de la clase (materia + día); vacía si aún no se captura. */
    public function clase(int $IdMateria = 0, int $IdGrupo = 0, string $Fecha = ''): void
    {
        $this->cargarContexto($IdGrupo);
        $this->materiaValida($IdMateria);

        $this->api->command('Reportes', 'Reporte', 'Lesson');
        $this->api->addParameter('IdGrupo',   'I', $this->grupo->IdGrupo);
        $this->api->addParameter('IdMateria', 'I', $IdMateria);
        $this->api->addParameter('Fecha',     'D', $this->fecha($Fecha)->format('Y-m-d'));
        $result = $this->api->execute();
        if ($result->status()) $this->failure($result);
        $clase = $result->row() ? ClaseReporte::fromRow($result->row()) : null;
        if ($clase === null) $this->success('Clase obtenida.', ['IdClase' => null, 'tema' => '', 'marcas' => []]);

        $this->api->command('Reportes', 'Reporte', 'Marks');
        $this->api->addParameter('IdClase', 'I', $clase->IdClase);
        $result = $this->api->execute();

        if (!$result->status()) {
            $this->success('Clase obtenida.', ['IdClase' => $clase->IdClase, 'tema' => $clase->Tema, 'marcas' => MarcaReporte::fromTable($result->table())]);
        } else {
            $this->failure($result);
        }
    }

    /**
     * Guarda la clase (lunes a viernes) y REEMPLAZA sus incidencias. $Marcas es la lista JSON que arma la pantalla:
     * [{IdCuenta, IdColumna, Marcado}] para casillas y [{IdCuenta, IdColumna, Nota}] para textos.
     */
    public function guardarClase(int $IdMateria = 0, int $IdGrupo = 0, string $Fecha = '', string $Tema = '', string $Marcas = '[]'): void
    {
        $this->cargarContexto($IdGrupo);
        $fecha = $this->fecha($Fecha);
        $this->materiaValida($IdMateria);
        $this->ensure((int) $fecha->format('N') <= 5, 'Solo se capturan clases de lunes a viernes.');
        $this->ensure((bool) preg_match('/^.{0,255}$/us', $Tema), 'El tema no puede pasar de 255 caracteres.');

        $tipos = [];
        foreach ($this->columnas() as $columna) $tipos[$columna->IdColumna] = $columna->Tipo;
        $alumnos = array_map(fn(AlumnoReporte $a) => $a->IdCuenta, $this->alumnos());

        $marcas = [];
        foreach (json_decode($Marcas, true) ?: [] as $marca) {
            $idCuenta  = (int) ($marca['IdCuenta']  ?? 0);
            $idColumna = (int) ($marca['IdColumna'] ?? 0);
            $this->ensure(in_array($idCuenta, $alumnos, true), 'Hay un alumno que no pertenece a tu grupo.', 403);
            $this->ensure(isset($tipos[$idColumna]), 'Hay una columna que ya no está disponible. Recarga la página.');

            if ($tipos[$idColumna] === 'texto') {
                $nota = trim((string) ($marca['Nota'] ?? ''));
                if ($nota === '') continue;
                $this->ensure((bool) preg_match('/^.{1,255}$/us', $nota), 'Las notas no pueden pasar de 255 caracteres.');
                $marcas["$idCuenta-$idColumna"] = ['IdCuenta' => $idCuenta, 'IdColumna' => $idColumna, 'Marcado' => 0, 'Nota' => $nota];
            } elseif (!empty($marca['Marcado'])) {
                $marcas["$idCuenta-$idColumna"] = ['IdCuenta' => $idCuenta, 'IdColumna' => $idColumna, 'Marcado' => 1, 'Nota' => ''];
            }
        }

        $this->api->command('Reportes', 'Reporte', 'SaveLesson');
        $this->api->addParameter('IdGrupo',   'I', $this->grupo->IdGrupo);
        $this->api->addParameter('IdMateria', 'I', $IdMateria);
        $this->api->addParameter('IdCiclo',   'I', $this->ciclo->IdCiclo);
        $this->api->addParameter('Fecha',     'D', $fecha->format('Y-m-d'));
        $this->api->addParameter('Tema',      'S', $Tema);
        $this->api->addParameter('Marcas',    'S', json_encode(array_values($marcas), JSON_UNESCAPED_UNICODE));
        $result = $this->api->execute();

        if (!$result->status()) {
            $this->success('Clase guardada.', ['IdClase' => $result->id()]);
        } else {
            $this->failure($result);
        }
    }

    /** La maestra de inglés solo borra clases de Inglés. */
    public function eliminarClase(int $IdClase, int $IdGrupo = 0): void
    {
        $this->cargarContexto($IdGrupo);

        $this->api->command('Reportes', 'Reporte', 'GetLesson');
        $this->api->addParameter('IdClase', 'I', $IdClase);
        $this->api->addParameter('IdGrupo', 'I', $this->grupo->IdGrupo);
        $result = $this->api->execute();
        if ($result->status()) $this->failure($result);
        $this->materiaValida((int) ($result->row()['IdMateria'] ?? 0));

        $this->api->command('Reportes', 'Reporte', 'DeleteLesson');
        $this->api->addParameter('IdClase', 'I', $IdClase);
        $this->api->addParameter('IdGrupo', 'I', $this->grupo->IdGrupo);
        $result = $this->api->execute();

        if (!$result->status()) {
            $this->ensure($result->affected() > 0, 'No se encontró la clase.', 404);
            $this->success('Clase eliminada.');
        } else {
            $this->failure($result);
        }
    }

    /** El correo de un alumno tal como lo recibirá el papá. */
    public function vistaPrevia(int $IdCuenta, int $IdGrupo = 0, string $Inicio = ''): void
    {
        $this->cargarContexto($IdGrupo);
        $alumno = $this->alumno($IdCuenta);
        [$html, $asunto] = $this->correo($alumno, $this->fecha($Inicio));
        $this->success('Vista previa generada.', ['asunto' => $asunto, 'html' => $html]);
    }

    /** Manda el reporte de la semana de UN alumno a sus tutores y lo registra. */
    public function enviar(int $IdCuenta, int $IdGrupo = 0, string $Inicio = ''): void
    {
        $this->cargarContexto($IdGrupo);
        $alumno = $this->alumno($IdCuenta);
        $correos = $alumno->correos();
        $this->ensure($correos !== [], 'El alumno no tiene correo de tutor registrado.', 409);

        [$html, $asunto, $lunes] = $this->correo($alumno, $this->fecha($Inicio));

        $enviados = 0; $errores = [];
        foreach ($correos as $correo) {
            $error = enviarCorreo($correo, $asunto, $html, ['fromName' => 'Colegio del Bosque']);
            if ($error === '') $enviados++;
            else $errores[] = $error;
        }
        if ($enviados === 0) {
            error_log('Reporte semanal no enviado: ' . implode(' | ', $errores));
            response(502, false, 'No se pudo enviar el correo.');
        }

        $enviadoEn = (new DateTime('now', new DateTimeZone(self::ZONA)))->format('Y-m-d H:i:s');
        $this->api->command('Reportes', 'Reporte', 'RegisterSend');
        $this->api->addParameter('IdCuenta',      'I', $IdCuenta);
        $this->api->addParameter('Semana',        'D', $lunes);
        $this->api->addParameter('EnviadoEn',     'S', $enviadoEn);
        $this->api->addParameter('EnviadoPor',    'I', $this->userId());
        $this->api->addParameter('Destinatarios', 'I', $enviados);
        $result = $this->api->execute();

        if (!$result->status()) {
            $this->success($errores ? 'Reporte enviado, pero uno de los correos falló.' : 'Reporte enviado.', ['EnviadoEn' => $enviadoEn]);
        } else {
            $this->failure($result);
        }
    }

    /* ── Ayudas ── */

    /** AAAA-MM-DD (vacío = hoy en el colegio); corta si no es una fecha válida. */
    private function fecha(string $valor): DateTime
    {
        if ($valor === '') return new DateTime('today', new DateTimeZone(self::ZONA));
        $fecha = DateTime::createFromFormat('!Y-m-d', $valor);
        $this->ensure($fecha && $fecha->format('Y-m-d') === $valor, 'Fecha no válida.');
        return $fecha;
    }

    /** [lunes, viernes] (AAAA-MM-DD) de la semana que contiene la fecha. */
    private function semanaDe(DateTime $fecha): array
    {
        $lunes = (clone $fecha)->modify('monday this week');
        return [$lunes->format('Y-m-d'), (clone $lunes)->modify('+4 days')->format('Y-m-d')];
    }

    private function materiaValida(int $IdMateria): void
    {
        $ids = array_map(fn(MateriaOpcion $m) => $m->IdMateria, $this->materias());
        $this->ensure($IdMateria > 0 && in_array($IdMateria, $ids, true), 'Materia no válida para tu grupo.');
    }

    /** @return ReporteColumna[] las columnas activas, en orden */
    private function columnas(): array
    {
        $this->api->command('Reportes', 'ReporteColumna', 'List');
        $this->api->addParameter('SoloActivas', 'B', true);
        $result = $this->api->execute();
        if ($result->status()) $this->failure($result);
        return ReporteColumna::fromTable($result->table());
    }

    /** @return AlumnoReporte[] los alumnos del grupo de trabajo */
    private function alumnos(): array
    {
        $this->api->command('Reportes', 'Reporte', 'Students');
        $this->api->addParameter('IdGrupo', 'I', $this->grupo->IdGrupo);
        $result = $this->api->execute();
        if ($result->status()) $this->failure($result);
        return AlumnoReporte::fromTable($result->table());
    }

    /** El alumno con los correos de sus tutores ya descifrados; corta con 403 si no es del grupo. */
    private function alumno(int $IdCuenta): CorreoAlumno
    {
        $this->api->command('Reportes', 'Reporte', 'Student');
        $this->api->addParameter('IdCuenta', 'I', $IdCuenta);
        $this->api->addParameter('IdGrupo',  'I', $this->grupo->IdGrupo);
        $result = $this->api->execute();
        if ($result->status()) $this->failure($result);
        $this->ensure($result->row() !== null, 'Ese alumno no pertenece a tu grupo.', 403);

        $alumno = CorreoAlumno::fromRow($result->row());
        $alumno->CorreoTutor  = $alumno->CorreoTutor  === null ? null : (string) decrypt($alumno->CorreoTutor);
        $alumno->CorreoTutor2 = $alumno->CorreoTutor2 === null ? null : (string) decrypt($alumno->CorreoTutor2);
        return $alumno;
    }

    /** [html, asunto, lunes] del correo de un alumno. Corta con 409 si no hay clases esa semana. */
    private function correo(CorreoAlumno $alumno, DateTime $fecha): array
    {
        [$lunes, $viernes] = $this->semanaDe($fecha);
        $this->api->command('Reportes', 'Reporte', 'StudentWeek');
        $this->api->addParameter('IdCuenta', 'I', $alumno->IdCuenta);
        $this->api->addParameter('IdGrupo',  'I', $this->grupo->IdGrupo);
        $this->api->addParameter('Desde',    'D', $lunes);
        $this->api->addParameter('Hasta',    'D', $viernes);
        $result = $this->api->execute();
        if ($result->status()) $this->failure($result);
        $clases = ReporteCorreo::clases($result->table());
        $this->ensure($clases !== [], 'No hay clases capturadas en esa semana.', 409);

        $html = ReporteCorreo::html($alumno->NombreCompleto, $this->grupo->Grado, $this->columnas(), $clases, $lunes, $viernes);
        return [$html, ReporteCorreo::asunto($alumno->NombreCompleto, $lunes, $viernes), $lunes];
    }
}
