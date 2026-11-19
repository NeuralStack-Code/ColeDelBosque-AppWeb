<?php
/** Model de cuántos alumnos tiene un grupo (gráfica del panel). Lo carga GrupoConteo::fromTable() desde sp_dashboard_alumnos_por_grupo. */
class GrupoConteo extends Model
{
    public string $Grado = '';
    public int    $Total = 0;
}
