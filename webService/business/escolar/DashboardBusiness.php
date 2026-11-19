<?php
/** Entidad Dashboard: los números del panel del administrador. Procedures: webService/sql/sp/sp_dashboard.sql. */
class DashboardBusiness extends Entity
{
    /** Una fila con los totales (alumnos, maestros, grupos, saldo y pagos). */
    public function summary(): array
    {
        $this->db->command('sp_dashboard_resumen');
        return $this->db->execute();
    }

    /** Cuántos alumnos tiene cada grupo. */
    public function studentsByGroup(): array
    {
        $this->db->command('sp_dashboard_alumnos_por_grupo');
        return $this->db->execute();
    }
}
