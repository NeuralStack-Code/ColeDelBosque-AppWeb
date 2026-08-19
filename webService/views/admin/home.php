<?php
$rol = 1; // solo admin
require __DIR__ . '/../partials/guard.php';

$base     = BASE_URL;
$title    = 'Panel de administración | Colegio del Bosque';
$extraCss = ['admin.css'];
$img      = $base . '/webService/wwwroot/img';
$nombre   = $_SESSION['usuario'] ?? 'Administrador';

// Separador de onda reutilizable
function olaSep(string $fill = 'rgba(91,62,224,.07)'): void { ?>
<div class="ola-sep"><svg viewBox="0 0 1440 46" preserveAspectRatio="none" xmlns="http://www.w3.org/2000/svg"><path fill="<?= $fill ?>" d="M0,24 C180,4 360,44 540,26 C720,8 900,44 1080,28 C1260,14 1350,30 1440,24 L1440,46 L0,46 Z"/></svg></div>
<?php }
?>
<!DOCTYPE html>
<html lang="es">
<head><?php require __DIR__ . '/../partials/head.php'; ?></head>
<body>
<?php require __DIR__ . '/../partials/notificador.php'; ?>

    <!-- Topbar -->
    <header class="panel-top">
        <div class="contenedor inner">
            <a href="<?= $base ?>/administrador" class="marca">
                <img src="<?= $img ?>/logo.png" alt="Colegio del Bosque">
                <span>Colegio del Bosque<small>Panel de administración</small></span>
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
    <section class="admin-hero">
        <span class="eyebrow">Panel de administración</span>
        <h1>Bienvenido, <?= htmlspecialchars($nombre) ?> 👋</h1>
        <p>Resumen general del colegio.</p>
    </section>

    <?php olaSep(); ?>

    <!-- Indicadores -->
    <section class="stats" id="stats">
        <div class="stat-card"><span class="s-ico"><svg class="ic-s" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M22 21v-2a4 4 0 0 0-3-3.9"/></svg></span><div><div class="s-num" id="m-alumnos">—</div><div class="s-lbl">Alumnos</div></div></div>
        <div class="stat-card"><span class="s-ico"><svg class="ic-s" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 10 12 5 2 10l10 5 10-5Z"/><path d="M6 12v5c3 2.5 9 2.5 12 0v-5"/></svg></span><div><div class="s-num" id="m-maestros">—</div><div class="s-lbl">Maestros</div></div></div>
        <div class="stat-card"><span class="s-ico"><svg class="ic-s" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="7" height="7" rx="1"/><rect x="14" y="3" width="7" height="7" rx="1"/><rect x="3" y="14" width="7" height="7" rx="1"/><rect x="14" y="14" width="7" height="7" rx="1"/></svg></span><div><div class="s-num" id="m-grupos">—</div><div class="s-lbl">Grupos</div></div></div>
        <div class="stat-card"><span class="s-ico"><svg class="ic-s" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 1v22"/><path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/></svg></span><div><div class="s-num" id="m-saldo">—</div><div class="s-lbl">Saldo actual</div></div></div>
        <div class="stat-card"><span class="s-ico"><svg class="ic-s" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><path d="M12 6v6l4 2"/></svg></span><div><div class="s-num" id="m-pend">—</div><div class="s-lbl">Pagos pendientes</div></div></div>
    </section>

    <!-- Gráficas -->
    <h3 class="admin-seccion-tit">Resumen</h3>
    <section class="dash-charts">
        <div class="dash-card">
            <h4>Alumnos por grupo</h4>
            <div class="dash-bars" id="chartGrupos"></div>
        </div>
        <div class="dash-card">
            <h4>Colegiaturas</h4>
            <div class="donut-wrap">
                <div class="donut" id="donut" style="position:relative;"><span class="donut-num" id="donutNum">—</span></div>
                <div class="donut-leg">
                    <div><span class="dot" style="background:var(--verde)"></span> Pagadas <small id="legPag">—</small></div>
                    <div><span class="dot" style="background:var(--amarillo)"></span> Pendientes <small id="legPend">—</small></div>
                </div>
            </div>
        </div>
    </section>

    <?php olaSep('rgba(34,197,94,.07)'); ?>

    <!-- Accesos (lista) -->
    <h3 class="admin-seccion-tit">Accesos</h3>
    <div class="accesos-lista">
        <div class="acceso-panel">
            <a class="acceso" href="<?= $base ?>/administrador/alumnos">
                <span class="a-ico"><svg class="ic-s" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M22 21v-2a4 4 0 0 0-3-3.9"/></svg></span>
                <span class="a-txt"><strong>Alumnos</strong><span>Altas, edición y bajas</span></span>
                <span class="chev"><svg class="ic-s" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m9 18 6-6-6-6"/></svg></span>
            </a>
            <a class="acceso" href="<?= $base ?>/administrador/maestros">
                <span class="a-ico"><svg class="ic-s" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 10 12 5 2 10l10 5 10-5Z"/><path d="M6 12v5c3 2.5 9 2.5 12 0v-5"/></svg></span>
                <span class="a-txt"><strong>Maestros</strong><span>Altas, edición y bajas</span></span>
                <span class="chev"><svg class="ic-s" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m9 18 6-6-6-6"/></svg></span>
            </a>
            <a class="acceso" href="<?= $base ?>/administrador/grupos">
                <span class="a-ico"><svg class="ic-s" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="7" height="7" rx="1"/><rect x="14" y="3" width="7" height="7" rx="1"/><rect x="3" y="14" width="7" height="7" rx="1"/><rect x="14" y="14" width="7" height="7" rx="1"/></svg></span>
                <span class="a-txt"><strong>Grupos</strong><span>Grupos, materias y maestra</span></span>
                <span class="chev"><svg class="ic-s" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m9 18 6-6-6-6"/></svg></span>
            </a>
            <a class="acceso" href="<?= $base ?>/administrador/materias">
                <span class="a-ico"><svg class="ic-s" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M4 19.5A2.5 2.5 0 0 1 6.5 17H20"/><path d="M6.5 2H20v20H6.5A2.5 2.5 0 0 1 4 19.5v-15A2.5 2.5 0 0 1 6.5 2Z"/></svg></span>
                <span class="a-txt"><strong>Materias</strong><span>Catálogo de materias</span></span>
                <span class="chev"><svg class="ic-s" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m9 18 6-6-6-6"/></svg></span>
            </a>
            <a class="acceso" href="<?= $base ?>/administrador/recibos">
                <span class="a-ico"><svg class="ic-s" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M4 2v20l2-1 2 1 2-1 2 1 2-1 2 1 2-1 2 1V2l-2 1-2-1-2 1-2-1-2 1-2-1-2 1Z"/><path d="M8 7h8M8 11h8M8 15h5"/></svg></span>
                <span class="a-txt"><strong>Finanzas</strong><span>Recibos: ingresos y egresos</span></span>
                <span class="chev"><svg class="ic-s" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m9 18 6-6-6-6"/></svg></span>
            </a>
            <a class="acceso" href="<?= $base ?>/administrador/colegiaturas">
                <span class="a-ico"><svg class="ic-s" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="2" y="5" width="20" height="14" rx="2"/><path d="M2 10h20"/><path d="M6 15h4"/></svg></span>
                <span class="a-txt"><strong>Colegiaturas</strong><span>Registro y estatus de pagos</span></span>
                <span class="chev"><svg class="ic-s" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m9 18 6-6-6-6"/></svg></span>
            </a>
        </div>
    </div>

    <script>
        function cerrarSesion() {
            fetch(window.BASE_URL + '/api/auth?action=logout', { method: 'POST' })
                .finally(() => location.href = window.BASE_URL + '/inicio-sesion');
        }

        (async function dashboard() {
            let d;
            try { d = await (await fetch(window.BASE_URL + '/api/dashboard?action=resumen')).json(); }
            catch { return; }
            if (!d.success) return;
            const money = n => '$' + Number(n || 0).toLocaleString('es-MX', { minimumFractionDigits: 2 });

            document.getElementById('m-alumnos').textContent = d.alumnos;
            document.getElementById('m-maestros').textContent = d.maestros;
            document.getElementById('m-grupos').textContent = d.grupos;
            document.getElementById('m-saldo').textContent = money(d.saldo);
            document.getElementById('m-pend').textContent = d.pagos_pendientes;

            // Barras: alumnos por grupo
            const cont = document.getElementById('chartGrupos');
            const grupos = d.alumnos_por_grupo || [];
            if (!grupos.length) { cont.innerHTML = '<div class="dash-empty">Sin grupos registrados.</div>'; }
            else {
                const max = Math.max(...grupos.map(g => g.total), 1);
                cont.innerHTML = grupos.map(g => `
                    <div class="dash-bar">
                        <div class="val">${g.total}</div>
                        <div class="barra" style="height:${(g.total / max) * 140 + 4}px"></div>
                        <div class="lbl">${g.grado}</div>
                    </div>`).join('');
            }

            // Donut: colegiaturas
            const pag = d.pagos_pagados || 0, pend = d.pagos_pendientes || 0, tot = pag + pend;
            const donut = document.getElementById('donut');
            const pct = tot ? Math.round(pag / tot * 100) : 0;
            donut.style.background = tot
                ? `conic-gradient(var(--verde) 0 ${pct}%, var(--amarillo) ${pct}% 100%)`
                : 'var(--borde)';
            document.getElementById('donutNum').textContent = tot ? pct + '%' : '—';
            document.getElementById('legPag').textContent = `(${pag})`;
            document.getElementById('legPend').textContent = `(${pend})`;
        })();
    </script>
</body>
</html>
