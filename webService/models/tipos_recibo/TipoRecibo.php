<?php
/** Model TipoRecibo. Lo carga TipoRecibo::fromTable() desde sp_tiporecibo_listar y TipoRecibo::fromForm() desde su formulario. */
class TipoRecibo extends Model
{
    public const NATURES = ['ingreso', 'gasto'];

    public ?int   $IdTipo     = null;
    public string $Nombre     = '';
    public string $Naturaleza = 'ingreso';   // ingreso · gasto
}
