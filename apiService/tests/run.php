<?php
/**
 * Todas las pruebas del colegio con un solo comando:
 *
 *   php apiService/tests/run.php              todas las suites, una tras otra
 *   php apiService/tests/run.php <suite> …    solo esas (por nombre de carpeta)
 *   php apiService/tests/run.php --sin-base   solo las que no necesitan base de datos (lo que corre la CI)
 *
 * Las suites con base usan el entorno de pruebas (.env.pruebas o PRUEBAS_ENV, ver lib.php), nunca el .env de
 * producción, y se niegan a correr si la base no es local (_local). Termina con 0 si todas pasaron y 1 si alguna falló.
 */
if (PHP_SAPI !== 'cli') exit("Solo desde la terminal.\n");

/** Orden y si necesitan base de datos. Una carpeta nueva con run.php que no esté aquí corre al final (con base). */
const SUITES = ['unit' => false, 'permisos' => true, 'rutas' => true];

$args    = array_slice($argv, 1);
$sinBase = in_array('--sin-base', $args, true);
$pedidas = array_values(array_filter($args, fn($a) => !str_starts_with($a, '--')));

$todas = SUITES;
foreach (glob(__DIR__ . '/*/run.php') ?: [] as $file) {
    $nombre = basename(dirname($file));
    if (!array_key_exists($nombre, $todas)) $todas[$nombre] = true;
}
if ($pedidas && ($faltan = array_diff($pedidas, array_keys($todas)))) exit('No existe la suite: ' . implode(', ', $faltan) . "\n");
$elegidas = array_filter($todas, fn($base, $nombre) => (!$pedidas || in_array($nombre, $pedidas, true)) && (!$sinBase || !$base),
                         ARRAY_FILTER_USE_BOTH);

$resumen = [];
$inicio  = microtime(true);
foreach (array_keys($elegidas) as $suite) {
    echo "\n\033[1m══ $suite ══\033[0m\n";
    $t0 = microtime(true);
    passthru(escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg(__DIR__ . "/$suite/run.php"), $rc);
    $resumen[$suite] = [$rc, microtime(true) - $t0];
}

echo "\n\033[1m══ Resumen ══\033[0m\n";
$fallaron = 0;
foreach ($resumen as $suite => [$rc, $segundos]) {
    printf("  %s %-12s %5.1f s\n", $rc === 0 ? "\033[32m✓\033[0m" : "\033[31m✗\033[0m", $suite, $segundos);
    if ($rc !== 0) $fallaron++;
}
printf("\n%s%d de %d suites pasaron\033[0m (%.0f s)\n", $fallaron ? "\033[31m" : "\033[32m", count($resumen) - $fallaron,
       count($resumen), microtime(true) - $inicio);
exit($fallaron ? 1 : 0);
