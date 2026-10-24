<?php
/**
 * Entidad Calificacion: las notas por alumno, materia y ciclo (tabla calificacion), y en qué grupos y materias
 * captura cada maestra. Procedures: webService/sql/sp/sp_calificacion.sql.
 */
class CalificacionBusiness extends Entity
{
    public ?int    $IdUsuario  = null;
    public ?int    $IdCiclo    = null;
    public ?int    $IdGrupo    = null;
    public bool    $SoloIngles = false;
    public ?int    $IdMateria  = null;
    public ?int    $IdCuenta   = null;
    public ?float  $P1         = null;
    public ?float  $P2         = null;
    public ?float  $P3         = null;
    public ?float  $CalifFinal = null;
    public ?string $Reporte    = null;

    /** Los grupos donde captura la maestra en el ciclo. */
    public function teacherGroups(): array
    {
        $this->db->command('sp_calificacion_grupos');
        $this->db->addParameter('IdUsuario', $this->IdUsuario);
        $this->db->addParameter('IdCiclo',   $this->IdCiclo);
        return $this->db->execute();
    }

    /** Las materias que se capturan en el grupo (todas, o solo inglés). */
    public function subjects(): array
    {
        $this->db->command('sp_calificacion_materias');
        $this->db->addParameter('IdGrupo',    $this->IdGrupo);
        $this->db->addParameter('SoloIngles', $this->SoloIngles);
        return $this->db->execute();
    }

    /** Los alumnos del grupo con sus notas de la materia. 'invalido' si la materia no es del grupo. */
    public function list(): array
    {
        $this->db->command('sp_calificacion_listar');
        $this->db->addParameter('IdMateria',  $this->IdMateria);
        $this->db->addParameter('IdCiclo',    $this->IdCiclo);
        $this->db->addParameter('IdGrupo',    $this->IdGrupo);
        $this->db->addParameter('SoloIngles', $this->SoloIngles);
        return $this->db->execute();
    }

    /** Alta o cambio de las notas. 'invalido' si la materia o el alumno no son del grupo. */
    public function save(): array
    {
        $this->db->command('sp_calificacion_guardar');
        $this->db->addParameter('IdCuenta',   $this->IdCuenta);
        $this->db->addParameter('IdMateria',  $this->IdMateria);
        $this->db->addParameter('IdCiclo',    $this->IdCiclo);
        $this->db->addParameter('IdGrupo',    $this->IdGrupo);
        $this->db->addParameter('SoloIngles', $this->SoloIngles);
        $this->db->addParameter('P1',         $this->P1);
        $this->db->addParameter('P2',         $this->P2);
        $this->db->addParameter('P3',         $this->P3);
        $this->db->addParameter('CalifFinal', $this->CalifFinal);
        $this->db->addParameter('Reporte',    $this->Reporte);
        return $this->db->execute();
    }
}
