<?php
/**
 * Catálogo de permisos: UN solo lugar que dice qué existe, quién lo puede usar y qué archivo lo atiende.
 *
 * El router (apiService/index.php) le pregunta por cada petición /<componente>/<método> con resolve():
 * una sola consulta (sp_objeto_autorizar) dice si el método está registrado, si exige POST, si algún perfil
 * de la cuenta lo concede y qué controller cargar (Componentes: Clase, Archivo, Modulo, Conexion).
 * Lo que no está registrado responde 404 sin tocar el disco. No hay roles: los permisos los dan los perfiles
 * (Perfiles, PerfilObjeto, CuentaPerfiles N:M). Un perfil EsAdmin concede todo.
 *
 * Acceso de cada método (columna Acceso):
 *   publico   cualquiera            sesion   cualquier cuenta con sesión            perfil   algún perfil lo concede
 *
 * Respuestas:
 *   404 el método no está en el catálogo (o está inactivo)
 *   405 exige POST (columna SoloPost) y llegó por otro método
 *   401 pide sesión y no hay              (una vista redirige a /auth/entrar)
 *   403 no tiene el permiso                (una vista redirige a la pantalla de inicio de su perfil)
 */
final class Catalog
{
    /** Clave del componente de un controller: CicloController → 'ciclo'. */
    public static function component(Controller $controller): string
    {
        return strtolower(substr($controller::class, 0, -strlen('Controller')));
    }

    /** Cuenta en sesión (0 si no hay). Los perfiles se asignan a la cuenta (la que entra con su matrícula). */
    public static function account(): int
    {
        return empty($_SESSION['usuario']) ? 0 : (int) ($_SESSION['id_cuenta'] ?? 0);
    }

    /**
     * Lo que contesta el catálogo para esta petición, sin responder nada todavía.
     * @return array{code:int, vista:bool, acceso:string, ruta:string, cuenta:int, http:string,
     *               clase:string, archivo:string, modulo:string, conexion:string}
     */
    public static function resolve(mysqli $db, string $component, string $method): array
    {
        $account = self::account();
        $http    = $_SERVER['REQUEST_METHOD'] ?? 'GET';

        $api = new ServerApi($db);
        $api->command('Framework', 'Objeto', 'Authorize');
        $api->addParameter('IdCuenta',   'I', $account);
        $api->addParameter('Componente', 'S', $component);
        $api->addParameter('Metodo',     'S', $method);
        $result = $api->execute();
        if ($result->status()) throw new RuntimeException('Catálogo: ' . $result->message());
        $row = $result->row() ?? [];

        $acceso = (string) ($row['Acceso'] ?? '');
        $code = match (true) {
            (int) ($row['Existe'] ?? 0) === 0                    => 404,
            (int) $row['SoloPost'] === 1 && $http !== 'POST'     => 405,
            $acceso !== 'publico' && $account === 0              => 401,
            (int) ($row['Permitido'] ?? 0) !== 1                 => 403,
            default                                              => 200,
        };
        return [
            'code' => $code, 'vista' => (int) ($row['Tipo'] ?? 0) === 2, 'acceso' => $acceso,
            'ruta' => "$component/$method", 'cuenta' => $account, 'http' => $http,
            'clase'    => (string) ($row['Clase']    ?? ''),
            'archivo'  => (string) ($row['Archivo']  ?? ''),
            'modulo'   => (string) ($row['Modulo']   ?? ''),
            'conexion' => (string) ($row['Conexion'] ?? 'principal'),
        ];
    }

    /** Aplica la decisión: si no es 200, responde (vista → redirige o pinta 404; método → JSON) y termina. */
    public static function enforce(array $d): void
    {
        if ($d['code'] === 200) return;

        $pideHtml = $d['vista'] || str_contains($_SERVER['HTTP_ACCEPT'] ?? '', 'text/html');
        if ($pideHtml && $d['code'] === 401) self::redirect('/auth/entrar');
        if ($pideHtml && $d['code'] === 403) self::redirect(self::home());
        if ($d['code'] === 404) notFound('Acción no encontrada.');
        response($d['code'], false, match ($d['code']) {
            401     => 'No autenticado.',
            403     => 'No tienes permiso para realizar esta acción.',
            405     => 'Método no permitido.',
            default => 'Acción no encontrada.',
        });
    }

    /**
     * Pantalla de inicio de quien está en sesión: la del perfil que se le asignó primero en importancia
     * (Perfiles.Inicio, la guarda AuthController al entrar). Sin sesión, la de entrar; sin perfil, la portada.
     */
    public static function home(): string
    {
        if (self::account() === 0) return '/auth/entrar';
        $inicio = (string) ($_SESSION['inicio'] ?? '');
        return $inicio !== '' ? $inicio : '/';
    }

    /**
     * Revisión del catálogo (sp_catalogo_revision): cambia con cada cambio de permisos. Una consulta por petición
     * como máximo; con ella se sabe si el menú y los botones guardados en la sesión siguen vigentes.
     */
    public static function revision(mysqli $db): int
    {
        static $revision = null;
        if ($revision === null) {
            $api = new ServerApi($db);
            $api->command('Framework', 'Catalogo', 'Revision');
            $result = $api->execute();
            $revision = $result->status() ? -1 : (int) ($result->row()['Revision'] ?? 0);
        }
        return $revision;
    }

    /** Métodos concedidos de un componente (para que la vista oculte botones). En caché por revisión. */
    public static function granted(mysqli $db, string $component): array
    {
        return self::cached('concedidos:' . $component, $db, function () use ($db, $component): ?array {
            $api = new ServerApi($db);
            $api->command('Framework', 'Objeto', 'Granted');
            $api->addParameter('IdCuenta',   'I', self::account());
            $api->addParameter('Componente', 'S', $component);
            $result = $api->execute();
            return $result->status() ? null : array_column($result->table(), 'Metodo');
        });
    }

    /** Grupos y vistas que la cuenta en sesión puede abrir (filas de sp_objeto_menu). En caché por revisión. */
    public static function menu(mysqli $db): array
    {
        return self::cached('menu', $db, function () use ($db): ?array {
            $api = new ServerApi($db);
            $api->command('Framework', 'Objeto', 'Menu');
            $api->addParameter('IdCuenta', 'I', self::account());
            $result = $api->execute();
            return $result->status() ? null : $result->table();
        });
    }

    /**
     * Guarda en la sesión lo que regresa $fetch, ligado a la cuenta y a la revisión del catálogo: en cuanto alguien
     * cambia un perfil o un permiso, la revisión cambia y se vuelve a pedir. Un error no se guarda.
     */
    private static function cached(string $key, mysqli $db, callable $fetch): array
    {
        $account  = self::account();
        $revision = self::revision($db);
        $saved    = $_SESSION['cb_catalogo'][$key] ?? null;
        if ($revision >= 0 && is_array($saved) && $saved['cuenta'] === $account && $saved['revision'] === $revision) {
            return $saved['datos'];
        }
        $datos = $fetch();
        if ($datos === null) return [];
        if ($revision >= 0 && session_status() === PHP_SESSION_ACTIVE) {
            $_SESSION['cb_catalogo'][$key] = ['cuenta' => $account, 'revision' => $revision, 'datos' => $datos];
        }
        return $datos;
    }

    private static function redirect(string $path): never
    {
        header('Location: ' . BASE_URL . $path, true, 302);
        exit;
    }
}
