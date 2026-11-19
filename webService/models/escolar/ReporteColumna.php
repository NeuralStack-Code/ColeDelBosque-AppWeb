<?php
/**
 * Model ReporteColumna: una incidencia del reporte semanal que la maestra marca (casilla) o escribe (texto).
 * Lo carga ReporteColumna::fromTable() desde sp_reporte_columna_listar y fromForm() desde su formulario.
 */
class ReporteColumna extends Model
{
    public ?int    $IdColumna = null;
    public string  $Nombre    = '';
    public string  $Tipo      = 'casilla';   // casilla · texto
    public ?string $Etiqueta  = null;        // lo que lee el papá cuando la casilla está marcada
    public int     $Orden     = 0;
    public bool    $Activo    = true;

    public function esTexto(): bool
    {
        return $this->Tipo === 'texto';
    }
}
