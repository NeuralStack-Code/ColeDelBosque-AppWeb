<?php
/** Entidad Recibo: los ingresos y egresos de la escuela (tabla recibo). Procedures: webService/sql/sp/sp_recibo.sql. */
class ReciboBusiness extends Entity
{
    public ?int    $IdRecibo         = null;
    public ?int    $IdTipoRecibo     = null;
    public string  $Naturaleza       = 'ingreso';
    public string  $DestinatarioTipo = 'escuela';
    public ?int    $IdCuenta         = null;
    public float   $Monto            = 0.0;
    public ?string $TipoPago         = null;
    public ?string $Comentario       = null;
    public string  $Fecha            = '';
    public ?int    $IdPermiso        = null;   // de quiénes se quiere la lista: 3 alumnos · 2 docentes

    /** Las cuentas de un rol para elegir destinatario. */
    public function people(): array
    {
        $this->db->command('sp_recibo_personas');
        $this->db->addParameter('IdPermiso', $this->IdPermiso);
        return $this->db->execute();
    }

    public function list(): array
    {
        $this->db->command('sp_recibo_listar');
        return $this->db->execute();
    }

    /** Un recibo (tabla vacía si no existe). */
    public function get(): array
    {
        $this->db->command('sp_recibo_obtener');
        $this->db->addParameter('IdRecibo', $this->IdRecibo);
        return $this->db->execute();
    }

    /** Regresa el Id nuevo. 'invalido' si el destinatario no es de ese rol. */
    public function insert(): array
    {
        $this->db->command('sp_recibo_crear');
        $this->fields();
        return $this->db->execute();
    }

    /** 'notfound' · 'invalido'. */
    public function update(): array
    {
        $this->db->command('sp_recibo_editar');
        $this->db->addParameter('IdRecibo', $this->IdRecibo);
        $this->fields();
        return $this->db->execute();
    }

    /** Regresa las filas afectadas (0 = no existía). */
    public function delete(): array
    {
        $this->db->command('sp_recibo_eliminar');
        $this->db->addParameter('IdRecibo', $this->IdRecibo);
        return $this->db->execute();
    }

    private function fields(): void
    {
        $this->db->addParameter('IdTipoRecibo',     $this->IdTipoRecibo);
        $this->db->addParameter('Naturaleza',       $this->Naturaleza);
        $this->db->addParameter('DestinatarioTipo', $this->DestinatarioTipo);
        $this->db->addParameter('IdCuenta',         $this->IdCuenta);
        $this->db->addParameter('Monto',            $this->Monto);
        $this->db->addParameter('TipoPago',         $this->TipoPago);
        $this->db->addParameter('Comentario',       $this->Comentario);
        $this->db->addParameter('Fecha',            $this->Fecha);
    }
}
