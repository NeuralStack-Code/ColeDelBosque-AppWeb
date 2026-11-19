<?php
/**
 * Actualizador del framework NeuralStack Code. Vive en el proyecto: no hay que bajar nada para usarlo.
 *
 *   php apiService/core/update.php            actualiza el core a la última versión
 *   php apiService/core/update.php --check    solo dice si hay una versión nueva (no cambia nada)
 *   php apiService/core/update.php --force    vuelve a bajar el core aunque la versión sea la misma
 *
 * Usa KEY_TOKEN y API_BASE_URL del .env del proyecto.
 *
 * Reemplaza SOLO el core (apiService/core: Command, Entity, Model, ServerApi, Crypto, procedure y este actualizador) y los
 * procedures del sistema (webService/sql/sp). Tu Controller base, tu autoload, tu router, tus controllers, business,
 * models, vistas y tu .env no se tocan. De lo reemplazado queda copia en apiService/storage/framework-backup/ (o en
 * storage/framework-backup/ en un proyecto anterior a la estructura de la 1.4.0).
 */

if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }    // solo por consola, nunca desde el navegador

/**
 * Llave pública (Ed25519) con la que NeuralStack firma cada versión del framework (1.5.0). El paquete trae
 * MANIFEST.json (el SHA-256 de cada archivo) y MANIFEST.sig (su firma): si la firma no valida o un solo archivo no
 * coincide, no se escribe nada. La llave privada nunca está en el servidor de la api.
 */
const FRAMEWORK_PUBLIC_KEY = 'NMmEpu6RL8OPjhl3har9mS6XVqZlWT5taR2k+rDU5FU=';

/**
 * Revisa un paquete de actualización antes de tocar el proyecto. Regresa null si es auténtico, o el motivo del rechazo.
 * Auténtico = MANIFEST.json firmado con FRAMEWORK_PUBLIC_KEY, de la versión $version, y cada archivo del paquete
 * (fuera del manifiesto mismo) listado en él con el mismo SHA-256, sin que falte ninguno.
 */
function frameworkVerifyPackage(ZipArchive $zip, string $version, string $publicKey = FRAMEWORK_PUBLIC_KEY): ?string
{
    if (!function_exists('sodium_crypto_sign_verify_detached')) {
        return "Falta la extensión 'sodium' de PHP: sin ella no se puede comprobar la firma del paquete.";
    }
    $manifest  = $zip->getFromName('MANIFEST.json');
    $signature = base64_decode((string) $zip->getFromName('MANIFEST.sig'), true);
    $key       = base64_decode($publicKey, true);
    if ($manifest === false || $signature === false || strlen($signature) !== SODIUM_CRYPTO_SIGN_BYTES) return 'el paquete no viene firmado.';
    if ($key === false || strlen($key) !== SODIUM_CRYPTO_SIGN_PUBLICKEYBYTES) return 'la llave pública del actualizador no es válida.';
    if (!sodium_crypto_sign_verify_detached($signature, $manifest, $key)) return 'la firma del manifiesto no es válida.';

    $data = json_decode($manifest, true);
    if (!is_array($data) || !is_array($data['files'] ?? null)) return 'el manifiesto no se puede leer.';
    if (($data['version'] ?? '') !== $version) return "el manifiesto es de la versión '" . ($data['version'] ?? '') . "', no de la $version.";

    $seen = [];
    for ($i = 0; $i < $zip->numFiles; $i++) {
        $path = $zip->getNameIndex($i);
        if ($path === 'MANIFEST.json' || $path === 'MANIFEST.sig' || str_ends_with($path, '/')) continue;
        $hash = $data['files'][$path] ?? null;
        if ($hash === null) return "$path no está en el manifiesto.";
        if (!hash_equals($hash, hash('sha256', (string) $zip->getFromIndex($i)))) return "$path no coincide con el manifiesto (alterado).";
        $seen[$path] = true;
    }
    foreach (array_keys($data['files']) as $path) {
        if (!isset($seen[$path])) return "falta $path en el paquete.";
    }
    return null;
}

