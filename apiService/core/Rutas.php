<?php
/**
 * Rutas del sitio desde la base El index.php ya no tiene rutas escritas en el código: pregunta aquí.
 *
 *     $ruta = Rutas::resolver('privacidad');     // ['Tipo' => 'vista', 'Destino' => 'webService/views/legal/privacidad.php', …]
 *     Rutas::vista($ruta)                         // la ruta absoluta del archivo, validada (solo webService/views/*.php)
 *     Rutas::redirigir($ruta)                     // manda el Location con su código (301/302) y termina
 *
 * La tabla es Rutas (base del colegio, sp_ruta_listar): páginas sueltas ('vista') y URLs que mandan a otra
 * ('redireccion'). Lo que no esté ahí es /<componente>/<método> y lo atiende el router de la api con el catálogo.
 *
 * Se guardan en caché 5 minutos (apiService/storage/cache/rutas.json): la portada no consulta la base en cada visita.
 * Si la base no responde se usa la última copia buena aunque sea vieja, así el sitio público no se cae con la base.
 * Un cambio en la tabla se ve a lo más 5 minutos después (o al borrar el archivo de caché).
 */
final class Rutas
{
    private const TTL = 300;

    /** @var array<string, array>|null ruta => fila */
    private static ?array $mapa = null;

    /** La fila de la ruta ('' = portada), o null si no está registrada. */
    public static function resolver(string $ruta): ?array
    {
        return self::mapa()[trim($ruta, '/')] ?? null;
    }

    /**
     * El archivo de una ruta de tipo vista. Truena si no está dentro de webService/views o no existe: un registro
     * equivocado en la base no puede cargar cualquier archivo.
     */
    public static function vista(array $ruta): string
    {
        $raiz  = dirname(__DIR__, 2);
        $dir   = realpath($raiz . '/webService/views');
        $path  = realpath($raiz . '/' . ltrim(str_replace('\\', '/', (string) $ruta['Destino']), '/'));
        if ($dir === false || $path === false || !str_starts_with($path, $dir . DIRECTORY_SEPARATOR) || !str_ends_with($path, '.php')) {
            throw new RuntimeException("La vista de la ruta no está en webService/views ('{$ruta['Destino']}').");
        }
        return $path;
    }

    /** Manda a la ruta interna de destino (con el ?query original si así está registrada) y termina. */
    public static function redirigir(array $ruta): never
    {
        $destino = (string) $ruta['Destino'];
        if (!str_starts_with($destino, '/') || str_starts_with($destino, '//')) {
            throw new RuntimeException("Redirección no válida: '$destino'.");     // nunca a otro dominio
        }
        $query = (int) $ruta['ConservarQuery'] === 1 ? ($_SERVER['QUERY_STRING'] ?? '') : '';
        $codigo = (int) $ruta['CodigoHttp'] === 302 ? 302 : 301;
        header('Location: ' . BASE_URL . $destino . ($query !== '' ? '?' . $query : ''), true, $codigo);
        exit;
    }

    /* ── Internos ── */

    private static function mapa(): array
    {
        if (self::$mapa !== null) return self::$mapa;

        $cache = dirname(__DIR__) . '/storage/cache/rutas.json';
        $guardadas = is_file($cache) ? json_decode((string) @file_get_contents($cache), true) : null;
        if (is_array($guardadas) && time() - (int) @filemtime($cache) < self::TTL) return self::$mapa = $guardadas;

        try {
            $db = conexionPrincipal();
            $mapa = [];
            foreach (sp($db, 'sp_ruta_listar') as $fila) $mapa[(string) $fila['Ruta']] = $fila;
            if (!is_dir(dirname($cache))) @mkdir(dirname($cache), 0755, true);
            @file_put_contents($cache, json_encode($mapa), LOCK_EX);
            return self::$mapa = $mapa;
        } catch (Throwable $e) {
            error_log('Rutas: no se pudieron leer de la base (' . $e->getMessage() . ')'
                      . (is_array($guardadas) ? '; se usa la última copia.' : '; no hay copia.'));
            if (is_array($guardadas)) @touch($cache);   // no reintentar en cada visita mientras la base no responda
            return self::$mapa = is_array($guardadas) ? $guardadas : [];
        }
    }
}
