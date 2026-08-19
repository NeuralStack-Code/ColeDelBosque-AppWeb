<?php
function loadEnv(string $path): void {
    if (!file_exists($path)) {
        error_log('Archivo .env no encontrado en: ' . $path);
        return;
    }

    $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (strpos(trim($line), '#') === 0) continue;
        if (strpos($line, '=') === false) continue;

        [$key, $value] = explode('=', $line, 2);
        $key   = trim($key);
        $value = trim($value);

        if (!empty($key)) {
            $_ENV[$key] = $value;
        }
    }
}

loadEnv(dirname(__DIR__, 2) . '/.env');