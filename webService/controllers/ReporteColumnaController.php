<?php

/**
 * Recurso: reporte-columnas.  Ruta: /api/reporte-columnas?action=listar|crear|editar|eliminar
 * Columnas que las maestras capturan en el reporte semanal (solo admin).
 */
class ReporteColumnaController
{
    private ReporteColumnaBusiness $cols;

    public function __construct(mysqli $conexion)
    {
        requireAdmin();
        $this->cols = new ReporteColumnaBusiness($conexion);
    }

    /** Lee y valida el formulario → [nombre, tipo, etiqueta, orden, activo]. */
    private function datos(): array
    {
        $nombre   = trim($_POST['nombre'] ?? '');
        $tipo     = ($_POST['tipo'] ?? '') === 'texto' ? 'texto' : 'casilla';
        $etiqueta = trim($_POST['etiqueta'] ?? '');
        $orden    = (int) ($_POST['orden'] ?? 0);
        $activo   = ($_POST['activo'] ?? '1') === '0' ? 0 : 1;

        if ($nombre === '')              response(400, false, 'El nombre es obligatorio.');
        if (iconv_strlen($nombre) > 60)     response(400, false, 'El nombre no puede pasar de 60 caracteres.');
        if (iconv_strlen($etiqueta) > 60)   response(400, false, 'El texto del correo no puede pasar de 60 caracteres.');
        // La etiqueta solo aplica a casillas (es lo que lee el papá cuando está marcada)
        $etiqueta = ($tipo === 'casilla' && $etiqueta !== '') ? $etiqueta : null;

        return [$nombre, $tipo, $etiqueta, $orden, $activo];
    }

    public function listar(): void
    {
        response(200, true, 'Columnas obtenidas.', ['items' => $this->cols->listar()]);
    }

    public function crear(): void
    {
        [$nombre, $tipo, $etiqueta, $orden, $activo] = $this->datos();
        if (!$this->cols->crear($nombre, $tipo, $etiqueta, $orden, $activo)) response(500, false, 'No se pudo crear la columna.');
        response(201, true, 'Columna creada.');
    }

    public function editar(): void
    {
        $id = (int) ($_POST['id_columna'] ?? 0);
        if ($id <= 0) response(400, false, 'Registro no válido.');
        [$nombre, $tipo, $etiqueta, $orden, $activo] = $this->datos();
        if (!$this->cols->editar($id, $nombre, $tipo, $etiqueta, $orden, $activo)) response(500, false, 'No se pudo actualizar la columna.');
        response(200, true, 'Columna actualizada.');
    }

    public function eliminar(): void
    {
        $id = (int) ($_POST['id_columna'] ?? 0);
        if ($id <= 0) response(400, false, 'Registro no válido.');
        if ($this->cols->enUso($id)) response(409, false, 'No se puede eliminar: ya hay reportes capturados con esta columna. Desactívala para ocultarla.');
        $this->cols->eliminar($id);
        response(200, true, 'Columna eliminada.');
    }
}
