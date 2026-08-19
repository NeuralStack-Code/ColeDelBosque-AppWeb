<?php
$rol = 2; // solo maestros
require __DIR__ . '/../partials/guard.php';

$base     = BASE_URL;
$title    = 'Panel del maestro | Colegio del Bosque';
$extraCss = ['maestro.css'];
$img      = $base . '/webService/wwwroot/img';
$nombre   = $_SESSION['usuario'] ?? 'Maestro';
?>
<!DOCTYPE html>
<html lang="es">
<head><?php require __DIR__ . '/../partials/head.php'; ?></head>
<body>
<?php require __DIR__ . '/../partials/notificador.php'; ?>

    <!-- Topbar -->
    <header class="panel-top">
        <div class="contenedor inner">
            <a href="<?= $base ?>/maestro" class="marca">
                <img src="<?= $img ?>/logo.png" alt="Colegio del Bosque">
                <span>Colegio del Bosque<small>Panel del maestro</small></span>
            </a>
            <div style="display:flex;align-items:center;gap:1.2em;">
                <span class="user">Hola, <strong><?= htmlspecialchars($nombre) ?></strong></span>
                <button class="btn-salir" onclick="cerrarSesion()">
                    <svg class="ic-s" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><path d="m16 17 5-5-5-5"/><path d="M21 12H9"/></svg>
                    Cerrar sesión
                </button>
            </div>
        </div>
    </header>

    <!-- Bienvenida -->
    <section class="panel-hero">
        <span class="eyebrow">Panel de actividades</span>
        <h1>Bienvenido, <?= htmlspecialchars($nombre) ?> 👋</h1>
        <p>Gestiona las tareas y calificaciones de tu grupo.</p>
    </section>

    <!-- Tiles -->
    <section class="panel-tiles">
        <a class="tile" href="<?= $base ?>/maestro/calificaciones">
            <span class="tile-ico"><svg class="ic-s" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 3v18h18"/><rect x="7" y="12" width="3" height="6"/><rect x="12" y="8" width="3" height="10"/><rect x="17" y="5" width="3" height="13"/></svg></span>
            <h3>Reportes y calificaciones</h3>
            <p>Registra calificaciones y reportes de tus alumnos.</p>
        </a>
    </section>

    <script>
        async function cerrarSesion() {
            try {
                const r = await fetch(BASE_URL + '/api/auth?action=logout', { method: 'POST' });
                const data = await r.json();
                if (data.redirect) { location.href = data.redirect; return; }
            } catch {}
            location.href = BASE_URL + '/inicio-sesion';
        }
    </script>
</body>
</html>