/** Pide algo a la api con la API key del proyecto. Devuelve el cuerpo (o false si no hubo respuesta). */
function frameworkRequest(string $url, string $apiKey, string $accept): string|false
{
    return @file_get_contents($url, false, stream_context_create([
        'http' => ['method' => 'GET', 'header' => "X-API-KEY: $apiKey\r\nAccept: $accept\r\n", 'timeout' => 30, 'ignore_errors' => true],
        'ssl'  => ['verify_peer' => true, 'verify_peer_name' => true],
    ]));
}

/** Lee una variable del .env del proyecto (sin cargar nada más). */
function frameworkEnv(string $root, string $name): string
{
    $env = is_file($root . '/.env') ? (string) file_get_contents($root . '/.env') : '';
    return preg_match('/^\s*' . preg_quote($name, '/') . '\s*=\s*["\']?([^"\'\r\n#]*)/m', $env, $m) ? trim($m[1]) : '';
}

/**
 * Actualiza el core del proyecto que está en $root con el que entrega la api. Devuelve true si todo salió bien.
 * $check = true solo informa. $force = true vuelve a bajarlo aunque la versión sea la misma.
 */
function updateFramework(string $root, string $apiBase, string $apiKey, bool $force = false, bool $check = false): bool
{
    $fail = function (string $message): bool { fwrite(STDERR, $message . "\n"); return false; };

    if (!class_exists('ZipArchive')) return $fail("Falta la extensión 'zip' de PHP (extension=zip en php.ini).");
    if (!is_file($root . '/apiService/core/autoload.php')) return $fail('Aquí no hay un proyecto NeuralStack (falta apiService/core).');
    if ($apiKey === '') return $fail('Falta la API key: llena KEY_TOKEN en el .env del proyecto.');

    $versionFile = $root . '/apiService/core/VERSION';
    $current     = is_file($versionFile) ? trim((string) file_get_contents($versionFile)) : '';

    echo "Consultando la versión del framework...\n";
    // Con la versión instalada, las notas traen lo de cada versión intermedia (no solo la última).
    $query = preg_match('/^\d+\.\d+\.\d+$/', $current) ? '?desde=' . $current : '';
    $info  = json_decode((string) frameworkRequest($apiBase . '/framework/version' . $query, $apiKey, 'application/json'), true);
    if (!is_array($info))        return $fail('Error: no se pudo contactar la API (' . $apiBase . ').');
    if (empty($info['success'])) return $fail('Error API: ' . ($info['message'] ?? 'respuesta inesperada'));
    $latest = (string) ($info['data']['version'] ?? '');
    $notes  = (string) ($info['data']['notes'] ?? '');

    echo '  Instalada: ' . ($current ?: 'sin versión (anterior a 1.0.0)') . "   Disponible: $latest\n";
    if ($current === $latest && !$force) {
        echo "✓ Ya tienes la última versión. (Usa --force para volver a bajarla.)\n";
        return true;
    }
    if ($check) {
        echo "→ Hay una versión nueva. Para instalarla: php apiService/core/update.php\n";
        if ($notes !== '') echo "\nNotas para pasar a la $latest (cada versión nueva, de la más reciente a la tuya):\n$notes\n";
        return true;
    }

    $bytes = frameworkRequest($apiBase . '/framework/update', $apiKey, 'application/zip');
    if ($bytes === false || $bytes === '') return $fail('Error: no se pudo descargar la actualización.');
    if ($bytes[0] === '{') {
        $error = json_decode($bytes, true);
        return $fail('Error API: ' . ($error['message'] ?? $bytes));
    }
    $tmpZip = sys_get_temp_dir() . '/nsc_update_' . time() . '.zip';
    file_put_contents($tmpZip, $bytes);
    $zip = new ZipArchive();
    if ($zip->open($tmpZip) !== true) { @unlink($tmpZip); return $fail('Error al abrir el paquete descargado.'); }
    // Firma y huellas ANTES de escribir nada: un paquete alterado no toca el proyecto.
    if (($problem = frameworkVerifyPackage($zip, $latest)) !== null) {
        $zip->close();
        @unlink($tmpZip);
        return $fail("Paquete rechazado: $problem No se cambió nada.");
    }
    echo "  Firma del paquete verificada.\n";

    // Proyectos de la 1.4.0 en adelante: apiService/storage. Los anteriores conservan su storage/ en la raíz.
    $storage = is_dir($root . '/apiService/storage') || !is_dir($root . '/storage') ? $root . '/apiService/storage' : $root . '/storage';
    $backup  = $storage . '/framework-backup/' . date('Ymd-His') . '_' . ($current ?: 'sin-version');
    $changes = []; $sql = []; $skipped = [];
    for ($i = 0; $i < $zip->numFiles; $i++) {
        $path = $zip->getNameIndex($i);
        // Lo único que una actualización puede tocar: el core y sus procedures. Nada más, venga lo que venga en el paquete.
        if (!preg_match('#^(apiService/core|webService/sql/sp)/[A-Za-z0-9_.-]+$#', $path)) continue;
        if (in_array($path, ['apiService/core/Controller.php', 'apiService/core/autoload.php'], true)) continue;   // son del proyecto

        $content  = $zip->getFromIndex($i);
        $file     = $root . '/' . $path;

        // En Windows (y en macOS) crypto.php y Crypto.php son el MISMO archivo: escribir uno pisaría el otro.
        // Si en la carpeta hay un archivo con el mismo nombre pero otras mayúsculas, es del proyecto: no se toca.
        $sameName = array_filter(glob(dirname($file) . '/*') ?: [],
            fn(string $f) => strcasecmp(basename($f), basename($file)) === 0 && basename($f) !== basename($file));
        if ($sameName) {
            $skipped[] = $path . ' (ya existe ' . basename(reset($sameName)) . ', que es del proyecto)';
            continue;
        }

        $previous = is_file($file) ? file_get_contents($file) : null;
        if ($previous === $content) continue;                                     // sin cambios

        if ($previous !== null) {                                                 // copia de lo que se va a reemplazar
            if (!is_dir(dirname($backup . '/' . $path))) mkdir(dirname($backup . '/' . $path), 0775, true);
            file_put_contents($backup . '/' . $path, $previous);
        }
        if (!is_dir(dirname($file))) mkdir(dirname($file), 0775, true);
        file_put_contents($file, $content);
        $changes[] = ($previous === null ? '+ ' : '~ ') . $path;
        if (substr($path, -4) === '.sql') $sql[] = $path;
    }
    $zip->close();
    unlink($tmpZip);

    if ($skipped) echo "  ! No se escribieron (choque de mayúsculas con un archivo del proyecto):\n    " . implode("\n    ", $skipped) . "\n";
    if (!$changes) {
        echo "✓ El core ya estaba igual; no se cambió nada.\n";
        return true;
    }
    echo "✓ Framework actualizado a $latest:\n  " . implode("\n  ", $changes) . "\n";
    if (is_dir($backup)) echo '  Copia de lo reemplazado: ' . str_replace($root . '/', '', $backup) . "\n";
    if ($sql) echo '  ! Cambiaron procedures: córrelos en tu base → ' . implode(', ', $sql) . "\n";
    if ($notes !== '') echo "\nNotas para pasar a la $latest (cada versión nueva, de la más reciente a la tuya):\n$notes\n";
    return true;
}

// Corrido directo (php apiService/core/update.php). Si lo incluye el setup, solo aporta las funciones.
if (realpath($_SERVER['SCRIPT_FILENAME'] ?? '') === __FILE__) {
    $root    = dirname(__DIR__, 2);
    $apiBase = rtrim(frameworkEnv($root, 'API_BASE_URL') ?: 'https://api.neuralstackcode.com.mx/v1', '/');
    $ok      = updateFramework($root, $apiBase, frameworkEnv($root, 'KEY_TOKEN'),
                               in_array('--force', $argv, true), in_array('--check', $argv, true));
    exit($ok ? 0 : 1);
}
