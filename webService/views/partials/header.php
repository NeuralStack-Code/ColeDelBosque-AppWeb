<?php
if (!defined('BASE_URL')) require_once __DIR__ . '/../../../apiService/core/config.php';
$base = BASE_URL;

require_once __DIR__ . '/notificador.php';

if (isset($_SESSION['usuario'])) {
    $displayName = $_SESSION['usuario'];
    if (strpos($displayName, '@') !== false) {
        $displayName = explode('@', $displayName)[0];
    }
}
?>
<header class="navbar">
    <div class="contenedor nav-inner">
        <a href="<?= $base ?>/" class="marca">
            <img src="<?= $base ?>/webService/wwwroot/img/logo.png" alt="Colegio del Bosque">
            <span>Colegio del Bosque
                <small>Construyendo un futuro juntos</small>
            </span>
        </a>

        <nav>
            <ul class="nav-links" id="navLinks">
                <li><a href="<?= $base ?>/" class="<?= ($activa ?? '') === 'inicio' ? 'active' : '' ?>">Inicio</a></li>
                <li><a href="<?= $base ?>/oferta-educativa" class="<?= ($activa ?? '') === 'oferta' ? 'active' : '' ?>">Oferta educativa</a></li>
                <li><a href="<?= $base ?>/actividades" class="<?= ($activa ?? '') === 'actividades' ? 'active' : '' ?>">Actividades</a></li>
                <li><a href="<?= $base ?>/contacto" class="<?= ($activa ?? '') === 'contacto' ? 'active' : '' ?>">Contacto</a></li>
            </ul>
        </nav>

        <div class="nav-acciones">
            <a href="<?= $base ?>/inicio-sesion" class="btn btn-fantasma">Iniciar sesión</a>
            <button class="hamburguesa" id="btnMenu" aria-label="Menú">&#9776;</button>
        </div>
    </div>
</header>

<script>
async function cerrarSesion() {
    try {
        await fetch((window.BASE_URL || '') + '/api/auth?action=logout', { method: 'POST' });
    } catch {}
    window.location.href = (window.BASE_URL || '') + '/inicio-sesion';
}

// Menú hamburguesa (global)
(function () {
    const btnMenu  = document.getElementById('btnMenu');
    const navLinks = document.getElementById('navLinks');
    if (!btnMenu || !navLinks) return;
    btnMenu.addEventListener('click', () => navLinks.classList.toggle('abierto'));
    navLinks.querySelectorAll('a').forEach(a =>
        a.addEventListener('click', () => navLinks.classList.remove('abierto'))
    );
})();
</script>