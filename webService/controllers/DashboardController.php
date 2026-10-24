<?php
/**
 * Panel del administrador: su pantalla de inicio.
 *   /dashboard/index    → pantalla del panel
 *   /dashboard/resumen  → sus números (totales, saldo, pagos y alumnos por grupo)
 * La URL anterior /administrador redirige a /dashboard/index (index.php raíz).
 */
class DashboardController extends Controller
{
    protected int $role = self::ADMIN;
    protected array $readActions = ['index', 'resumen'];

    public function index(): void
    {
        $this->view('admin/home', ['nombre' => $this->user() ?? 'Administrador']);
    }

    public function resumen(): void
    {
        $this->api->command('Dashboard', 'Dashboard', 'Summary');
        $result = $this->api->execute();
        if ($result->status()) $this->failure($result);
        $resumen = DashboardResumen::fromRow($result->row() ?? []);

        $this->api->command('Dashboard', 'Dashboard', 'StudentsByGroup');
        $result = $this->api->execute();

        if (!$result->status()) {
            $this->success('Métricas obtenidas.', ['resumen' => $resumen, 'alumnosPorGrupo' => GrupoConteo::fromTable($result->table())]);
        } else {
            $this->failure($result);
        }
    }
}
