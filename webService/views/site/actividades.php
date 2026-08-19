<?php
if (!defined('BASE_URL')) require_once __DIR__ . '/../../../apiService/core/config.php';
$base     = BASE_URL;
$title    = 'Actividades | Colegio del Bosque';
$activa   = 'actividades';
$extraCss = ['actividades.css'];
$img      = $base . '/webService/wwwroot/img/actividades';
?>
<!DOCTYPE html>
<html lang="es">
<head><?php require __DIR__ . '/../partials/head.php'; ?></head>
<body>
<?php require __DIR__ . '/../partials/header.php'; ?>

    <!-- ===================== PAGE HERO ===================== -->
    <section class="page-hero">
        <span class="badge"><svg class="ic-s" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14.5 4h-5L7 7H4a2 2 0 0 0-2 2v9a2 2 0 0 0 2 2h16a2 2 0 0 0 2-2V9a2 2 0 0 0-2-2h-3l-2.5-3Z"/><circle cx="12" cy="13" r="3.5"/></svg> Momentos que nos definen</span>
        <h1>Nuestras actividades</h1>
        <p>Así vivimos el ciclo escolar en el Colegio del Bosque: eventos, aprendizaje y mucha diversión.</p>
    </section>
    <svg class="ola" viewBox="0 0 1440 90" preserveAspectRatio="none" xmlns="http://www.w3.org/2000/svg">
        <path fill="#f7f6fd" d="M0,32L60,37.3C120,43,240,53,360,53.3C480,53,600,43,720,37.3C840,32,960,32,1080,37.3C1200,43,1320,53,1380,58.7L1440,64L1440,90L0,90Z"></path>
    </svg>

    <!-- ===================== EVENTOS ===================== -->
    <section class="seccion-act tono-morado" id="eventos">
        <div class="contenedor">
            <div class="cab">
                <span class="pill"><svg class="ic-s" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="4" width="18" height="18" rx="2"/><path d="M16 2v4M8 2v4M3 10h18"/></svg> Eventos</span>
                <h2>Eventos durante el ciclo escolar</h2>
                <p>Celebraciones y tradiciones que vivimos en familia a lo largo del año.</p>
            </div>
            <section class="gallery">
                <figure class="polaroid-card"><img src="<?= $img ?>/ev-1.jpg" alt=""><figcaption>Festival 15 de septiembre</figcaption></figure>
                <figure class="polaroid-card"><img src="<?= $img ?>/ev-2.jpg" alt=""><figcaption>Picnic</figcaption></figure>
                <figure class="polaroid-card"><img src="<?= $img ?>/ev-3.jpg" alt=""><figcaption>Concurso de disfraces</figcaption></figure>
                <figure class="polaroid-card"><img src="<?= $img ?>/ev-4.jpg" alt=""><figcaption>Thanksgiving day</figcaption></figure>
                <figure class="polaroid-card"><img src="<?= $img ?>/ev-11.jpg" alt=""><figcaption>Día de la familia</figcaption></figure>
                <figure class="polaroid-card"><img src="<?= $img ?>/ev-6.jpg" alt=""><figcaption>Festival de diciembre</figcaption></figure>
                <figure class="polaroid-card"><img src="<?= $img ?>/ev-7.jpg" alt=""><figcaption>Saint Patrick's day</figcaption></figure>
                <figure class="polaroid-card"><img src="<?= $img ?>/ev-9.jpg" alt=""><figcaption>Día del niño</figcaption></figure>
            </section>
        </div>
    </section>

    <!-- ===================== PEQUEÑO EMPRENDEDOR ===================== -->
    <section class="seccion-act tono-verde" id="emprendedor">
        <div class="contenedor">
            <div class="cab">
                <span class="pill"><svg class="ic-s" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M9 18h6"/><path d="M10 22h4"/><path d="M15.1 14c.8-1 1.9-1.8 1.9-4a5 5 0 0 0-10 0c0 2.2 1.1 3 1.9 4"/></svg> Proyecto</span>
                <h2>Pequeño emprendedor</h2>
                <p>Nuestros alumnos desarrollan creatividad, liderazgo y trabajo en equipo.</p>
            </div>
            <section class="gallery">
                <figure class="polaroid-card"><img src="<?= $img ?>/em-1.jpg" alt=""><figcaption></figcaption></figure>
                <figure class="polaroid-card"><img src="<?= $img ?>/em-3.jpg" alt=""><figcaption></figcaption></figure>
                <figure class="polaroid-card"><img src="<?= $img ?>/em-4.jpg" alt=""><figcaption></figcaption></figure>
                <figure class="polaroid-card"><img src="<?= $img ?>/em-5.jpg" alt=""><figcaption></figcaption></figure>
                <figure class="polaroid-card"><img src="<?= $img ?>/em-6.jpg" alt=""><figcaption></figcaption></figure>
                <figure class="polaroid-card"><img src="<?= $img ?>/em-7.jpg" alt=""><figcaption></figcaption></figure>
                <figure class="polaroid-card"><img src="<?= $img ?>/em-9.jpg" alt=""><figcaption></figcaption></figure>
                <figure class="polaroid-card"><img src="<?= $img ?>/em-10.jpg" alt=""><figcaption></figcaption></figure>
            </section>
        </div>
    </section>

    <!-- ===================== EVENTOS CULTURALES ===================== -->
    <section class="seccion-act tono-ambar" id="cultural">
        <div class="contenedor">
            <div class="cab">
                <span class="pill"><svg class="ic-s" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m12 3 1.9 4.8L18.7 9l-4.8 1.9L12 15l-1.9-4.1L5.3 9l4.8-1.2L12 3Z"/><path d="M19 15l.7 1.8 1.8.7-1.8.7-.7 1.8-.7-1.8-1.8-.7 1.8-.7L19 15Z"/></svg> Cultura</span>
                <h2>Eventos culturales</h2>
                <p>Museos, ferias y proyectos que amplían la mirada de nuestros alumnos.</p>
            </div>
            <section class="gallery">
                <figure class="polaroid-card"><img src="<?= $img ?>/c-2.jpg" alt=""><figcaption>Visita museo del alfeñique</figcaption></figure>
                <figure class="polaroid-card"><img src="<?= $img ?>/c-3.jpg" alt=""><figcaption>Feria de ciencias</figcaption></figure>
                <figure class="polaroid-card"><img src="<?= $img ?>/c-4.jpg" alt=""><figcaption>Historia de los países</figcaption></figure>
                <figure class="polaroid-card"><img src="<?= $img ?>/c-5.jpg" alt=""><figcaption>Concurso de calaveritas</figcaption></figure>
                <figure class="polaroid-card"><img src="<?= $img ?>/c-15.jpg" alt=""><figcaption>Historia de continentes</figcaption></figure>
                <figure class="polaroid-card"><img src="<?= $img ?>/c-16.jpg" alt=""><figcaption>Spelling bee</figcaption></figure>
                <figure class="polaroid-card"><img src="<?= $img ?>/c-17.jpg" alt=""><figcaption>16 de septiembre</figcaption></figure>
                <figure class="polaroid-card"><img src="<?= $img ?>/c-18.jpg" alt=""><figcaption>Historia de los países</figcaption></figure>
            </section>
        </div>
    </section>

<?php require __DIR__ . '/../partials/footer.php'; ?>
</body>
</html>
