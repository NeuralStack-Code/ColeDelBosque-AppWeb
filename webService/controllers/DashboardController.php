<?php
/**
 * Panel del administrador: su pantalla de inicio.
 *   /dashboard/index    → pantalla del panel
 *   /dashboard/resumen  → sus números (totales, saldo, pagos y alumnos por grupo)
 * La URL anterior /administrador redirige a /dashboard/index (tabla Rutas).
 */
class DashboardController extends Controller
{
    public const MODULE = 'escolar';

    public function index(): void
    {
        $this->view('admin/home', ['nombre' => $this->user() ?? 'Administrador']);
    }

    public function resumen(): void
    {
        $this->api->command('Escolar', 'Dashboard', 'Summary');
        $result = $this->api->execute();
        if ($result->status()) $this->failure($result);
        $resumen = DashboardResumen::fromRow($result->row() ?? []);

        $this->api->command('Escolar', 'Dashboard', 'StudentsByGroup');
        $result = $this->api->execute();

        if (!$result->status()) {
            $this->success('Métricas obtenidas.', ['resumen' => $resumen, 'alumnosPorGrupo' => GrupoConteo::fromTable($result->table())]);
        } else {
            $this->failure($result);
        }
    }
}
