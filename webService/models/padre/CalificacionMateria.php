<?php
/**
 * Model de la calificación de un alumno en una materia (portal del alumno). Lo carga CalificacionMateria::fromTable()
 * desde sp_padre_calificaciones. Promedio: la calificación final si ya la hay; si no, el promedio de los parciales capturados.
 */
class CalificacionMateria extends Model
{
    public string  $Materia    = '';
    public ?float  $P1         = null;
    public ?float  $P2         = null;
    public ?float  $P3         = null;
    public ?float  $CalifFinal = null;
    public ?string $Reporte    = null;
    public ?float  $Promedio   = null;

    protected function onLoad(array $row): void
    {
        $parciales = array_filter([$this->P1, $this->P2, $this->P3], fn($nota) => $nota !== null);
        $this->Promedio = $this->CalifFinal ?? ($parciales ? round(array_sum($parciales) / count($parciales), 1) : null);
    }
}
