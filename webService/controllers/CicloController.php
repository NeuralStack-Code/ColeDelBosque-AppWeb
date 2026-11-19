<?php
/**
 * Ciclos escolares (solo el administrador).
 *   /ciclo/index     → pantalla de ciclos
 *   /ciclo/listar    → los ciclos
 *   /ciclo/guardar   → alta (nace cerrado) o cambio
 *   /ciclo/activar   → activa uno y cierra los demás
 *   /ciclo/eliminar  → baja
 * La URL anterior /administrador/ciclos redirige a /ciclo/index (tabla Rutas).
 */
class CicloController extends Controller
{
    public const MODULE = 'escolar';

    public function index(): void
    {
        $this->view('admin/ciclos');
    }

    public function listar(): void
    {
        $this->api->command('Escolar', 'Ciclo', 'List');
        $result = $this->api->execute();

        if (!$result->status()) {
            $this->success('Ciclos obtenidos.', ['items' => Ciclo::fromTable($result->table())]);
        } else {
            $this->failure($result);
        }
    }

    /** Sin IdCiclo es un alta; con él, un cambio. */
    public function guardar(Ciclo $ciclo): void
    {
        $this->ensure($ciclo->Nombre !== '', 'El nombre es obligatorio.');
        $this->ensure((bool) preg_match('/^.{1,30}$/us', $ciclo->Nombre), 'El nombre admite máximo 30 caracteres.');
        $this->ensure($this->isDate($ciclo->FechaInicio) && $this->isDate($ciclo->FechaFin), 'Indica fecha de inicio y fin.');
        $this->ensure($ciclo->FechaFin >= $ciclo->FechaInicio, 'La fecha fin no puede ser anterior a la de inicio.');

        if (!$ciclo->IdCiclo) {
            $this->api->command('Escolar', 'Ciclo', 'Insert');
        } else {
            $this->api->command('Escolar', 'Ciclo', 'Update');
            $this->api->addParameter('IdCiclo', 'I', $ciclo->IdCiclo);
        }
        $this->api->addParameter('Nombre',      'S', $ciclo->Nombre);
        $this->api->addParameter('FechaInicio', 'D', $ciclo->FechaInicio);
        $this->api->addParameter('FechaFin',    'D', $ciclo->FechaFin);
        $result = $this->api->execute();

        if (!$result->status()) {
            if (!$ciclo->IdCiclo) $this->success('Ciclo creado.', ['IdCiclo' => $result->id()], 201);
            $this->success('Ciclo actualizado.');
        } else {
            $this->failure($result);
        }
    }

    public function activar(int $IdCiclo): void
    {
        $this->api->command('Escolar', 'Ciclo', 'Activate');
        $this->api->addParameter('IdCiclo', 'I', $IdCiclo);
        $result = $this->api->execute();

        if (!$result->status()) {
            $this->success('Ciclo activado.');
        } else {
            $this->failure($result);
        }
    }

    public function eliminar(int $IdCiclo): void
    {
        $this->api->command('Escolar', 'Ciclo', 'Delete');
        $this->api->addParameter('IdCiclo', 'I', $IdCiclo);
        $result = $this->api->execute();

        if (!$result->status()) {
            $this->ensure($result->affected() > 0, 'No se encontró el ciclo.', 404);
            $this->success('Ciclo eliminado.');
        } else {
            $this->failure($result);
        }
    }

    /** ¿Es una fecha real con la forma AAAA-MM-DD? */
    private function isDate(string $value): bool
    {
        return (bool) preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $value, $m) && checkdate((int) $m[2], (int) $m[3], (int) $m[1]);
    }
}
