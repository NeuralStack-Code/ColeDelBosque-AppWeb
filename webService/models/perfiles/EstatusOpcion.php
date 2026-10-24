<?php
/** Model de un estatus del catálogo como opción de una lista. Lo carga EstatusOpcion::fromTable() desde sp_perfil_estatus. */
class EstatusOpcion extends Model
{
    public string $Clave  = '';
    public string $Nombre = '';
}
