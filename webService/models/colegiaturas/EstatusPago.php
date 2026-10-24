<?php
/** Model de un estado de pago del catálogo status (pendiente · parcial · pagado). Lo carga EstatusPago::fromTable() desde sp_colegiatura_estatus. */
class EstatusPago extends Model
{
    public string $Clave  = '';
    public string $Nombre = '';
    public string $Color  = '#888';
}
