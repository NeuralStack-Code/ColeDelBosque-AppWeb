<?php

/**
 * Recurso: tipos-recibo.  Ruta: /api/tipos-recibo?action=listar|crear|editar|eliminar
 * Catálogo de tipos de recibo (solo admin).
 */
class TipoReciboController
{
    private TipoReciboBusiness $tipos;

    public function __construct(mysqli $conexion)
    {
        requireAdmin();
        $this->tipos = new TipoReciboBusiness($conexion);
    }

    /** naturaleza solo puede ser ingreso o gasto. */
    private function naturaleza(string $n): string
    {
        return $n === 'gasto' ? 'gasto' : 'ingreso';
    }

    public function listar(): void
    {
        response(200, true, 'Tipos obtenidos.', ['items' => $this->tipos->listar()]);
    }

    public function crear(): void
    {
        $nombre = trim($_POST['nombre'] ?? '');
        $nat    = $this->naturaleza($_POST['naturaleza'] ?? 'ingreso');
        if ($nombre === '') response(400, false, 'El nombre es obligatorio.');
        if (!$this->tipos->crear($nombre, $nat)) response(500, false, 'No se pudo crear el tipo.');
        response(201, true, 'Tipo de recibo creado.');
    }

    public function editar(): void
    {
        $id     = (int) ($_POST['id_tipo'] ?? 0);
        $nombre = trim($_POST['nombre'] ?? '');
        $nat    = $this->naturaleza($_POST['naturaleza'] ?? 'ingreso');
        if ($id <= 0)       response(400, false, 'Registro no válido.');
        if ($nombre === '') response(400, false, 'El nombre es obligatorio.');
        if (!$this->tipos->editar($id, $nombre, $nat)) response(500, false, 'No se pudo actualizar el tipo.');
        response(200, true, 'Tipo de recibo actualizado.');
    }

    public function eliminar(): void
    {
        $id = (int) ($_POST['id_tipo'] ?? 0);
        if ($id <= 0) response(400, false, 'Registro no válido.');
        if ($this->tipos->tieneRecibos($id)) response(409, false, 'No se puede eliminar: hay recibos con este tipo.');
        $this->tipos->eliminar($id);
        response(200, true, 'Tipo de recibo eliminado.');
    }
}
