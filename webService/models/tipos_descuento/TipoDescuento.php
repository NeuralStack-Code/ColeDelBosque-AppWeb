<?php
/** Model TipoDescuento, con su estado del catálogo status. Lo carga TipoDescuento::fromTable() desde sp_tipodescuento_listar y fromForm() desde su formulario. */
class TipoDescuento extends Model
{
    public const TARGETS = ['colegiatura', 'inscripcion'];

    public ?int   $IdDescuento = null;
    public string $Nombre      = '';
    public float  $Porcentaje  = 0.0;             // sobre el monto del pago
    public string $AplicaA     = 'colegiatura';   // colegiatura · inscripcion
    public bool   $Activo      = true;
}
