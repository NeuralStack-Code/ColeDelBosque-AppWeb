<?php
/**
 * Auditoría del repositorio: revisa los archivos y los commits antes de que lleguen a master.
 * La corre la integración continua (.github/workflows/auditoria.yml) y cualquiera en local (no necesita base ni red):
 *
 *   php apiService/tools/auditoria/revisar.php                      todo el repo (archivos versionados)
 *   php apiService/tools/auditoria/revisar.php --rango origin/master..HEAD
 *                                                                   además, los commits y archivos de ese rango
 *
 * Errores (terminan en 1): archivos prohibidos en el repo (.env, SQL, volcados, storage), secretos, marcadores de
 * conflicto, BOM en PHP, restos de depuración, archivos de más de 10 MB, reglas de seguridad faltantes en el .htaccess,
 * JavaScript que no compila, avisos del catálogo de permisos y commits con archivos prohibidos.
 * Avisos (no detienen): imágenes pesadas, mensajes de commit fuera del formato y controllers cambiados (semilla).
 * En GitHub Actions cada hallazgo sale como anotación en el archivo y la línea.
 */
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }

$root = dirname(__DIR__, 3);
chdir($root);
$enGitHub = getenv('GITHUB_ACTIONS') === 'true';
$rango    = null;
foreach ($argv as $i => $a) if ($a === '--rango') $rango = $argv[$i + 1] ?? null;

$errores = 0; $avisos = 0;
function hallazgo(string $tipo, string $msg, ?string $archivo = null, ?int $linea = null): void
{
    global $enGitHub, $errores, $avisos;
    $tipo === 'error' ? $errores++ : $avisos++;
    if ($enGitHub) {
        $donde = $archivo ? ' file=' . $archivo . ($linea ? ",line=$linea" : '') : '';
        echo "::$tipo$donde::" . str_replace(["\r", "\n"], ' ', $msg) . "\n";
    } else {
        echo ($tipo === 'error' ? "  \033[31m✗\033[0m " : "  \033[33m!\033[0m ") . ($archivo ? $archivo . ($linea ? ":$linea" : '') . ' — ' : '') . "$msg\n";
    }
}
function seccion(string $t): void { echo "\n\033[1m$t\033[0m\n"; }
function git(string $args): array
{
    exec('git ' . $args . ' 2>&1', $out, $rc);
    if ($rc !== 0) { fwrite(STDERR, "git $args falló:\n" . implode("\n", $out) . "\n"); exit(2); }
    return $out;
}

$archivos = array_values(array_filter(git('ls-files'), fn($f) => $f !== '' && is_file($f)));
$texto    = fn(string $f) => (bool) preg_match('/\.(php|js|css|html?|json|ya?ml|neon|md|txt|sh|htaccess|example|xml|svg)$|(^|\/)\.[a-z]+$/i', $f);

/* Rutas que nunca deben estar en el repo (las mismas ideas que el .gitignore, por si alguien forzó un git add -f). */
$prohibido = function (string $f): ?string {
    $reglas = [
        '/(^|\/)\.env$|(^|\/)\.env\.(?!example$)[^\/]+$|(^|\/)[^\/]+\.env$/' => 'archivo .env con credenciales',
        '/\.sql(\.gz)?$/i'                                     => 'SQL: va en la rama DeployBacklog, nunca en master',
        '/^(docs|webService\/sql|dumps|storage)\//'            => 'carpeta que no va al repo',
        '/\.(bak|zip|dump|diff|patch|log)$/i'                  => 'respaldo, volcado o log',
        '/^apiService\/storage\/(?!(avatars\/)?\.htaccess$|\.htaccess$)/' => 'datos de apiService/storage (privado del servidor)',
        '/(^|\/)\.env\.pruebas$/'                            => 'entorno de pruebas local',
        '/(^|\/)\.env\.respaldo$/'                             => 'respaldo del .env',
    ];
    foreach ($reglas as $re => $por) if (preg_match($re, $f)) return $por;
    return null;
};

