<?php

/**
 * Recurso: padre.  Ruta: /api/padre?action=resumen|guardar_correos
 * Portal del padre/alumno (permiso 3): solo lectura, salvo sus correos de tutor.
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

    /** Correo de tutor opcional: '' o un correo válido. */
    private function correoTutor(string $campo): string
    {
        $c = trim($_POST[$campo] ?? '');
        if ($c === '') return '';
        if (strlen($c) > 100 || !filter_var($c, FILTER_VALIDATE_EMAIL)) response(400, false, 'Revisa el correo: no parece válido.');
        return strtolower($c);
    }

    /** El papá registra/corrige a qué correos llega el reporte semanal. */
    public function guardar_correos(): void
    {
        $correo1 = $this->correoTutor('correo_tutor');
        $correo2 = $this->correoTutor('correo_tutor2');
        if (!$this->padre->guardarCorreosTutor((int) ($_SESSION['id'] ?? 0), $correo1, $correo2)) {
            response(500, false, 'No se pudieron guardar los correos.');
        }
        response(200, true, 'Correos guardados.');
    }
}
