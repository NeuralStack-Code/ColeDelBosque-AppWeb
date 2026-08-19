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
<link rel="stylesheet" href="<?= $base ?>/webService/wwwroot/CSS/reset.css">
<link rel="stylesheet" href="<?= $base ?>/webService/wwwroot/CSS/style.css">

<!-- Confirmador global (window.confirmar) -->
<script src="<?= $base ?>/webService/wwwroot/JavaScript/confirm.js" defer></script>

<!-- CSS extra por página -->
<?php foreach ($extraCss as $css): ?>
<link rel="stylesheet" href="<?= $base ?>/webService/wwwroot/CSS/<?= htmlspecialchars($css) ?>">
<?php endforeach; ?>

<!-- JS extra por página -->
<?php foreach ($extraJs as $js): ?>
<script src="<?= $base ?>/webService/wwwroot/JavaScript/<?= htmlspecialchars($js) ?>"></script>
<?php endforeach; ?>