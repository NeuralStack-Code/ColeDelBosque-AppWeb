<?php
// La pinta DesarrolladorController::index() (/desarrollador). Recibe $nombre y $base. Cada acceso se ve si el
// perfil de la cuenta lo concede (can(), catálogo de permisos).
$title    = 'Panel del desarrollador | Colegio del Bosque';
$extraCss = ['admin.css'];
$img      = $base . '/webService/wwwroot/img';

function olaSep(string $fill = 'rgba(91,62,224,.07)'): void { ?>
<div class="ola-sep"><svg viewBox="0 0 1440 46" preserveAspectRatio="none" xmlns="http://www.w3.org/2000/svg"><path fill="<?= $fill ?>" d="M0,24 C180,4 360,44 540,26 C720,8 900,44 1080,28 C1260,14 1350,30 1440,24 L1440,46 L0,46 Z"/></svg></div>
<?php }
?>
<!DOCTYPE html>
<html lang="es">
<head><?php require __DIR__ . '/../partials/head.php'; ?></head>
<body>
<?php require __DIR__ . '/../partials/notificador.php'; ?>

    <header class="panel-top">
        <div class="contenedor inner">
            <a href="<?= $base ?>/desarrollador" class="marca">
                <img src="<?= $img ?>/logo.png" alt="Colegio del Bosque">
                <span>Colegio del Bosque<small>Panel del desarrollador</small></span>
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

    <section class="admin-hero">
        <span class="eyebrow">Panel del desarrollador</span>
        <h1>Hola, <?= htmlspecialchars($nombre) ?> 🛠️</h1>
        <p>Cuentas, perfiles con sus permisos y tablas del sistema.</p>
    </section>

    <?php olaSep(); ?>

    <section class="stats" id="stats">
        <div class="stat-card"><span class="s-ico"><svg class="ic-s" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M22 21v-2a4 4 0 0 0-3-3.9"/></svg></span><div><div class="s-num" id="m-cuentas">—</div><div class="s-lbl">Cuentas</div></div></div>
        <div class="stat-card"><span class="s-ico"><svg class="ic-s" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 2 2 7l10 5 10-5-10-5Z"/><path d="m2 17 10 5 10-5M2 12l10 5 10-5"/></svg></span><div><div class="s-num" id="m-perfiles">—</div><div class="s-lbl">Perfiles</div></div></div>
        <div class="stat-card"><span class="s-ico"><svg class="ic-s" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><ellipse cx="12" cy="5" rx="9" ry="3"/><path d="M3 5v14c0 1.7 4 3 9 3s9-1.3 9-3V5"/><path d="M3 12c0 1.7 4 3 9 3s9-1.3 9-3"/></svg></span><div><div class="s-num" id="m-tablas">—</div><div class="s-lbl">Tablas</div></div></div>
    </section>

    <?php olaSep('rgba(34,197,94,.07)'); ?>

    <h3 class="admin-seccion-tit">Accesos</h3>
    <div class="accesos-lista">
        <div class="acceso-panel">
            <?php if (can('cuenta/index')): ?>
            <a class="acceso" href="<?= $base ?>/cuenta/index">
                <span class="a-ico"><svg class="ic-s" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M22 21v-2a4 4 0 0 0-3-3.9"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg></span>
                <span class="a-txt"><strong>Cuentas</strong><span>Altas, bajas y qué perfiles tiene cada cuenta</span></span>
                <span class="chev"><svg class="ic-s" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m9 18 6-6-6-6"/></svg></span>
            </a>
            <?php endif; ?>
            <?php if (can('perfilacceso/index')): ?>
            <a class="acceso" href="<?= $base ?>/perfilacceso/index">
                <span class="a-ico"><svg class="ic-s" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M9 12l2 2 4-4"/><path d="M12 2a10 10 0 1 0 10 10A10 10 0 0 0 12 2Z"/></svg></span>
                <span class="a-txt"><strong>Perfiles y permisos</strong><span>Qué pantallas y acciones concede cada perfil</span></span>
                <span class="chev"><svg class="ic-s" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m9 18 6-6-6-6"/></svg></span>
            </a>
            <?php endif; ?>
            <?php if (can('tablas/index')): ?>
            <a class="acceso" href="<?= $base ?>/tablas/index">
                <span class="a-ico"><svg class="ic-s" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><ellipse cx="12" cy="5" rx="9" ry="3"/><path d="M3 5v14c0 1.7 4 3 9 3s9-1.3 9-3V5"/><path d="M3 12c0 1.7 4 3 9 3s9-1.3 9-3"/></svg></span>
                <span class="a-txt"><strong>Tablas</strong><span>Visor de solo lectura de la base de datos</span></span>
                <span class="chev"><svg class="ic-s" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m9 18 6-6-6-6"/></svg></span>
            </a>
            <?php endif; ?>
        </div>
    </div>

    <script>
        function cerrarSesion() {
            fetch(window.BASE_URL + '/auth/salir', { method: 'POST' })
                .finally(() => location.href = window.BASE_URL + '/auth/entrar');
        }
        (async () => {
            try {
                // Cada cifra por separado: si el perfil no concede una, esa se queda en "—".
                const contar = (url, id) => fetch(window.BASE_URL + url).then(r => r.json())
                    .then(r => { if (r.success) document.getElementById(id).textContent = (r.data.items || []).length; })
                    .catch(() => {});
                await Promise.all([
                    contar('/cuenta/listar', 'm-cuentas'),
                    contar('/perfilacceso/listar', 'm-perfiles'),
                    contar('/tablas/listar', 'm-tablas'),
                ]);
            } catch { /* silencio */ }
        })();
    </script>
</body>
</html>
