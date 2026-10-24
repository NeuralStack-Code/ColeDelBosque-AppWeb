<?php
/**
 * Base de los controllers: UN controller por entidad. La URL es /<controller>/<método> y cada método
 * público es un endpoint (index es la pantalla). El controller es el backend: valida, decide y responde.
 * NO toca la base: le pide todo al business por el server api ($this->api, ver core/ServerApi.php),
 * revisa el status y carga en models la tabla que recibe.
 *
 *     class ClienteController extends Controller {
 *         public function eliminar(int $IdCliente): void {
 *             $this->api->command('Ventas', 'Cliente', 'Delete');          // módulo, entidad, operación
 *             $this->api->addParameter('IdCliente', 'I', $IdCliente);      // nombre, tipo, valor
 *             $result = $this->api->execute();
 *
 *             if (!$result->status()) {                                    // status() TRUE = hubo error
 *                 $this->ensure($result->affected() > 0, 'Cliente no encontrado.', 404);
 *                 $this->success('Cliente eliminado.');
 *             } else {
 *                 $this->failure($result);
 *             }
 *         }
 *     }
 *
 * Lo que se repetiría en cada método vive aquí una sola vez (authorize): la sesión y el verbo HTTP.
 * Los errores inesperados los atrapa el router (log + "Error interno"); nunca llegan al navegador.
 */
abstract class Controller
{
    /** Base de datos que le da el router a este controller: 'principal' o 'ninguna'. */
    public const CONNECTION = 'principal';

    /** Roles de las cuentas (tabla permisos). */
    public const ADMIN = 1, TEACHER = 2, PARENT = 3, DEVELOPER = 4;

    /** Pantalla de inicio de cada rol. */
    private const HOME = [self::ADMIN => '/dashboard/index', self::TEACHER => '/maestro', self::PARENT => '/padre/index', self::DEVELOPER => '/desarrollador'];

    /** Rol que pide TODO el controller (0 = basta la sesión). Lo que sea de otro rol lo revisa su método. */
    protected int $role = 0;

    /** Métodos que cualquiera puede llamar SIN sesión (una página pública, 'entrar'…). */
    protected array $publicActions = [];
    /** Métodos que solo LEEN (aceptan GET). Todo lo demás escribe y exige POST. */
    protected array $readActions = ['index', 'listar'];

    /** Por aquí se le habla al business. */
    protected ServerApi $api;
    protected ?mysqli $db;
    protected string $method;

    public function __construct(?mysqli $connection = null)
    {
        $this->db     = $connection;
        $this->api    = new ServerApi($connection);
        $this->method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
        Command::actor($this->userId());                          // auditoría: quién actúa en esta petición (0 = sin sesión)
    }

    /* ── Guardias ── */

    /**
     * Guardia ÚNICO de todos los métodos: el router lo llama antes de ejecutar cualquiera.
     *   1. Sesión y el rol del controller ($role), salvo en los métodos de $publicActions.
     *      index es la PANTALLA: sin sesión manda a entrar; con otro rol, a la pantalla de inicio de ese rol.
     *   2. POST, salvo en los métodos de $readActions.
     * Lo que pida algo más lo agrega su método o sobrescribe authorize().
     */
    public function authorize(string $action): void
    {
        if (!in_array($action, $this->publicActions, true)) {
            if ($action === 'index') $this->requireScreen($this->role);
            else $this->requireRole($this->role);
        }
        if (!in_array($action, $this->readActions, true)) $this->requirePost();
    }

    /** Rol de quien está en sesión (0 si no hay). */
    protected function roleId(): int
    {
        return $this->user() === null ? 0 : (int) ($_SESSION['permiso'] ?? 0);
    }

    /** Id del usuario en sesión (0 si no hay). */
    protected function userId(): int
    {
        return $this->user() === null ? 0 : (int) ($_SESSION['id'] ?? 0);
    }

    /** Pantalla de inicio que le toca a un rol (la del sitio si no tiene). */
    protected static function homeOf(int $role): string
    {
        return self::HOME[$role] ?? '/';
    }

    /** Guardia de un método: sesión y, si se indica, ese rol. Responde 401 / 403 en JSON. */
    protected function requireRole(int $role = 0): void
    {
        $this->requireSession();
        if ($role !== 0 && $this->roleId() !== $role) response(403, false, 'No tienes permiso para realizar esta acción.');
    }

    /** Guardia de una PANTALLA: sin sesión manda a entrar; con otro rol, a la pantalla de inicio de ese rol. */
    protected function requireScreen(int $role = 0): void
    {
        if ($this->user() === null) $this->redirect('/auth/entrar');
        if ($role !== 0 && $this->roleId() !== $role) $this->redirect(self::homeOf($this->roleId()));
    }

    /** Usuario en sesión (el que guardó tu login en $_SESSION['usuario']), o null. */
    protected function user(): mixed
    {
        return $_SESSION['usuario'] ?? null;
    }

    protected function requireSession(): void
    {
        if ($this->user() === null) response(401, false, 'No autenticado.');
    }

    protected function requirePost(): void
    {
        if ($this->method !== 'POST') response(405, false, 'Método no permitido.');
    }

    protected function isAdmin(): bool
    {
        return $this->roleId() === self::ADMIN;
    }

    /** Corta con 422 (o el estatus que se indique) si la condición no se cumple. */
    protected function ensure(bool $condition, string $message, int $http = 422): void
    {
        if (!$condition) response($http, false, $message);
    }

    /* ── Respuestas ── */

    /** Respuesta de éxito (JSON): mensaje y, si hace falta, datos. */
    protected function success(string $message, array $data = [], int $http = 200): void
    {
        response($http, true, $message, $data);
    }

