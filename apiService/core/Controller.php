<?php
/**
 * Base de los controllers: UN controller por entidad. La URL es /<controller>/<método> y cada método
 * público es un endpoint (index es la pantalla). El controller es el backend: valida, decide y responde.
 * NO toca la base: le pide todo al business por el server api ($this->api, ver core/ServerApi.php),
 * revisa el status y carga en models la tabla que recibe.
 *
 *     class CicloController extends Controller {
 *         public const MODULE = 'escolar';
 *         public function eliminar(int $IdCiclo): void {
 *             $this->api->command('Escolar', 'Ciclo', 'Delete');           // módulo, entidad, operación
 *             $this->api->addParameter('IdCiclo', 'I', $IdCiclo);          // nombre, tipo, valor
 *             $result = $this->api->execute();
 *
 *             if (!$result->status()) {                                    // status() TRUE = hubo error
 *                 $this->ensure($result->affected() > 0, 'Ciclo no encontrado.', 404);
 *                 $this->success('Ciclo eliminado.');
 *             } else {
 *                 $this->failure($result);
 *             }
 *         }
 *     }
 *
 * Permisos: NO se revisan aquí. El router le pregunta al catálogo (core/Catalog.php) antes de ejecutar cualquier
 * método: si está registrado, si exige POST y si algún perfil de la cuenta lo concede. Ya no hay roles.
 * Los errores inesperados los atrapa el router (log + "Error interno"); nunca llegan al navegador.
 */
abstract class Controller
{
    /** Base de datos que le da el router a este controller: 'principal' o 'ninguna'. */
    public const CONNECTION = 'principal';

    /**
     * Su módulo (la sección del menú: escolar, pagos, padre, sistema; sitio para lo público). Es su paquete: sus
     * business y models viven en webService/business/<módulo> y webService/models/<módulo>. Un controller pertenece
     * a UN módulo; lo que comparten varios va en webService/libs. El módulo framework (el catálogo) siempre está activo.
     */
    public const MODULE = '';

    /** Por aquí se le habla al business. */
    protected ServerApi $api;
    protected ?mysqli $db;
    protected string $method;

    public function __construct(?mysqli $connection = null)
    {
        $this->db     = $connection;
        $this->api    = new ServerApi($connection);
        $this->method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
        Command::actor($this->accountId());                       // auditoría: la CUENTA que actúa (los perfiles son de la cuenta; 0 = sin sesión)
    }

    /**
     * Guardia de un controller que el catálogo no decide. En el colegio todos están en el catálogo: el router nunca
     * llega aquí. Se queda como red de seguridad (pide sesión).
     */
    public function authorize(string $action): void
    {
        $this->requireSession();
    }

    /* ── Sesión ── */

    /** Nombre de quien está en sesión (lo guarda AuthController), o null. */
    protected function user(): ?string
    {
        return $_SESSION['usuario'] ?? null;
    }

    /** Id de la persona en sesión (tabla usuario; 0 si no hay). Es el actor de la auditoría. */
    protected function userId(): int
    {
        return $this->user() === null ? 0 : (int) ($_SESSION['id'] ?? 0);
    }

    /** Id de la cuenta en sesión (tabla cuenta; 0 si no hay). Los perfiles se asignan a la cuenta. */
    protected function accountId(): int
    {
        return Catalog::account();
    }

    /** Pantalla de inicio de quien está en sesión (la de su perfil principal). */
    protected function home(): string
    {
        return Catalog::home();
    }

    protected function requireSession(): void
    {
        if ($this->user() === null) response(401, false, 'No autenticado.');
    }

    protected function requirePost(): void
    {
        if ($this->method !== 'POST') response(405, false, 'Método no permitido.');
    }

    /* ── Catálogo de permisos (core/Catalog.php) ── */

    /**
     * ¿Algún perfil de la cuenta le concede este método de este controller? Para decidir qué ofrece la pantalla
     * (botones, datos): la guardia del router ya decidió si puede entrar; esto es para lo que muestra adentro.
     */
    protected function granted(string $method): bool
    {
        static $cache = [];
        $component = Catalog::component($this);
        $cache[$component] ??= $this->db !== null ? Catalog::granted($this->db, $component) : [];
        return in_array($method, $cache[$component], true);
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
     * La vista solo imprime: recibe cada dato como variable y usa e() para escapar. Con sesión, los accesos de los
     * paneles salen del catálogo: la vista pregunta can('ciclo/index') y solo muestra lo que el perfil concede.
     */
    protected function view(string $file, array $data = []): void
    {
        header('Content-Type: text/html; charset=utf-8');
        if ($this->db !== null && $this->accountId() !== 0) {
            $GLOBALS['__cbMenu'] = array_column(Catalog::menu($this->db), 'Ruta');
        }
        $base = BASE_URL;
        extract($data, EXTR_SKIP);
        require dirname(__DIR__, 2) . '/webService/views/' . $file . '.php';
    }
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

/** ¿El perfil de quien está en sesión puede abrir esa pantalla? can('ciclo/index'). Lo usan los paneles. */
function can(string $route): bool
{
    return in_array('/' . trim($route, '/'), $GLOBALS['__cbMenu'] ?? [], true);
}
