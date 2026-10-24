<?php
/**
 * Lo que necesitan las pantallas de la maestra antes de cualquier cosa: el ciclo activo, los grupos donde captura
 * (el suyo y los de sus niveles de inglés) y en cuál está trabajando. Lo usan CalificacionController y ReporteController.
 */
trait ContextoMaestro
{
    private Ciclo $ciclo;
    /** @var GrupoMaestro[] */
    private array $grupos = [];
    /** El grupo en que se trabaja: el que pidió la pantalla si es suyo; si no, el primero. */
    private GrupoMaestro $grupo;

    /** Carga el contexto. Corta con 409 si no hay ciclo activo o la maestra no tiene grupo. */
    private function cargarContexto(int $IdGrupo): void
    {
        $this->api->command('Ciclos', 'Ciclo', 'Active');
        $result = $this->api->execute();
        if ($result->status()) $this->failure($result);
        $this->ensure($result->row() !== null, 'No hay un ciclo escolar activo. Pide al administrador que active uno.', 409);
        $this->ciclo = Ciclo::fromRow($result->row());

        $this->api->command('Calificaciones', 'Calificacion', 'TeacherGroups');
        $this->api->addParameter('IdUsuario', 'I', $this->userId());
        $this->api->addParameter('IdCiclo',   'I', $this->ciclo->IdCiclo);
        $result = $this->api->execute();
        if ($result->status()) $this->failure($result);
        $this->grupos = GrupoMaestro::fromTable($result->table());
        $this->ensure($this->grupos !== [], 'No tienes un grupo asignado. Contacta a la administración.', 409);

        $this->grupo = $this->grupos[0];
        foreach ($this->grupos as $grupo) {
            if ($grupo->IdGrupo === $IdGrupo) $this->grupo = $grupo;
        }
    }

    /** Las materias que la maestra captura en el grupo de trabajo. */
    private function materias(): array
    {
        $this->api->command('Calificaciones', 'Calificacion', 'Subjects');
        $this->api->addParameter('IdGrupo',    'I', $this->grupo->IdGrupo);
        $this->api->addParameter('SoloIngles', 'B', $this->grupo->SoloIngles);
        $result = $this->api->execute();
        if ($result->status()) $this->failure($result);
        return MateriaOpcion::fromTable($result->table());
    }
}
