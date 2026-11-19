<?php
/**
 * Materias (solo el administrador). Una materia puede impartirse en varios grupos.
 *   /materia/index     → pantalla del catálogo
 *   /materia/listar    → las materias con sus grupos
 *   /materia/guardar   → alta o cambio, con los grupos donde se imparte
 *   /materia/eliminar  → baja (si no tiene calificaciones)
 * La URL anterior /administrador/materias redirige a /materia/index (tabla Rutas).
 */
class MateriaController extends Controller
{
    public const MODULE = 'escolar';

    public function index(): void
    {
        $this->view('admin/materias');
    }

    public function listar(): void
    {
        $this->api->command('Escolar', 'Materia', 'List');
        $result = $this->api->execute();

        if (!$result->status()) {
            $this->success('Materias obtenidas.', ['items' => Materia::fromTable($result->table())]);
        } else {
            $this->failure($result);
        }
    }

    /** Sin IdMateria es un alta; con ella, un cambio. $Grupos son los ids de los grupos marcados en el formulario. */
    public function guardar(Materia $materia, array $Grupos = []): void
    {
        $this->ensure($materia->Nombre !== '', 'El nombre es obligatorio.');
        $this->ensure((bool) preg_match('/^.{1,25}$/us', $materia->Nombre), 'El nombre admite máximo 25 caracteres.');

        // Solo ids de grupo válidos y sin repetir; al business viajan como "69,70,71".
        $ids = array_values(array_unique(array_filter(array_map('intval', $Grupos), fn(int $id) => $id > 0)));

        if (!$materia->IdMateria) {
            $this->api->command('Escolar', 'Materia', 'Insert');
        } else {
            $this->api->command('Escolar', 'Materia', 'Update');
            $this->api->addParameter('IdMateria', 'I', $materia->IdMateria);
        }
        $this->api->addParameter('Nombre', 'S', $materia->Nombre);
        $this->api->addParameter('Grupos', 'S', implode(',', $ids));
        $result = $this->api->execute();

        if (!$result->status()) {
            if (!$materia->IdMateria) $this->success('Materia creada.', ['IdMateria' => $result->id()], 201);
            $this->success('Materia actualizada.');
        } else {
            $this->failure($result);
        }
    }

    public function eliminar(int $IdMateria): void
    {
        $this->api->command('Escolar', 'Materia', 'Delete');
        $this->api->addParameter('IdMateria', 'I', $IdMateria);
        $result = $this->api->execute();

        if (!$result->status()) {
            $this->ensure($result->affected() > 0, 'No se encontró la materia.', 404);
            $this->success('Materia eliminada.');
        } else {
            $this->failure($result);
        }
    }
}
