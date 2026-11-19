<?php
/**
 * El catálogo de permisos tal como lo describe el CÓDIGO: los controllers (ReflectionClass) + los ajustes
 * que el código no puede decir solo (menú, usos entre vistas, sensibles, accesos). Sin base de datos.
 *
 * Lo usa, desde la terminal, apiService/tools/catalogo/generar_semilla.php (la semilla que se corre en la base).
 * Es la misma pieza que en NeuralStack Code; aquí cambian los ajustes (los del colegio) y lo que cuenta como lectura.
 * Entran todos los controllers que heredan de Controller.
 */
final class CatalogoCodigo
{
    /**
     * Lo que el código NO puede deducir. Todo lo que no aparezca aquí toma su valor por convención:
     *   - Vista:    un método cuyo cuerpo llama a $this->view(...)
     *   - Padre:    los métodos cuelgan de la vista 'index' de su controller (si tiene)
     *   - SoloPost: 1, excepto vistas, listar*, obtener*, config, foto y datos
     *   - Acceso:   'perfil'
     * Las claves son '<componente>/<método>' con el nombre EXACTO del método (listarEmpresa, no listarempresa).
     * Al cambiar algo aquí: regenerar la semilla (o sincronizar desde la pantalla).
     */
    public static function ajustes(): array
    {
        return [
            // Secciones = MÓDULOS (la clave es el MODULE de sus controllers). clave => [Nombre, Icono, Orden]
            // El colegio no pinta un menú lateral: los paneles muestran cada acceso si el perfil lo concede (can()).
            'grupos' => [
                'escolar' => ['Control escolar', 'school',          10],
                'pagos'   => ['Pagos',           'payments',        20],
                'padre'   => ['Familias',        'family_restroom', 30],
                'sistema' => ['Sistema',         'settings',        40],
            ],

            // Pantallas de los paneles (las que tienen un acceso). Las demás cuelgan de la 'index' de su controller.
            'vistas' => [
                'dashboard/index'      => ['grupo' => 'escolar', 'nombre' => 'Panel del administrador', 'orden' => 10],
                'maestro/index'        => ['grupo' => 'escolar', 'nombre' => 'Panel del maestro',       'orden' => 12],
                'alumno/index'         => ['grupo' => 'escolar', 'nombre' => 'Alumnos',                 'orden' => 20],
                'docente/index'        => ['grupo' => 'escolar', 'nombre' => 'Maestros',                'orden' => 30],
                'grupo/index'          => ['grupo' => 'escolar', 'nombre' => 'Grupos',                  'orden' => 40],
                'materia/index'        => ['grupo' => 'escolar', 'nombre' => 'Materias',                'orden' => 50],
                'ciclo/index'          => ['grupo' => 'escolar', 'nombre' => 'Ciclo escolar',           'orden' => 60],
                'calificacion/index'   => ['grupo' => 'escolar', 'nombre' => 'Calificaciones',          'orden' => 70],
                'reporte/index'        => ['grupo' => 'escolar', 'nombre' => 'Reporte semanal',         'orden' => 80],
                'reportecolumna/index' => ['grupo' => 'escolar', 'nombre' => 'Columnas del reporte',    'orden' => 90],
                'recibo/index'         => ['grupo' => 'pagos',   'nombre' => 'Recibos',                 'orden' => 10],
                'colegiatura/index'    => ['grupo' => 'pagos',   'nombre' => 'Colegiaturas',            'orden' => 20],
                'esquemapago/index'    => ['grupo' => 'pagos',   'nombre' => 'Esquemas de pago',        'orden' => 30],
                'tiporecibo/index'     => ['grupo' => 'pagos',   'nombre' => 'Tipos de recibo',         'orden' => 40],
                'tipodescuento/index'  => ['grupo' => 'pagos',   'nombre' => 'Tipos de descuento',      'orden' => 50],
                'padre/index'          => ['grupo' => 'padre',   'nombre' => 'Panel de la familia',     'orden' => 10],
                'desarrollador/index'  => ['grupo' => 'sistema', 'nombre' => 'Panel del desarrollador', 'orden' => 10],
                'cuenta/index'         => ['grupo' => 'sistema', 'nombre' => 'Cuentas',                 'orden' => 20],
                'perfilacceso/index'   => ['grupo' => 'sistema', 'nombre' => 'Perfiles y permisos',     'orden' => 30],
                'tablas/index'         => ['grupo' => 'sistema', 'nombre' => 'Tablas',                  'orden' => 40],
            ],

            // Métodos que NO cuelgan de 'index' ('' = sin padre: marcar la pantalla no lo da; se concede aparte).
            'padre' => [
                'reporte/grupos' => '',          // trabajar los reportes de TODOS los grupos (el Administrador), no solo los suyos
            ],

            // Pantallas públicas o de paso: no van en ningún panel ni cuelgan de otra pantalla.
            'fuera_menu' => ['auth/entrar', 'contacto/index'],

            // Acceso distinto de 'perfil'.
            'acceso' => [
                'auth/entrar'     => 'publico',
                'auth/salir'      => 'publico',
                'contacto/index'  => 'publico',
                'contacto/enviar' => 'publico',
                'recibo/imprimir' => 'sesion',   // el controller decide: quien administra recibos, o el alumno dueño del recibo
            ],

            // SoloPost distinto de la convención (ver isRead()).
            'solo_post' => [
                'auth/salir' => 0,
            ],

            // Solo un perfil EsAdmin (Desarrollador) los puede conceder.
            'sensible' => [
                'cuenta/guardar', 'cuenta/eliminar', 'cuenta/perfiles',
                'perfilacceso/index', 'perfilacceso/guardar', 'perfilacceso/eliminar', 'perfilacceso/objetos',
                'tablas/index', 'tablas/listar', 'tablas/ver',
            ],

            // Una pantalla que llama métodos de otro controller (leído de su JavaScript). Quien tiene la pantalla los puede llamar.
            'usos' => [
                'alumno/index'  => ['grupo/listar'],
                'docente/index' => ['grupo/listar'],
                'grupo/index'   => ['ciclo/listar', 'materia/listar'],
            ],
        ];
    }

