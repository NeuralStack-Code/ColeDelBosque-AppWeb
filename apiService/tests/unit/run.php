<?php
/**
 * Pruebas unitarias del colegio. NO necesitan base de datos ni .env (las corre la CI en cada push).
 *
 *   php apiService/tests/unit/run.php
 *
 * 1) Cifrado (matrículas, esquema anterior): ida y vuelta, y que sea DETERMINISTA (se busca WHERE matricula = ?).
 * 2) Crypto (framework, v2 GCM): ida y vuelta, cada cifrado distinto, y un texto alterado no se descifra.
 * 3) Catálogo leído del código: sin avisos, cada controller con su módulo, todas las vistas existen.
 * 4) Las pantallas que pintan los controllers ($this->view('…')) existen en webService/views.
 */
require __DIR__ . '/../lib.php';
$root = realpath(RAIZ);

// Llaves solo de prueba (nada de producción).
$_ENV['CRYPTO_KEY']       = 'clave-de-prueba-unitaria-32bytes';
$_ENV['CRYPTO_IV']        = 'iv-de-prueba-16b';
$_ENV['CRYPTO_INDEX_KEY'] = 'indice-de-prueba-unitaria-32byte';

require_once $root . '/apiService/core/autoload.php';
require_once $root . '/webService/libs/Cifrado.php';
require_once $root . '/webService/libs/CatalogoCodigo.php';

titulo('1) Cifrado de matrículas');
foreach (['ADM-1', 'A0001234', 'ñandú-Ü', ''] as $texto) {
    $c = Cifrado::encrypt($texto);
    ok(Cifrado::decrypt($c) === $texto, "Cifrado ida y vuelta de '$texto'");
    ok(Cifrado::encrypt($texto) === $c, "Cifrado es determinista para '$texto' (la búsqueda por matrícula depende de eso)");
}
ok(Cifrado::encrypt('A1') !== Cifrado::encrypt('A2'), 'Cifrado: matrículas distintas dan textos distintos');

titulo('2) Crypto (framework)');
// Crypto pide CRYPTO_KEY en base64 de 32 bytes; la del colegio es la cadena que usa Cifrado. Comparten nombre, así que
// el sitio NO debe llamar a Crypto hasta migrar las matrículas (y su llave) a v2.
$usos = [];
foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator("$root/webService", FilesystemIterator::SKIP_DOTS)) as $f) {
    if ($f->getExtension() === 'php' && str_contains((string) file_get_contents($f->getPathname()), 'Crypto::')) $usos[] = $f->getFilename();
}
ok($usos === [], 'El sitio no usa Crypto (su CRYPTO_KEY es la de Cifrado)' . ($usos ? ': ' . implode(', ', $usos) : ''));
$llaveCifrado = $_ENV['CRYPTO_KEY'];
$_ENV['CRYPTO_KEY'] = base64_encode(str_repeat('k', 32));
$_ENV['CRYPTO_INDEX_KEY'] = base64_encode(str_repeat('i', 32));
$a = Crypto::encrypt('dato sensible');
$b = Crypto::encrypt('dato sensible');
ok(Crypto::decrypt($a) === 'dato sensible', 'Crypto ida y vuelta');
ok($a !== $b, 'Crypto: el mismo texto se cifra distinto cada vez');
$alterado = substr($a, 0, -2) . (substr($a, -2) === 'AA' ? 'BB' : 'AA');
ok(Crypto::decrypt($alterado) === null, 'Crypto: un texto alterado no se descifra');
ok(Crypto::blindIndex('X') === Crypto::blindIndex('X') && Crypto::blindIndex('X') !== Crypto::blindIndex('Y'),
   'Crypto: el índice ciego es estable y distingue valores');
$_ENV['CRYPTO_KEY'] = $llaveCifrado;

titulo('3) Catálogo leído del código');
$codigo = CatalogoCodigo::read($root);
ok($codigo['avisos'] === [], 'Sin avisos del catálogo' . ($codigo['avisos'] ? ': ' . implode(' | ', $codigo['avisos']) : ''));
ok(count($codigo['componentes']) >= 20, 'Se leen los componentes (' . count($codigo['componentes']) . ')');
foreach ($codigo['componentes'] as $clave => $c) {
    ok(is_dir("$root/webService/business/{$c['modulo']}"), "$clave: su módulo '{$c['modulo']}' existe");
}
$publicos = array_keys(array_filter($codigo['metodos'] + $codigo['vistas'], fn($m) => ($m['acceso'] ?? '') === 'publico'));
sort($publicos);
ok($publicos === ['auth/entrar', 'auth/salir', 'contacto/enviar', 'contacto/index'],
   'Solo entrar, salir y contacto son públicos (son: ' . implode(', ', $publicos) . ')');
$sensibles = array_keys(array_filter($codigo['metodos'] + $codigo['vistas'], fn($m) => (int) ($m['sensible'] ?? 0) === 1));
foreach (['cuenta/guardar', 'cuenta/perfiles', 'perfilacceso/guardar'] as $s) {
    ok(in_array($s, $sensibles, true), "$s es sensible (solo lo concede un administrador del sistema)");
}

titulo('4) Pantallas de los controllers');
foreach (glob("$root/webService/controllers/*.php") as $archivo) {
    preg_match_all("/->view\\('([^']+)'/", (string) file_get_contents($archivo), $m);
    foreach (array_unique($m[1]) as $vista) {
        ok(is_file("$root/webService/views/$vista.php"), basename($archivo) . ": existe la vista $vista");
    }
}

terminar();
