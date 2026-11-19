<?php
/**
 * Cargador propio del proyecto (sustituye a Composer: ya no hay vendor/).
 *
 * El router no busca controllers en el disco: el catálogo de permisos le dice cuál es (Componentes: Clase,
 * Archivo, Modulo, Conexion) y aquí se carga ese archivo, validando que de verdad esté en webService/controllers.
 * Después solo se "activa" el módulo del componente (su paquete), así una clase se busca únicamente en:
 *
 *   webService/libs/<Clase>.php                 (siempre: apoyos compartidos)
 *   webService/business/<módulo>/<Clase>.php    (los módulos activos: framework, para el catálogo, y el del componente)
 *   webService/models/<módulo>/<Clase>.php
 *
 * Con eso dos carpetas pueden tener una clase con el mismo nombre: solo se ve la del componente que atiende la petición.
 */
final class Loader
{
    private static string $root = '';
    /** @var string[] módulos activos ('framework' y el del componente) */
    private static array $folders = [];

    /** Registra el cargador. Lo llama core/autoload.php una sola vez. */
    public static function boot(string $root): void
    {
        if (self::$root !== '') return;
        self::$root = rtrim($root, '/\\');
        spl_autoload_register([self::class, 'load']);
    }

    /** Activa un módulo (su carpeta de business y models): 'escolar'. '' no activa nada. */
    public static function activate(string $folder): void
    {
        $folder = strtolower(trim($folder));
        if ($folder === '' || !preg_match('/^[a-z_]+$/', $folder)) return;
        if (!in_array($folder, self::$folders, true)) self::$folders[] = $folder;
    }

    /**
     * Carga el archivo de un controller registrado y regresa su clase. Truena si el archivo no está dentro de
     * webService/controllers, no se llama como la clase o no la define (un registro equivocado en la base no
     * puede cargar cualquier cosa).
     */
    public static function controller(string $class, string $file): string
    {
        if (!preg_match('/^[A-Za-z][A-Za-z0-9_]*Controller$/', $class)) {
            throw new RuntimeException("Controller inválido en el catálogo: '$class'.");
        }
        $dir  = realpath(self::$root . '/webService/controllers');
        $path = realpath(self::$root . '/' . ltrim(str_replace('\\', '/', $file), '/'));
        if ($dir === false || $path === false || dirname($path) !== $dir || basename($path) !== $class . '.php') {
            throw new RuntimeException("El archivo de $class no está en webService/controllers ('$file').");
        }
        require_once $path;
        if (!class_exists($class, false)) throw new RuntimeException("$path no define la clase $class.");
        return $class;
    }

    /**
     * ¿Hay un archivo de controller para ese componente? ('ciclo' → webService/controllers/CicloController.php,
     * sin importar mayúsculas: la misma convención de Catalog::component()). El router lo pregunta ANTES de consultar
     * el catálogo, así una URL inventada (bots: /wp-admin, /.env…) responde 404 sin abrir la base.
     * El catálogo sigue siendo quien decide: esto solo descarta lo que seguro no existe.
     */
    public static function hasController(string $component): bool
    {
        static $known = null;
        if ($known === null) {
            $known = [];
            foreach (glob(self::$root . '/webService/controllers/*Controller.php') ?: [] as $file) {
                $known[strtolower(substr(basename($file), 0, -strlen('Controller.php')))] = true;
            }
        }
        return isset($known[strtolower($component)]);
    }

    /** spl_autoload: libs y las carpetas activas. Nada más. */
    public static function load(string $class): void
    {
        if (!preg_match('/^[A-Za-z][A-Za-z0-9_]*$/', $class)) return;
        $candidates = [self::$root . "/webService/libs/$class.php"];
        foreach (self::$folders as $folder) {
            $candidates[] = self::$root . "/webService/business/$folder/$class.php";
            $candidates[] = self::$root . "/webService/models/$folder/$class.php";
        }
        foreach ($candidates as $file) {
            if (is_file($file)) { require_once $file; return; }
        }
    }
}
