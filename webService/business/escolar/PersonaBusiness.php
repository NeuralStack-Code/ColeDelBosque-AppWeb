<?php
/**
 * Entidad Persona: las cuentas de alumnos y maestros (tablas usuario + cuenta). La matrícula y los correos llegan
 * ya encriptados. Procedures: webService/sql/sp/sp_persona.sql.
 */
class PersonaBusiness extends Entity
{
    public ?int    $IdCuenta      = null;
    public ?int    $IdPermiso     = null;   // 3 alumno · 2 maestro
    public string  $Nombre        = '';
    public string  $Paterno       = '';
    public ?string $Materno       = null;
    public ?int    $IdGrupo       = null;
    public bool    $SinGrupo      = false;
    public string  $Matricula     = '';
    public ?string $NivelesIngles = null;
    public ?string $CorreoTutor   = null;
    public ?string $CorreoTutor2  = null;

    /** Las cuentas de un rol. */
    public function list(): array
    {
        $this->db->command('sp_persona_listar');
        $this->db->addParameter('IdPermiso', $this->IdPermiso);
        return $this->db->execute();
    }

    /** Usuario + cuenta, todo o nada. Regresa el Id de la cuenta; 'dup' si la matrícula ya existe. */
    public function insert(): array
    {
        $this->db->command('sp_persona_crear');
        $this->db->addParameter('Nombre',        $this->Nombre);
        $this->db->addParameter('Paterno',       $this->Paterno);
        $this->db->addParameter('Materno',       $this->Materno);
        $this->db->addParameter('IdGrupo',       $this->IdGrupo);
        $this->db->addParameter('Matricula',     $this->Matricula);
        $this->db->addParameter('IdPermiso',     $this->IdPermiso);
        $this->db->addParameter('NivelesIngles', $this->NivelesIngles);
        $this->db->addParameter('CorreoTutor',   $this->CorreoTutor);
        $this->db->addParameter('CorreoTutor2',  $this->CorreoTutor2);
        return $this->db->execute();
    }

    /** 'notfound' · 'dup' (matrícula de otra cuenta). */
    public function update(): array
    {
        $this->db->command('sp_persona_editar');
        $this->db->addParameter('IdCuenta',      $this->IdCuenta);
        $this->db->addParameter('Nombre',        $this->Nombre);
        $this->db->addParameter('Paterno',       $this->Paterno);
        $this->db->addParameter('Materno',       $this->Materno);
        $this->db->addParameter('IdGrupo',       $this->IdGrupo);
        $this->db->addParameter('SinGrupo',      $this->SinGrupo);
        $this->db->addParameter('Matricula',     $this->Matricula);
        $this->db->addParameter('IdPermiso',     $this->IdPermiso);
        $this->db->addParameter('NivelesIngles', $this->NivelesIngles);
        $this->db->addParameter('CorreoTutor',   $this->CorreoTutor);
        $this->db->addParameter('CorreoTutor2',  $this->CorreoTutor2);
        return $this->db->execute();
    }

    /** La cuenta con todo lo suyo, todo o nada. 'notfound' si no existe. */
    public function delete(): array
    {
        $this->db->command('sp_persona_eliminar');
        $this->db->addParameter('IdCuenta', $this->IdCuenta);
        return $this->db->execute();
    }

    /** Nombre, matrícula y correos de un alumno (tabla vacía si no es un alumno). */
    public function mailData(): array
    {
        $this->db->command('sp_persona_correo');
        $this->db->addParameter('IdCuenta', $this->IdCuenta);
        return $this->db->execute();
    }
}
