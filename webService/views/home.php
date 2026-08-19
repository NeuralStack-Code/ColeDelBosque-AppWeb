<?php
if (!defined('BASE_URL')) require_once __DIR__ . '/../../apiService/core/config.php';
$base   = BASE_URL;
$title  = 'Inicio | Colegio del Bosque';
$activa = 'inicio';
?>
<!DOCTYPE html>
<html lang="es">
<head><?php require __DIR__ . '/partials/head.php'; ?></head>
<body>
<?php require __DIR__ . '/partials/header.php'; ?>

<!-- ===================== HERO ===================== -->
    <section class="hero">
        <div class="contenedor hero-inner">
            <div class="hero-txt">
                <span class="hero-badge"><svg class="ic-s" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M11 20A7 7 0 0 1 9.8 6.1C15.5 5 17 4.5 19 2c1 2 2 4.2 2 8 0 5.5-4.8 10-10 10Z"/><path d="M2 22c0-3 1.8-5.4 5-6"/></svg> Preescolar y Primaria en Lerma</span>
                <h1>Colegio del <span class="resalta">Bosque</span></h1>
                <p class="slogan">"Construyendo un futuro juntos"</p>
                <p class="desc">
                    Despertamos la curiosidad, cultivamos el conocimiento y construimos
                    futuros brillantes desde el primer día, a través del humanismo y el
                    pensamiento crítico.
                </p>
                <div class="hero-cta">
                    <a href="<?= $base ?>/oferta-educativa" class="btn btn-primario">Conoce nuestra oferta →</a>
                    <a href="<?= $base ?>/contacto" class="btn btn-claro">Agendar una visita</a>
                </div>
                <div class="hero-stats">
                    <div class="stat"><strong>+20</strong><span>años formando niños</span></div>
                    <div class="stat"><strong>2</strong><span>niveles educativos</span></div>
                    <div class="stat"><strong>100%</strong><span>enfoque humanista</span></div>
                </div>
            </div>

            <div class="hero-visual">
                <img class="hero-foto" src="<?= $base ?>/webService/wwwroot/img/info.jpg" alt="Alumnos del Colegio del Bosque">
                <span class="pieza r"><svg class="ic-s" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 2l2.9 6.3 6.8.7-5 4.6 1.4 6.7L12 17.8 5.9 20.3 7.3 13.6l-5-4.6 6.8-.7L12 2Z"/></svg></span>
                <span class="pieza a"><svg class="ic-s" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 7v14"/><path d="M3 18a1 1 0 0 1-1-1V4a1 1 0 0 1 1-1h5a4 4 0 0 1 4 4 4 4 0 0 1 4-4h5a1 1 0 0 1 1 1v13a1 1 0 0 1-1 1h-6a3 3 0 0 0-3 3 3 3 0 0 0-3-3z"/></svg></span>
                <span class="pieza v"><svg class="ic-s" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M11 20A7 7 0 0 1 9.8 6.1C15.5 5 17 4.5 19 2c1 2 2 4.2 2 8 0 5.5-4.8 10-10 10Z"/><path d="M2 22c0-3 1.8-5.4 5-6"/></svg></span>
                <span class="pieza y"><svg class="ic-s" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 20h9"/><path d="M16.5 3.5a2.1 2.1 0 0 1 3 3L7 19l-4 1 1-4 12.5-12.5Z"/></svg></span>
            </div>
        </div>
        <svg class="ola" viewBox="0 0 1440 90" preserveAspectRatio="none" xmlns="http://www.w3.org/2000/svg">
            <path fill="#f7f6fd" d="M0,32L60,37.3C120,43,240,53,360,53.3C480,53,600,43,720,37.3C840,32,960,32,1080,37.3C1200,43,1320,53,1380,58.7L1440,64L1440,90L0,90Z"></path>
        </svg>
    </section>

    <!-- ===================== VALORES ===================== -->
    <section class="valores" style="background:#f7f6fd">
        <div class="contenedor">
            <div class="valores-grid">
                <div class="valor">
                    <div class="ico"><svg class="ic-s" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M19 14c1.5-1.5 3-3.2 3-5.5A5.5 5.5 0 0 0 16.5 3c-1.8 0-3 .5-4.5 2-1.5-1.5-2.7-2-4.5-2A5.5 5.5 0 0 0 2 8.5c0 2.3 1.5 4 3 5.5l7 7Z"/></svg></div>
                    <h3>Humanismo</h3>
                    <p>Formamos personas empáticas, seguras y felices.</p>
                </div>
                <div class="valor">
                    <div class="ico"><svg class="ic-s" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M9 18h6"/><path d="M10 22h4"/><path d="M15.1 14c.8-1 1.9-1.8 1.9-4a5 5 0 0 0-10 0c0 2.2 1.1 3 1.9 4"/></svg></div>
                    <h3>Pensamiento crítico</h3>
                    <p>Aprenden a preguntar, analizar y decidir por sí mismos.</p>
                </div>
                <div class="valor">
                    <div class="ico"><svg class="ic-s" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M11 20A7 7 0 0 1 9.8 6.1C15.5 5 17 4.5 19 2c1 2 2 4.2 2 8 0 5.5-4.8 10-10 10Z"/><path d="M2 22c0-3 1.8-5.4 5-6"/></svg></div>
                    <h3>Ambiente sano</h3>
                    <p>Un espacio emocionalmente seguro para crecer.</p>
                </div>
                <div class="valor">
                    <div class="ico"><svg class="ic-s" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M18.4 2.6a2 2 0 0 1 3 3L12 15l-4 1 1-4 9.4-9.4Z"/><path d="M6 16a3 3 0 0 0-3 3c0 1-1 2-1 2h4a3 3 0 0 0 0-6Z"/></svg></div>
                    <h3>Creatividad</h3>
                    <p>Actividades que despiertan su curiosidad e imaginación.</p>
                </div>
            </div>
        </div>
    </section>

    <!-- ===================== MISIÓN ===================== -->
    <section class="mision">
        <div class="contenedor mision-inner">
            <div class="mision-foto">
                <img src="<?= $base ?>/webService/wwwroot/img/info.jpg" alt="Nuestra principal tarea">
                <span class="deco"></span>
            </div>
            <div class="mision-txt">
                <span class="eyebrow">Nuestra principal tarea</span>
                <h2>Más que educación de calidad, una familia</h2>
                <p>
                    Nuestra misión va más allá de ofrecer una educación de calidad:
                    creamos una familia y un espacio donde nuestros alumnos pueden
                    explotar su máximo potencial a través del humanismo y el
                    pensamiento crítico.
                </p>
                <ul class="mision-lista">
                    <li><span class="check"><svg class="ic-s" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6 9 17l-5-5"/></svg></span> Aprendizajes innovadores y significativos</li>
                    <li><span class="check"><svg class="ic-s" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6 9 17l-5-5"/></svg></span> Desarrollo de habilidades sociales y cognitivas</li>
                    <li><span class="check"><svg class="ic-s" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6 9 17l-5-5"/></svg></span> Acompañamiento cercano y personalizado</li>
                </ul>
                <a href="<?= $base ?>/oferta-educativa" class="btn btn-primario">Consulta nuestra oferta educativa →</a>
            </div>
        </div>
    </section>

    <!-- ===================== CARDS ===================== -->
    <section class="cards" id="cards">
        <div class="contenedor">
            <div class="seccion-cab">
                <span class="eyebrow">Quiénes somos</span>
                <h2>Conoce al Colegio del Bosque</h2>
                <p>Actividades, misión y visión que nos definen como institución.</p>
            </div>
            <div class="cards-grid">
                <article class="card">
                    <div class="card-img">
                        <img src="<?= $base ?>/webService/wwwroot/img/card1.png" alt="Actividades del colegio">
                        <span class="card-tag">Actividades</span>
                    </div>
                    <div class="card-body">
                        <h3>Actividades del colegio</h3>
                        <p>Observa las actividades que realizamos durante el ciclo escolar y cómo aprenden divirtiéndose nuestros alumnos.</p>
                        <a href="<?= $base ?>/actividades" class="btn btn-fantasma">Visitar →</a>
                    </div>
                </article>

                <article class="card">
                    <div class="card-img">
                        <img src="<?= $base ?>/webService/wwwroot/img/card2.jpg" alt="Misión">
                        <span class="card-tag">Misión</span>
                    </div>
                    <div class="card-body">
                        <h3>Nuestra misión</h3>
                        <p>Promovemos e impulsamos aprendizajes, habilidades sociales, cognitivas y humanas que les permitan enfrentar nuevos retos en el futuro.</p>
                    </div>
                </article>

                <article class="card">
                    <div class="card-img">
                        <img src="<?= $base ?>/webService/wwwroot/img/card3.jpg" alt="Visión">
                        <span class="card-tag">Visión</span>
                    </div>
                    <div class="card-body">
                        <h3>Nuestra visión</h3>
                        <p>Ser una institución integral que ofrezca educación de calidad con aprendizajes innovadores en un ambiente emocionalmente sano.</p>
                    </div>
                </article>
            </div>
        </div>
    </section>

    <!-- ===================== GALERÍA ===================== -->
    <section class="galeria">
        <div class="contenedor">
            <div class="seccion-cab">
                <span class="eyebrow">Nuestras instalaciones</span>
                <h2>Un espacio pensado para ellos</h2>
                <p>Aulas, áreas de juego y espacios seguros donde nuestros alumnos crecen y aprenden.</p>
            </div>
            <div class="galeria-grid">
                <figure class="g-grande"><img src="<?= $base ?>/webService/wwwroot/img/instalaciones1.jpg" alt="Instalaciones 1"></figure>
                <figure><img src="<?= $base ?>/webService/wwwroot/img/instalaciones2.jpg" alt="Instalaciones 2"></figure>
                <figure class="g-alto"><img src="<?= $base ?>/webService/wwwroot/img/instalaciones3.jpg" alt="Instalaciones 3"></figure>
                <figure><img src="<?= $base ?>/webService/wwwroot/img/instalaciones4.jpg" alt="Instalaciones 4"></figure>
                <figure><img src="<?= $base ?>/webService/wwwroot/img/instalaciones5.jpg" alt="Instalaciones 5"></figure>
                <figure><img src="<?= $base ?>/webService/wwwroot/img/info.jpg" alt="Alumnos"></figure>
            </div>
        </div>
    </section>

    <!-- ===================== CTA ===================== -->
    <section class="cta">
        <div class="contenedor">
            <div class="cta-caja">
                <h2>¿Listo para formar parte de nuestra familia?</h2>
                <p>Agenda una visita y conoce por qué somos el segundo hogar de tus hijos.</p>
                <div class="hero-cta">
                    <a href="<?= $base ?>/contacto" class="btn btn-claro">Contáctanos</a>
                    <a href="<?= $base ?>/oferta-educativa" class="btn btn-fantasma" style="color:#fff;border-color:#fff">Ver oferta educativa</a>
                </div>
            </div>
        </div>
    </section>

<?php require __DIR__ . '/partials/footer.php'; ?>
</body>
</html>