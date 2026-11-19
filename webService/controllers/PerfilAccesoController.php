<?php
/**
 * Perfiles y permisos (catálogo de permisos): qué pantallas y acciones concede cada perfil. Ya no hay roles: una
 * cuenta tiene uno o varios perfiles (se asignan en /cuenta/index) y puede abrir la suma de lo que conceden.
 *   /perfilacceso/index    → la pantalla (árbol con casillas: marcar una vista marca sus acciones)
 *   /perfilacceso/listar   → los perfiles (el resumen del panel del desarrollador)
 *   /perfilacceso/{guardar|eliminar|objetos}  → lo que llama su JavaScript
 * Las reglas de quién puede dar qué ("nadie da lo que no tiene") las aplican los procedures; aquí solo se valida la
 * forma de los datos. La URL anterior /permiso/index redirige aquí (tabla Rutas).
 */
class PerfilAccesoController extends Controller
{
    public const MODULE = 'sistema';

    /** Pantalla: los perfiles con lo que concede cada uno, el árbol del catálogo y lo que puede conceder quien entra. */
    public function index(): void
    {
        $perfiles = $this->perfiles();

        $this->api->command('Framework', 'PerfilAcceso', 'Grants');         // sin IdPerfil: los de todos
        $result = $this->api->execute();
        if ($result->status()) $this->failure($result);
        PerfilAcceso::assignGrants($perfiles, $result->table());

        $this->api->command('Framework', 'Objeto', 'List');
        $result = $this->api->execute();
        if ($result->status()) $this->failure($result);
        $objetos = [];
        foreach ($result->table() as $o) {
            if ((int) $o['Activo'] !== 1) continue;
            $objetos[] = [
                'IdObjeto' => (int) $o['IdObjeto'], 'IdPadre' => $o['IdPadre'] === null ? null : (int) $o['IdPadre'],
                'Tipo' => (int) $o['Tipo'], 'Componente' => $o['Componente'], 'Clave' => $o['Clave'],
                'Nombre' => $o['Nombre'], 'Acceso' => $o['Acceso'], 'Sensible' => (bool) (int) $o['Sensible'],
            ];
        }

        // Quien entra: ¿tiene un perfil administrador? y, si no, qué puede conceder (lo de sus perfiles activos).
        $this->api->command('Framework', 'PerfilAcceso', 'AccountProfiles');
        $this->api->addParameter('IdCuenta', 'I', $this->accountId());
        $result = $this->api->execute();
        if ($result->status()) $this->failure($result);
        $mios = array_map('intval', array_column($result->table(), 'IdPerfil'));
        $esAdmin = false;
        $concedibles = [];
        foreach ($perfiles as $p) {
            if (!in_array($p->IdPerfil, $mios, true) || !$p->Activo) continue;
            if ($p->EsAdmin) $esAdmin = true;
            $concedibles = array_merge($concedibles, $p->Concede);
        }

        $this->view('dev/perfiles', [
            'nombre'      => $this->user(),
            'perfiles'    => $perfiles,
            'objetos'     => $objetos,
            'esAdmin'     => $esAdmin,
            'concedibles' => array_values(array_unique($concedibles)),
            'mios'        => $mios,
        ]);
    }

    public function listar(): void
    {
        $this->success('Perfiles obtenidos.', ['items' => $this->perfiles()]);
    }

    /** Crea (sin IdPerfil) o edita (con IdPerfil). */
    public function guardar(PerfilAcceso $perfil): void
    {
        $this->ensure($perfil->Nombre !== '', 'El nombre es obligatorio.');
        $this->ensure($perfil->Inicio === '' || preg_match('#^/[a-z0-9_/]*$#', $perfil->Inicio) === 1,
                      'La pantalla de inicio es una ruta del sitio, por ejemplo /maestro.');
        $isNew = (int) $perfil->IdPerfil === 0;

        $this->api->command('Framework', 'PerfilAcceso', $isNew ? 'Insert' : 'Update');
        if (!$isNew) $this->api->addParameter('IdPerfil', 'I', $perfil->IdPerfil);
        $this->api->addParameter('Nombre',      'S', $perfil->Nombre);
        $this->api->addParameter('Descripcion', 'S', $perfil->Descripcion);
        $this->api->addParameter('EsAdmin',     'B', $perfil->EsAdmin);
        $this->api->addParameter('Activo',      'B', $perfil->Activo);
        $this->api->addParameter('Inicio',      'S', $perfil->Inicio);
        $result = $this->api->execute();

        if (!$result->status()) {
            if ($isNew) $this->success('Perfil creado.', ['id' => $result->id()], 201);
            $this->success('Perfil actualizado.', ['id' => $perfil->IdPerfil]);
        } else {
            $this->failure($result);
        }
    }

    public function eliminar(int $IdPerfil): void
    {
        $this->api->command('Framework', 'PerfilAcceso', 'Delete');
        $this->api->addParameter('IdPerfil', 'I', $IdPerfil);
        $result = $this->api->execute();

        if (!$result->status()) {
            $this->ensure($result->affected() > 0, 'Perfil no encontrado.', 404);
            $this->success('Perfil eliminado.');
        } else {
            $this->failure($result);
        }
    }

    /** Guarda qué pantallas y acciones concede el perfil ($Objetos: JSON con los IdObjeto marcados). */
    public function objetos(int $IdPerfil, string $Objetos = '[]'): void
    {
        $pedidos = json_decode($Objetos, true);
        $this->ensure(is_array($pedidos), 'Lista de objetos inválida.');
        $ids = array_values(array_unique(array_filter(array_map('intval', $pedidos), fn(int $id) => $id > 0)));

        $this->api->command('Framework', 'PerfilAcceso', 'SaveGrants');
        $this->api->addParameter('IdPerfil', 'I', $IdPerfil);
        $this->api->addParameter('Objetos',  'S', json_encode($ids));
        $result = $this->api->execute();

        if (!$result->status()) {
            $this->success('Permisos del perfil actualizados. Aplican desde la siguiente petición de cada cuenta.');
        } else {
            $this->failure($result);
        }
    }

    /** Todos los perfiles, con cuántas cuentas y objetos tiene cada uno. */
    private function perfiles(): array
    {
        $this->api->command('Framework', 'PerfilAcceso', 'List');
        $result = $this->api->execute();
        if ($result->status()) $this->failure($result);
        return PerfilAcceso::fromTable($result->table());
    }
}
