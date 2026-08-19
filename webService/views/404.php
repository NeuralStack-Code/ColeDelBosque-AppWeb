<?php
if (!defined('BASE_URL')) require_once __DIR__ . '/../../apiService/core/config.php';
$base = BASE_URL;
?>
<!DOCTYPE html>
<html lang="es">
<head><?php require __DIR__ . '/partials/head.php'; ?></head>
<body>
<?php require __DIR__ . '/partials/header.php'; ?>

<section style="min-height:60vh;display:flex;flex-direction:column;align-items:center;justify-content:center;text-align:center;padding:4rem 2rem;">
    <h1 style="font-family:var(--ns-font-display,'sans-serif');font-size:6rem;color:var(--ns-color-text,#222);line-height:1;">404</h1>
    <p style="font-size:1.2rem;margin:1rem 0 2rem;color:var(--ns-color-text,#222);">Página no encontrada.</p>
    <a href="<?= $base ?>/" style="padding:0.75rem 2rem;background:var(--ns-success,#4a7c59);color:#fff;text-decoration:none;">Volver al inicio</a>
</section>

<?php require __DIR__ . '/partials/footer.php'; ?>
</body>
</html>