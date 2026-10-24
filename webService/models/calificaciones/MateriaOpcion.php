<?php
/** Model de una materia como opción de una lista. Lo carga MateriaOpcion::fromTable() desde sp_calificacion_materias. */
class MateriaOpcion extends Model
{
    public ?int   $IdMateria = null;
    public string $Nombre    = '';
}
