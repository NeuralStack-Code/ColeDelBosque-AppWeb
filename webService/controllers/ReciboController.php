<?php
/**
 * Recibos: ingresos y egresos de la escuela (el administrador) y el recibo imprimible (también el alumno, solo los suyos).
 *   /recibo/index      → pantalla de finanzas
 *   /recibo/catalogos  → tipos de recibo, alumnos y docentes para el formulario
 *   /recibo/listar     → los recibos con el balance
 *   /recibo/guardar    → alta o cambio
 *   /recibo/eliminar   → baja
 *   /recibo/imprimir   → el recibo para imprimir o guardar en PDF (?IdRecibo=)
 * Las URLs anteriores /administrador/recibos y /recibo?id= redirigen aquí (index.php raíz).
 */
class ReciboController extends Controller
{
    protected int $role = self::ADMIN;
    protected array $readActions = ['index', 'listar', 'catalogos', 'imprimir'];

    /** El recibo impreso también lo abre el alumno: ahí basta la sesión y el método revisa que sea suyo. */
    public function authorize(string $action): void
    {
        if ($action === 'imprimir') {
            $this->requireScreen();
            return;
        }
        parent::authorize($action);
    }

    public function index(): void
    {
        $this->view('admin/recibos');
    }

    public function catalogos(): void
    {
        $this->api->command('Tipos_recibo', 'TipoRecibo', 'List');
        $result = $this->api->execute();
        if ($result->status()) $this->failure($result);
        $tipos = TipoRecibo::fromTable($result->table());

        $this->api->command('Recibos', 'Recibo', 'People');
        $this->api->addParameter('IdPermiso', 'I', self::PARENT);
        $result = $this->api->execute();
        if ($result->status()) $this->failure($result);
        $alumnos = PersonaOpcion::fromTable($result->table());

        $this->api->command('Recibos', 'Recibo', 'People');
        $this->api->addParameter('IdPermiso', 'I', self::TEACHER);
        $result = $this->api->execute();

        if (!$result->status()) {
            $this->success('Catálogos.', ['tipos' => $tipos, 'alumnos' => $alumnos, 'docentes' => PersonaOpcion::fromTable($result->table())]);
        } else {
            $this->failure($result);
        }
    }

    /** Los recibos y el balance (ingresos menos egresos). */
    public function listar(): void
    {
        $this->api->command('Recibos', 'Recibo', 'List');
        $result = $this->api->execute();

        if (!$result->status()) {
            $recibos = Recibo::fromTable($result->table());
            $saldo = array_sum(array_map(fn(Recibo $r) => ($r->esIngreso() ? 1 : -1) * $r->Monto, $recibos));
            $this->success('Recibos obtenidos.', ['items' => $recibos, 'saldo' => $saldo]);
        } else {
            $this->failure($result);
        }
    }

    /** Sin IdRecibo es un alta; con él, un cambio. Sin fecha es ahora; el destinatario alumno o docente pide su cuenta. */
    public function guardar(Recibo $recibo): void
    {
        $naturaleza   = in_array($recibo->Naturaleza, Recibo::NATURES, true) ? $recibo->Naturaleza : 'ingreso';
        $destinatario = in_array($recibo->DestinatarioTipo, Recibo::RECIPIENTS, true) ? $recibo->DestinatarioTipo : 'escuela';
        $this->ensure($recibo->Monto > 0, 'El monto debe ser mayor a 0.');
        if ($destinatario !== 'escuela') $this->ensure((int) $recibo->IdCuenta > 0, "Selecciona el $destinatario destinatario.");
        $fecha = $recibo->Fecha === '' ? date('Y-m-d H:i:s') : str_replace('T', ' ', $recibo->Fecha);

        if (!$recibo->IdRecibo) {
            $this->api->command('Recibos', 'Recibo', 'Insert');
        } else {
            $this->api->command('Recibos', 'Recibo', 'Update');
            $this->api->addParameter('IdRecibo', 'I', $recibo->IdRecibo);
        }
        $this->api->addParameter('IdTipoRecibo',     'I', $recibo->IdTipoRecibo ?: null);
        $this->api->addParameter('Naturaleza',       'S', $naturaleza);
        $this->api->addParameter('DestinatarioTipo', 'S', $destinatario);
        $this->api->addParameter('IdCuenta',         'I', $destinatario === 'escuela' ? null : $recibo->IdCuenta);
        $this->api->addParameter('Monto',            'N', $recibo->Monto);
        $this->api->addParameter('TipoPago',         'S', $recibo->TipoPago);
        $this->api->addParameter('Comentario',       'S', $recibo->Comentario);
        $this->api->addParameter('Fecha',            'S', $fecha);
        $result = $this->api->execute();

        if (!$result->status()) {
            if (!$recibo->IdRecibo) $this->success('Recibo registrado.', ['IdRecibo' => $result->id()], 201);
            $this->success('Recibo actualizado.');
        } else {
            $this->failure($result);
        }
    }

    public function eliminar(int $IdRecibo): void
    {
        $this->api->command('Recibos', 'Recibo', 'Delete');
        $this->api->addParameter('IdRecibo', 'I', $IdRecibo);
        $result = $this->api->execute();

        if (!$result->status()) {
            $this->ensure($result->affected() > 0, 'No se encontró el recibo.', 404);
            $this->success('Recibo eliminado.');
        } else {
            $this->failure($result);
        }
    }

    /** El recibo para imprimir. El administrador ve cualquiera; el alumno, solo los suyos. $id es el de la URL anterior (/recibo?id=). */
    public function imprimir(int $IdRecibo = 0, int $id = 0): void
    {
        $this->api->command('Recibos', 'Recibo', 'Get');
        $this->api->addParameter('IdRecibo', 'I', $IdRecibo ?: $id);
        $result = $this->api->execute();
        if ($result->status()) $this->failure($result);

        header('Content-Type: text/html; charset=utf-8');
        if ($result->row() === null) {
            http_response_code(404);
            exit('Recibo no encontrado.');
        }
        $recibo = Recibo::fromRow($result->row());

        $propio = $this->roleId() === self::PARENT && $recibo->IdUsuarioCuenta === $this->userId() && $recibo->IdPermisoCuenta === self::PARENT;
        if (!$this->isAdmin() && !$propio) {
            http_response_code(403);
            exit('No tienes acceso a este recibo.');
        }

        $this->view('recibo', ['recibo' => $recibo, 'volver' => $this->isAdmin() ? '/recibo/index' : '/padre/index']);
    }
}
