<?php
/** Model compartido (libs: lo usan Recibos y Grupos, de módulos distintos): una persona (alumno o docente) como opción de una lista. */
class PersonaOpcion extends Model
{
    public ?int   $IdCuenta = null;
    public string $Nombre   = '';
}
