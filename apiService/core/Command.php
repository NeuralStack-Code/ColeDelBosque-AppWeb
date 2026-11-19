<?php
/**
 * Ejecuta un stored procedure con parámetros POR NOMBRE y deja el resultado en un estado uniforme.
 * Es la conexión a la base que usan las entidades (ver core/Entity.php):
 *
 *     $this->db->command('sp_cliente_eliminar');
 *     $this->db->addParameter('IdCliente', $this->IdCliente);
 *     $rows = $this->db->execute();
 *     if ($this->db->error()) … $this->db->code() / $this->db->message()
 *
 * MySQL/MariaDB solo aceptan parámetros por posición en un CALL. Para que el nombre valga de verdad,
 * Command lee la firma del procedure (sp_sistema_parametros) y acomoda los valores en su orden; si falta
 * o sobra un parámetro truena de inmediato, en vez de guardar un valor en la columna equivocada.
 * El nombre es el del parámetro sin la "p" inicial: pIdCliente → 'IdCliente'.
 *
 * Las firmas no se consultan una por una (1.5.0): Command pregunta UNA vez por petición la versión de los procedures de la
 * base (sp_sistema_version) y, mientras no cambie, usa las firmas que guardó en caché (APCu si existe; si no, un archivo
 * en Command::$cacheDir). Cualquier CREATE, DROP o ALTER de un procedure cambia la versión y la caché se rehace sola.
 * Una base que todavía no tiene sp_sistema_version (sp_sistema.sql anterior a la 1.5.0) sigue como antes.
 *
 * Nada es automático, salvo los dos campos de auditoría: si el procedure declara pIdUsuarioRegistra o
 * pIdUsuarioCambia y el business no los pasó, Command los llena con quien actúa en la petición
 * (Command::actor, lo fija el Controller base de cada proyecto; 0 = sin sesión, nunca administrador). Si el business sí los pasó,
 * gana lo que pasó (un proceso que actúa a nombre de otro).
 */
final class Command
{
    /** Códigos de negocio (SIGNAL 'codigo:mensaje') → estatus HTTP. Lo que no esté aquí es 500. */
    private const HTTP = ['dup' => 409, 'fk' => 409, 'en_uso' => 409, 'notfound' => 404, 'invalido' => 422, 'rol' => 422,
                          'permiso' => 403];

    /** Los únicos parámetros que Command llena solo, con el actor. */
    private const AUDIT = ['IdUsuarioRegistra', 'IdUsuarioCambia'];

    /**
     * Firmas por base, cargadas en esta petición: base → ['version' => …, 'firmas' => [procedure => ['params' => […],
     * 'abre' => bool]]]. 'legado' => true si la base aún no tiene sp_sistema_version (se consulta procedure por procedure).
     */
    private static array $catalogs = [];

    /** Carpeta de la caché de firmas en archivo (cuando no hay APCu). null = apiService/storage/cache del proyecto. */
    public static ?string $cacheDir = null;

    /** Dentro de ServerApi::transaction(): los procedures que abren su propia transacción no se dejan ejecutar. */
    public static bool $inTransaction = false;

    /** Consultas al sistema (versión, firmas) hechas en esta petición: para diagnóstico y pruebas. */
    public static int $systemQueries = 0;

    /** Quién actúa en la petición (el usuario en sesión); 0 = sin sesión. */
    private static int $actor = 0;

    /**
     * Se llama cuando un procedure falla sin código de negocio (el 'interno' que llega al usuario como 500), con el
     * nombre del procedure y la excepción. El proyecto cuelga aquí su bitácora de errores; sin gancho solo queda el log.
     */
    public static ?Closure $onInternalError = null;

    private mysqli $db;
    private string $procedure  = '';
    private array  $parameters = [];
    private array  $rows       = [];
    private bool   $error      = false;
    private string $code       = '';
    private string $message    = '';

    public function __construct(mysqli $db) { $this->db = $db; }

    /**
     * Fija quién actúa en la petición: lo llama el Controller base de cada proyecto en su constructor, con el
     * usuario de SU sesión (así el framework no conoce la sesión de nadie). Sin llamarlo, el actor es 0 (sin sesión, sin permisos de administrador).
     */
    public static function actor(int $idUsuario): void
    {
        self::$actor = $idUsuario;
    }