    /**
     * Lee los controllers y regresa lo que hay en el código:
     *   componentes: clave => [clase, archivo, modulo, conexion]  (lo que el router necesita para cargarlo)
     *   vistas:      'cliente/index' => [...]
     *   metodos:     'cliente/listar' => [...]
     *   avisos:      lo que no cuadra entre el código y los ajustes
     */
    public static function read(string $root, ?array $ajustes = null): array
    {
        $ajustes ??= self::ajustes();
        $out = ['componentes' => [], 'vistas' => [], 'metodos' => [], 'avisos' => []];

        $archivos = glob($root . '/webService/controllers/*Controller.php');
        sort($archivos);
        foreach ($archivos as $archivo) {
            $clase = basename($archivo, '.php');
            require_once $archivo;                                                                // sin autoload: se carga directo
            if (!class_exists($clase, false) || !is_subclass_of($clase, Controller::class)) continue;   // forma anterior
            $rc = new ReflectionClass($clase);
            if ($rc->isAbstract()) continue;

            $componente = strtolower(substr($clase, 0, -10));
            $out['componentes'][$componente] = [
                'clase'    => $clase,
                'archivo'  => 'webService/controllers/' . basename($archivo),
                'modulo'   => $clase::MODULE,
                'conexion' => $clase::CONNECTION,
            ];
            if ($clase::MODULE === '' || !is_dir($root . '/webService/business/' . $clase::MODULE)) {
                $out['avisos'][] = "$clase declara el módulo '" . $clase::MODULE . "', que no existe en webService/business.";
            }
            $lineas = file($archivo);
            $posicion = 0;

            foreach ($rc->getMethods(ReflectionMethod::IS_PUBLIC) as $m) {
                if ($m->isStatic() || $m->isConstructor() || $m->getDeclaringClass()->getName() !== $clase) continue;
                if ($m->getName() === 'authorize') continue;

                $nombre = $m->getName();
                $clave  = "$componente/$nombre";
                $cuerpo = implode('', array_slice($lineas, $m->getStartLine() - 1, $m->getEndLine() - $m->getStartLine() + 1));
                $esVista = str_contains($cuerpo, '$this->view(') || isset($ajustes['vistas'][$clave]);

                $item = [
                    'componente' => $componente,
                    'clase'      => $clase,
                    'metodo'     => $nombre,
                    'soloPost'   => $ajustes['solo_post'][$clave] ?? (int) !self::isRead($nombre, $esVista),
                    'acceso'     => $ajustes['acceso'][$clave] ?? 'perfil',
                    'sensible'   => (int) in_array($clave, $ajustes['sensible'], true),
                ];
                if ($esVista) {
                    // Vista de menú: la de los ajustes con 'grupo'. Vista sin menú: la que no está en los ajustes o trae
                    // 'padre' (otra vista del mismo controller; sin 'padre', la 'index'). Se resuelve después del ciclo.
                    $v = $ajustes['vistas'][$clave] ?? null;
                    $enMenu = $v !== null && !isset($v['padre']);
                    if (in_array($clave, $ajustes['fuera_menu'], true)) $v = ['padre' => ''];   // pantalla de la academia
                    $out['vistas'][$clave] = $item + [
                        'grupo'  => $enMenu ? ($v['grupo'] ?? '') : ($v['padre'] ?? 'index'),
                        'nombre' => $v['nombre'] ?? self::human($nombre),
                        'icono'  => $v['icono']  ?? '',
                        'orden'  => $v['orden']  ?? ++$posicion * 10,
                        'pagina' => $v['pagina'] ?? null,
                        'menu'   => $enMenu,
                    ];
                } else {
                    $out['metodos'][$clave] = $item + ['nombre' => self::human($nombre), 'orden' => ++$posicion * 10];   // en el orden del código
                }
            }
        }

        // Vistas sin menú: cuelgan de su vista padre. Si no existe, quedan sueltas (sin nombre, para que no salgan en el menú).
        foreach ($out['vistas'] as $clave => &$v) {
            if ($v['menu']) continue;
            if (in_array($clave, $ajustes['fuera_menu'], true)) { $v['grupo'] = ''; $v['nombre'] = ''; continue; }
            if ($v['grupo'] === $v['metodo'] || !isset($out['vistas']["{$v['componente']}/{$v['grupo']}"])) {
                $out['avisos'][] = "Vista $clave sin datos de menú ni vista padre (queda suelta: agrégala a los ajustes).";
                $v['grupo']  = '';
                $v['nombre'] = '';
            }
        }
        unset($v);

        // Padre de cada método: el de los ajustes ('' = ninguno), o 'index' si el controller tiene esa vista, o ninguno.
        foreach ($out['metodos'] as $clave => &$m) {
            $padre = $ajustes['padre'][$clave] ?? 'index';
            $m['padre'] = $padre !== '' && isset($out['vistas']["{$m['componente']}/$padre"]) ? $padre : '';
            if ($padre !== '' && isset($ajustes['padre'][$clave]) && $m['padre'] === '') $out['avisos'][] = "El padre de $clave no es una vista.";
        }
        unset($m);

        // Que lo de los ajustes exista en el código.
        $todo = $out['vistas'] + $out['metodos'];
        foreach (['acceso', 'solo_post', 'padre'] as $seccion) {
            foreach (array_keys($ajustes[$seccion]) as $clave) {
                if (!isset($todo[$clave])) $out['avisos'][] = "Ajustes [$seccion]: $clave no existe en el código.";
            }
        }
        foreach ($ajustes['sensible'] as $clave) {
            if (!isset($todo[$clave])) $out['avisos'][] = "Ajustes [sensible]: $clave no existe en el código.";
        }
        foreach ($ajustes['fuera_menu'] as $clave) {
            if (!isset($out['vistas'][$clave])) $out['avisos'][] = "Ajustes [fuera_menu]: $clave no es una vista en el código.";
        }
        foreach ($ajustes['usos'] as $vista => $usados) {
            if (!isset($out['vistas'][$vista])) $out['avisos'][] = "Ajustes [usos]: la vista $vista no existe.";
            foreach ($usados as $u) if (!isset($todo[$u])) $out['avisos'][] = "Ajustes [usos]: $u no existe en el código.";
        }
        foreach ($out['vistas'] as $clave => $v) {
            if ($v['menu'] && $v['grupo'] !== '' && !isset($ajustes['grupos'][$v['grupo']])) $out['avisos'][] = "La vista $clave usa el grupo '{$v['grupo']}', que no está en los ajustes.";
            // La sección del menú es el módulo: la vista de un controller va en la sección de su MODULE. Un módulo sin sección
            // en 'grupos' ('portal', 'suscripciones') va arriba, sin sección.
            $modulo = $out['componentes'][$v['componente']]['modulo'] ?? '';
            $esperado = isset($ajustes['grupos'][$modulo]) ? $modulo : '';
            if ($v['menu'] && $v['grupo'] !== $esperado) $out['avisos'][] = "La vista $clave está en la sección '{$v['grupo']}' pero su controller es del módulo '$modulo'.";
        }
        return $out;
    }

