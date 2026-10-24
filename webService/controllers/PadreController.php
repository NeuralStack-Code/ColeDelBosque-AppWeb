<?php
require_once dirname(__DIR__, 2) . '/apiService/core/crypto.php';   // encrypt() / decrypt(): los correos de los tutores van encriptados

/**
 * Portal del alumno y su familia (solo el rol padre/alumno): solo lectura, salvo los correos de sus tutores.
 *   /padre/index           → pantalla
 *   /padre/resumen         → su grupo, calificaciones del ciclo activo, pagos y correos de tutor
 *   /padre/guardarCorreos  → a qué correos llega el reporte semanal
 * La URL anterior /padre redirige a /padre/index (index.php raíz).
 */
class PadreController extends Controller
{
    protected int $role = self::PARENT;
    protected array $readActions = ['index', 'resumen'];

    public function index(): void
    {
        $this->view('padre/home', ['nombre' => $this->user()]);
    }

    public function resumen(): void
    {
        $cuenta = $this->cuenta();

        $this->api->command('Ciclos', 'Ciclo', 'Active');
        $result = $this->api->execute();
        if ($result->status()) $this->failure($result);
        $idCiclo = (int) ($result->row()['IdCiclo'] ?? 0);

        $this->api->command('Padre', 'Padre', 'Grades');
        $this->api->addParameter('IdCuenta', 'I', $cuenta->IdCuenta);
        $this->api->addParameter('IdGrupo',  'I', $cuenta->IdGrupo);
        $this->api->addParameter('IdCiclo',  'I', $idCiclo);
        $result = $this->api->execute();
        if ($result->status()) $this->failure($result);
        $calificaciones = CalificacionMateria::fromTable($result->table());

        $this->api->command('Colegiaturas', 'Colegiatura', 'List');
        $this->api->addParameter('IdCuenta', 'I', $cuenta->IdCuenta);
        $result = $this->api->execute();

        if (!$result->status()) {
            $pagos = Colegiatura::fromTable($result->table());
            foreach ($pagos as $pago) Recargo::completar($pago);   // las mismas cantidades que ve el administrador
            $this->success('Resumen obtenido.', [
                'grado'          => (string) $cuenta->Grado,
                'calificaciones' => $calificaciones,
                'pagos'          => $pagos,
                'correoTutor'    => $cuenta->CorreoTutor  === null ? '' : (string) decrypt($cuenta->CorreoTutor),
                'correoTutor2'   => $cuenta->CorreoTutor2 === null ? '' : (string) decrypt($cuenta->CorreoTutor2),
            ]);
        } else {
            $this->failure($result);
        }
    }

    /** Los correos son opcionales: vacío o un correo válido. Se guardan encriptados. */
    public function guardarCorreos(string $CorreoTutor = '', string $CorreoTutor2 = ''): void
    {
        $correo1 = $this->correoTutor($CorreoTutor);
        $correo2 = $this->correoTutor($CorreoTutor2);

        $this->api->command('Padre', 'Padre', 'SaveEmails');
        $this->api->addParameter('IdUsuario',    'I', $this->userId());
        $this->api->addParameter('CorreoTutor',  'S', $correo1 === '' ? null : encrypt($correo1));
        $this->api->addParameter('CorreoTutor2', 'S', $correo2 === '' ? null : encrypt($correo2));
        $result = $this->api->execute();

        if (!$result->status()) {
            $this->success('Correos guardados.');
        } else {
            $this->failure($result);
        }
    }

    /** La cuenta del alumno en sesión; corta con 404 si no tiene. */
    private function cuenta(): CuentaAlumno
    {
        $this->api->command('Padre', 'Padre', 'Account');
        $this->api->addParameter('IdUsuario', 'I', $this->userId());
        $result = $this->api->execute();
        if ($result->status()) $this->failure($result);
        $this->ensure($result->row() !== null, 'No se encontró tu cuenta.', 404);
        return CuentaAlumno::fromRow($result->row());
    }

    /** '' o el correo en minúsculas; corta si no es válido. */
    private function correoTutor(string $correo): string
    {
        if ($correo === '') return '';
        $this->ensure(strlen($correo) <= 100 && filter_var($correo, FILTER_VALIDATE_EMAIL) !== false, 'Revisa el correo: no parece válido.');
        return strtolower($correo);
    }
}