    /** Inicia un comando nuevo (limpia parámetros y estado del anterior). */
    public function command(string $procedure): static
    {
        $this->procedure  = $procedure;
        $this->parameters = $this->rows = [];
        $this->error      = false;
        $this->code       = $this->message = '';
        return $this;
    }

    public function addParameter(string $name, mixed $value): static
    {
        $this->parameters[$name] = $value;
        return $this;
    }

    /** Nombres de los parámetros del procedure, en su orden. */
    public function signature(): array
    {
        return $this->entry()['params'];
    }

    /** La firma del procedure ('params' sin la p inicial, 'abre' = abre su propia transacción). */
    private function entry(): array
    {
        $key = self::baseKey($this->db);
        $catalog = self::$catalogs[$key] ??= self::loadCatalog($this->db);
        if (!empty($catalog['legado'])) {
            if (!isset($catalog['firmas'][$this->procedure])) {
                self::$systemQueries++;
                $params = array_column(sp($this->db, 'sp_sistema_parametros', [$this->procedure]), 'Nombre');
                self::$catalogs[$key]['firmas'][$this->procedure] = ['params' => self::strip($params), 'abre' => null];
            }
            return self::$catalogs[$key]['firmas'][$this->procedure];
        }
        // Un procedure que no está: se vuelve a leer la base una vez (pudo crearse después de leer la versión).
        if (!isset($catalog['firmas'][$this->procedure]) && empty($catalog['releido'])) {
            self::$catalogs[$key] = self::loadCatalog($this->db, true) + ['releido' => true];
        }
        return self::$catalogs[$key]['firmas'][$this->procedure] ?? ['params' => [], 'abre' => false];
    }

    /** Las firmas de la base de esta conexión: de la caché si la versión no cambió, si no de sp_sistema_firmas. */
    private static function loadCatalog(mysqli $db, bool $refresh = false): array
    {
        try {
            self::$systemQueries++;
            $row = spFila($db, 'sp_sistema_version') ?? [];
        } catch (mysqli_sql_exception $e) {
            if ($e->getCode() === 1305) return ['legado' => true, 'firmas' => []];   // la base aún no tiene la 1.5.0
            throw $e;
        }
        $version = (string) ($row['Version'] ?? '');
        $cacheKey = 'ns_firmas_' . md5((string) ($row['Base'] ?? '') . '|' . $db->host_info);
        if (!$refresh) {
            $cached = self::cacheGet($cacheKey);
            if (is_array($cached) && ($cached['version'] ?? null) === $version) return $cached;
        }
        self::$systemQueries++;
        $firmas = [];
        foreach (sp($db, 'sp_sistema_firmas') as $r) {
            $proc = (string) $r['Procedimiento'];
            $firmas[$proc] ??= ['params' => [], 'abre' => (int) $r['AbreTransaccion'] === 1];
            if ($r['Nombre'] !== null) $firmas[$proc]['params'][] = preg_replace('/^p(?=[A-Z])/', '', (string) $r['Nombre']);
        }
        $catalog = ['version' => $version, 'firmas' => $firmas];
        self::cachePut($cacheKey, $catalog);
        return $catalog;
    }

    private static function strip(array $names): array
    {
        return array_map(fn($n) => preg_replace('/^p(?=[A-Z])/', '', (string) $n), $names);
    }

    /** Una conexión = una base (y un servidor): la caché de firmas es por base. */
    private static function baseKey(mysqli $db): string
    {
        return spl_object_id($db) . '';
    }

    private static function cacheGet(string $key): mixed
    {
        if (function_exists('apcu_fetch') && filter_var(ini_get('apc.enabled'), FILTER_VALIDATE_BOOL)) {
            $value = apcu_fetch($key, $ok);
            if ($ok) return $value;
        }
        $file = self::cacheFile($key);
        return $file !== null && is_file($file) ? json_decode((string) @file_get_contents($file), true) : null;
    }

