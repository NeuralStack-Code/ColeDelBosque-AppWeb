<?php
/**
 * Lo que comparten las suites de pruebas: el entorno de pruebas, el servidor de PHP, un cliente HTTP con cookies,
 * cuentas de prueba y las comprobaciones.
 *
 * EL .env DEL COLEGIO APUNTA A PRODUCCIÓN: las pruebas nunca lo usan. Leen su propio archivo de entorno:
 *   - la variable PRUEBAS_ENV (ruta a un archivo .env), o
 *   - .env.pruebas en la raíz del proyecto (está en .gitignore; puede ser una copia del .env.local del entorno local).
 * Y se niegan a correr si la base no es local (127.0.0.1/localhost) o su nombre no termina en _local.
 */
if (PHP_SAPI !== 'cli') exit("Solo desde la terminal.\n");

const RAIZ = __DIR__ . '/../..';

/** Carga el entorno de pruebas en $_ENV (encima de lo que haya) y devuelve la ruta del archivo. */
function entornoPruebas(bool $necesitaBase = true): string
{
    $archivo = getenv('PRUEBAS_ENV') ?: realpath(RAIZ) . '/.env.pruebas';
    putenv('PRUEBAS_ENV=' . $archivo);                         // el servidor de pruebas lo hereda
    if (!is_file($archivo)) {
        if (!$necesitaBase) return '';
        fwrite(STDERR, "Falta el entorno de pruebas: crea .env.pruebas en la raíz (copia de entorno-local/.env.local de "
            . "DeployBacklog) o pon PRUEBAS_ENV=<ruta>. El .env del colegio es el de producción: nunca se usa aquí.\n");
        exit(2);
    }
    foreach (file($archivo, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $linea) {
        $linea = trim($linea);
        if ($linea === '' || $linea[0] === '#' || !str_contains($linea, '=')) continue;
        [$k, $v] = array_map('trim', explode('=', $linea, 2));
        $_ENV[$k] = trim($v, "\"'");
    }
    if ($necesitaBase && (!in_array($_ENV['DB_HOST'] ?? '', ['127.0.0.1', 'localhost', '::1'], true)
                          || !str_ends_with($_ENV['DB_NAME'] ?? '', '_local'))) {
        fwrite(STDERR, "Las pruebas escriben en la base: DB_HOST debe ser local y DB_NAME terminar en _local (es '"
            . ($_ENV['DB_NAME'] ?? '') . "').\n");
        exit(2);
    }
    return $archivo;
}

function base(): mysqli
{
    static $db = null;
    if ($db === null) {
        mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
        $db = new mysqli($_ENV['DB_HOST'], $_ENV['DB_USER'] ?? 'root', $_ENV['DB_PASSWORD'] ?? '', $_ENV['DB_NAME'],
                         (int) ($_ENV['DB_PORT'] ?? 3306));
        $db->set_charset('utf8mb4');
    }
    return $db;
}

/** Filas de una consulta (con parámetros opcionales). */
function filas(string $sql, string $tipos = '', array $valores = []): array
{
    $st = base()->prepare($sql);
    if ($tipos !== '') $st->bind_param($tipos, ...$valores);
    $st->execute();
    $r = $st->get_result();
    $out = $r ? $r->fetch_all(MYSQLI_ASSOC) : [];
    while (base()->more_results() && base()->next_result()) { /* resultados extra de un CALL */ }
    return $out;
}

// ─────────────────────────── Servidor ───────────────────────────
/** Levanta el sitio con el router de pruebas (que pisa el .env de producción con el de pruebas). */
function levantar(int $puerto, string $archivoEnv): string
{
    $log = sys_get_temp_dir() . '/colegio_pruebas_server.log';
    $env = array_merge(getenv(), ['PRUEBAS_ENV' => $archivoEnv]);
    $p = proc_open([PHP_BINARY, '-S', "127.0.0.1:$puerto", '-t', realpath(RAIZ), __DIR__ . '/router.php'],
                   [0 => ['pipe', 'r'], 1 => ['file', $log, 'a'], 2 => ['file', $log, 'a']], $pipes, realpath(RAIZ), $env);
    register_shutdown_function(function () use ($p) {
        $st = proc_get_status($p);
        if (!$st['running']) return;
        PHP_OS_FAMILY === 'Windows' ? exec('taskkill /F /T /PID ' . $st['pid'] . ' >NUL 2>&1') : proc_terminate($p);
    });
    for ($i = 0; $i < 50 && !@fsockopen('127.0.0.1', $puerto); $i++) usleep(100_000);
    return "http://127.0.0.1:$puerto";
}

// ─────────────────────────── Cliente HTTP (con cookies) ───────────────────────────
final class Cliente
{
    private array $cookies = [];
    public function __construct(private string $base) {}

    public function pedir(string $metodo, string $ruta, array $datos = [], bool $html = false): array
    {
        // Mismo origen: la defensa CSRF corre antes que el catálogo.
        $headers = ['Accept: ' . ($html ? 'text/html' : 'application/json'), 'Origin: ' . $this->base, 'Referer: ' . $this->base . '/'];
        if ($this->cookies) $headers[] = 'Cookie: ' . implode('; ', array_map(fn($k, $v) => "$k=$v", array_keys($this->cookies), $this->cookies));
        $cuerpo = '';
        if ($metodo === 'POST') { $cuerpo = http_build_query($datos); $headers[] = 'Content-Type: application/x-www-form-urlencoded'; }
        $ctx = stream_context_create(['http' => ['method' => $metodo, 'header' => implode("\r\n", $headers), 'content' => $cuerpo,
                                                 'follow_location' => 0, 'ignore_errors' => true, 'timeout' => 30]]);
        $body = (string) @file_get_contents($this->base . $ruta, false, $ctx);
        $resp = function_exists('http_get_last_response_headers') ? (http_get_last_response_headers() ?? []) : ($http_response_header ?? []);
        $status = 0; $location = '';
        foreach ($resp as $h) {
            if (preg_match('#^HTTP/\S+\s+(\d{3})#', $h, $m)) $status = (int) $m[1];
            if (stripos($h, 'Location:') === 0) $location = trim(substr($h, 9));
            if (preg_match('/^Set-Cookie:\s*([^=]+)=([^;]*)/i', $h, $m)) $this->cookies[$m[1]] = $m[2];
        }
        return ['status' => $status, 'location' => (string) parse_url($location, PHP_URL_PATH), 'body' => $body,
                'json' => json_decode($body, true)];
    }
    public function get(string $r, bool $html = true): array  { return $this->pedir('GET', $r, [], $html); }
    public function post(string $r, array $d = []): array      { return $this->pedir('POST', $r, $d); }

    /** Inicia sesión como lo hace la gente: POST /auth/entrar con la matrícula. */
    public static function entrar(string $base, string $matricula): self
    {
        $c = new self($base);
        $c->get('/auth/entrar');
        $r = $c->post('/auth/entrar', ['Matricula' => $matricula]);
        if ($r['status'] !== 200 || !($r['json']['success'] ?? false)) {
            throw new RuntimeException("No pudo entrar con $matricula: {$r['status']} " . substr($r['body'], 0, 200));
        }
        return $c;
    }
}

// ─────────────────────────── Cuentas de prueba ───────────────────────────
/**
 * Crea las cuentas de prueba (una por tipo, con su perfil) y las borra al terminar el proceso.
 * Devuelve [matrícula => ['id' => IdCuenta, 'perfil' => nombre, 'tipo' => permiso_id]].
 */
function cuentasDePrueba(): array
{
    require_once realpath(RAIZ) . '/webService/libs/Cifrado.php';
    $definicion = [
        'PRUEBA-ADM' => ['Administrador', 1],
        'PRUEBA-MAE' => ['Maestro', 2],
        'PRUEBA-PAD' => ['Padre', 3],
        'PRUEBA-DEV' => ['Desarrollador', 4],
    ];
    $db = base();
    $cuentas = [];
    foreach ($definicion as $matricula => [$perfil, $tipo]) {
        $cifrada = Cifrado::encrypt($matricula);
        foreach (filas('SELECT id_cuenta, usuario_id FROM cuenta WHERE matricula = ?', 's', [$cifrada]) as $vieja) {
            borrarCuenta((int) $vieja['id_cuenta'], (int) $vieja['usuario_id']);       // restos de una corrida cortada
        }
        $st = $db->prepare("INSERT INTO usuario (nombre, paterno, materno) VALUES ('Prueba', ?, NULL)");
        $st->bind_param('s', $perfil);
        $st->execute();
        $usuario = $db->insert_id;
        $st = $db->prepare('INSERT INTO cuenta (matricula, usuario_id, permiso_id) VALUES (?, ?, ?)');
        $st->bind_param('sii', $cifrada, $usuario, $tipo);
        $st->execute();
        $id = $db->insert_id;
        $st = $db->prepare('INSERT IGNORE INTO CuentaPerfiles (IdCuenta, IdPerfil) SELECT ?, IdPerfil FROM Perfiles WHERE Nombre = ?');
        $st->bind_param('is', $id, $perfil);
        $st->execute();
        $cuentas[$matricula] = ['id' => $id, 'usuario' => $usuario, 'perfil' => $perfil, 'tipo' => $tipo];
    }
    register_shutdown_function(function () use ($cuentas) {
        foreach ($cuentas as $c) borrarCuenta($c['id'], $c['usuario']);
    });
    return $cuentas;
}

function borrarCuenta(int $idCuenta, int $idUsuario): void
{
    $db = base();
    $db->query("DELETE FROM CuentaPerfiles WHERE IdCuenta = $idCuenta");
    $db->query("DELETE FROM cuenta WHERE id_cuenta = $idCuenta");
    $db->query("DELETE FROM usuario WHERE id_usuario = $idUsuario");
}

// ─────────────────────────── Comprobaciones ───────────────────────────
$GLOBALS['__ok'] = 0;
$GLOBALS['__fallas'] = [];

function ok(bool $cond, string $que): void
{
    if ($cond) { $GLOBALS['__ok']++; return; }
    $GLOBALS['__fallas'][] = $que;
    echo "  \033[31m✗\033[0m $que\n";
}

function titulo(string $t): void { echo "\n\033[1m$t\033[0m\n"; }

/** Imprime el resumen y termina: 0 si todo pasó. */
function terminar(): never
{
    $f = count($GLOBALS['__fallas']);
    printf("\n%s%d comprobaciones, %d fallaron\033[0m\n", $f ? "\033[31m" : "\033[32m", $GLOBALS['__ok'] + $f, $f);
    exit($f ? 1 : 0);
}
