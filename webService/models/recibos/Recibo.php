<?php
/**
 * Model Recibo: un ingreso o un egreso de la escuela. Lo carga Recibo::fromTable() desde sp_recibo_listar,
 * fromRow() desde sp_recibo_obtener y fromForm() desde su formulario.
 */
class Recibo extends Model
{
    public const NATURES    = ['ingreso', 'gasto'];
    public const RECIPIENTS = ['alumno', 'docente', 'escuela'];

    public ?int    $IdRecibo         = null;
    public ?int    $IdTipoRecibo     = null;
    public string  $Naturaleza       = 'ingreso';   // ingreso · gasto
    public string  $DestinatarioTipo = 'escuela';   // alumno · docente · escuela
    public ?int    $IdCuenta         = null;        // la del alumno o docente (nada si es la escuela)
    public float   $Monto            = 0.0;
    public ?string $TipoPago         = null;        // Efectivo, Transferencia…
    public ?string $Comentario       = null;
    public string  $Fecha            = '';          // AAAA-MM-DD HH:MM:SS (del formulario llega AAAA-MM-DDTHH:MM)
    public ?string $TipoNombre       = null;
    public ?string $Destinatario     = null;        // "Escuela" o el nombre de la persona
    public ?int    $IdUsuarioCuenta  = null;        // solo sp_recibo_obtener: para saber si un alumno puede verlo
    public ?int    $IdPermisoCuenta  = null;

    public function esIngreso(): bool
    {
        return $this->Naturaleza === 'ingreso';
    }

    /** Folio impreso: el id con ceros a la izquierda (000123). */
    public function folio(): string
    {
        return str_pad((string) $this->IdRecibo, 6, '0', STR_PAD_LEFT);
    }

    /** Cómo se rotula al destinatario en el recibo impreso. */
    public function etiquetaDestinatario(): string
    {
        return match ($this->DestinatarioTipo) {
            'alumno'  => 'Alumno',
            'docente' => 'Docente',
            default   => $this->esIngreso() ? 'Recibí de' : 'Pagado a',
        };
    }

    /** El concepto impreso: el comentario o, si no hay, el tipo de recibo. */
    public function concepto(): string
    {
        return $this->Comentario ?: ($this->TipoNombre ?? '—');
    }
}
