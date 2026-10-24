<?php
/**
 * Entidad EsquemaPago: lo que se cobra en un ciclo escolar (tabla esquema_pago) y a quiénes se aplica.
 * Generar los pagos lo hace la entidad Colegiatura (Generate). Procedures: webService/sql/sp/sp_esquema_pago.sql.
 */
class EsquemaPagoBusiness extends Entity
{
    public ?int    $IdEsquema        = null;
    public ?int    $IdCiclo          = null;
    public string  $Nombre           = '';
    public float   $MontoInscripcion = 0.0;
    public float   $MontoColegiatura = 0.0;
    public string  $PrimerMes        = '';   // AAAA-MM-01
    public int     $NumMeses         = 0;
    public int     $DiaVencimiento   = 0;
    public float   $RecargoMonto     = 0.0;
    public string  $AplicarA         = 'todos';   // todos · grupo · alumno
    public ?int    $IdGrupo          = null;
    public ?int    $IdCuenta         = null;

    public function list(): array
    {
        $this->db->command('sp_esquema_listar');
        return $this->db->execute();
    }

    /** Un esquema (tabla vacía si no existe). */
    public function get(): array
    {
        $this->db->command('sp_esquema_obtener');
        $this->db->addParameter('IdEsquema', $this->IdEsquema);
        return $this->db->execute();
    }

    /** Regresa el Id nuevo. */
    public function insert(): array
    {
        $this->db->command('sp_esquema_crear');
        $this->fields();
        return $this->db->execute();
    }

    /** 'notfound' si no existe. */
    public function update(): array
    {
        $this->db->command('sp_esquema_editar');
        $this->db->addParameter('IdEsquema', $this->IdEsquema);
        $this->fields();
        return $this->db->execute();
    }

    /** Regresa las filas afectadas (0 = no existía). */
    public function delete(): array
    {
        $this->db->command('sp_esquema_eliminar');
        $this->db->addParameter('IdEsquema', $this->IdEsquema);
        return $this->db->execute();
    }

    public function groups(): array
    {
        $this->db->command('sp_esquema_grupos');
        return $this->db->execute();
    }

    public function students(): array
    {
        $this->db->command('sp_esquema_alumnos');
        return $this->db->execute();
    }

    /** Los IdCuenta a quienes se aplica. 'invalido' (grupo de otro ciclo) · 'notfound' (alumno fuera del ciclo). */
    public function recipients(): array
    {
        $this->db->command('sp_esquema_destinatarios');
        $this->db->addParameter('IdCiclo',  $this->IdCiclo);
        $this->db->addParameter('AplicarA', $this->AplicarA);
        $this->db->addParameter('IdGrupo',  $this->IdGrupo);
        $this->db->addParameter('IdCuenta', $this->IdCuenta);
        return $this->db->execute();
    }

    /** Los campos del esquema, iguales en el alta y en el cambio. */
    private function fields(): void
    {
        $this->db->addParameter('IdCiclo',          $this->IdCiclo);
        $this->db->addParameter('Nombre',           $this->Nombre);
        $this->db->addParameter('MontoInscripcion', $this->MontoInscripcion);
        $this->db->addParameter('MontoColegiatura', $this->MontoColegiatura);
        $this->db->addParameter('PrimerMes',        $this->PrimerMes);
        $this->db->addParameter('NumMeses',         $this->NumMeses);
        $this->db->addParameter('DiaVencimiento',   $this->DiaVencimiento);
        $this->db->addParameter('RecargoMonto',     $this->RecargoMonto);
    }
}