    /**
     * Huella del catálogo que describe el código: sha256 de su contenido normalizado (sin fechas).
     * La guarda cada carga en CatalogoVersion; comparar.php y la pantalla la recalculan para saber si la base está al día.
     */
    public static function fingerprint(array $codigo, ?array $ajustes = null): string
    {
        $ajustes ??= self::ajustes();
        $vistas  = $codigo['vistas'];
        $metodos = $codigo['metodos'];
        ksort($vistas);
        ksort($metodos);
        $componentes = $codigo['componentes'];
        ksort($componentes);
        return hash('sha256', json_encode([$ajustes['grupos'], $componentes, $vistas, $metodos, $ajustes['usos']], JSON_UNESCAPED_UNICODE));
    }

    /**
     * Componentes del código contra los de la base (filas de sp_componente_listar): lista de diferencias
     * [qué, clave, detalle]. Un archivo, carpeta o conexión equivocados harían que el router cargue mal el componente.
     */
    public static function compareComponents(array $codigo, array $enBase): array
    {
        $base = [];
        foreach ($enBase as $c) $base[$c['Clave']] = $c;
        $dif = [];
        foreach ($codigo['componentes'] as $clave => $c) {
            $b = $base[$clave] ?? null;
            if ($b === null || $b['Archivo'] === '') { $dif[] = ['componente sin registrar', $clave, 'el router no sabría qué archivo cargar']; continue; }
            foreach (['clase' => 'Clase', 'archivo' => 'Archivo', 'modulo' => 'Modulo', 'conexion' => 'Conexion'] as $k => $col) {
                if ((string) $b[$col] !== (string) $c[$k]) $dif[] = [$col, $clave, "base '{$b[$col]}' · código '{$c[$k]}'"];
            }
        }
        return $dif;
    }

