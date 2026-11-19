<?php
/**
 * Model Grupo: un grado de un ciclo, con su maestra, su nivel y su nivel de inglés. Lo carga Grupo::fromTable()
 * desde sp_grupo_listar, fromRow() desde sp_grupo_obtener y fromForm() desde su formulario.
 */
class Grupo extends Model
{
    public const ENGLISH_LEVELS = ['basico', 'medio', 'avanzado'];

    public ?int    $IdGrupo     = null;
    public string  $Grado       = '';
    public ?int    $IdMaestra   = null;   // la cuenta de su maestra
    public ?int    $IdCiclo     = null;
    public ?int    $Nivel       = null;
    public ?string $NivelIngles = null;   // basico · medio · avanzado
    public ?string $CicloNombre = null;
    public int     $NumMaterias = 0;
    public int     $NumAlumnos  = 0;
}
