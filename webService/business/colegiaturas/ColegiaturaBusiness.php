<?php
/**
 * Entidad Colegiatura: los pagos de cada alumno (inscripción y colegiaturas, tabla colegiatura) y sus abonos.
 * Procedures: webService/sql/sp/sp_colegiatura.sql.
 */
class ColegiaturaBusiness extends Entity
{
    public ?int    $IdPago            = null;
    public ?int    $IdCuenta          = null;
    public ?int    $IdCiclo           = null;
    public ?int    $IdDescuento       = null;
    public ?int    $IdTipoDescuento   = null;   // null = quitar el descuento
    public float   $Descuento         = 0.0;
    public ?string $ConceptoDescuento = null;
    public float   $RecargoMonto      = 0.0;
    public float   $Recargo           = 0.0;    // el que se congela al saldar
    public float   $Monto             = 0.0;
    public string  $TipoPago          = '';
    public string  $Comentario        = '';
    public string  $Fecha             = '';     // AAAA-MM-DD HH:MM:SS
    public bool    $Completa          = false;
    public string  $Tipo              = '';
    public string  $Mes               = '';
    public int     $Anio              = 0;
    public string  $FechaVencimiento  = '';

    /** Los grupos de un ciclo (IdCiclo vacío = todos). */
    public function groups(): array
    {
        $this->db->command('sp_colegiatura_grupos');
        $this->db->addParameter('IdCiclo', $this->IdCiclo);
        return $this->db->execute();
    }

    public function discounts(): array
    {
        $this->db->command('sp_colegiatura_descuentos');
        return $this->db->execute();
    }

    /** Un descuento activo (tabla vacía si no existe o está inactivo). */
    public function discount(): array
    {
        $this->db->command('sp_colegiatura_descuento_obtener');
        $this->db->addParameter('IdDescuento', $this->IdDescuento);
        return $this->db->execute();
    }

    public function statuses(): array
    {
        $this->db->command('sp_colegiatura_estatus');
        return $this->db->execute();
    }

    /** Los pagos de todos (IdCuenta vacío) o de un alumno, con lo abonado. */
    public function list(): array
    {
        $this->db->command('sp_colegiatura_listar');
        $this->db->addParameter('IdCuenta', $this->IdCuenta);
        return $this->db->execute();
    }

    /** Un pago con lo abonado (tabla vacía si no existe). */
    public function get(): array
    {
        $this->db->command('sp_colegiatura_obtener');
        $this->db->addParameter('IdPago', $this->IdPago);
        return $this->db->execute();
    }

    /** Recibo del abono + estado del pago, todo o nada. Regresa el Id del recibo. 'notfound' · 'invalido'. */
    public function pay(): array
    {
        $this->db->command('sp_colegiatura_pagar');
        $this->db->addParameter('IdPago',     $this->IdPago);
        $this->db->addParameter('Monto',      $this->Monto);
        $this->db->addParameter('TipoPago',   $this->TipoPago);
        $this->db->addParameter('Comentario', $this->Comentario);
        $this->db->addParameter('Fecha',      $this->Fecha);
        $this->db->addParameter('Completa',   $this->Completa);
        $this->db->addParameter('Recargo',    $this->Recargo);
        return $this->db->execute();
    }

    /** Aplica (o quita, con IdTipoDescuento vacío) un descuento. 'notfound' · 'invalido' si ya está pagado. */
    public function applyDiscount(): array
    {
        $this->db->command('sp_colegiatura_descuento');
        $this->db->addParameter('IdPago',            $this->IdPago);
        $this->db->addParameter('Descuento',         $this->Descuento);
        $this->db->addParameter('IdTipoDescuento',   $this->IdTipoDescuento);
        $this->db->addParameter('ConceptoDescuento', $this->ConceptoDescuento);
        return $this->db->execute();
    }

    /** 'invalido' si el pago no existe o ya está saldado. */
    public function surcharge(): array
    {
        $this->db->command('sp_colegiatura_recargo');
        $this->db->addParameter('IdPago',       $this->IdPago);
        $this->db->addParameter('RecargoMonto', $this->RecargoMonto);
        return $this->db->execute();
    }

    /** Crea o actualiza un pago de un esquema para un alumno. Regresa Resultado: creado · actualizado · sin_cambios. */
    public function generate(): array
    {
        $this->db->command('sp_colegiatura_generar');
        $this->db->addParameter('IdCuenta',         $this->IdCuenta);
        $this->db->addParameter('IdCiclo',          $this->IdCiclo);
        $this->db->addParameter('Tipo',             $this->Tipo);
        $this->db->addParameter('Mes',              $this->Mes);
        $this->db->addParameter('Anio',             $this->Anio);
        $this->db->addParameter('FechaVencimiento', $this->FechaVencimiento);
        $this->db->addParameter('Monto',            $this->Monto);
        $this->db->addParameter('RecargoMonto',     $this->RecargoMonto);
        return $this->db->execute();
    }

    /** Regresa las filas afectadas (0 = no existía). */
    public function delete(): array
    {
        $this->db->command('sp_colegiatura_eliminar');
        $this->db->addParameter('IdPago', $this->IdPago);
        return $this->db->execute();
    }
}
