<?php
/**
 * Calificaciones por alumno, materia y ciclo activo (solo maestros), acotadas a sus grupos: el propio (todas las
 * materias) y los de sus niveles de inglés (solo Inglés). Cada método recibe el grupo en que se trabaja (IdGrupo).
 *   /calificacion/index     → pantalla
 *   /calificacion/contexto  → grupos de la maestra y materias del grupo
 *   /calificacion/listar    → alumnos del grupo con sus notas de una materia
 *   /calificacion/guardar   → notas de un alumno en una materia
 * La URL anterior /maestro/calificaciones redirige a /calificacion/index (index.php raíz).
 */
class CalificacionController extends Controller
{
    use ContextoMaestro;

    protected int $role = self::TEACHER;
    protected array $readActions = ['index', 'contexto', 'listar'];

    public function index(): void
    {
        $this->view('maestro/calificaciones', ['nombre' => $this->user()]);
    }

    public function contexto(int $IdGrupo = 0): void
    {
        $this->cargarContexto($IdGrupo);
        $this->success('Contexto obtenido.', [
            'IdGrupo'  => $this->grupo->IdGrupo,
            'grupos'   => $this->grupos,
            'grado'    => $this->grupo->Grado,
            'materias' => $this->materias(),
            'ciclo'    => $this->ciclo->Nombre,
        ]);
    }

    public function listar(int $IdMateria = 0, int $IdGrupo = 0): void
    {
        $this->cargarContexto($IdGrupo);
        $this->ensure($IdMateria > 0, 'Materia no válida para tu grupo.');

        $this->api->command('Calificaciones', 'Calificacion', 'List');
        $this->api->addParameter('IdMateria',  'I', $IdMateria);
        $this->api->addParameter('IdCiclo',    'I', $this->ciclo->IdCiclo);
        $this->api->addParameter('IdGrupo',    'I', $this->grupo->IdGrupo);
        $this->api->addParameter('SoloIngles', 'B', $this->grupo->SoloIngles);
        $result = $this->api->execute();

        if (!$result->status()) {
            $this->success('Calificaciones obtenidas.', ['alumnos' => AlumnoCalificacion::fromTable($result->table())]);
        } else {
            $this->failure($result);
        }
    }

    /** Cada nota es opcional (vacía) o un número de 0 a 10. Con calificación final queda aprobado (6 o más) o reprobado. */
    public function guardar(int $IdCuenta, int $IdMateria, int $IdGrupo = 0, string $P1 = '', string $P2 = '', string $P3 = '',
                            string $CalifFinal = '', string $Reporte = ''): void
    {
        $this->cargarContexto($IdGrupo);
        $notas = [];
        foreach (['P1' => $P1, 'P2' => $P2, 'P3' => $P3, 'CalifFinal' => $CalifFinal] as $campo => $valor) {
            $notas[$campo] = $this->nota($valor);
        }

        $this->api->command('Calificaciones', 'Calificacion', 'Save');
        $this->api->addParameter('IdCuenta',   'I', $IdCuenta);
        $this->api->addParameter('IdMateria',  'I', $IdMateria);
        $this->api->addParameter('IdCiclo',    'I', $this->ciclo->IdCiclo);
        $this->api->addParameter('IdGrupo',    'I', $this->grupo->IdGrupo);
        $this->api->addParameter('SoloIngles', 'B', $this->grupo->SoloIngles);
        foreach ($notas as $campo => $nota) $this->api->addParameter($campo, 'N', $nota);
        $this->api->addParameter('Reporte',    'S', $Reporte === '' ? null : $Reporte);
        $result = $this->api->execute();

        if (!$result->status()) {
            $this->success('Calificación guardada.');
        } else {
            $this->failure($result);
        }
    }

    /** '' → sin nota; si no, un número entre 0 y 10 (corta si no lo es). */
    private function nota(string $valor): ?float
    {
        if ($valor === '') return null;
        $nota = filter_var($valor, FILTER_VALIDATE_FLOAT);
        $this->ensure($nota !== false, 'Las calificaciones deben ser números.');
        $this->ensure($nota >= 0 && $nota <= 10, 'Las calificaciones deben estar entre 0 y 10.');
        return (float) $nota;
    }
}
