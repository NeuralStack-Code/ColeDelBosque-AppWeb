<?php
if (!defined('BASE_URL')) require_once __DIR__ . '/../../../apiService/core/config.php';
$base     = BASE_URL;
$title    = 'Inicio de sesión | Colegio del Bosque';
$extraCss = ['sesion.css'];
$img      = $base . '/webService/wwwroot/img';
?>
<!DOCTYPE html>
<html lang="es">
<head><?php require __DIR__ . '/../partials/head.php'; ?></head>
<body>
<?php require __DIR__ . '/../partials/notificador.php'; ?>

    <main class="login">
        <!-- Panel de marca -->
        <aside class="login-marca">
            <div class="top">
                <img src="<?= $img ?>/logo.png" alt="Colegio del Bosque">
                <span>Colegio del Bosque</span>
            </div>
            <h2 class="frase">Construyendo<br>un <em>futuro</em><br>juntos.</h2>
            <p class="pie">Plataforma educativa del Colegio del Bosque Lerma.</p>
            <span class="deco-pieza p1"></span>
            <span class="deco-pieza p2"></span>
            <span class="deco-pieza p3"></span>
        </aside>

        <!-- Panel de formulario -->
        <section class="login-form">
            <div class="login-caja">
                <h1>Inicio de sesión</h1>
                <p class="info">Ingresa con tu matrícula para acceder a la plataforma.</p>

                <form id="formLogin" method="POST">
                    <label for="pass">Matrícula / ID</label>
                    <div class="input-pass">
                        <input id="pass" type="password" name="matricula" required placeholder="Ingresa tu ID">
                        <img class="ojo" id="imgVerContrasena" src="<?= $img ?>/ver.png" alt="Ver/ocultar">
                    </div>
                    <span class="olvida" id="olvidaMatricula">He olvidado la matrícula</span>
                    <button class="btn btn-primario ISbtn" id="isbtn" type="submit">Iniciar sesión</button>
                </form>

                <a class="volver" href="<?= $base ?>/">← Volver al inicio</a>
            </div>
        </section>
    </main>

    <script>
        // Mostrar / ocultar matrícula
        const pass = document.getElementById('pass');
        const ojo  = document.getElementById('imgVerContrasena');
        ojo.addEventListener('click', () => {
            pass.type = (pass.type === 'password') ? 'text' : 'password';
        });

        // "He olvidado la matrícula"
        document.getElementById('olvidaMatricula').addEventListener('click', () => {
            window.notify('info', 'Comunícate con la administración del colegio para recuperar tu matrícula.');
        });

        // Enviar login a la API de auth
        const formLogin = document.getElementById('formLogin');
        const btnLogin = document.getElementById('isbtn');
        formLogin.addEventListener('submit', async (e) => {
            e.preventDefault();
            btnLogin.disabled = true;
            try {
                const r = await fetch(window.BASE_URL + '/api/auth?action=login', {
                    method: 'POST',
                    body: new FormData(formLogin)
                });
                const data = await r.json();
                window.notifyResponse(data);
                if (data.success && data.redirect) {
                    setTimeout(() => location.href = data.redirect, 600);
                    return;
                }
            } catch (err) {
                window.notify('error', 'No se pudo conectar con el servidor.');
            }
            btnLogin.disabled = false;
        });
    </script>
</body>
</html>
