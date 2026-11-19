<?php
/**
 * Crea (o deja como están) las cuentas de prueba del entorno local: una por tipo de cuenta, cada una con su perfil.
 *
 *   php entorno-local/cuentas_prueba.php "<carpeta del código del colegio>"
 *
 * Usa la base y las llaves de entorno-local/.env.local (nunca producción) y el mismo cifrado del sitio para la matrícula.
 * Se puede correr cuantas veces se quiera: no duplica.
 */
if (PHP_SAPI !== 'cli') exit;

$app = rtrim(str_replace('\\', '/', $argv[1] ?? ''), '/');
if ($app === '' || !is_file($app . '/webService/libs/Cifrado.php')) {
    fwrite(STDERR, "Uso: php entorno-local/cuentas_prueba.php \"<carpeta del código del colegio>\"\n");
    exit(2);
}
$archivo = __DIR__ . '/.env.local';
if (!is_file($archivo)) { fwrite(STDERR, "Falta entorno-local/.env.local (cópialo de .env.local.example).\n"); exit(2); }
foreach (file($archivo, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $linea) {
    $linea = trim($linea);
    if ($linea === '' || $linea[0] === '#' || !str_contains($linea, '=')) continue;
    [$k, $v] = array_map('trim', explode('=', $linea, 2));
    $_ENV[$k] = trim($v, "\"'");
}
if (!in_array($_ENV['DB_HOST'] ?? '', ['127.0.0.1', 'localhost', '::1'], true) || !str_ends_with($_ENV['DB_NAME'] ?? '', '_local')) {
    fwrite(STDERR, "DB_HOST debe ser local y DB_NAME terminar en _local. No se toca otra base.\n");
    exit(2);
}
require $app . '/webService/libs/Cifrado.php';

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
$db = new mysqli($_ENV['DB_HOST'], $_ENV['DB_USER'] ?? 'root', $_ENV['DB_PASSWORD'] ?? '', $_ENV['DB_NAME'],
                 (int) ($_ENV['DB_PORT'] ?? 3306));
$db->set_charset('utf8mb4');

// matrícula => [nombre, paterno, tipo de cuenta (permiso_id), perfil]
$cuentas = [
    'ADM-LOCAL' => ['Ana',    'Administradora', 1, 'Administrador'],
    'MAE-LOCAL' => ['Marta',  'Maestra',        2, 'Maestro'],
    'ALU-LOCAL' => ['Pedro',  'Alumno',         3, 'Padre'],
    'DEV-LOCAL' => ['Diana',  'Desarrolladora', 4, 'Desarrollador'],
];

foreach ($cuentas as $matricula => [$nombre, $paterno, $tipo, $perfil]) {
    $cifrada = Cifrado::encrypt($matricula);
    $st = $db->prepare('SELECT id_cuenta FROM cuenta WHERE matricula = ?');
    $st->bind_param('s', $cifrada);
    $st->execute();
    $id = (int) ($st->get_result()->fetch_column() ?: 0);

    if ($id === 0) {
        $st = $db->prepare('INSERT INTO usuario (nombre, paterno, materno) VALUES (?, ?, NULL)');   // materno nunca obligatorio
        $st->bind_param('ss', $nombre, $paterno);
        $st->execute();
        $idUsuario = $db->insert_id;
        $st = $db->prepare('INSERT INTO cuenta (matricula, usuario_id, permiso_id) VALUES (?, ?, ?)');
        $st->bind_param('sii', $cifrada, $idUsuario, $tipo);
        $st->execute();
        $id = $db->insert_id;
        $estado = 'creada';
    } else {
        $estado = 'ya existía';
    }

    $st = $db->prepare('INSERT IGNORE INTO CuentaPerfiles (IdCuenta, IdPerfil) SELECT ?, IdPerfil FROM Perfiles WHERE Nombre = ?');
    $st->bind_param('is', $id, $perfil);
    $st->execute();
    printf("  %-10s %-14s cuenta %d (%s)\n", $matricula, $perfil, $id, $estado);
}
echo "Listo. Se entra en /auth/entrar con la matrícula (ADM-LOCAL, MAE-LOCAL, ALU-LOCAL o DEV-LOCAL).\n";
