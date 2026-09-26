<?php

/**
 * Recurso: padre.  Ruta: /api/padre?action=resumen
 * Vista de solo lectura del padre/alumno (permiso 3).
 */
class PadreController
{
    private PadreBusiness $padre;

    public function __construct(mysqli $conexion)
    {
        requireAuth();
        if ((int) ($_SESSION['permiso'] ?? 0) !== 3) {
            response(403, false, 'Sección disponible solo para alumnos/padres.');
        }
        $this->padre = new PadreBusiness($conexion);
    }

    public function resumen(): void
    {
        $data = $this->padre->resumen((int) ($_SESSION['id'] ?? 0));
        if ($data === null) response(404, false, 'No se encontró tu cuenta.');
        response(200, true, 'Resumen obtenido.', $data);
    }
}
