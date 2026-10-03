<?php
/** Entidad ReporteColumna (columna configurable del reporte semanal). Solo variables. */
class ReporteColumna
{
    public ?int    $id_columna = null;
    public ?string $nombre     = null;
    public ?string $tipo       = null;   // casilla | texto
    public ?string $etiqueta   = null;   // texto que sale en el correo si la casilla está marcada
    public ?int    $orden      = null;
    public ?int    $activo     = null;

    public function __construct(array $fila = [])
    {
        foreach ($fila as $col => $val) {
            if (property_exists($this, $col)) $this->$col = $val;
        }
    }
}
