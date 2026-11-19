<?php
/**
 * Columnas del reporte semanal que capturan las maestras (solo el administrador).
 *   /reportecolumna/index     → pantalla
 *   /reportecolumna/listar    → todas las columnas, en su orden
 *   /reportecolumna/guardar   → alta o cambio
 *   /reportecolumna/eliminar  → baja (si aún no hay reportes con ella; si los hay, se desactiva)
 * La URL anterior /administrador/reporte-columnas redirige a /reportecolumna/index (tabla Rutas).
 */
class ReporteColumnaController extends Controller
{
    public const MODULE = 'escolar';

    public function index(): void
    {
        $this->view('admin/reporte_columnas');
    }

    public function listar(): void
    {
        $this->api->command('Escolar', 'ReporteColumna', 'List');
        $result = $this->api->execute();

        if (!$result->status()) {
            $this->success('Columnas obtenidas.', ['items' => ReporteColumna::fromTable($result->table())]);
        } else {
            $this->failure($result);
        }
    }

    /** Sin IdColumna es un alta; con ella, un cambio. La etiqueta solo aplica a las casillas. */
    public function guardar(ReporteColumna $columna): void
    {
        $tipo     = $columna->Tipo === 'texto' ? 'texto' : 'casilla';
        $etiqueta = (string) $columna->Etiqueta;
        $this->ensure($columna->Nombre !== '', 'El nombre es obligatorio.');
        $this->ensure((bool) preg_match('/^.{1,60}$/us', $columna->Nombre), 'El nombre no puede pasar de 60 caracteres.');
        $this->ensure((bool) preg_match('/^.{0,60}$/us', $etiqueta), 'El texto del correo no puede pasar de 60 caracteres.');

        if (!$columna->IdColumna) {
            $this->api->command('Escolar', 'ReporteColumna', 'Insert');
        } else {
            $this->api->command('Escolar', 'ReporteColumna', 'Update');
            $this->api->addParameter('IdColumna', 'I', $columna->IdColumna);
        }
        $this->api->addParameter('Nombre',   'S', $columna->Nombre);
        $this->api->addParameter('Tipo',     'S', $tipo);
        $this->api->addParameter('Etiqueta', 'S', $tipo === 'casilla' && $etiqueta !== '' ? $etiqueta : null);
        $this->api->addParameter('Orden',    'I', $columna->Orden);
        $this->api->addParameter('Activo',   'B', $columna->Activo);
        $result = $this->api->execute();

        if (!$result->status()) {
            if (!$columna->IdColumna) $this->success('Columna creada.', ['IdColumna' => $result->id()], 201);
            $this->success('Columna actualizada.');
        } else {
            $this->failure($result);
        }
    }

    public function eliminar(int $IdColumna): void
    {
        $this->api->command('Escolar', 'ReporteColumna', 'Delete');
        $this->api->addParameter('IdColumna', 'I', $IdColumna);
        $result = $this->api->execute();

        if (!$result->status()) {
            $this->ensure($result->affected() > 0, 'No se encontró la columna.', 404);
            $this->success('Columna eliminada.');
        } else {
            $this->failure($result);
        }
    }
}
