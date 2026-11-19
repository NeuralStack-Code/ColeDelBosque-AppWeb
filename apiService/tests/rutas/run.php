<?php
/**
 * Pruebas de la tabla Rutas contra el sitio levantado (base LOCAL, ver lib.php).
 *
 *   php apiService/tests/rutas/run.php
 *
 * Cada ruta activa: una página suelta responde 200 y su archivo existe; una redirección responde su código (301) y
 * manda a su destino, que a su vez existe (una ruta de la tabla o una pantalla del catálogo).
 */
require __DIR__ . '/../lib.php';
$env  = entornoPruebas();
$root = realpath(RAIZ);
$base = levantar(8792, $env);
$cli  = new Cliente($base);

$rutas = filas("SELECT Ruta, Tipo, Destino, CodigoHttp FROM Rutas WHERE Activo = 1 ORDER BY Tipo, Ruta");
ok(count($rutas) > 10, 'La tabla Rutas tiene registros (' . count($rutas) . ')');
$pantallas = array_column(filas("SELECT CONCAT('/', co.Clave, '/', o.Clave) AS Ruta FROM Objetos o
                                   JOIN Componentes co ON co.IdComponente = o.IdComponente
                                  WHERE o.Activo = 1 AND o.IdTipoObjeto = 2"), 'Ruta');
$sueltas = array_map(fn($r) => '/' . $r['Ruta'], array_filter($rutas, fn($r) => $r['Tipo'] === 'vista'));

titulo('Páginas sueltas');
foreach ($rutas as $r) {
    if ($r['Tipo'] !== 'vista') continue;
    ok(is_file("$root/{$r['Destino']}"), "/{$r['Ruta']}: existe {$r['Destino']}");
    $resp = $cli->get('/' . $r['Ruta']);
    ok($resp['status'] === 200, "/{$r['Ruta']} → 200 (dio {$resp['status']})");
}

titulo('Redirecciones');
foreach ($rutas as $r) {
    if ($r['Tipo'] !== 'redireccion') continue;
    $resp = $cli->get('/' . $r['Ruta']);
    ok($resp['status'] === (int) $r['CodigoHttp'] && $resp['location'] === $r['Destino'],
       "/{$r['Ruta']} → {$r['CodigoHttp']} {$r['Destino']} (dio {$resp['status']} {$resp['location']})");
    $destino = rtrim((string) parse_url($r['Destino'], PHP_URL_PATH), '/') ?: '/';
    ok(in_array($destino, $pantallas, true) || in_array($destino, $sueltas, true) || $destino === '/',
       "/{$r['Ruta']}: su destino $destino existe (pantalla del catálogo o página suelta)");
}

terminar();