/* Secretos con formato reconocible. Los .env.example y las pruebas no llevan valores reales. */
$secretos = [
    '/AKIA[0-9A-Z]{16}/'                            => 'llave de AWS',
    '/GOCSPX-[A-Za-z0-9_-]{10,}/'                   => 'secreto de cliente de Google',
    '/AIza[0-9A-Za-z_-]{35}/'                       => 'API key de Google',
    '/gh[pousr]_[A-Za-z0-9]{30,}/'                  => 'token de GitHub',
    '/github_pat_[A-Za-z0-9_]{40,}/'                => 'token de GitHub',
    '/-----BEGIN [A-Z ]*PRIVATE KEY-----/'          => 'llave privada',
    '/xox[baprs]-[A-Za-z0-9-]{10,}/'                => 'token de Slack',
    '/sk-(ant-)?[A-Za-z0-9_-]{32,}/'                => 'API key (OpenAI/Anthropic)',
    '/sk_live_[A-Za-z0-9]{20,}/'                    => 'llave de Stripe en vivo',
    '/mysqli?_connect\s*\(\s*[\'"][^\'"]+[\'"]\s*,\s*[\'"][^\'"]+[\'"]\s*,\s*[\'"][^\'"]{4,}[\'"]/' => 'credenciales de base escritas en el código',
];

seccion('1. Archivos que no van al repo');
foreach ($archivos as $f) if ($por = $prohibido($f)) hallazgo('error', "No debe estar versionado ($por).", $f);

seccion('2. Contenido: secretos, conflictos, BOM y restos de depuración');
foreach ($archivos as $f) {
    $tam = filesize($f);
    if ($tam > 10 * 1024 * 1024) hallazgo('error', sprintf('Pesa %.1f MB (máximo 10 MB).', $tam / 1048576), $f);
    elseif ($tam > 1024 * 1024 && preg_match('/\.(png|jpe?g|gif|webp)$/i', $f))
        hallazgo('warning', sprintf('Imagen de %.1f MB: conviene comprimirla o pasarla a WebP (carga más rápida).', $tam / 1048576), $f);
    if (!$texto($f)) continue;

    $c = (string) file_get_contents($f);
    if (str_ends_with($f, '.php') && str_starts_with($c, "\xEF\xBB\xBF")) hallazgo('error', 'Empieza con BOM: manda bytes antes de las cabeceras.', $f, 1);
    $esPrueba = str_starts_with($f, 'apiService/tests/');
    foreach (preg_split('/\R/', $c) as $n => $l) {
        $ln = $n + 1;
        if (preg_match('/^(<{7}|>{7})( |$)|^={7}$/', $l)) hallazgo('error', 'Marcador de conflicto de merge sin resolver.', $f, $ln);
        foreach ($secretos as $re => $que) if (preg_match($re, $l)) hallazgo('error', "Parece un secreto ($que). Muévelo al .env y cámbialo.", $f, $ln);
        if (str_ends_with($f, '.php') && !$esPrueba && preg_match('/(?<![\w>:$])(var_dump|print_r|dd|dump)\s*\((?![^;]*,\s*true\s*\))/', $l)
            && !preg_match('/^\s*(\/\/|\*|#)/', $l))
            hallazgo('error', 'Resto de depuración (var_dump/print_r/dd) que puede mostrar datos internos.', $f, $ln);
        if (str_ends_with($f, '.js') && preg_match('/^\s*debugger\s*;/', $l)) hallazgo('error', 'debugger; olvidado.', $f, $ln);
    }
}

seccion('3. Reglas de seguridad del .htaccess');
$ht = (string) @file_get_contents('.htaccess');
$reglasHt = [
    'apiService/(core|middleware|storage|tests|tools)' => 'bloqueo del código interno de apiService',
    'webService/(controllers|business|models|libs|views)' => 'bloqueo del código interno de webService',
    '(?!index\.php$|apiService/index\.php$)'          => 'solo index.php y apiService/index.php se ejecutan',
    '\.(sql|md)$'                                       => 'bloqueo de SQL y Markdown',
    '(^|/)\.(?!well-known'                              => 'bloqueo de archivos ocultos (.env, .git…)',
    'X-Content-Type-Options'                            => 'cabecera nosniff',
    'X-Frame-Options'                                   => 'cabecera X-Frame-Options',
];
foreach ($reglasHt as $aguja => $que) if (!str_contains($ht, $aguja)) hallazgo('error', "Falta la regla: $que.", '.htaccess');

