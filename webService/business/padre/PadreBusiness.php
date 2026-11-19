<?php
/**
 * Entidad Padre: lo que ve el alumno (o su familia) en su portal, siempre de la cuenta en sesión.
 * Procedures: webService/sql/sp/sp_padre.sql. Sus pagos los trae la entidad Colegiatura.
 */
class PadreBusiness extends Entity
{
    public ?int    $IdUsuario    = null;
    public ?int    $IdCuenta     = null;
    public ?int    $IdGrupo      = null;
    public ?int    $IdCiclo      = null;
    public ?string $CorreoTutor  = null;   // encriptado; null = sin correo
    public ?string $CorreoTutor2 = null;

    /** La cuenta del usuario (tabla vacía si no es un alumno). */
    public function account(): array
    {
        $this->db->command('sp_padre_cuenta');
        $this->db->addParameter('IdUsuario', $this->IdUsuario);
        return $this->db->execute();
    }

    /** Sus calificaciones por materia del grupo en el ciclo. */
    public function grades(): array
    {
        $this->db->command('sp_padre_calificaciones');
        $this->db->addParameter('IdCuenta', $this->IdCuenta);
        $this->db->addParameter('IdGrupo',  $this->IdGrupo);
        $this->db->addParameter('IdCiclo',  $this->IdCiclo);
        return $this->db->execute();
    }

    public function saveEmails(): array
    {
        $this->db->command('sp_padre_guardar_correos');
        $this->db->addParameter('IdUsuario',    $this->IdUsuario);
        $this->db->addParameter('CorreoTutor',  $this->CorreoTutor);
        $this->db->addParameter('CorreoTutor2', $this->CorreoTutor2);
        return $this->db->execute();
    }

    /** El ciclo activo (tabla vacía si no hay ninguno). */
    public function activeCycle(): array
    {
        $this->db->command('sp_ciclo_activo');
        return $this->db->execute();
    }

    /** Las colegiaturas de la cuenta, con lo abonado (las mismas filas que ve el administrador). */
    public function payments(): array
    {
        $this->db->command('sp_colegiatura_listar');
        $this->db->addParameter('IdCuenta', $this->IdCuenta);
        return $this->db->execute();
    }
}
