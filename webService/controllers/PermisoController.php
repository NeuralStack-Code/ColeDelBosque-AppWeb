<?php
/**
 * Permisos: el catálogo de roles (solo el desarrollador).
 *   /permiso/index     → pantalla del catálogo
 *   /permiso/listar    → los roles, con cuántas cuentas tiene cada uno
 *   /permiso/guardar   → alta o cambio
 *   /permiso/eliminar  → baja (nunca los roles base del sistema ni uno con cuentas)
 * La URL anterior /desarrollador/permisos redirige a /permiso/index (index.php raíz).
 */
class PermisoController extends Controller
{
    protected int $role = self::DEVELOPER;

    public function index(): void
    {
        $this->view('dev/permisos');
    }

    public function listar(): void
    {
        $this->api->command('Permisos', 'Permiso', 'List');
        $result = $this->api->execute();

        if (!$result->status()) {
            $this->success('Permisos obtenidos.', ['items' => Permiso::fromTable($result->table())]);
        } else {
            $this->failure($result);
        }
    }

    /** Sin IdPermiso es un alta; con él, un cambio. */
    public function guardar(Permiso $permiso): void
    {
        $this->ensure($permiso->Nombre !== '', 'El nombre del rol es obligatorio.');
        $this->ensure((bool) preg_match('/^.{1,25}$/us', $permiso->Nombre), 'El nombre del rol admite máximo 25 caracteres.');

        if (!$permiso->IdPermiso) {
            $this->api->command('Permisos', 'Permiso', 'Insert');
        } else {
            $this->api->command('Permisos', 'Permiso', 'Update');
            $this->api->addParameter('IdPermiso', 'I', $permiso->IdPermiso);
        }
        $this->api->addParameter('Nombre', 'S', $permiso->Nombre);
        $result = $this->api->execute();

        if (!$result->status()) {
            if (!$permiso->IdPermiso) $this->success('Rol creado.', ['IdPermiso' => $result->id()], 201);
            $this->success('Rol actualizado.');
        } else {
            $this->failure($result);
        }
    }

    public function eliminar(int $IdPermiso): void
    {
        $this->ensure($IdPermiso > Permiso::LAST_SYSTEM_ROLE, 'No se pueden eliminar los roles base del sistema.', 409);

        $this->api->command('Permisos', 'Permiso', 'Delete');
        $this->api->addParameter('IdPermiso', 'I', $IdPermiso);
        $result = $this->api->execute();

        if (!$result->status()) {
            $this->ensure($result->affected() > 0, 'No se encontró el rol.', 404);
            $this->success('Rol eliminado.');
        } else {
            $this->failure($result);
        }
    }
}
