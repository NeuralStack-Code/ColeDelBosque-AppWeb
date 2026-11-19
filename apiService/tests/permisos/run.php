<?php
/**
 * Pruebas del catálogo de permisos contra el sitio levantado (base LOCAL, ver lib.php).
 *
 *   php apiService/tests/permisos/run.php
 *
 * Crea una cuenta de prueba por perfil (Administrador, Maestro, Padre, Desarrollador), entra con cada una por
 * /auth/entrar y recorre TODO el catálogo: lo que la base dice que puede (sp_objeto_autorizar) es lo que el sitio deja.
 *   - sin sesión: una pantalla manda a /auth/entrar y una acción responde 401
 *   - lo que no existe: 404 · una acción de escritura por GET: 405
 *   - con sesión: pantalla permitida 200, negada → a su pantalla de inicio; acción permitida no da 401/403, negada 403
 *   - casos de negocio: reporte/grupos solo Administrador; Perfiles y permisos solo Desarrollador
 * Las acciones se llaman con el formulario vacío (las validaciones cortan con 422): la base local es desechable.
 * Al terminar borra las cuentas de prueba.
 */
require __DIR__ . '/../lib.php';
$env = entornoPruebas();

$cuentas = cuentasDePrueba();
$base    = levantar(8791, $env);

$objetos = filas("SELECT co.Clave AS Componente, o.Clave AS Metodo, o.IdTipoObjeto AS Tipo, o.SoloPost, o.Acceso
                    FROM Objetos o JOIN Componentes co ON co.IdComponente = o.IdComponente
                   WHERE o.Activo = 1 AND o.IdTipoObjeto IN (2, 3) AND co.Clave <> ''
                ORDER BY co.Clave, o.IdTipoObjeto, o.Clave");
ok(count($objetos) > 50, 'El catálogo tiene pantallas y acciones (' . count($objetos) . ')');
// Lo que no se recorre: entrar/salir (cambian la sesión del recorrido) y lo público.
$recorrer = array_values(array_filter($objetos, fn($o) => $o['Acceso'] !== 'publico'));

titulo('Sin sesión');
$anon = new Cliente($base);
foreach ($recorrer as $o) {
    $ruta = "/{$o['Componente']}/{$o['Metodo']}";
    if ((int) $o['Tipo'] === 2) {
        $r = $anon->get($ruta);
        ok($r['status'] === 302 && $r['location'] === '/auth/entrar', "$ruta sin sesión → /auth/entrar (dio {$r['status']} {$r['location']})");
    } elseif ((int) $o['SoloPost'] === 1) {
        $r = $anon->post($ruta);
        ok($r['status'] === 401, "$ruta sin sesión → 401 (dio {$r['status']})");
    }
}
ok($anon->get('/auth/entrar')['status'] === 200, '/auth/entrar es pública');

titulo('Lo que no existe y el método equivocado');
ok($anon->get('/noexiste/nada')['status'] === 404, '/noexiste/nada → 404');
ok($anon->get('/grupo/inventado', false)['status'] === 404, '/grupo/inventado → 404');
$soloPost = array_values(array_filter($recorrer, fn($o) => (int) $o['Tipo'] === 3 && (int) $o['SoloPost'] === 1));
$r = $anon->get("/{$soloPost[0]['Componente']}/{$soloPost[0]['Metodo']}", false);
ok($r['status'] === 405, "GET a /{$soloPost[0]['Componente']}/{$soloPost[0]['Metodo']} (solo POST) → 405 (dio {$r['status']})");

foreach ($cuentas as $matricula => $c) {
    titulo("{$c['perfil']} ($matricula)");
    $inicio = (string) (filas('SELECT Inicio FROM Perfiles WHERE Nombre = ?', 's', [$c['perfil']])[0]['Inicio'] ?? '');
    $cli = new Cliente($base);
    $cli->get('/auth/entrar');
    $r = $cli->post('/auth/entrar', ['Matricula' => $matricula]);
    ok(($r['json']['data']['redirect'] ?? '') === $inicio, "entra y va a su inicio $inicio (va a " . ($r['json']['data']['redirect'] ?? '?') . ')');

    $permitidas = $negadas = 0;
    foreach ($recorrer as $o) {
        $ruta = "/{$o['Componente']}/{$o['Metodo']}";
        $puede = (int) (filas('CALL sp_objeto_autorizar(?, ?, ?)', 'iss', [$c['id'], $o['Componente'], $o['Metodo']])[0]['Permitido'] ?? 0) === 1;
        $puede ? $permitidas++ : $negadas++;
        if ((int) $o['Tipo'] === 2) {
            $r = $cli->get($ruta);
            if ($puede) ok($r['status'] === 200 || negocio404($r), "$ruta permitida → 200 (dio {$r['status']})");
            else        ok($r['status'] === 302 && $r['location'] === $inicio, "$ruta negada → $inicio (dio {$r['status']} {$r['location']})");
        } else {
            $r = (int) $o['SoloPost'] === 1 ? $cli->post($ruta) : $cli->get($ruta, false);
            if ($puede) ok(!in_array($r['status'], [401, 403, 405, 500, 0], true) && ($r['status'] !== 404 || negocio404($r)),
                           "$ruta permitida: no la bloquea el catálogo (dio {$r['status']})");
            else        ok($r['status'] === 403, "$ruta negada → 403 (dio {$r['status']})");
        }
    }
    echo "  $permitidas permitidas, $negadas negadas\n";

    $grupos = $cli->get('/reporte/grupos', false)['status'];
    if (in_array($c['perfil'], ['Administrador', 'Desarrollador'], true)) ok($grupos !== 403, "reporte/grupos permitido (dio $grupos)");
    else                                                                ok($grupos === 403, "reporte/grupos negado (dio $grupos)");
    $perfiles = $cli->get('/perfilacceso/index')['status'];
    ok(($c['perfil'] === 'Desarrollador') === ($perfiles === 200), "Perfiles y permisos solo para Desarrollador (dio $perfiles)");
}

terminar();

/** Un 404 del propio controller (no hay recibo, no hay datos), no del catálogo ("Acción no encontrada."). */
function negocio404(array $r): bool
{
    return $r['status'] === 404 && !str_contains($r['body'], 'Acción no encontrada');
}
