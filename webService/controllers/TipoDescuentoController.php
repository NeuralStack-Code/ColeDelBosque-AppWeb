<?php
/**
 * Tipos de descuento (solo el administrador). El porcentaje vive en la base, nada va fijo en el código.
 *   /tipodescuento/index     → pantalla del catálogo
 *   /tipodescuento/listar    → los descuentos
 *   /tipodescuento/guardar   → alta (nace activo) o cambio (también lo activa o desactiva)
 *   /tipodescuento/eliminar  → baja (si no hay pagos con ese descuento)
 * La URL anterior /administrador/tipos-descuento redirige a /tipodescuento/index (index.php raíz).
 */
class TipoDescuentoController extends Controller
{
    protected int $role = self::ADMIN;

    public function index(): void
    {
        $this->view('admin/tipos_descuento');
    }

    public function listar(): void
    {
        $this->api->command('Tipos_descuento', 'TipoDescuento', 'List');
        $result = $this->api->execute();

        if (!$result->status()) {
            $this->success('Tipos de descuento obtenidos.', ['items' => TipoDescuento::fromTable($result->table())]);
        } else {
            $this->failure($result);
        }
    }

    /** Sin IdDescuento es un alta; con él, un cambio. $Porcentaje llega aparte para revisar que sí sea un número. */
    public function guardar(TipoDescuento $descuento, string $Porcentaje = ''): void
    {
        $this->ensure($descuento->Nombre !== '', 'El nombre es obligatorio.');
        $this->ensure((bool) preg_match('/^.{1,80}$/us', $descuento->Nombre), 'El nombre admite máximo 80 caracteres.');
        $this->ensure(is_numeric($Porcentaje) && $descuento->Porcentaje >= 0 && $descuento->Porcentaje <= 100,
                      'El porcentaje debe estar entre 0 y 100.');
        $this->ensure(in_array($descuento->AplicaA, TipoDescuento::TARGETS, true), 'El descuento aplica a colegiatura o a inscripción.');

        if (!$descuento->IdDescuento) {
            $this->api->command('Tipos_descuento', 'TipoDescuento', 'Insert');
        } else {
            $this->api->command('Tipos_descuento', 'TipoDescuento', 'Update');
            $this->api->addParameter('IdDescuento', 'I', $descuento->IdDescuento);
            $this->api->addParameter('Activo',      'B', $descuento->Activo);
        }
        $this->api->addParameter('Nombre',     'S', $descuento->Nombre);
        $this->api->addParameter('Porcentaje', 'N', $descuento->Porcentaje);
        $this->api->addParameter('AplicaA',    'S', $descuento->AplicaA);
        $result = $this->api->execute();

        if (!$result->status()) {
            if (!$descuento->IdDescuento) $this->success('Tipo de descuento creado.', ['IdDescuento' => $result->id()], 201);
            $this->success('Tipo de descuento actualizado.');
        } else {
            $this->failure($result);
        }
    }

    public function eliminar(int $IdDescuento): void
    {
        $this->api->command('Tipos_descuento', 'TipoDescuento', 'Delete');
        $this->api->addParameter('IdDescuento', 'I', $IdDescuento);
        $result = $this->api->execute();

        if (!$result->status()) {
            $this->ensure($result->affected() > 0, 'No se encontró el descuento.', 404);
            $this->success('Tipo de descuento eliminado.');
        } else {
            $this->failure($result);
        }
    }
}
