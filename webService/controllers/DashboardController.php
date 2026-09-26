<?php

/**
 * Recurso: dashboard.  Ruta: /api/dashboard?action=resumen  (solo admin).
 */
class DashboardController
{
    private DashboardBusiness $dash;

    public function __construct(mysqli $conexion)
    {
        requireAdmin();
        $this->dash = new DashboardBusiness($conexion);
    }

    public function resumen(): void
    {
        response(200, true, 'Métricas obtenidas.', $this->dash->resumen());
    }
}
