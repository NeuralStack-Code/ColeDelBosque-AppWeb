<?php
if (!defined('BASE_URL')) require_once __DIR__ . '/../../../apiService/core/config.php';
$base     = BASE_URL;
$title    = 'Contacto | Colegio del Bosque';
$activa   = 'contacto';
$extraCss = ['contacto.css'];
$img      = $base . '/webService/wwwroot/img';
?>
<!DOCTYPE html>
<html lang="es">
<head><?php require __DIR__ . '/../partials/head.php'; ?></head>
<body>
<?php require __DIR__ . '/../partials/header.php'; ?>

    <!-- ===================== PAGE HERO ===================== -->
    <section class="page-hero">
        <span class="badge"><svg class="ic-s" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="2" y="4" width="20" height="16" rx="2"/><path d="m22 7-10 6L2 7"/></svg> Estamos para ayudarte</span>
        <h1>¡Contáctanos!</h1>
        <p>Resolvemos tus dudas y te acompañamos en el proceso de inscripción. Escríbenos o visítanos.</p>
    </section>
    <svg class="ola" viewBox="0 0 1440 90" preserveAspectRatio="none" xmlns="http://www.w3.org/2000/svg">
        <path fill="#ffffff" d="M0,32L60,37.3C120,43,240,53,360,53.3C480,53,600,43,720,37.3C840,32,960,32,1080,37.3C1200,43,1320,53,1380,58.7L1440,64L1440,90L0,90Z"></path>
    </svg>

    <!-- ===================== CONTACTO ===================== -->
    <section class="contacto-wrap">
        <div class="contenedor contacto-grid">

            <!-- Formulario -->
            <div class="tarjeta">
                <h2>Escríbenos</h2>
                <p class="sub">Utilizamos tu información sólo para contactarnos contigo.</p>

                <form id="formContacto" method="post">
                    <div class="campo">
                        <label for="nombre">Nombre completo</label>
                        <input name="nombre" type="text" id="nombre" required placeholder="Nombre Apellido Apellido">
                    </div>
                    <div class="campo">
                        <label for="tel">Teléfono</label>
                        <input name="tel" type="tel" id="tel" required placeholder="(XX) XXXX-XXXX">
                    </div>
                    <div class="campo">
                        <label for="coment">Comentario</label>
                        <textarea name="coment" id="coment" required placeholder="Cuéntanos en qué podemos ayudarte..."></textarea>
                    </div>
                    <input type="submit" value="Enviar mensaje" class="btn btn-primario enviar">
                </form>
            </div>

            <!-- Ubicación e info -->
            <div class="tarjeta">
                <h2>Ubicación</h2>
                <p class="sub">Ven a conocernos, te esperamos con gusto.</p>
                <div class="info-item">
                    <span class="ic"><svg class="ic-s" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 10c0 4.4-8 12-8 12s-8-7.6-8-12a8 8 0 0 1 16 0Z"/><circle cx="12" cy="10" r="3"/></svg></span>
                    <div>
                        <h4>Dirección</h4>
                        <p>Privada Serafín Hernández SN, C. Benito Juárez SNI, Adolfo López Mateos, 52030 San Pedro Tultepec, Méx.</p>
                    </div>
                </div>
                <div class="info-item">
                    <span class="ic"><svg class="ic-s" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 16.9v3a2 2 0 0 1-2.2 2 19.8 19.8 0 0 1-8.6-3.1 19.5 19.5 0 0 1-6-6 19.8 19.8 0 0 1-3.1-8.7A2 2 0 0 1 4.1 2h3a2 2 0 0 1 2 1.7c.1 1 .4 1.9.7 2.8a2 2 0 0 1-.5 2.1L8.1 9.9a16 16 0 0 0 6 6l1.3-1.3a2 2 0 0 1 2.1-.4c.9.3 1.8.6 2.8.7a2 2 0 0 1 1.7 2Z"/></svg></span>
                    <div>
                        <h4>Teléfono</h4>
                        <p>722 906 4022</p>
                    </div>
                </div>
                <iframe class="mapa" src="https://www.google.com/maps/embed?pb=!1m18!1m12!1m3!1d3766.114947476909!2d-99.51624072533004!3d19.277366845621135!2m3!1f0!2f0!3f0!3m2!1i1024!2i768!4f13.1!3m3!1m2!1s0x85cdf5c27a1ed76b%3A0x499ca042bfc9f5be!2sSalida%20de%20Tultepec!5e0!3m2!1ses!2smx!4v1722023698948!5m2!1ses!2smx" allowfullscreen="" loading="lazy" referrerpolicy="no-referrer-when-downgrade"></iframe>
                <h4 style="font-family:var(--fuente-titulo);margin-bottom:12px;">Nuestras redes sociales</h4>
                <div class="redes-contacto">
                    <a class="fb" href="https://www.facebook.com/profile.php?id=100063510279404"><img src="<?= $img ?>/facebook-icon.png" alt=""> Facebook</a>
                    <a class="ig" href="#"><img src="<?= $img ?>/Instagram-Icon.png" alt=""> Instagram</a>
                </div>
            </div>

        </div>
    </section>

<?php require __DIR__ . '/../partials/footer.php'; ?>

    <script>
        // Enviar el formulario de contacto a la API
        const formContacto = document.getElementById('formContacto');
        formContacto.addEventListener('submit', async (e) => {
            e.preventDefault();
            const btn = formContacto.querySelector('[type="submit"]');
            btn.disabled = true;
            try {
                const r = await fetch(window.BASE_URL + '/api/contacto', {
                    method: 'POST',
                    body: new FormData(formContacto)
                });
                const data = await r.json();
                window.notifyResponse(data);
                if (data.success) formContacto.reset();
            } catch (err) {
                window.notify('error', 'No se pudo enviar el mensaje. Intenta más tarde.');
            }
            btn.disabled = false;
        });
    </script>
</body>
</html>