seccion('4. JavaScript (node --check)');
exec('node --version 2>&1', $nv, $rcNode);
if ($rcNode !== 0) {
    hallazgo('warning', 'No hay node en este equipo: se salta la revisión de sintaxis de JavaScript.');
} else {
    foreach ($archivos as $f) {
        if (!str_ends_with($f, '.js')) continue;
        $out = []; exec('node --check ' . escapeshellarg($f) . ' 2>&1', $out, $rc);
        if ($rc !== 0) hallazgo('error', 'No compila: ' . trim(implode(' ', array_slice($out, 0, 5))), $f);
    }
}

seccion('5. Catálogo de permisos (lo que lee del código)');
require_once $root . '/apiService/core/autoload.php';
require_once $root . '/webService/libs/CatalogoCodigo.php';
try {
    $cat = CatalogoCodigo::read($root);
    foreach ($cat['avisos'] as $a) hallazgo('error', "Catálogo: $a");
    printf("  %d componentes, %d vistas, %d métodos.\n", count($cat['componentes']), count($cat['vistas']), count($cat['metodos']));
} catch (Throwable $e) {
    hallazgo('error', 'No se pudo leer el catálogo desde el código: ' . $e->getMessage(), str_replace($root . DIRECTORY_SEPARATOR, '', $e->getFile()), $e->getLine());
}

if ($rango) {
    seccion("6. Commits de $rango");
    $commits = git('log --no-merges --format=%H%x09%an%x09%s ' . escapeshellarg($rango));
    if (!$commits) echo "  (sin commits nuevos)\n";
    foreach ($commits as $c) {
        [$sha, $autor, $asunto] = array_pad(explode("\t", $c, 3), 3, '');
        $corto = substr($sha, 0, 7);
        if (trim($asunto) === '') hallazgo('error', "$corto no tiene mensaje.");
        elseif (preg_match('/^(wip|fixup!|squash!|tmp|temp|asdf|test)\b/i', $asunto)) hallazgo('warning', "$corto «{$asunto}»: parece un commit temporal; júntalo antes de llegar a master.");
        elseif (preg_match_all("/./u", $asunto) > 100) hallazgo('warning', "$corto: el asunto pasa de 100 caracteres.");
        elseif (!preg_match('/^(feat|fix|docs|refactor|test|chore|ci|perf|style|build|revert)(\([^)]+\))?!?: \S/', $asunto)
                && !preg_match('/^(Merge|Revert|Fase|Refactor|Workflows|README|DeployBacklog|Documentacion)\b/', $asunto))
            hallazgo('warning', "$corto «{$asunto}»: se recomienda el formato tipo(área): descripción (feat, fix, docs, ci…).");

        foreach (git('diff-tree --no-commit-id --name-status -r ' . $sha) as $linea) {
            [$estado, $f] = array_pad(preg_split('/\t/', $linea, 2), 2, '');
            if ($estado !== 'D' && $f !== '' && ($por = $prohibido($f))) hallazgo('error', "El commit $corto agrega $f ($por). Aunque se borre después, sigue en el historial.", $f);
        }
    }

    $cambiados = git('diff --name-only ' . escapeshellarg(str_replace('..', '...', $rango)));
    $controllers = array_filter($cambiados, fn($f) => str_starts_with($f, 'webService/controllers/'));
    if ($controllers) hallazgo('warning', 'Cambiaron controllers (' . implode(', ', array_map('basename', $controllers))
        . '): si hay métodos públicos nuevos o quitados, regenera la semilla (php apiService/tools/catalogo/generar_semilla.php) y deja su entrega en DeployBacklog.');
}

echo "\n" . ($errores ? "\033[31m$errores error(es)\033[0m" : "\033[32mSin errores\033[0m") . ", $avisos aviso(s).\n";
exit($errores ? 1 : 0);
