<?php
/**
 * Tipos de recibo (solo el administrador).
 *   /tiporecibo/index     → pantalla del catálogo
 *   /tiporecibo/listar    → los tipos
 *   /tiporecibo/guardar   → alta o cambio
 *   /tiporecibo/eliminar  → baja (si no hay recibos de ese tipo)
 * La URL anterior /administrador/tipos-recibo redirige a /tiporecibo/index (tabla Rutas).
 */
class TipoReciboController extends Controller
{
    public const MODULE = 'pagos';

    public function index(): void
    {
        $this->view('admin/tipos_recibo');
    }

    public function listar(): void
    {
        $this->api->command('Pagos', 'TipoRecibo', 'List');
        $result = $this->api->execute();

        if (!$result->status()) {
            $this->success('Tipos obtenidos.', ['items' => TipoRecibo::fromTable($result->table())]);
        } else {
            $this->failure($result);
        }
    }

    /** Sin IdTipo es un alta; con él, un cambio. */
    public function guardar(TipoRecibo $tipo): void
    {
        $this->ensure($tipo->Nombre !== '', 'El nombre es obligatorio.');
        $this->ensure((bool) preg_match('/^.{1,50}$/us', $tipo->Nombre), 'El nombre admite máximo 50 caracteres.');
        $this->ensure(in_array($tipo->Naturaleza, TipoRecibo::NATURES, true), 'La naturaleza debe ser ingreso o gasto.');

        if (!$tipo->IdTipo) {
            $this->api->command('Pagos', 'TipoRecibo', 'Insert');
        } else {
            $this->api->command('Pagos', 'TipoRecibo', 'Update');
            $this->api->addParameter('IdTipo', 'I', $tipo->IdTipo);
        }
        $this->api->addParameter('Nombre',     'S', $tipo->Nombre);
        $this->api->addParameter('Naturaleza', 'S', $tipo->Naturaleza);
        $result = $this->api->execute();

        if (!$result->status()) {
            if (!$tipo->IdTipo) $this->success('Tipo de recibo creado.', ['IdTipo' => $result->id()], 201);
            $this->success('Tipo de recibo actualizado.');
        } else {
            $this->failure($result);
        }
    }

    public function eliminar(int $IdTipo): void
    {
        $this->api->command('Pagos', 'TipoRecibo', 'Delete');
        $this->api->addParameter('IdTipo', 'I', $IdTipo);
        $result = $this->api->execute();

        if (!$result->status()) {
            $this->ensure($result->affected() > 0, 'No se encontró el tipo de recibo.', 404);
            $this->success('Tipo de recibo eliminado.');
        } else {
            $this->failure($result);
        }
    }
}
