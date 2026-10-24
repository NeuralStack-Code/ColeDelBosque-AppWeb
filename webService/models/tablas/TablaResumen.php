<?php
/** Model de una tabla de la base con su número de filas (visor del desarrollador). Lo carga TablaResumen::fromTable() desde sp_tablas_listar. */
class TablaResumen extends Model
{
    public string $Tabla = '';
    public int    $Filas = 0;
}
