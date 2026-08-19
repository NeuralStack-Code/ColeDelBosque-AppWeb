<?php
require_once __DIR__ . '/env.php';

define('KEY_TOKEN', $_ENV['KEY_TOKEN'] ?? '');

// BASE_URL: vacío en producción y VS Code PHP Server.
// Solo definir en .env si la app está en subcarpeta (XAMPP local).
define('BASE_URL', $_ENV['BASE_URL'] ?? '');