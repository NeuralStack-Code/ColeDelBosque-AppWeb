<?php
/** Model de los números del panel del administrador. Lo carga DashboardResumen::fromRow() desde sp_dashboard_resumen. */
class DashboardResumen extends Model
{
    public int   $Alumnos         = 0;
    public int   $Maestros        = 0;
    public int   $Grupos          = 0;
    public float $Saldo           = 0.0;   // ingresos menos gastos de los recibos
    public int   $PagosPendientes = 0;
    public int   $PagosPagados    = 0;
}
