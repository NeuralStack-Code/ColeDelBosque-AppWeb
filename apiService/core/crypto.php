<?php
// Asegura que $_ENV esté cargado (llave/IV viven en el .env)
require_once __DIR__ . '/env.php';

define('METHOD', 'AES-256-CBC');
// Llave e IV desde el .env. El fallback mantiene compatibilidad con datos ya encriptados.
define('SECRET_KEY', $_ENV['CRYPTO_KEY'] ?? 'Hg25Ls4');
define('SECRET_IV', $_ENV['CRYPTO_IV'] ?? '101712'); // Manteniendo el IV constante

// Función para encriptar un string
function encrypt($string) {
    $key = hash('sha256', SECRET_KEY);
    $iv = substr(hash('sha256', SECRET_IV), 0, 16); // IV constante
    $output = openssl_encrypt($string, METHOD, $key, 0, $iv);
    $output = base64_encode($output);
    return $output;
}

// Función para desencriptar un string
function decrypt($string) {
    $key = hash('sha256', SECRET_KEY);
    $iv = substr(hash('sha256', SECRET_IV), 0, 16); // IV constante
    $output = openssl_decrypt(base64_decode($string), METHOD, $key, 0, $iv);
    return $output;
}

