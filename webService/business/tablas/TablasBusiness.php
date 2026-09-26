<?php
require_once dirname(__DIR__, 3) . '/apiService/core/crypto.php';

/**
 * Visor de solo lectura de las tablas de la BD. El nombre de tabla se valida
 * contra la lista real (whitelist) en el controller para evitar inyección.
 */
class TablasBusiness
{
    private mysqli $db;

    public function __construct(mysqli $db)
    {
        $this->db = $db;
    }

    /** Lista real de tablas de la BD (whitelist). @return string[] */
    public function tablas(): array
    {
        $t = [];
        $res = mysqli_query($this->db, 'SHOW TABLES');
        while ($r = mysqli_fetch_row($res)) $t[] = $r[0];
        return $t;
    }

    /** Cada tabla con su conteo de filas. @return array[] {tabla, filas} */
    public function listarConConteo(): array
    {
        $items = [];
        foreach ($this->tablas() as $t) {
            $c = mysqli_fetch_row(mysqli_query($this->db, 'SELECT COUNT(*) FROM `' . $t . '`'))[0] ?? 0;
            $items[] = ['tabla' => $t, 'filas' => (int) $c];
        }
        return $items;
    }

    /** Lee una tabla (nombre YA validado contra la whitelist). Desencripta matrícula. */
    public function ver(string $tabla, int $limite): array
    {
        $res = mysqli_query($this->db, 'SELECT * FROM `' . $tabla . '` LIMIT ' . $limite);
        $columnas = [];
        foreach (mysqli_fetch_fields($res) as $f) $columnas[] = $f->name;
        $desencriptar = in_array('matricula', $columnas, true);

        $filas = [];
        while ($r = mysqli_fetch_assoc($res)) {
            if ($desencriptar && !empty($r['matricula'])) {
                $dec = decrypt($r['matricula']);
                if ($dec !== '' && $dec !== null) $r['matricula'] = $dec;
            }
            $filas[] = $r;
        }
        return ['columnas' => $columnas, 'filas' => $filas];
    }
}