    /** Respuesta de error con lo que contestó el business: su mensaje y el estatus HTTP que le corresponde. */
    protected function failure(Result $result): void
    {
        response($result->http(), false, $result->message());
    }

    /** Manda al navegador a otra pantalla del sitio. */
    protected function redirect(string $path): never
    {
        header('Location: ' . BASE_URL . $path, true, 302);
        exit;
    }

    /**
     * Pinta una vista (webService/views/<archivo>.php) con sus datos ya preparados.
     * La vista solo imprime: recibe cada dato como variable y usa e() para escapar.
     */
    protected function view(string $file, array $data = []): void
    {
        header('Content-Type: text/html; charset=utf-8');
        $base = BASE_URL;
        extract($data, EXTR_SKIP);
        require dirname(__DIR__, 2) . '/webService/views/' . $file . '.php';
    }
}

/**
 * Rutas por convención:  /<controller>/<método>   →   <Controller>Controller::<método>()
 *     /cliente/index      → ClienteController::index()       (la pantalla; /cliente a secas también llega aquí)
 *     /cliente/guardar    → ClienteController::guardar()
 * Regresa la clase del controller de ese primer tramo de la URL, o null si no hay. No hay mapa de rutas.
 */
function routeController(string $segment): ?string
{
    static $classes = null;
    if ($classes === null) {
        $classes = [];
        foreach (glob(dirname(__DIR__, 2) . '/webService/controllers/*Controller.php') as $file) {
            $class = basename($file, '.php');
            $classes[strtolower(substr($class, 0, -10))] = $class;      // 'cliente' => 'ClienteController'
        }
    }
    $class = $classes[strtolower($segment)] ?? null;
    return $class !== null && is_subclass_of($class, Controller::class) ? $class : null;
}

/**
 * Lo que el navegador le manda a un método (formulario, JSON o URL) llega como sus PARÁMETROS, por nombre:
 *
 *     public function eliminar(int $IdCliente): void            // un dato: se convierte a su tipo
 *     public function guardar(Cliente $cliente): void           // un model: se carga con los campos del formulario
 *
 * - Con tipo simple (int, float, bool, string): se toma el dato del mismo nombre y se convierte.
 *   Si no vino, vale su valor por defecto; sin valor por defecto, su vacío (0, '', false) o null si lo admite.
 * - Con tipo de un model: se arma el model con los campos que coincidan con sus propiedades.
 * - Sin tipo: llega el dato tal cual, o null si no vino.
 * - Un id obligatorio (int $IdAlgo, sin valor por defecto) que no venga o no sea mayor a 0 se rechaza aquí
 *   mismo con "ID inválido.": el método ya no tiene que revisarlo.
 */
function actionArguments(object $controller, string $action): array
{
    $data = $_POST + $_GET;
    if (stripos($_SERVER['CONTENT_TYPE'] ?? '', 'application/json') !== false) {
        $json = json_decode((string) file_get_contents('php://input'), true);
        if (is_array($json)) $data = $json + $data;
    }

    $arguments = [];
    foreach ((new ReflectionMethod($controller, $action))->getParameters() as $parameter) {
        $type  = $parameter->getType();
        $value = $data[$parameter->getName()] ?? null;
        if (is_string($value)) $value = trim($value);

        if ($type instanceof ReflectionNamedType && !$type->isBuiltin() && is_subclass_of($type->getName(), Model::class)) {
            $arguments[] = $type->getName()::fromForm($data);
        } elseif ($value === null || ($value === '' && $type?->getName() !== 'string')) {
            $arguments[] = match (true) {
                $parameter->isDefaultValueAvailable() => $parameter->getDefaultValue(),
                $type === null || $type->allowsNull()  => null,
                default => ['int' => 0, 'float' => 0.0, 'bool' => false, 'string' => '', 'array' => []][$type->getName()] ?? null,
            };
        } else {
            $arguments[] = match ($type?->getName()) {
                'int'    => (int) $value,
                'float'  => (float) $value,
                'bool'   => !in_array($value, ['0', 'false', 'no', 0, false], true),
                'string' => is_scalar($value) ? (string) $value : '',
                'array'  => (array) $value,
                default  => $value,
            };
        }
        $isRequiredId = str_starts_with($parameter->getName(), 'Id') && !$parameter->isDefaultValueAvailable() && $type?->getName() === 'int';
        if ($isRequiredId && end($arguments) <= 0) response(422, false, 'ID inválido.');
    }
    return $arguments;
}

/* ── Ayudas para imprimir en las vistas ── */

/** Escapa un valor para imprimirlo en HTML. */
function e(mixed $value): string
{
    return htmlspecialchars((string) ($value ?? ''), ENT_QUOTES, 'UTF-8');
}

/** Un model (o lista) como JSON listo para un atributo HTML: data-record="<?= record($cliente) ?>". */
function record(mixed $value): string
{
    return e(json_encode($value, JSON_UNESCAPED_UNICODE));
}

/** Fecha de la base (2026-10-05 o 2026-10-05 13:20:00) → 05/10/2026. Vacía → '—'. */
function formatDate(?string $value): string
{
    $time = $value ? strtotime($value) : false;
    return $time ? date('d/m/Y', $time) : '—';
}

/** 1234.5 → $1,234.50 */
function money(float|int|string|null $amount): string
{
    return '$' . number_format((float) $amount, 2);
}

/** Recorta un texto a $length caracteres y le pone "…" si era más largo. */
function truncate(string $text, int $length): string
{
    return preg_match('/^.{' . $length . '}(?=.)/us', $text, $m) ? $m[0] . '…' : $text;
}