    private static function cachePut(string $key, array $value): void
    {
        if (function_exists('apcu_store') && filter_var(ini_get('apc.enabled'), FILTER_VALIDATE_BOOL)) apcu_store($key, $value);
        $file = self::cacheFile($key);
        if ($file === null) return;
        if (!is_dir(dirname($file))) @mkdir(dirname($file), 0755, true);
        $tmp = $file . '.' . bin2hex(random_bytes(4));
        if (@file_put_contents($tmp, json_encode($value), LOCK_EX) !== false) @rename($tmp, $file);
    }

    /** apiService/storage/cache/<clave>.json (o el storage/ de la raíz en un proyecto anterior a la 1.4.0). */
    private static function cacheFile(string $key): ?string
    {
        $dir = self::$cacheDir;
        if ($dir === null) {
            $root = dirname(__DIR__, 2);
            $dir  = is_dir($root . '/apiService/storage') || !is_dir($root . '/storage') ? $root . '/apiService/storage/cache' : $root . '/storage/cache';
        }
        return rtrim($dir, '/\\') . '/' . $key . '.json';
    }

    /** Borra la caché de firmas de este proceso (las pruebas; en producción se rehace sola al cambiar la versión). */
    public static function forgetSignatures(): void
    {
        self::$catalogs = [];
    }

    /** Ejecuta y regresa las filas. Un error NO lanza excepción: queda en error()/code()/message(). */
    public function execute(): array
    {
        $entry     = $this->entry();
        $signature = $entry['params'];
        // Dentro de una transacción de varios comandos, un procedure que abre la suya la confirmaría a medias
        // (en MySQL/MariaDB un START TRANSACTION adentro confirma la de afuera). Se detiene ANTES de ejecutarlo.
        if (self::$inTransaction && $entry['abre'] !== false) {
            throw new LogicException($entry['abre'] === null
                ? "Para usar transaction() la base necesita sp_sistema.sql 1.5.0 ({$this->procedure} no se puede revisar)."
                : "{$this->procedure} abre su propia transacción: dentro de transaction() confirmaría la de afuera. "
                  . 'Usa la convención: IF @@in_transaction = 0 THEN START TRANSACTION; SET vPropia = 1; END IF; '
                  . 'y COMMIT / ROLLBACK solo si vPropia = 1.');
        }
        // Auditoría: lo que el procedure declara y el business no pasó lo llena el actor.
        foreach (self::AUDIT as $name) {
            if (in_array($name, $signature, true) && !array_key_exists($name, $this->parameters)) {
                $this->parameters[$name] = self::$actor;
            }
        }
        // Un nombre mal escrito o un parámetro olvidado es un error de programación: que se note al instante.
        if ($extra = array_diff(array_keys($this->parameters), $signature)) {
            throw new LogicException("{$this->procedure} no tiene el parámetro: " . implode(', ', $extra));
        }
        if ($missing = array_diff($signature, array_keys($this->parameters))) {
            throw new LogicException("A {$this->procedure} le falta el parámetro: " . implode(', ', $missing));
        }

        try {
            $this->rows = sp($this->db, $this->procedure, array_map(fn(string $name) => $this->parameters[$name], $signature));
        } catch (mysqli_sql_exception $e) {
            if (spCodigo($e) !== null) {
                $this->fail(spCodigo($e), spMensaje($e));                    // regla de negocio que avisó el procedure
            } else {
                error_log("{$this->procedure}: " . $e->getMessage());        // el detalle va al log, nunca al usuario
                if (self::$onInternalError !== null) (self::$onInternalError)($this->procedure, $e);
                $this->fail('interno', 'Error interno del servidor.');
            }
        }
        return $this->rows;
    }

    /** Marca el comando como fallido. */
    public function fail(string $code, string $message): void
    {
        $this->error   = true;
        $this->code    = $code;
        $this->message = $message;
        $this->rows    = [];
    }

    public function error(): bool     { return $this->error; }
    public function code(): string    { return $this->code; }
    public function message(): string { return $this->message; }
    public function http(): int       { return $this->error ? (self::HTTP[$this->code] ?? 500) : 200; }
}
