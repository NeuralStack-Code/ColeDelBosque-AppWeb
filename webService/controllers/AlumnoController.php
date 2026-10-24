<?php
require_once dirname(__DIR__, 2) . '/apiService/core/crypto.php';   // encrypt() / decrypt(): matrícula y correos van encriptados
require_once dirname(__DIR__, 2) . '/apiService/core/mail.php';     // enviarCorreo() → api central

/**
 * Alumnos (solo el administrador).
 *   /alumno/index            → pantalla
 *   /alumno/listar           → los alumnos con su grupo, matrícula y correos de tutor
 *   /alumno/guardar          → alta o cambio (al dar de alta con correo, la familia recibe su matrícula)
 *   /alumno/eliminar         → baja con todo lo suyo (colegiaturas, calificaciones, recibos)
 *   /alumno/enviarMatricula  → manda la matrícula al correo de los tutores
 * La URL anterior /administrador/alumnos redirige a /alumno/index (index.php raíz).
 */
class AlumnoController extends Controller
{
    use CuentasPersona;

    protected int $role = self::ADMIN;

    public function index(): void
    {
        $this->view('admin/alumnos');
    }

    public function listar(): void
    {
        $this->success('Listado obtenido.', ['items' => $this->personas(self::PARENT)]);
    }

    /** Sin IdCuenta es un alta (pide grupo); con ella, un cambio (IdGrupo vacío o 0 = no cambia el grupo). */
    public function guardar(Persona $persona): void
    {
        if (!$persona->IdCuenta) $this->ensure((int) $persona->IdGrupo > 0, 'Selecciona un grupo válido.');
        $this->validarPersona($persona);
        $correo1 = $this->correoTutor((string) $persona->CorreoTutor);
        $correo2 = $this->correoTutor((string) $persona->CorreoTutor2);

        $this->api->command('Control_escolar', 'Persona', $persona->IdCuenta ? 'Update' : 'Insert');
        if ($persona->IdCuenta) $this->api->addParameter('IdCuenta', 'I', $persona->IdCuenta);
        $this->api->addParameter('IdPermiso',    'I', self::PARENT);
        $this->api->addParameter('Nombre',       'S', $persona->Nombre);
        $this->api->addParameter('Paterno',      'S', $persona->Paterno);
        $this->api->addParameter('Materno',      'S', $persona->Materno);
        $this->api->addParameter('IdGrupo',      'I', $persona->IdGrupo);
        $this->api->addParameter('Matricula',    'S', encrypt($persona->Matricula));   // se guarda encriptada
        $this->api->addParameter('CorreoTutor',  'S', $correo1 === '' ? null : encrypt($correo1));
        $this->api->addParameter('CorreoTutor2', 'S', $correo2 === '' ? null : encrypt($correo2));
        $result = $this->api->execute();
        if ($result->status()) $this->failure($result);

        if ($persona->IdCuenta) $this->success('Se ha actualizado la información.');

        $mensaje = '¡Se ha registrado un nuevo alumno!';
        if ($correo1 !== '' || $correo2 !== '') {
            // Alta con correo: la familia recibe su matrícula de una vez
            $mensaje .= $this->mandarMatricula($result->id()) > 0
                ? ' Se envió la matrícula al correo del tutor.'
                : ' No se pudo enviar la matrícula por correo; reenvíala desde la lista.';
        }
        $this->success($mensaje, ['IdCuenta' => $result->id()], 201);
    }

    public function eliminar(int $IdCuenta): void
    {
        $this->eliminarPersona($IdCuenta);
    }

    /** Botón "enviar matrícula" de la lista. */
    public function enviarMatricula(int $IdCuenta): void
    {
        $enviados = $this->mandarMatricula($IdCuenta);
        $this->ensure($enviados !== -1, 'El alumno no tiene correo de tutor. Agrégalo en su ficha.', 409);
        $this->ensure($enviados !== 0, 'No se pudo enviar el correo.', 502);
        $this->success('Matrícula enviada al correo del tutor.');
    }

    /** Manda la matrícula a cada tutor. Regresa cuántos correos salieron (-1 = no tiene correo). Corta con 404 si no es un alumno. */
    private function mandarMatricula(int $IdCuenta): int
    {
        $this->api->command('Control_escolar', 'Persona', 'MailData');
        $this->api->addParameter('IdCuenta', 'I', $IdCuenta);
        $result = $this->api->execute();
        if ($result->status()) $this->failure($result);
        $this->ensure($result->row() !== null, 'No se encontró al alumno.', 404);

        $alumno = CorreoAlumno::fromRow($result->row());
        $alumno->CorreoTutor  = $alumno->CorreoTutor  === null ? null : (string) decrypt($alumno->CorreoTutor);
        $alumno->CorreoTutor2 = $alumno->CorreoTutor2 === null ? null : (string) decrypt($alumno->CorreoTutor2);
        $correos = $alumno->correos();
        if (!$correos) return -1;

        $html = CorreoMatricula::html($alumno->NombreCompleto, (string) decrypt((string) $alumno->Matricula));
        $enviados = 0; $errores = [];
        foreach ($correos as $correo) {
            $error = enviarCorreo($correo, CorreoMatricula::asunto($alumno->NombreCompleto), $html, ['fromName' => 'Colegio del Bosque']);
            if ($error === '') $enviados++;
            else $errores[] = $error;
        }
        if ($errores) error_log('Matrícula no enviada: ' . implode(' | ', $errores));
        return $enviados;
    }

    /** Correo de tutor opcional: '' o un correo válido, en minúsculas. */
    private function correoTutor(string $correo): string
    {
        if ($correo === '') return '';
        $this->ensure(strlen($correo) <= 100 && filter_var($correo, FILTER_VALIDATE_EMAIL) !== false, 'Revisa el correo del tutor.');
        return strtolower($correo);
    }
}
