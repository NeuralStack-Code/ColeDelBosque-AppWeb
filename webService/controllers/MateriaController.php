<?php

/**
 * Recurso: materias.  Ruta: /api/materias?action=listar|crear|editar|eliminar
 * Catálogo de materias, muchos-a-muchos con grupos (solo admin).
 */
class MateriaController
{
    private MateriaBusiness $materias;

    public function __construct(mysqli $conexion)
    {
        requireAdmin();
        $this->materias = new MateriaBusiness($conexion);
    }

    public function listar(): void
    {
        response(200, true, 'Materias obtenidas.', ['items' => $this->materias->listar()]);
    }

    public function crear(): void
    {
        $nombre = trim($_POST['nombre'] ?? '');
        $grupos = json_decode($_POST['grupos'] ?? '[]', true) ?: [];
        if ($nombre === '') response(400, false, 'El nombre es obligatorio.');
        if ($this->materias->nombreDuplicado($nombre)) response(409, false, 'Ya existe una materia con ese nombre.');

        $id = $this->materias->crear($nombre);
        if ($id <= 0) response(500, false, 'No se pudo crear la materia.');

        $this->materias->sincronizarGrupos($id, $grupos);
        response(201, true, 'Materia creada.');
    }

    public function editar(): void
    {
        $id     = (int) ($_POST['id_materia'] ?? 0);
        $nombre = trim($_POST['nombre'] ?? '');
        $grupos = json_decode($_POST['grupos'] ?? '[]', true) ?: [];
        if ($id <= 0)       response(400, false, 'Registro no válido.');
        if ($nombre === '') response(400, false, 'El nombre es obligatorio.');
        if ($this->materias->nombreDuplicado($nombre, $id)) response(409, false, 'Ya existe otra materia con ese nombre.');

        if (!$this->materias->editar($id, $nombre)) response(500, false, 'No se pudo actualizar la materia.');

        $this->materias->sincronizarGrupos($id, $grupos);
        response(200, true, 'Materia actualizada.');
    }

    public function eliminar(): void
    {
        $id = (int) ($_POST['id_materia'] ?? 0);
        if ($id <= 0) response(400, false, 'Registro no válido.');
        if ($this->materias->tieneCalificaciones($id)) {
            response(409, false, 'No se puede eliminar: la materia tiene calificaciones registradas.');
        }
        $this->materias->eliminar($id);
        response(200, true, 'Materia eliminada.');
    }
}
