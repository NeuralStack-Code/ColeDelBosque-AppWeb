<?php
require_once dirname(__DIR__, 2) . '/apiService/core/crypto.php';   // decrypt(): las matrículas se guardan encriptadas

/**
 * Visor de SOLO LECTURA de la base (solo el desarrollador).
 *   /tablas/index   → pantalla del visor
 *   /tablas/listar  → las tablas con su número de filas
 *   /tablas/ver     → las columnas y las primeras filas de una tabla (?Tabla=&Limite=)
 * La URL anterior /desarrollador/tablas redirige a /tablas/index (index.php raíz).
 */
class TablasController extends Controller
{
    protected int $role = self::DEVELOPER;
    protected array $readActions = ['index', 'listar', 'ver'];

    public function index(): void
    {
        $this->view('dev/tablas');
    }

    public function listar(): void
    {
        $this->api->command('Tablas', 'Tablas', 'List');
        $result = $this->api->execute();

        if (!$result->status()) {
            $this->success('Tablas obtenidas.', ['items' => TablaResumen::fromTable($result->table())]);
        } else {
            $this->failure($result);
        }
    }

    public function ver(string $Tabla = '', int $Limite = 200): void
    {
        $Limite = min(500, max(1, $Limite));

        // Las columnas se piden aparte: una tabla vacía no trae filas de dónde sacarlas.
        $this->api->command('Tablas', 'Tablas', 'Columns');
        $this->api->addParameter('Tabla', 'S', $Tabla);
        $result = $this->api->execute();
        if ($result->status()) $this->failure($result);
        $columnas = array_column($result->table(), 'Columna');
        $this->ensure($Tabla !== '' && $columnas !== [], 'Tabla no válida.');

        $this->api->command('Tablas', 'Tablas', 'Rows');
        $this->api->addParameter('Tabla',  'S', $Tabla);
        $this->api->addParameter('Limite', 'I', $Limite);
        $result = $this->api->execute();

        if (!$result->status()) {
            $filas = $result->table();
            // La matrícula está encriptada en la base: aquí se muestra legible.
            if (in_array('matricula', $columnas, true)) {
                foreach ($filas as &$fila) {
                    $clara = empty($fila['matricula']) ? '' : (string) decrypt($fila['matricula']);
                    if ($clara !== '') $fila['matricula'] = $clara;
                }
                unset($fila);
            }
            $this->success('Datos de la tabla.', ['tabla' => $Tabla, 'columnas' => $columnas, 'filas' => $filas]);
        } else {
            $this->failure($result);
        }
    }
}
