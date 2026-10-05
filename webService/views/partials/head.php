<?php
/**
 * Partial: <head>
 * Variables esperadas (definir antes de incluir):
 *   $title    string  Título de la página
 *   $extraCss array   Archivos CSS adicionales de wwwroot/CSS/
 *   $extraJs  array   Archivos JS adicionales de wwwroot/JavaScript/
 */
$title    = $title    ?? 'Colegio del Bosque';
$extraCss = $extraCss ?? [];
$extraJs  = $extraJs  ?? [];
if (!defined('BASE_URL')) require_once __DIR__ . '/../../../apiService/core/config.php';
$base = BASE_URL;

// Versión por fecha de modificación: al subir un CSS/JS nuevo el navegador lo vuelve a pedir
// (sin esto se queda con la copia vieja en caché y la página se ve desacomodada).
$wwwroot = __DIR__ . '/../../wwwroot/';
$ver = static fn(string $rel): string => $rel . '?v=' . (@filemtime($wwwroot . $rel) ?: 1);
?>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<link rel="icon" href="<?= $base ?>/webService/wwwroot/img/logo.png">
<title><?= htmlspecialchars($title) ?></title>

<!-- Base URL para JS -->
<script>window.BASE_URL = '<?= $base ?>';</script>

<!-- Fuentes -->
<link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:opsz,wght,FILL,GRAD@20..48,100..700,0..1,-50..200" rel="stylesheet">
<link href="https://fonts.googleapis.com/css2?family=Fredoka:wght@400;500;600;700&family=Nunito:wght@400;600;700;800&family=Dancing+Script:wght@600;700&display=swap" rel="stylesheet">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>

<!-- CSS base -->
<link rel="stylesheet" href="<?= $base ?>/webService/wwwroot/<?= $ver('CSS/reset.css') ?>">
<link rel="stylesheet" href="<?= $base ?>/webService/wwwroot/<?= $ver('CSS/style.css') ?>">

<!-- Confirmador global (window.confirmar) -->
<script src="<?= $base ?>/webService/wwwroot/<?= $ver('JavaScript/confirm.js') ?>" defer></script>

<!-- CSS extra por página -->
<?php foreach ($extraCss as $css): ?>
<link rel="stylesheet" href="<?= $base ?>/webService/wwwroot/<?= htmlspecialchars($ver('CSS/' . $css)) ?>">
<?php endforeach; ?>

<!-- JS extra por página -->
<?php foreach ($extraJs as $js): ?>
<script src="<?= $base ?>/webService/wwwroot/<?= htmlspecialchars($ver('JavaScript/' . $js)) ?>"></script>
<?php endforeach; ?>