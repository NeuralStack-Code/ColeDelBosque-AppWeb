<?php
/** Model de una persona (alumno o docente) como opción de una lista. Lo carga PersonaOpcion::fromTable() desde sp_recibo_personas. */
class PersonaOpcion extends Model
{
    public ?int   $IdCuenta = null;
    public string $Nombre   = '';
}
