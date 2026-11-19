<?php
/**
 * Grupos de cada ciclo con sus materias (solo el administrador).
 *   /grupo/index     → pantalla
 *   /grupo/listar    → los grupos (?IdCiclo= para uno solo); también los usan alumnos, maestros y materias
 *   /grupo/detalle   → un grupo con sus materias, las maestras y los ciclos para el formulario
 *   /grupo/guardar   → alta o cambio, con sus materias (las nuevas se agregan al catálogo)
 *   /grupo/eliminar  → baja (sus alumnos quedan "sin grupo")
 * La URL anterior /administrador/grupos redirige a /grupo/index (tabla Rutas).
 */
class GrupoController extends Controller
{
    public const MODULE = 'escolar';

    public function index(): void
    {
        $this->view('admin/grupos');
    }

    public function listar(int $IdCiclo = 0): void
    {
        $this->api->command('Escolar', 'Grupo', 'List');
        $this->api->addParameter('IdCiclo', 'I', $IdCiclo);
        $result = $this->api->execute();

        if (!$result->status()) {
            $this->success('Grupos obtenidos.', ['items' => Grupo::fromTable($result->table())]);
        } else {
            $this->failure($result);
        }
    }

    public function detalle(int $IdGrupo): void
    {
        $this->api->command('Escolar', 'Grupo', 'Get');
        $this->api->addParameter('IdGrupo', 'I', $IdGrupo);
        $result = $this->api->execute();
        if ($result->status()) $this->failure($result);
        $this->ensure($result->row() !== null, 'No se encontró el grupo.', 404);
        $grupo = Grupo::fromRow($result->row());

        $this->api->command('Escolar', 'Grupo', 'Subjects');
        $this->api->addParameter('IdGrupo', 'I', $IdGrupo);
        $result = $this->api->execute();
        if ($result->status()) $this->failure($result);
        $materias = MateriaOpcion::fromTable($result->table());

        $this->api->command('Escolar', 'Grupo', 'Teachers');
        $result = $this->api->execute();
        if ($result->status()) $this->failure($result);
        $maestros = PersonaOpcion::fromTable($result->table());

        $this->api->command('Escolar', 'Ciclo', 'List');
        $result = $this->api->execute();

        if (!$result->status()) {
            $this->success('Detalle del grupo.', ['grupo' => $grupo, 'materias' => $materias, 'maestros' => $maestros, 'ciclos' => Ciclo::fromTable($result->table())]);
        } else {
            $this->failure($result);
        }
    }

    /**
     * Sin IdGrupo es un alta; con él, un cambio en el que solo se aplica lo que traiga valor (grado, maestra, nivel,
     * ciclo). $Materias son los ids marcados y $MateriasNuevas los nombres a agregar al catálogo.
     */
    public function guardar(Grupo $grupo, array $Materias = [], array $MateriasNuevas = []): void
    {
        $ids     = array_values(array_unique(array_filter(array_map('intval', $Materias), fn(int $id) => $id > 0)));
        $nuevas  = array_values(array_filter(array_map(fn($n) => trim((string) $n), $MateriasNuevas), fn(string $n) => $n !== ''));
        foreach ($nuevas as $nombre) $this->ensure((bool) preg_match('/^.{1,25}$/us', $nombre), "La materia \"$nombre\" pasa de 25 caracteres.");
        $nivelIngles = in_array($grupo->NivelIngles, Grupo::ENGLISH_LEVELS, true) ? $grupo->NivelIngles : null;

        if (!$grupo->IdGrupo) {
            $this->ensure($grupo->Grado !== '', 'Indica el grado del grupo.');
            $this->ensure($ids !== [] || $nuevas !== [], 'Agrega al menos una materia.');

            $this->api->command('Escolar', 'Grupo', 'Insert');
            $this->api->addParameter('Grado',          'S', $grupo->Grado);
            $this->api->addParameter('IdCiclo',        'I', $grupo->IdCiclo ?: null);
            $this->api->addParameter('Nivel',          'I', $grupo->Nivel);
            $this->api->addParameter('NivelIngles',    'S', $nivelIngles);
            $this->api->addParameter('Materias',       'S', implode(',', $ids));
            $this->api->addParameter('MateriasNuevas', 'S', json_encode($nuevas, JSON_UNESCAPED_UNICODE));
            $result = $this->api->execute();
            if ($result->status()) $this->failure($result);
            $this->success('¡Se ha creado el grupo y sus materias!', ['IdGrupo' => $result->id()], 201);
        }

        $this->api->command('Escolar', 'Grupo', 'Update');
        $this->api->addParameter('IdGrupo',            'I', $grupo->IdGrupo);
        $this->api->addParameter('Grado',              'S', $grupo->Grado !== '' ? $grupo->Grado : null);
        $this->api->addParameter('IdMaestra',          'I', $grupo->IdMaestra ?: null);
        $this->api->addParameter('Nivel',              'I', $grupo->Nivel);
        $this->api->addParameter('IdCiclo',            'I', $grupo->IdCiclo ?: null);
        $this->api->addParameter('CambiarNivelIngles', 'B', true);
        $this->api->addParameter('NivelIngles',        'S', $nivelIngles);
        $this->api->addParameter('CambiarMaterias',    'B', true);
        $this->api->addParameter('Materias',           'S', implode(',', $ids));
        $this->api->addParameter('MateriasNuevas',     'S', json_encode($nuevas, JSON_UNESCAPED_UNICODE));
        $result = $this->api->execute();

        if (!$result->status()) {
            $this->success('Se han aplicado los cambios al grupo.');
        } else {
            $this->failure($result);
        }
    }

    public function eliminar(int $IdGrupo): void
    {
        $this->api->command('Escolar', 'Grupo', 'Delete');
        $this->api->addParameter('IdGrupo', 'I', $IdGrupo);
        $result = $this->api->execute();

        if (!$result->status()) {
            $this->ensure($result->affected() > 0, 'No se encontró el grupo.', 404);
            $alumnos = (int) ($result->row()['Alumnos'] ?? 0);
            $mensaje = 'Se ha eliminado el grupo y sus materias.';
            if ($alumnos > 0) $mensaje .= " $alumnos alumno(s) quedaron en «Sin grupo» — reasígnalos pronto.";
            $this->success($mensaje);
        } else {
            $this->failure($result);
        }
    }
}
