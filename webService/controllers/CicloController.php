<?php

/**
 * Recurso: ciclos.  Ruta: /api/ciclos?action=listar|crear|editar|activar|eliminar
 * Ciclos escolares (solo admin). Orquesta validación + respuesta; el SQL vive en
 * CicloBusiness. La conexión la provee la api (router).
 */
class CicloController
{
    private CicloBusiness $ciclos;

    public function __construct(mysqli $conexion)
    {
        requireAdmin();                       // todas las acciones de ciclos son admin
        $this->ciclos = new CicloBusiness($conexion);
    }

    public function listar(): void
    {
        response(200, true, 'Ciclos obtenidos.', ['items' => $this->ciclos->listar()]);
    }

    public function crear(): void
    {
        $nombre = trim($_POST['nombre'] ?? '');
        $ini    = trim($_POST['fecha_inicio'] ?? '');
        $fin    = trim($_POST['fecha_fin'] ?? '');
        if ($nombre === '')             response(400, false, 'El nombre es obligatorio.');
        if ($ini === '' || $fin === '') response(400, false, 'Indica fecha de inicio y fin.');
        if ($fin < $ini)                response(400, false, 'La fecha fin no puede ser anterior a la de inicio.');

        if (!$this->ciclos->crear($nombre, $ini, $fin)) response(500, false, 'No se pudo crear el ciclo.');
        response(201, true, 'Ciclo creado.');
    }

    public function editar(): void
    {
        $id     = (int) ($_POST['id_ciclo'] ?? 0);
        $nombre = trim($_POST['nombre'] ?? '');
        $ini    = trim($_POST['fecha_inicio'] ?? '');
        $fin    = trim($_POST['fecha_fin'] ?? '');
        if ($id <= 0)                   response(400, false, 'Registro no válido.');
        if ($nombre === '')             response(400, false, 'El nombre es obligatorio.');
        if ($ini === '' || $fin === '') response(400, false, 'Indica fecha de inicio y fin.');
        if ($fin < $ini)                response(400, false, 'La fecha fin no puede ser anterior a la de inicio.');

        if (!$this->ciclos->editar($id, $nombre, $ini, $fin)) response(500, false, 'No se pudo actualizar el ciclo.');
        response(200, true, 'Ciclo actualizado.');
    }

    public function activar(): void
    {
        $id = (int) ($_POST['id_ciclo'] ?? 0);
        if ($id <= 0) response(400, false, 'Registro no válido.');
        $this->ciclos->activar($id);
        response(200, true, 'Ciclo activado.');
    }

    public function eliminar(): void
    {
        $id = (int) ($_POST['id_ciclo'] ?? 0);
        if ($id <= 0) response(400, false, 'Registro no válido.');
        if ($this->ciclos->eliminar($id) === 0) response(404, false, 'No se encontró el ciclo.');
        response(200, true, 'Ciclo eliminado.');
    }
}
