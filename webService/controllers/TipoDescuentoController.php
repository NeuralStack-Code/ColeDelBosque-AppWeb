<?php

/**
 * Recurso: tipos-descuento.  Ruta: /api/tipos-descuento?action=listar|crear|editar|eliminar
 * Catálogo de tipos de descuento (solo admin). El % vive en la BD, nada hardcodeado.
 */
class TipoDescuentoController
{
    private TipoDescuentoBusiness $tipos;

    public function __construct(mysqli $conexion)
    {
        requireAdmin();
        $this->tipos = new TipoDescuentoBusiness($conexion);
    }

    /** aplica_a solo puede ser colegiatura o inscripción. */
    private function aplica(string $a): string
    {
        return $a === 'inscripcion' ? 'inscripcion' : 'colegiatura';
    }

    public function listar(): void
    {
        response(200, true, 'Tipos de descuento obtenidos.', ['items' => $this->tipos->listar()]);
    }

    public function crear(): void
    {
        $nombre = trim($_POST['nombre'] ?? '');
        $pct    = filter_var($_POST['porcentaje'] ?? '', FILTER_VALIDATE_FLOAT);
        $aplica = $this->aplica($_POST['aplica_a'] ?? 'colegiatura');
        if ($nombre === '')                            response(400, false, 'El nombre es obligatorio.');
        if ($pct === false || $pct < 0 || $pct > 100)  response(400, false, 'El porcentaje debe estar entre 0 y 100.');
        if (!$this->tipos->crear($nombre, $pct, $aplica)) response(500, false, 'No se pudo crear el descuento.');
        response(201, true, 'Tipo de descuento creado.');
    }

    public function editar(): void
    {
        $id     = (int) ($_POST['id_descuento'] ?? 0);
        $nombre = trim($_POST['nombre'] ?? '');
        $pct    = filter_var($_POST['porcentaje'] ?? '', FILTER_VALIDATE_FLOAT);
        $aplica = $this->aplica($_POST['aplica_a'] ?? 'colegiatura');
        $activo = (int) ($_POST['activo'] ?? 1) === 0 ? 0 : 1;
        if ($id <= 0)                                  response(400, false, 'Registro no válido.');
        if ($nombre === '')                            response(400, false, 'El nombre es obligatorio.');
        if ($pct === false || $pct < 0 || $pct > 100)  response(400, false, 'El porcentaje debe estar entre 0 y 100.');
        if (!$this->tipos->editar($id, $nombre, $pct, $aplica, $activo)) response(500, false, 'No se pudo actualizar el descuento.');
        response(200, true, 'Tipo de descuento actualizado.');
    }

    public function eliminar(): void
    {
        $id = (int) ($_POST['id_descuento'] ?? 0);
        if ($id <= 0) response(400, false, 'Registro no válido.');
        if ($this->tipos->tieneColegiaturas($id)) response(409, false, 'No se puede eliminar: hay pagos con este descuento aplicado. Puedes desactivarlo.');
        $this->tipos->eliminar($id);
        response(200, true, 'Tipo de descuento eliminado.');
    }
}
