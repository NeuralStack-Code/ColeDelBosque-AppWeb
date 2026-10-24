<?php
/**
 * Entidad Reporte: el reporte semanal a papás (clases, incidencias por alumno y envíos).
 * Procedures: webService/sql/sp/sp_reporte.sql.
 */
class ReporteBusiness extends Entity
{
    public ?int   $IdGrupo       = null;
    public ?int   $IdCuenta      = null;
    public ?int   $IdMateria     = null;
    public ?int   $IdCiclo       = null;
    public ?int   $IdClase       = null;
    public string $Fecha         = '';     // AAAA-MM-DD
    public string $Desde         = '';
    public string $Hasta         = '';
    public string $Semana        = '';     // el lunes de la semana
    public string $Tema          = '';
    public string $Marcas        = '[]';   // JSON: [{IdCuenta, IdColumna, Marcado, Nota}]
    public string $EnviadoEn     = '';
    public ?int   $EnviadoPor    = null;
    public int    $Destinatarios = 0;

    public function students(): array
    {
        $this->db->command('sp_reporte_alumnos');
        $this->db->addParameter('IdGrupo', $this->IdGrupo);
        return $this->db->execute();
    }

    /** Un alumno del grupo con los correos de sus tutores (tabla vacía si no es de ese grupo). */
    public function student(): array
    {
        $this->db->command('sp_reporte_alumno');
        $this->db->addParameter('IdCuenta', $this->IdCuenta);
        $this->db->addParameter('IdGrupo',  $this->IdGrupo);
        return $this->db->execute();
    }

    public function classes(): array
    {
        $this->db->command('sp_reporte_clases');
        $this->db->addParameter('IdGrupo', $this->IdGrupo);
        $this->db->addParameter('Desde',   $this->Desde);
        $this->db->addParameter('Hasta',   $this->Hasta);
        return $this->db->execute();
    }

    /** La clase de un día y materia (tabla vacía si aún no se captura). */
    public function lesson(): array
    {
        $this->db->command('sp_reporte_clase');
        $this->db->addParameter('IdGrupo',   $this->IdGrupo);
        $this->db->addParameter('IdMateria', $this->IdMateria);
        $this->db->addParameter('Fecha',     $this->Fecha);
        return $this->db->execute();
    }

    /** Una clase del grupo por su Id (tabla vacía si no existe o es de otro grupo). */
    public function getLesson(): array
    {
        $this->db->command('sp_reporte_clase_obtener');
        $this->db->addParameter('IdClase', $this->IdClase);
        $this->db->addParameter('IdGrupo', $this->IdGrupo);
        return $this->db->execute();
    }

    public function marks(): array
    {
        $this->db->command('sp_reporte_marcas');
        $this->db->addParameter('IdClase', $this->IdClase);
        return $this->db->execute();
    }

    /** Clase + sus incidencias (las reemplaza), todo o nada. Regresa el Id de la clase. */
    public function saveLesson(): array
    {
        $this->db->command('sp_reporte_guardar_clase');
        $this->db->addParameter('IdGrupo',   $this->IdGrupo);
        $this->db->addParameter('IdMateria', $this->IdMateria);
        $this->db->addParameter('IdCiclo',   $this->IdCiclo);
        $this->db->addParameter('Fecha',     $this->Fecha);
        $this->db->addParameter('Tema',      $this->Tema);
        $this->db->addParameter('Marcas',    $this->Marcas);
        return $this->db->execute();
    }

    /** Regresa las filas afectadas (0 = no existe o es de otro grupo). */
    public function deleteLesson(): array
    {
        $this->db->command('sp_reporte_eliminar_clase');
        $this->db->addParameter('IdClase', $this->IdClase);
        $this->db->addParameter('IdGrupo', $this->IdGrupo);
        return $this->db->execute();
    }

    /** Las clases de la semana vistas por un alumno (una fila por clase y columna con incidencia). */
    public function studentWeek(): array
    {
        $this->db->command('sp_reporte_semana_alumno');
        $this->db->addParameter('IdCuenta', $this->IdCuenta);
        $this->db->addParameter('IdGrupo',  $this->IdGrupo);
        $this->db->addParameter('Desde',    $this->Desde);
        $this->db->addParameter('Hasta',    $this->Hasta);
        return $this->db->execute();
    }

    public function sends(): array
    {
        $this->db->command('sp_reporte_envios');
        $this->db->addParameter('IdGrupo', $this->IdGrupo);
        $this->db->addParameter('Semana',  $this->Semana);
        return $this->db->execute();
    }

    public function registerSend(): array
    {
        $this->db->command('sp_reporte_registrar_envio');
        $this->db->addParameter('IdCuenta',      $this->IdCuenta);
        $this->db->addParameter('Semana',        $this->Semana);
        $this->db->addParameter('EnviadoEn',     $this->EnviadoEn);
        $this->db->addParameter('EnviadoPor',    $this->EnviadoPor);
        $this->db->addParameter('Destinatarios', $this->Destinatarios);
        return $this->db->execute();
    }
}
