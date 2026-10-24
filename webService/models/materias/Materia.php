<?php
/**
 * Model Materia, con los grupos donde se imparte (una materia puede estar en varios).
 * Lo carga Materia::fromTable() desde sp_materia_listar y Materia::fromForm() desde su formulario.
 */
class Materia extends Model
{
    public ?int    $IdMateria = null;
    public string  $Nombre    = '';
    public ?string $GruposTxt = null;   // "Primero, Segundo": los grados, para mostrar
    public array   $GrupoIds  = [];     // ids de sus grupos
    public int     $NumGrupos = 0;

    /** El procedure trae los ids de los grupos como texto ("69,70"): aquí quedan como lista de enteros. */
    protected function onLoad(array $row): void
    {
        $csv = (string) ($row['GruposCsv'] ?? '');
        $this->GrupoIds = $csv === '' ? [] : array_map('intval', explode(',', $csv));
    }
}
