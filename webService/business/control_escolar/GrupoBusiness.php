<?php
/**
 * Entidad Grupo: los grupos de cada ciclo y sus materias (tablas grupo + grupo_materia).
 * Procedures: webService/sql/sp/sp_grupo.sql.
 */
class GrupoBusiness extends Entity
{
    public ?int    $IdGrupo           = null;
    public ?int    $IdCiclo           = null;
    public ?string $Grado             = null;   // en un cambio: null = no cambia
    public ?int    $IdMaestra         = null;
    public ?int    $Nivel             = null;
    public bool    $CambiarNivelIngles = false;
    public ?string $NivelIngles       = null;
    public bool    $CambiarMaterias   = false;
    public string  $Materias          = '';     // ids marcados: '3,5,8'
    public string  $MateriasNuevas    = '[]';   // nombres nuevos: arreglo JSON

    /** Los grupos de un ciclo (IdCiclo vacío = todos). */
    public function list(): array
    {
        $this->db->command('sp_grupo_listar');
        $this->db->addParameter('IdCiclo', $this->IdCiclo);
        return $this->db->execute();
    }

    /** Un grupo (tabla vacía si no existe). */
    public function get(): array
    {
        $this->db->command('sp_grupo_obtener');
        $this->db->addParameter('IdGrupo', $this->IdGrupo);
        return $this->db->execute();
    }

    public function subjects(): array
    {
        $this->db->command('sp_grupo_materias');
        $this->db->addParameter('IdGrupo', $this->IdGrupo);
        return $this->db->execute();
    }

    public function teachers(): array
    {
        $this->db->command('sp_grupo_maestros');
        return $this->db->execute();
    }

    /** Grupo + materias, todo o nada. Regresa el Id; 'dup' (grado repetido en el ciclo) · 'invalido' (sin materias). */
    public function insert(): array
    {
        $this->db->command('sp_grupo_crear');
        $this->db->addParameter('Grado',          $this->Grado);
        $this->db->addParameter('IdCiclo',        $this->IdCiclo);
        $this->db->addParameter('Nivel',          $this->Nivel);
        $this->db->addParameter('NivelIngles',    $this->NivelIngles);
        $this->db->addParameter('Materias',       $this->Materias);
        $this->db->addParameter('MateriasNuevas', $this->MateriasNuevas);
        return $this->db->execute();
    }

    /** Cambia lo que traiga, todo o nada. 'notfound' · 'invalido' (sin materias). */
    public function update(): array
    {
        $this->db->command('sp_grupo_editar');
        $this->db->addParameter('IdGrupo',            $this->IdGrupo);
        $this->db->addParameter('Grado',              $this->Grado);
        $this->db->addParameter('IdMaestra',          $this->IdMaestra);
        $this->db->addParameter('Nivel',              $this->Nivel);
        $this->db->addParameter('IdCiclo',            $this->IdCiclo);
        $this->db->addParameter('CambiarNivelIngles', $this->CambiarNivelIngles);
        $this->db->addParameter('NivelIngles',        $this->NivelIngles);
        $this->db->addParameter('CambiarMaterias',    $this->CambiarMaterias);
        $this->db->addParameter('Materias',           $this->Materias);
        $this->db->addParameter('MateriasNuevas',     $this->MateriasNuevas);
        return $this->db->execute();
    }

    /** Regresa Afectadas (0 = no existía) y Alumnos (los que quedaron sin grupo). */
    public function delete(): array
    {
        $this->db->command('sp_grupo_eliminar');
        $this->db->addParameter('IdGrupo', $this->IdGrupo);
        return $this->db->execute();
    }
}
