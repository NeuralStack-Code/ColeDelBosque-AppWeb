<?php

/**
 * Recurso: tablas.  Ruta: /api/tablas?action=listar|ver
 * Visor de solo lectura de la BD (solo desarrollador, permiso 4).
 */
class TablasController
{
    private TablasBusiness $tablas;

    public function __construct(mysqli $conexion)
    {
        requireDev();
        $this->tablas = new TablasBusiness($conexion);
    }

    public function listar(): void
    {
        response(200, true, 'Tablas obtenidas.', ['items' => $this->tablas->listarConConteo()]);
    }

    public function ver(): void
    {
        $tabla = trim($_GET['tabla'] ?? '');
        if (!in_array($tabla, $this->tablas->tablas(), true)) response(400, false, 'Tabla no válida.');
        $limite = min(500, max(1, (int) ($_GET['limite'] ?? 200)));

        $data = $this->tablas->ver($tabla, $limite);
        response(200, true, 'Datos de la tabla.', [
            'tabla'    => $tabla,
            'columnas' => $data['columnas'],
            'filas'    => $data['filas'],
        ]);
    }
}
