<?php

/**
 * Cuentas: alta, cambio y baja de cuentas de cualquier tipo, y qué perfiles del catálogo tiene cada una.
 *   /cuenta/index      → pantalla de cuentas
 *   /cuenta/catalogos  → tipos de cuenta, perfiles, grupos y estatus para el formulario
 *   /cuenta/listar     → las cuentas con sus perfiles (?IdPermiso= para un solo tipo)
 *   /cuenta/guardar    → alta o cambio
 *   /cuenta/perfiles   → reemplaza los perfiles de una cuenta (el procedure aplica "nadie da lo que no tiene")
 *   /cuenta/eliminar   → baja de la cuenta con todo lo suyo (colegiaturas, calificaciones, recibos)
 * Las URLs anteriores /perfil/index y /desarrollador/perfiles redirigen aquí (tabla Rutas).
 */
class CuentaController extends Controller
{
    public const MODULE = 'sistema';

    public function index(): void
    {
        $this->view('dev/cuentas');
    }

    public function catalogos(): void
    {
        $this->api->command('Sistema', 'Cuenta', 'Types');
        $result = $this->api->execute();
        if ($result->status()) $this->failure($result);
        $permisos = TipoCuentaOpcion::fromTable($result->table());

        $this->api->command('Framework', 'PerfilAcceso', 'List');
        $result = $this->api->execute();
        if ($result->status()) $this->failure($result);
        $perfiles = PerfilAcceso::fromTable($result->table());

        $this->api->command('Sistema', 'Cuenta', 'Groups');
        $result = $this->api->execute();
        if ($result->status()) $this->failure($result);
        $grupos = GrupoOpcion::fromTable($result->table());

        $this->api->command('Sistema', 'Cuenta', 'Statuses');
        $result = $this->api->execute();

        if (!$result->status()) {
            $this->success('Catálogos.', ['permisos' => $permisos, 'perfiles' => $perfiles, 'grupos' => $grupos, 'estatus' => EstatusOpcion::fromTable($result->table())]);
        } else {
            $this->failure($result);
        }
    }

    public function listar(int $IdPermiso = 0): void
    {
        $this->api->command('Sistema', 'Cuenta', 'List');
        $this->api->addParameter('IdPermiso', 'I', $IdPermiso);
        $result = $this->api->execute();

        if (!$result->status()) {
            $cuentas = Cuenta::fromTable($result->table());

            // Los perfiles de todas las cuentas en una sola consulta (sin IdCuenta = todas).
            $this->api->command('Framework', 'PerfilAcceso', 'AccountProfiles');
            $this->api->addParameter('IdCuenta', 'I', 0);
            $result = $this->api->execute();
            if ($result->status()) $this->failure($result);
            $porCuenta = [];
            foreach ($result->table() as $fila) $porCuenta[(int) $fila['IdCuenta']][] = (int) $fila['IdPerfil'];

            foreach ($cuentas as $cuenta) {
                $cuenta->Matricula = (string) Cifrado::decrypt($cuenta->Matricula);   // en la base va encriptada: aquí se muestra legible
                $cuenta->Perfiles  = $porCuenta[$cuenta->IdCuenta] ?? [];
            }
            $this->success('Cuentas obtenidas.', ['items' => $cuentas]);
        } else {
            $this->failure($result);
        }
    }

    /** Sin IdCuenta es un alta; con ella, un cambio. */
    public function guardar(Cuenta $cuenta): void
    {
        $this->ensure($cuenta->Nombre !== '' && $cuenta->Paterno !== '', 'Nombre y apellido paterno son obligatorios.');   // el materno nunca es obligatorio
        $fits = fn(?string $text): bool => (bool) preg_match('/^.{0,25}$/us', (string) $text);
        $this->ensure($fits($cuenta->Nombre) && $fits($cuenta->Paterno) && $fits($cuenta->Materno),
                      'El nombre y los apellidos admiten máximo 25 caracteres cada uno.');
        $this->ensure(strlen($cuenta->Matricula) >= 4, 'La matrícula debe tener al menos 4 caracteres.');
        $this->ensure(strlen($cuenta->Matricula) <= 60, 'La matrícula admite máximo 60 caracteres.');
        $this->ensure($cuenta->IdPermiso > 0, 'Tipo de cuenta no válido.');

        if (!$cuenta->IdCuenta) {
            $this->api->command('Sistema', 'Cuenta', 'Insert');
        } else {
            $this->api->command('Sistema', 'Cuenta', 'Update');
            $this->api->addParameter('IdCuenta', 'I', $cuenta->IdCuenta);
        }
        $this->api->addParameter('Nombre',    'S', $cuenta->Nombre);
        $this->api->addParameter('Paterno',   'S', $cuenta->Paterno);
        $this->api->addParameter('Materno',   'S', $cuenta->Materno);
        $this->api->addParameter('Matricula', 'S', Cifrado::encrypt($cuenta->Matricula));
        $this->api->addParameter('IdPermiso', 'I', $cuenta->IdPermiso);
        $this->api->addParameter('IdGrupo',   'I', $cuenta->hasGroup() ? $cuenta->IdGrupo : 0);   // administrador y desarrollador no llevan grupo
        $this->api->addParameter('Estatus',   'S', $cuenta->Estatus);
        $result = $this->api->execute();

        if (!$result->status()) {
            if (!$cuenta->IdCuenta) $this->success('Cuenta creada.', ['IdCuenta' => $result->id()], 201);
            $this->success('Cuenta actualizada.');
        } else {
            $this->failure($result);
        }
    }

    /** Reemplaza los perfiles de la cuenta ($Perfiles: JSON con los IdPerfil marcados). */
    public function perfiles(int $IdCuenta, string $Perfiles = '[]'): void
    {
        $pedidos = json_decode($Perfiles, true);
        $this->ensure(is_array($pedidos), 'Lista de perfiles inválida.');
        $ids = array_values(array_unique(array_filter(array_map('intval', $pedidos), fn(int $id) => $id > 0)));

        $this->api->command('Framework', 'PerfilAcceso', 'Assign');
        $this->api->addParameter('IdCuenta', 'I', $IdCuenta);
        $this->api->addParameter('Perfiles', 'S', json_encode($ids));
        $result = $this->api->execute();

        if (!$result->status()) {
            $this->success('Perfiles de la cuenta actualizados. Aplican desde su siguiente petición.');
        } else {
            $this->failure($result);
        }
    }

    public function eliminar(int $IdCuenta): void
    {
        $this->api->command('Sistema', 'Cuenta', 'Delete');
        $this->api->addParameter('IdCuenta',        'I', $IdCuenta);
        $this->api->addParameter('IdUsuarioSesion', 'I', $this->userId());
        $result = $this->api->execute();

        if (!$result->status()) {
            $this->success('Cuenta y sus datos relacionados eliminados.');
        } else {
            $this->failure($result);
        }
    }
}
