<?php
/** Entidad Tablas: visor de solo lectura de la base (solo el desarrollador). Procedures: webService/sql/sp/sp_dashboard.sql. */
class TablasBusiness extends Entity
{
    public string $Tabla  = '';
    public int    $Limite = 200;

    /** Cada tabla con su número de filas. */
    public function list(): array
    {
        $this->db->command('sp_tablas_listar');
        return $this->db->execute();
    }

    /** Los nombres de las columnas de la tabla, en su orden; sin filas = la tabla no existe. */
    public function columns(): array
    {
        $this->db->command('sp_tablas_columnas');
        $this->db->addParameter('Tabla', $this->Tabla);
        return $this->db->execute();
    }

    /** Las primeras filas de la tabla, tal como están en la base. 'notfound' si la tabla no existe. */
    public function rows(): array
    {
        $this->db->command('sp_tablas_ver');
        $this->db->addParameter('Tabla',  $this->Tabla);
        $this->db->addParameter('Limite', $this->Limite);
        return $this->db->execute();
    }
}
