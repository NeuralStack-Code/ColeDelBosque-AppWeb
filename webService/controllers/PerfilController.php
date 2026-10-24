<?php
require_once dirname(__DIR__, 2) . '/apiService/core/crypto.php';   // encrypt() / decrypt(): la matrícula se guarda encriptada

/**
 * Perfiles: alta, cambio y baja de cuentas de cualquier rol (solo el desarrollador).
 *   /perfil/index      → pantalla de perfiles
 *   /perfil/catalogos  → roles, grupos y estatus para el formulario
 *   /perfil/listar     → las cuentas (?IdPermiso= para un solo rol)
 *   /perfil/guardar    → alta o cambio
 *   /perfil/eliminar   → baja de la cuenta con todo lo suyo (colegiaturas, calificaciones, recibos)
 * La URL anterior /desarrollador/perfiles redirige a /perfil/index (index.php raíz).
 */
class PerfilController extends Controller
{
    protected int $role = self::DEVELOPER;
    protected array $readActions = ['index', 'listar', 'catalogos'];

    public function index(): void
    {
        $this->view('dev/perfiles');
    }

    public function catalogos(): void
    {
        $this->api->command('Permisos', 'Permiso', 'List');
        $result = $this->api->execute();
        if ($result->status()) $this->failure($result);
        $permisos = Permiso::fromTable($result->table());

        $this->api->command('Perfiles', 'Perfil', 'Groups');
        $result = $this->api->execute();
        if ($result->status()) $this->failure($result);
        $grupos = GrupoOpcion::fromTable($result->table());

        $this->api->command('Perfiles', 'Perfil', 'Statuses');
        $result = $this->api->execute();

        if (!$result->status()) {
            $this->success('Catálogos.', ['permisos' => $permisos, 'grupos' => $grupos, 'estatus' => EstatusOpcion::fromTable($result->table())]);
        } else {
            $this->failure($result);
        }
    }

    public function listar(int $IdPermiso = 0): void
    {
        $this->api->command('Perfiles', 'Perfil', 'List');
        $this->api->addParameter('IdPermiso', 'I', $IdPermiso);
        $result = $this->api->execute();

        if (!$result->status()) {
            $perfiles = Perfil::fromTable($result->table());
            foreach ($perfiles as $perfil) {
                $perfil->Matricula = (string) decrypt($perfil->Matricula);   // en la base va encriptada: aquí se muestra legible
            }
            $this->success('Perfiles obtenidos.', ['items' => $perfiles]);
        } else {
            $this->failure($result);
        }
    }

    /** Sin IdCuenta es un alta; con ella, un cambio. */
    public function guardar(Perfil $perfil): void
    {
        $this->ensure($perfil->Nombre !== '' && $perfil->Paterno !== '', 'Nombre y apellido paterno son obligatorios.');   // el materno nunca es obligatorio
        $fits = fn(?string $text): bool => (bool) preg_match('/^.{0,25}$/us', (string) $text);
        $this->ensure($fits($perfil->Nombre) && $fits($perfil->Paterno) && $fits($perfil->Materno),
                      'El nombre y los apellidos admiten máximo 25 caracteres cada uno.');
        $this->ensure(strlen($perfil->Matricula) >= 4, 'La matrícula debe tener al menos 4 caracteres.');
        $this->ensure(strlen($perfil->Matricula) <= 60, 'La matrícula admite máximo 60 caracteres.');
        $this->ensure($perfil->IdPermiso > 0, 'Rol (permiso) no válido.');

        if (!$perfil->IdCuenta) {
            $this->api->command('Perfiles', 'Perfil', 'Insert');
        } else {
            $this->api->command('Perfiles', 'Perfil', 'Update');
            $this->api->addParameter('IdCuenta', 'I', $perfil->IdCuenta);
        }
        $this->api->addParameter('Nombre',    'S', $perfil->Nombre);
        $this->api->addParameter('Paterno',   'S', $perfil->Paterno);
        $this->api->addParameter('Materno',   'S', $perfil->Materno);
        $this->api->addParameter('Matricula', 'S', encrypt($perfil->Matricula));
        $this->api->addParameter('IdPermiso', 'I', $perfil->IdPermiso);
        $this->api->addParameter('IdGrupo',   'I', $perfil->hasGroup() ? $perfil->IdGrupo : 0);   // administrador y desarrollador no llevan grupo
        $this->api->addParameter('Estatus',   'S', $perfil->Estatus);
        $result = $this->api->execute();

        if (!$result->status()) {
            if (!$perfil->IdCuenta) $this->success('Perfil creado.', ['IdCuenta' => $result->id()], 201);
            $this->success('Perfil actualizado.');
        } else {
            $this->failure($result);
        }
    }

    public function eliminar(int $IdCuenta): void
    {
        $this->api->command('Perfiles', 'Perfil', 'Delete');
        $this->api->addParameter('IdCuenta',        'I', $IdCuenta);
        $this->api->addParameter('IdUsuarioSesion', 'I', $this->userId());
        $result = $this->api->execute();

        if (!$result->status()) {
            $this->success('Perfil y sus datos relacionados eliminados.');
        } else {
            $this->failure($result);
        }
    }
}
