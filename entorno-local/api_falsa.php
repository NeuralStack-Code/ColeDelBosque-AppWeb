<?php
/**
 * Api central falsa para el entorno local: responde licencia válida y guarda los correos en correos.log (no sale nada).
 *
 *   php -S 127.0.0.1:8788 "<esta carpeta>/api_falsa.php"
 */
header('Content-Type: application/json');
$ruta = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH) ?: '/';
if (str_ends_with($ruta, '/mail/send')) {
    file_put_contents(__DIR__ . '/correos.log', date('c') . ' ' . file_get_contents('php://input') . PHP_EOL, FILE_APPEND);
}
echo json_encode([
    'success' => true, 'status' => 200, 'message' => 'ok',
    'data'    => ['proyecto' => 'colegio-local', 'plan' => 'local', 'valid' => true],
]);
