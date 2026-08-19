<?php
if (!defined('BASE_URL')) require_once __DIR__ . '/../../../apiService/core/config.php';
$base     = BASE_URL;
$title    = 'Oferta educativa | Colegio del Bosque';
$activa   = 'oferta';
$extraCss = ['oferta.css'];
$img      = $base . '/webService/wwwroot/img';
?>
<!DOCTYPE html>
<html lang="es">
<head><?php require __DIR__ . '/../partials/head.php'; ?></head>
<body>
<?php require __DIR__ . '/../partials/header.php'; ?>

    <!-- ===================== PAGE HERO ===================== -->
    <section class="page-hero">
        <span class="badge"><svg class="ic-s" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 10 12 5 2 10l10 5 10-5Z"/><path d="M6 12v5c3 2.5 9 2.5 12 0v-5"/></svg> Preescolar y Primaria</span>
        <h1>Oferta educativa</h1>
        <p>Una educación integral que combina calidad académica, humanismo y pensamiento crítico para preparar a tus hijos para el futuro.</p>
    </section>
    <svg class="ola" viewBox="0 0 1440 90" preserveAspectRatio="none" xmlns="http://www.w3.org/2000/svg">
        <path fill="#ffffff" d="M0,32L60,37.3C120,43,240,53,360,53.3C480,53,600,43,720,37.3C840,32,960,32,1080,37.3C1200,43,1320,53,1380,58.7L1440,64L1440,90L0,90Z"></path>
    </svg>

    <!-- ===================== NIVELES ===================== -->
    <section class="niveles">
        <div class="contenedor">
            <div class="seccion-cab">
                <span class="eyebrow">Nuestros niveles</span>
                <h2>Acompañamos cada etapa</h2>
                <p>Dos niveles educativos pensados para el desarrollo integral de cada niña y niño.</p>
            </div>
            <div class="niveles-grid">
                <article class="nivel preescolar">
                    <span class="emoji"><svg class="ic-s" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><path d="M8 14s1.5 2 4 2 4-2 4-2"/><line x1="9" y1="9" x2="9.01" y2="9"/><line x1="15" y1="9" x2="15.01" y2="9"/></svg></span>
                    <h3>Preescolar</h3>
                    <p>Los primeros pasos en un ambiente cálido y seguro, donde el juego y la curiosidad guían el aprendizaje.</p>
                </article>
                <article class="nivel primaria">
                    <span class="emoji"><svg class="ic-s" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 7v14"/><path d="M3 18a1 1 0 0 1-1-1V4a1 1 0 0 1 1-1h5a4 4 0 0 1 4 4 4 4 0 0 1 4-4h5a1 1 0 0 1 1 1v13a1 1 0 0 1-1 1h-6a3 3 0 0 0-3 3 3 3 0 0 0-3-3z"/></svg></span>
                    <h3>Primaria</h3>
                    <p>Formación sólida en valores, habilidades y conocimientos para enfrentar con confianza los retos del futuro.</p>
                </article>
            </div>
        </div>
    </section>

    <!-- ===================== BENEFICIOS ===================== -->
    <section class="beneficios">
        <div class="contenedor">
            <div class="seccion-cab">
                <span class="eyebrow">Por qué elegirnos</span>
                <h2>Lo que nos hace diferentes</h2>
            </div>
            <div class="beneficios-grid">
                <div class="beneficio">
                    <div class="ico"><svg class="ic-s" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M22 21v-2a4 4 0 0 0-3-3.9"/><path d="M16 3.1a4 4 0 0 1 0 7.8"/></svg></div>
                    <h3>Grupos reducidos</h3>
                    <p>Máximo 15 alumnos por grupo para una atención cercana y personalizada.</p>
                </div>
                <div class="beneficio">
                    <div class="ico"><svg class="ic-s" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 10 12 5 2 10l10 5 10-5Z"/><path d="M6 12v5c3 2.5 9 2.5 12 0v-5"/><path d="M22 10v6"/></svg></div>
                    <h3>Profesorado con experiencia</h3>
                    <p>Docentes con más de 10 años de experiencia y antigüedad en el colegio.</p>
                </div>
                <div class="beneficio">
                    <div class="ico"><svg class="ic-s" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 10.5 12 3l9 7.5"/><path d="M5 9.5V21h14V9.5"/><path d="M9.5 21v-5a2.5 2.5 0 0 1 5 0v5"/></svg></div>
                    <h3>Escuela para padres</h3>
                    <p>Acompañamos también a las familias en la formación de sus hijos.</p>
                </div>
                <div class="beneficio">
                    <div class="ico"><svg class="ic-s" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M19 7V5a2 2 0 0 0-2-2H5a2 2 0 0 0 0 4h15a1 1 0 0 1 1 1v3"/><path d="M3 5v14a2 2 0 0 0 2 2h14a1 1 0 0 0 1-1v-4"/><path d="M18 12a2 2 0 0 0 0 4h3v-4Z"/></svg></div>
                    <h3>Colegiaturas accesibles</h3>
                    <p>Educación de calidad con costos pensados para tu familia.</p>
                </div>
                <div class="beneficio">
                    <div class="ico"><svg class="ic-s" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M4 17V6a2 2 0 0 1 2-2h10a2 2 0 0 1 2 2v11"/><path d="M2 12h16"/><path d="M4 11V7"/><path d="M18 8h1.5a2 2 0 0 1 1.9 1.4l1 3a2 2 0 0 1 .1.6v3a1 1 0 0 1-1 1h-1.5"/><circle cx="7.5" cy="17.5" r="1.8"/><circle cx="17" cy="17.5" r="1.8"/></svg></div>
                    <h3>Servicio de transporte</h3>
                    <p>Transporte escolar seguro para la comodidad y tranquilidad de todos.</p>
                </div>
            </div>
        </div>
    </section>

    <!-- ===================== INSTALACIONES ===================== -->
    <section class="instalaciones">
        <div class="contenedor">
            <div class="seccion-cab">
                <span class="eyebrow">Nuestras instalaciones</span>
                <h2>Un espacio pensado para ellos</h2>
                <p>Aulas, áreas de juego y espacios seguros donde nuestros alumnos crecen y aprenden.</p>
            </div>
            <div class="inst-grid">
                <figure class="g-grande"><img src="<?= $img ?>/instalaciones1.jpg" alt="Instalaciones 1"></figure>
                <figure><img src="<?= $img ?>/instalaciones2.jpg" alt="Instalaciones 2"></figure>
                <figure class="g-alto"><img src="<?= $img ?>/instalaciones3.jpg" alt="Instalaciones 3"></figure>
                <figure><img src="<?= $img ?>/instalaciones4.jpg" alt="Instalaciones 4"></figure>
                <figure><img src="<?= $img ?>/instalaciones5.jpg" alt="Instalaciones 5"></figure>
                <figure><img src="<?= $img ?>/info.jpg" alt="Alumnos"></figure>
            </div>
        </div>
    </section>

    <!-- ===================== CTA ===================== -->
    <section class="cta">
        <div class="contenedor">
            <div class="cta-caja">
                <h2>¿Quieres conocernos en persona?</h2>
                <p>Agenda una visita guiada y descubre por qué somos el segundo hogar de tus hijos.</p>
                <div class="cta-botones">
                    <a href="<?= $base ?>/contacto" class="btn btn-claro">Agendar una visita</a>
                    <a href="<?= $base ?>/actividades" class="btn btn-fantasma" style="color:#fff;border-color:#fff">Ver actividades</a>
                </div>
            </div>
        </div>
    </section>

<?php require __DIR__ . '/../partials/footer.php'; ?>
</body>
</html>
