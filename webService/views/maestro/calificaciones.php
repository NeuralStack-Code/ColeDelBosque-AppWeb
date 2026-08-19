<?php
$rol = 2; // solo maestros
require __DIR__ . '/../partials/guard.php';

$base     = BASE_URL;
$title    = 'Calificaciones | Colegio del Bosque';
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

    <a class="volver-panel" href="<?= $base ?>/maestro">
        <svg class="ic-s" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m15 18-6-6 6-6"/></svg>
        Regresar al panel
    </a>

    <section class="calif-wrap">
        <div class="calif-cab">
            <h2>Reportes y calificaciones <span id="grado" style="color:var(--texto-suave);font-weight:400;"></span></h2>
            <div class="selector">
                <label for="materia">Materia:</label>
                <select id="materia"><option value="">Selecciona…</option></select>
            </div>
        </div>

        <div class="tabla-scroll">
            <table class="tabla-calif" id="tabla" style="display:none;">
                <thead>
                    <tr>
                        <th>Alumno</th>
                        <th>T1</th><th>T2</th><th>T3</th><th>T4</th><th>Examen</th>
                        <th>Reporte</th><th></th>
                    </tr>
                </thead>
                <tbody id="filas"></tbody>
            </table>
        </div>
        <p class="calif-vacio" id="vacio">Selecciona una materia para ver a tus alumnos.</p>
    </section>

    <script>
        const API = window.BASE_URL + '/api/calificaciones';
        const selMateria = document.getElementById('materia');
        const tabla = document.getElementById('tabla');
        const filas = document.getElementById('filas');
        const vacio = document.getElementById('vacio');

        async function cerrarSesion() {
            try {
                const r = await fetch(window.BASE_URL + '/api/auth?action=logout', { method: 'POST' });
                const d = await r.json();
                if (d.redirect) { location.href = d.redirect; return; }
            } catch {}
            location.href = window.BASE_URL + '/inicio-sesion';
        }

        // Cargar contexto (materias del grupo)
        async function cargarContexto() {
            try {
                const r = await fetch(API + '?action=contexto');
                const d = await r.json();
                if (!d.success) { window.notify('error', d.message); return; }
                document.getElementById('grado').textContent = d.grado ? '· ' + d.grado : '';
                d.materias.forEach(m => {
                    const opt = document.createElement('option');
                    opt.value = m.id_materia;
                    opt.textContent = m.nombre;
                    selMateria.appendChild(opt);
                });
                if (d.materias.length === 0) vacio.textContent = 'Tu grupo no tiene materias registradas.';
            } catch { window.notify('error', 'No se pudo cargar el contexto.'); }
        }

        // Cargar alumnos + notas de la materia elegida
        async function cargarAlumnos(materiaId) {
            const r = await fetch(API + '?action=listar&materia_id=' + encodeURIComponent(materiaId));
            const d = await r.json();
            if (!d.success) { window.notify('error', d.message); return; }
            filas.innerHTML = '';
            if (d.alumnos.length === 0) {
                tabla.style.display = 'none';
                vacio.style.display = 'block';
                vacio.textContent = 'No hay alumnos en tu grupo.';
                return;
            }
            d.alumnos.forEach(a => {
                const tr = document.createElement('tr');
                const num = (v) => `value="${v ?? ''}"`;
                tr.innerHTML = `
                    <td class="alumno">${a.nombre_completo}</td>
                    <td><input type="number" min="0" max="10" step="0.1" data-f="t1" ${num(a.t1)}></td>
                    <td><input type="number" min="0" max="10" step="0.1" data-f="t2" ${num(a.t2)}></td>
                    <td><input type="number" min="0" max="10" step="0.1" data-f="t3" ${num(a.t3)}></td>
                    <td><input type="number" min="0" max="10" step="0.1" data-f="t4" ${num(a.t4)}></td>
                    <td><input type="number" min="0" max="10" step="0.1" data-f="examen" ${num(a.examen)}></td>
                    <td><input type="text" data-f="reporte" placeholder="Opcional" value="${(a.reporte ?? '').replace(/"/g,'&quot;')}"></td>
                    <td><button class="btn-guardar" data-id="${a.id_cuenta}">Guardar</button></td>`;
                filas.appendChild(tr);
            });
            tabla.style.display = '';
            vacio.style.display = 'none';
            filas.querySelectorAll('.btn-guardar').forEach(b =>
                b.addEventListener('click', () => guardar(b))
            );
        }

        async function guardar(btn) {
            const tr = btn.closest('tr');
            const fd = new FormData();
            fd.append('cuenta_id', btn.dataset.id);
            fd.append('materia_id', selMateria.value);
            tr.querySelectorAll('input').forEach(i => fd.append(i.dataset.f, i.value));
            btn.disabled = true;
            try {
                const r = await fetch(API + '?action=guardar', { method: 'POST', body: fd });
                window.notifyResponse(await r.json());
            } catch { window.notify('error', 'No se pudo guardar.'); }
            btn.disabled = false;
        }

        selMateria.addEventListener('change', () => {
            if (selMateria.value) cargarAlumnos(selMateria.value);
            else { tabla.style.display = 'none'; vacio.style.display = 'block'; }
        });

        cargarContexto();
    </script>
</body>
</html>
