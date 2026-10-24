<?php
/** Calificaciones de la maestra. La pinta CalificacionController::index(). Recibe $base y $nombre. Su JavaScript llama a /calificacion/<método>. */
$title    = 'Calificaciones | Colegio del Bosque';
$extraCss = ['maestro.css'];
$img      = $base . '/webService/wwwroot/img';
$nombre   = $nombre ?: 'Maestro';
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
            <div class="selector" id="cajaGrupo" style="display:none;">
                <label for="selGrupo">Grupo:</label>
                <select id="selGrupo"></select>
            </div>
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
                        <th>P1</th><th>P2</th><th>P3</th><th>Final</th>
                        <th>Reporte</th><th></th>
                    </tr>
                </thead>
                <tbody id="filas"></tbody>
            </table>
        </div>
        <p class="calif-vacio" id="vacio">Selecciona una materia para ver a tus alumnos.</p>
    </section>

    <script>
        const API = window.BASE_URL + '/calificacion';
        const selMateria = document.getElementById('materia');
        const tabla = document.getElementById('tabla');
        const filas = document.getElementById('filas');
        const vacio = document.getElementById('vacio');
        const selGrupo = document.getElementById('selGrupo');
        let grupoId = '';   // grupo en el que se captura (el propio o uno de sus niveles de inglés)

        async function cerrarSesion() {
            try {
                const r = await fetch(window.BASE_URL + '/auth/salir', { method: 'POST' });
                const d = await r.json();
                if (d.redirect) { location.href = d.redirect; return; }
            } catch {}
            location.href = window.BASE_URL + '/auth/entrar';
        }

        // Cargar contexto (materias del grupo)
        async function cargarContexto() {
            try {
                const r = await fetch(API + '/contexto?IdGrupo=' + grupoId);
                const res = await r.json();
                if (!res.success) { window.notify('error', res.message); return; }
                const d = res.data;
                document.getElementById('grado').textContent = d.grado ? '· ' + d.grado : '';
                grupoId = d.IdGrupo;
                selGrupo.innerHTML = d.grupos.map(g => `<option value="${g.IdGrupo}">${g.Grado}${g.SoloIngles ? ' (inglés)' : ''}</option>`).join('');
                selGrupo.value = d.IdGrupo;
                document.getElementById('cajaGrupo').style.display = d.grupos.length > 1 ? '' : 'none';
                selMateria.innerHTML = '<option value="">Selecciona…</option>';
                tabla.style.display = 'none';
                vacio.style.display = 'block';
                vacio.textContent = 'Selecciona una materia para ver a tus alumnos.';
                d.materias.forEach(m => {
                    const opt = document.createElement('option');
                    opt.value = m.IdMateria;
                    opt.textContent = m.Nombre;
                    selMateria.appendChild(opt);
                });
                if (d.materias.length === 0) vacio.textContent = 'Tu grupo no tiene materias registradas.';
            } catch { window.notify('error', 'No se pudo cargar el contexto.'); }
        }

        // Cargar alumnos + notas de la materia elegida
        async function cargarAlumnos(materiaId) {
            const r = await fetch(API + '/listar?IdGrupo=' + grupoId + '&IdMateria=' + encodeURIComponent(materiaId));
            const res = await r.json();
            if (!res.success) { window.notify('error', res.message); return; }
            const d = res.data;
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
                const prom = promedio(a.P1, a.P2, a.P3);
                tr.innerHTML = `
                    <td class="alumno">${a.NombreCompleto}</td>
                    <td><input type="number" min="0" max="10" step="0.1" data-f="P1" ${num(a.P1)}></td>
                    <td><input type="number" min="0" max="10" step="0.1" data-f="P2" ${num(a.P2)}></td>
                    <td><input type="number" min="0" max="10" step="0.1" data-f="P3" ${num(a.P3)}></td>
                    <td><input type="number" min="0" max="10" step="0.1" data-f="CalifFinal" ${num(a.CalifFinal)} placeholder="${prom}" title="Promedio automático de P1–P3; edítalo solo si quieres sobrescribir"></td>
                    <td><input type="text" data-f="Reporte" placeholder="Opcional" value="${(a.Reporte ?? '').replace(/"/g,'&quot;')}"></td>
                    <td><button class="btn-guardar" data-id="${a.IdCuenta}">Guardar</button></td>`;
                filas.appendChild(tr);
            });
            tabla.style.display = '';
            vacio.style.display = 'none';
            // Promedio automático en vivo (placeholder del Final)
            filas.querySelectorAll('tr').forEach(tr => {
                const ps = ['P1', 'P2', 'P3'].map(f => tr.querySelector(`[data-f="${f}"]`));
                const fin = tr.querySelector('[data-f="CalifFinal"]');
                const upd = () => { fin.placeholder = promedio(ps[0].value, ps[1].value, ps[2].value); };
                ps.forEach(i => i.addEventListener('input', upd));
            });
            filas.querySelectorAll('.btn-guardar').forEach(b =>
                b.addEventListener('click', () => guardar(b))
            );
        }
        function promedio(a, b, c) {
            const arr = [a, b, c].filter(v => v !== null && v !== '' && !isNaN(Number(v))).map(Number);
            return arr.length ? (arr.reduce((x, y) => x + y, 0) / arr.length).toFixed(1) : '—';
        }

        async function guardar(btn) {
            const tr = btn.closest('tr');
            const fd = new FormData();
            fd.append('IdCuenta', btn.dataset.id);
            fd.append('IdMateria', selMateria.value);
            fd.append('IdGrupo', grupoId);
            tr.querySelectorAll('input').forEach(i => fd.append(i.dataset.f, i.value));
            btn.disabled = true;
            try {
                const r = await fetch(API + '/guardar', { method: 'POST', body: fd });
                window.notifyResponse(await r.json());
            } catch { window.notify('error', 'No se pudo guardar.'); }
            btn.disabled = false;
        }

        selMateria.addEventListener('change', () => {
            if (selMateria.value) cargarAlumnos(selMateria.value);
            else { tabla.style.display = 'none'; vacio.style.display = 'block'; }
        });

        selGrupo.addEventListener('change', () => { grupoId = selGrupo.value; cargarContexto(); });

        cargarContexto();
    </script>
</body>
</html>
