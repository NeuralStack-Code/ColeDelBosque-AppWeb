<?php
/**
 * Model de un pago del alumno (colegiatura mensual o inscripción), con lo que lleva abonado.
 * Lo carga Colegiatura::fromTable() desde sp_colegiatura_listar y fromRow() desde sp_colegiatura_obtener.
 * RecargoCalc, Total y Saldo no vienen de la base: los calcula Recargo::completar().
 */
class Colegiatura extends Model
{
    public ?int    $IdPago            = null;
    public ?int    $IdCuenta          = null;
    public ?int    $IdCiclo           = null;
    public ?int    $IdGrupo           = null;
    public string  $Alumno            = '';
    public ?string $Grado             = null;
    public string  $Tipo              = 'colegiatura';   // colegiatura · inscripcion
    public ?string $Mes               = null;
    public ?string $FechaVencimiento  = null;           // AAAA-MM-DD
    public ?string $FechaPago         = null;
    public float   $Monto             = 0.0;
    public float   $Descuento         = 0.0;
    public ?int    $IdTipoDescuento   = null;
    public ?string $ConceptoDescuento = null;
    public float   $RecargoMonto      = 0.0;   // pesos por cada mes vencido
    public float   $Recargo           = 0.0;   // el que quedó congelado al saldarse
    public ?int    $IdRecibo          = null;   // el último recibo que se le generó
    public string  $Estatus           = 'pendiente';   // pendiente · parcial · pagado
    public ?string $EstatusNombre     = null;
    public ?string $EstatusColor      = null;
    public float   $Abonado           = 0.0;   // suma de sus recibos

    public float   $RecargoCalc       = 0.0;
    public float   $Total             = 0.0;
    public float   $Saldo             = 0.0;

    public function esInscripcion(): bool
    {
        return $this->Tipo === 'inscripcion';
    }
}