    /**
     * Código contra base: lista de diferencias [qué, clave, detalle]. $enBase son las filas de sp_objeto_listar.
     * Vacía = la base describe exactamente lo que hay en el código.
     */
    public static function compare(array $codigo, array $enBase): array
    {
        $enCodigo = $codigo['vistas'] + $codigo['metodos'];
        $porId = $objetos = [];
        foreach ($enBase as $o) $porId[(int) $o['IdObjeto']] = $o;
        foreach ($enBase as $o) {
            if ((int) $o['Tipo'] === 1) continue;
            $o['PadreClave'] = (int) $o['Tipo'] === 3 && $o['IdPadre'] !== null ? ($porId[(int) $o['IdPadre']]['Clave'] ?? '') : null;
            $objetos["{$o['Componente']}/{$o['Clave']}"] = $o;
        }

        $dif = [];
        foreach ($enCodigo as $clave => $c) {
            $b = $objetos[$clave] ?? null;
            if ($b === null)                 { $dif[] = ['falta en la base', $clave, 'da 404 hasta que se registre']; continue; }
            if ((int) $b['Activo'] !== 1)    { $dif[] = ['inactivo en la base', $clave, 'daría 404']; }
            $tipo = isset($codigo['vistas'][$clave]) ? 2 : 3;
            if ((int) $b['Tipo'] !== $tipo)                 $dif[] = ['tipo', $clave, "base {$b['Tipo']} · código $tipo"];
            if ((int) $b['SoloPost'] !== $c['soloPost'])    $dif[] = ['SoloPost', $clave, "base {$b['SoloPost']} · código {$c['soloPost']}"];
            if ($b['Acceso'] !== $c['acceso'])              $dif[] = ['Acceso', $clave, "base {$b['Acceso']} · código {$c['acceso']}"];
            if ((int) $b['Sensible'] !== $c['sensible'])    $dif[] = ['Sensible', $clave, "base {$b['Sensible']} · código {$c['sensible']}"];
            if ($tipo === 3 && (string) $b['PadreClave'] !== $c['padre'])
                                                            $dif[] = ['padre', $clave, "base '{$b['PadreClave']}' · código '{$c['padre']}'"];
        }
        foreach ($objetos as $clave => $b) {
            if ((int) $b['Activo'] === 1 && !isset($enCodigo[$clave])) $dif[] = ['sobra en la base', $clave, 'ya no existe en el código'];
        }
        return $dif;
    }

    /** Lo que solo lee no exige POST. */
    public static function isRead(string $metodo, bool $esVista): bool
    {
        return $esVista
            || preg_match('/^(listar|obtener)/', $metodo)
            || in_array($metodo, ['catalogos', 'contexto', 'semana', 'resumen', 'detalle', 'desglose', 'ver', 'vistaPrevia', 'grupos'], true);
    }

    /** 'listarEmpresa' → 'Listar empresa'. */
    public static function human(string $metodo): string
    {
        return ucfirst(strtolower(preg_replace('/(?<!^)([A-Z])/', ' $1', $metodo)));
    }
}
