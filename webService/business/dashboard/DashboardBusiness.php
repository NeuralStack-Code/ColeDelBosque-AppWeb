<?php
/**
 * Métricas del panel de administración. Aquí vive el SQL de los agregados.
 */
class DashboardBusiness
{
    private mysqli $db;

    public function __construct(mysqli $db)
    {
        $this->db = $db;
    }

    /** Primer valor escalar de una consulta. */
    private function escalar(string $sql, $def = 0)
    {
        $res = mysqli_query($this->db, $sql);
        if (!$res) return $def;
        $row = mysqli_fetch_row($res);
        return $row ? $row[0] : $def;
    }

    public function resumen(): array
    {
        $alumnos  = (int)   $this->escalar('SELECT COUNT(*) FROM cuenta WHERE permiso_id = 3');
        $maestros = (int)   $this->escalar('SELECT COUNT(*) FROM cuenta WHERE permiso_id = 2');
        $grupos   = (int)   $this->escalar('SELECT COUNT(*) FROM grupo');
        $saldo    = (float) $this->escalar(
            "SELECT COALESCE(SUM(CASE WHEN naturaleza = 'ingreso' THEN monto ELSE -monto END), 0) FROM recibo");
        $pend     = (int)   $this->escalar("SELECT COUNT(*) FROM colegiatura WHERE status_id <> (SELECT id_status FROM status WHERE ambito='pago' AND clave='pagado')");
        $pagados  = (int)   $this->escalar("SELECT COUNT(*) FROM colegiatura WHERE status_id = (SELECT id_status FROM status WHERE ambito='pago' AND clave='pagado')");

        $porGrupo = [];
        $res = mysqli_query($this->db,
            "SELECT g.grado, COUNT(c.id_cuenta) AS total
             FROM grupo g
             LEFT JOIN cuenta c ON c.grupo_id = g.id_grupo AND c.permiso_id = 3
             GROUP BY g.id_grupo ORDER BY g.grado");
        while ($r = mysqli_fetch_assoc($res)) {
            $porGrupo[] = ['grado' => $r['grado'], 'total' => (int) $r['total']];
        }

        return [
            'alumnos'           => $alumnos,
            'maestros'          => $maestros,
            'grupos'            => $grupos,
            'saldo'             => $saldo,
            'pagos_pendientes'  => $pend,
            'pagos_pagados'     => $pagados,
            'alumnos_por_grupo' => $porGrupo,
        ];
    }
}
