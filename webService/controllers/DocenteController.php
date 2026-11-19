<?php

/**
 * Maestros (solo el administrador). Se llama Docente porque /maestro ya es la pantalla de inicio de la maestra.
 *   /docente/index     → pantalla
 *   /docente/listar    → los maestros con su grupo, matrícula y niveles de inglés
 *   /docente/guardar   → alta o cambio
 *   /docente/eliminar  → baja (sus grupos quedan sin asignar)
 * Un maestro tiene su grupo, da inglés en uno o varios niveles, o las dos cosas.
 * La URL anterior /administrador/maestros redirige a /docente/index (tabla Rutas).
 */
class DocenteController extends Controller
{
    public const MODULE = 'escolar';
    use CuentasPersona;


    public function index(): void
    {
        $this->view('admin/maestros');
    }

    public function listar(): void
    {
        $this->success('Listado obtenido.', ['items' => $this->personas(TipoCuenta::MAESTRO)]);
    }

    /**
     * Sin IdCuenta es un alta; con ella, un cambio (IdGrupo vacío o 0 = no cambia el grupo).
     * $Niveles son los niveles de inglés marcados; $SinGrupo deja al maestro sin grupo propio (solo si da inglés).
     */
    public function guardar(Persona $persona, array $Niveles = [], bool $SinGrupo = false): void
    {
        $niveles = array_values(array_intersect(Grupo::ENGLISH_LEVELS, $Niveles));
        $niveles = $niveles ? implode(',', $niveles) : null;

        if (!$persona->IdCuenta) {
            // Un maestro que solo da inglés no necesita grupo propio (queda en el comodín 0)
            $this->ensure((int) $persona->IdGrupo > 0 || $niveles !== null, 'Selecciona un grupo o marca los niveles de inglés que imparte.');
            $persona->IdGrupo = max(0, (int) $persona->IdGrupo);
        } elseif ($SinGrupo) {
            $this->ensure($niveles !== null, 'Sin grupo propio, el maestro debe tener al menos un nivel de inglés.');
        }
        $this->validarPersona($persona);

        $this->api->command('Escolar', 'Persona', $persona->IdCuenta ? 'Update' : 'Insert');
        if ($persona->IdCuenta) {
            $this->api->addParameter('IdCuenta', 'I', $persona->IdCuenta);
            $this->api->addParameter('SinGrupo', 'B', $SinGrupo);
        }
        $this->api->addParameter('IdPermiso',     'I', TipoCuenta::MAESTRO);
        $this->api->addParameter('Nombre',        'S', $persona->Nombre);
        $this->api->addParameter('Paterno',       'S', $persona->Paterno);
        $this->api->addParameter('Materno',       'S', $persona->Materno);
        $this->api->addParameter('IdGrupo',       'I', $persona->IdGrupo);
        $this->api->addParameter('Matricula',     'S', Cifrado::encrypt($persona->Matricula));   // se guarda encriptada
        $this->api->addParameter('NivelesIngles', 'S', $niveles);
        $result = $this->api->execute();

        if (!$result->status()) {
            if (!$persona->IdCuenta) $this->success('¡Se ha registrado un nuevo maestro!', ['IdCuenta' => $result->id()], 201);
            $this->success('Se ha actualizado la información.');
        } else {
            $this->failure($result);
        }
    }

    public function eliminar(int $IdCuenta): void
    {
        $this->eliminarPersona($IdCuenta);
    }
}
