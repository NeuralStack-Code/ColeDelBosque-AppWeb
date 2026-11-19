<?php
/** Model compartido (libs: lo usan Cuentas, Colegiaturas y Esquemas de pago, de módulos distintos): un grupo como opción de una lista. Lo cargan sp_cuenta_grupos, sp_colegiatura_grupos y sp_esquema_grupos. */
class GrupoOpcion extends Model
{
    public ?int    $IdGrupo = null;
    public string  $Grado   = '';
    public ?string $Ciclo   = null;   // nombre de su ciclo escolar
    public ?int    $IdCiclo = null;
}
