<?php
$rol = 2; // solo maestros
require __DIR__ . '/../partials/guard.php';

$base     = BASE_URL;
$title    = 'Reporte semanal | Colegio del Bosque';
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
            <h2>Reporte semanal <span id="grado" style="color:var(--texto-suave);font-weight:400;"></span></h2>
            <div class="selector" id="cajaGrupo" style="display:none;">
                <label for="selGrupo">Grupo:</label>
                <select id="selGrupo"></select>
            </div>
            <div class="semana-nav">
                <button type="button" id="semAnt" title="Semana anterior" aria-label="Semana anterior">‹</button>
                <span id="semLbl">—</span>
                <button type="button" id="semSig" title="Semana siguiente" aria-label="Semana siguiente">›</button>
            </div>
        </div>

        <!-- Clases por día -->
        <div class="rep-dias" id="dias"></div>

        <!-- Envío a papás -->
        <div class="rep-envio">
            <div class="rep-envio-cab">
                <div>
                    <h3>Envío a papás</h3>
                    <p id="envioResumen">—</p>
                </div>
                <button type="button" class="btn btn-primario" id="btnEnviarTodos">Enviar reporte semanal</button>
            </div>
            <div class="tabla-scroll">
                <table class="tabla-calif">
                    <thead><tr><th>Alumno</th><th>Estado</th><th></th></tr></thead>
                    <tbody id="filasEnvio"></tbody>
                </table>
            </div>
        </div>
    </section>

    <!-- Captura de una clase -->
    <dialog class="rep-modal" id="dlgClase">
        <form id="formClase">
            <h3 id="claseTitulo">Clase</h3>
            <div class="rep-campos">
                <div class="campo"><label for="claseMateria">Materia</label><select id="claseMateria" required></select></div>
                <div class="campo crece"><label for="claseTema">Tema</label><input type="text" id="claseTema" maxlength="255" placeholder="Qué se vio en clase"></div>
            </div>
            <p class="rep-ayuda">Marca solo a quien tuvo algo que reportar; lo demás se queda en blanco.</p>
            <div class="tabla-scroll rep-tabla-alumnos">
                <table class="tabla-calif">
                    <thead id="claseCab"></thead>
                    <tbody id="claseFilas"></tbody>
                </table>
            </div>
            <div class="rep-acciones">
                <button type="button" class="btn btn-fantasma rep-peligro" id="btnEliminarClase">Eliminar clase</button>
                <span style="flex:1"></span>
                <button type="button" class="btn btn-fantasma" onclick="dlgClase.close()">Cancelar</button>
                <button type="submit" class="btn btn-primario">Guardar</button>
            </div>
        </form>
    </dialog>

    <!-- Vista previa del correo -->
    <dialog class="rep-modal" id="dlgPrevia">
        <h3 id="previaAsunto">Vista previa</h3>
        <iframe id="previaFrame" title="Vista previa del correo" sandbox></iframe>
        <div class="rep-acciones">
            <span style="flex:1"></span>
            <button type="button" class="btn btn-fantasma" onclick="dlgPrevia.close()">Cerrar</button>
        </div>
    </dialog>

    <script>
        const API = window.BASE_URL + '/api/reportes';
        const DIAS = ['Lunes', 'Martes', 'Miércoles', 'Jueves', 'Viernes'];
        const dlgClase = document.getElementById('dlgClase');
        const dlgPrevia = document.getElementById('dlgPrevia');
        const selMateria = document.getElementById('claseMateria');
        const esc = s => String(s ?? '').replace(/[&<>"']/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
        const corta = f => { const [, m, d] = f.split('-'); return `${d}/${m}`; };   // Y-m-d → d/m

        let ctx = { materias: [], columnas: [] };
        let sem = null;          // respuesta de ?action=semana
        let clase = null;        // clase abierta en el modal: { fecha, id_clase }
        let grupoId = '';        // grupo en el que se captura (el propio o uno de sus niveles de inglés)
        const selGrupo = document.getElementById('selGrupo');

        async function cerrarSesion() {
            try {
                const r = await fetch(window.BASE_URL + '/api/auth?action=logout', { method: 'POST' });
                const d = await r.json();
                if (d.redirect) { location.href = d.redirect; return; }
            } catch {}
            location.href = window.BASE_URL + '/inicio-sesion';
        }

        async function getJSON(url, opts) {
            try { return await (await fetch(url, opts)).json(); }
            catch { return { success: false, message: 'No se pudo conectar con el servidor.' }; }
        }

        /* ---------------- Semana ---------------- */

        async function cargarContexto() {
            const d = await getJSON(API + '?grupo_id=' + grupoId + '&action=contexto');
            if (!d.success) { window.notify('error', d.message); return false; }
            ctx = d;
            grupoId = d.grupo_id;
            selGrupo.innerHTML = d.grupos.map(g => `<option value="${g.id_grupo}">${esc(g.grado)}${g.solo_ingles ? ' (inglés)' : ''}</option>`).join('');
            selGrupo.value = d.grupo_id;
            document.getElementById('cajaGrupo').style.display = d.grupos.length > 1 ? '' : 'none';
            document.getElementById('grado').textContent = d.grado ? '· ' + d.grado : '';
            selMateria.innerHTML = d.materias.map(m => `<option value="${m.id_materia}">${esc(m.nombre)}</option>`).join('');
            return true;
        }

        async function cargarSemana(inicio = '') {
            const d = await getJSON(API + '?grupo_id=' + grupoId + '&action=semana&inicio=' + encodeURIComponent(inicio));
            if (!d.success) { window.notify('error', d.message); return; }
            sem = d;
            document.getElementById('semLbl').textContent = `${corta(d.dias[0])} al ${corta(d.dias[4])}`;
            pintarDias();
            pintarEnvio();
        }

        // Semana anterior / siguiente: ±7 días sobre el lunes actual
        function moverSemana(delta) {
            const [y, m, d] = sem.inicio.split('-').map(Number);
            const f = new Date(y, m - 1, d + delta * 7);
            const iso = `${f.getFullYear()}-${String(f.getMonth() + 1).padStart(2, '0')}-${String(f.getDate()).padStart(2, '0')}`;
            cargarSemana(iso);
        }
        document.getElementById('semAnt').addEventListener('click', () => moverSemana(-1));
        document.getElementById('semSig').addEventListener('click', () => moverSemana(1));

        function pintarDias() {
            const cont = document.getElementById('dias');
            cont.innerHTML = '';
            sem.dias.forEach((fecha, i) => {
                const clases = sem.clases.filter(c => c.fecha === fecha);
                const card = document.createElement('div');
                card.className = 'rep-dia';
                card.innerHTML = `
                    <div class="rep-dia-cab"><strong>${DIAS[i]}</strong><span>${corta(fecha)}</span></div>
                    <div class="rep-clases">
                        ${clases.map(c => `
                            <button type="button" class="rep-clase" data-materia="${c.materia_id}" ${ctx.materias.some(m => String(m.id_materia) === String(c.materia_id)) ? '' : 'disabled title="Clase de otra maestra"'}>
                                <strong>${esc(c.materia)}</strong>
                                <span>${esc(c.tema) || 'Sin tema'}</span>
                                ${Number(c.incidencias) ? `<em>${c.incidencias} con incidencia</em>` : ''}
                            </button>`).join('') || '<p class="rep-sin">Sin clases capturadas</p>'}
                    </div>
                    <button type="button" class="rep-agregar">+ Agregar clase</button>`;
                card.querySelectorAll('.rep-clase').forEach(b =>
                    b.addEventListener('click', () => abrirClase(fecha, i, b.dataset.materia)));
                card.querySelector('.rep-agregar').addEventListener('click', () => abrirClase(fecha, i, null));
                cont.appendChild(card);
            });
        }

        /* ---------------- Captura de clase ---------------- */

        function pintarTablaAlumnos() {
            document.getElementById('claseCab').innerHTML =
                '<tr><th>Alumno</th>' + ctx.columnas.map(c => `<th>${esc(c.nombre)}</th>`).join('') + '</tr>';
            document.getElementById('claseFilas').innerHTML = sem.alumnos.map(a => `
                <tr data-id="${a.id_cuenta}">
                    <td class="alumno">${esc(a.nombre_completo)}</td>
                    ${ctx.columnas.map(c => c.tipo === 'texto'
                        ? `<td><input type="text" maxlength="255" data-col="${c.id_columna}" aria-label="${esc(c.nombre)}"></td>`
                        : `<td class="centro"><input type="checkbox" data-col="${c.id_columna}" title="${esc(c.etiqueta || c.nombre)}" aria-label="${esc(c.nombre)}"></td>`
                    ).join('')}
                </tr>`).join('');
        }

        async function abrirClase(fecha, iDia, materiaId) {
            if (!ctx.materias.length) { window.notify('warning', 'Tu grupo no tiene materias registradas.'); return; }
            if (!ctx.columnas.length) { window.notify('warning', 'La administración aún no ha configurado las columnas del reporte.'); return; }
            if (!sem.alumnos.length)  { window.notify('warning', 'No hay alumnos en tu grupo.'); return; }

            clase = { fecha, id_clase: null };
            document.getElementById('claseTitulo').textContent = `${DIAS[iDia]} ${corta(fecha)}`;
            // Clase nueva: sugiere la primera materia que aún no se captura ese día
            if (materiaId === null) {
                const usadas = sem.clases.filter(c => c.fecha === fecha).map(c => String(c.materia_id));
                const libre = ctx.materias.find(m => !usadas.includes(String(m.id_materia)));
                materiaId = (libre || ctx.materias[0]).id_materia;
            }
            selMateria.value = materiaId;
            pintarTablaAlumnos();
            dlgClase.showModal();
            await cargarClase();
        }

        // Trae tema e incidencias de (día, materia); si no existe, deja todo en blanco
        async function cargarClase() {
            const d = await getJSON(`${API}?grupo_id=${grupoId}&action=clase&fecha=${clase.fecha}&materia_id=${encodeURIComponent(selMateria.value)}`);
            if (!d.success) { window.notify('error', d.message); return; }
            clase.id_clase = d.id_clase;
            document.getElementById('claseTema').value = d.tema;
            document.getElementById('btnEliminarClase').style.visibility = d.id_clase ? 'visible' : 'hidden';
            document.querySelectorAll('#claseFilas input').forEach(i => { i.type === 'checkbox' ? i.checked = false : i.value = ''; });
            d.marcas.forEach(m => {
                const i = document.querySelector(`#claseFilas tr[data-id="${m.cuenta_id}"] input[data-col="${m.columna_id}"]`);
                if (!i) return;
                if (i.type === 'checkbox') i.checked = !!Number(m.marcado);
                else i.value = m.nota ?? '';
            });
        }
        selMateria.addEventListener('change', cargarClase);

        document.getElementById('formClase').addEventListener('submit', async (e) => {
            e.preventDefault();
            const marcas = [];
            document.querySelectorAll('#claseFilas tr').forEach(tr => {
                tr.querySelectorAll('input').forEach(i => {
                    const base = { cuenta_id: Number(tr.dataset.id), columna_id: Number(i.dataset.col) };
                    if (i.type === 'checkbox') { if (i.checked) marcas.push({ ...base, marcado: 1 }); }
                    else if (i.value.trim() !== '') marcas.push({ ...base, nota: i.value.trim() });
                });
            });
            const fd = new FormData();
            fd.append('fecha', clase.fecha);
            fd.append('materia_id', selMateria.value);
            fd.append('tema', document.getElementById('claseTema').value);
            fd.append('marcas', JSON.stringify(marcas));
            const d = await getJSON(API + '?grupo_id=' + grupoId + '&action=guardar_clase', { method: 'POST', body: fd });
            window.notifyResponse(d);
            if (d.success) { dlgClase.close(); cargarSemana(sem.inicio); }
        });

        document.getElementById('btnEliminarClase').addEventListener('click', async () => {
            if (!clase.id_clase) return;
            if (!await window.confirmar('¿Eliminar esta clase y sus incidencias? Ya no saldrá en el reporte.')) return;
            const fd = new FormData(); fd.append('id_clase', clase.id_clase);
            const d = await getJSON(API + '?grupo_id=' + grupoId + '&action=eliminar_clase', { method: 'POST', body: fd });
            window.notifyResponse(d);
            if (d.success) { dlgClase.close(); cargarSemana(sem.inicio); }
        });

        /* ---------------- Envío ---------------- */

        function estadoHTML(a) {
            if (!a.tiene_correo) return '<span class="rep-pill sin">Sin correo</span>';
            if (a.enviado_en)    return `<span class="rep-pill ok">Enviado ${corta(a.enviado_en.slice(0, 10))} ${a.enviado_en.slice(11, 16)}</span>`;
            return '<span class="rep-pill pend">Pendiente</span>';
        }

        function pintarEnvio() {
            const conCorreo = sem.alumnos.filter(a => a.tiene_correo).length;
            const enviados  = sem.alumnos.filter(a => a.enviado_en).length;
            document.getElementById('envioResumen').textContent =
                `${sem.clases.length} clase(s) capturada(s) · ${enviados} de ${conCorreo} reportes enviados`
                + (conCorreo < sem.alumnos.length ? ` · ${sem.alumnos.length - conCorreo} alumno(s) sin correo` : '');

            const filas = document.getElementById('filasEnvio');
            filas.innerHTML = '';
            sem.alumnos.forEach(a => {
                const tr = document.createElement('tr');
                tr.innerHTML = `
                    <td class="alumno">${esc(a.nombre_completo)}</td>
                    <td class="estado">${estadoHTML(a)}</td>
                    <td class="rep-fila-acc">
                        <button type="button" class="btn-guardar claro previa">Vista previa</button>
                        <button type="button" class="btn-guardar enviar" ${a.tiene_correo ? '' : 'disabled'}>${a.enviado_en ? 'Reenviar' : 'Enviar'}</button>
                    </td>`;
                tr.querySelector('.previa').addEventListener('click', () => vistaPrevia(a));
                tr.querySelector('.enviar').addEventListener('click', async (e) => {
                    e.target.disabled = true;
                    const d = await enviarUno(a);
                    window.notifyResponse(d);
                    pintarEnvio();
                });
                filas.appendChild(tr);
            });
        }

        async function vistaPrevia(a) {
            const d = await getJSON(`${API}?grupo_id=${grupoId}&action=vista_previa&cuenta_id=${a.id_cuenta}&inicio=${sem.inicio}`);
            if (!d.success) { window.notify('error', d.message); return; }
            document.getElementById('previaAsunto').textContent = d.asunto;
            dlgPrevia.showModal();
            // iframe nuevo en cada apertura: reasignar srcdoc al mismo iframe lo deja en blanco
            const frame = document.createElement('iframe');
            frame.id = 'previaFrame';
            frame.title = 'Vista previa del correo';
            frame.setAttribute('sandbox', '');
            frame.srcdoc = d.html;
            document.getElementById('previaFrame').replaceWith(frame);
        }

        async function enviarUno(a) {
            const fd = new FormData();
            fd.append('cuenta_id', a.id_cuenta);
            fd.append('inicio', sem.inicio);
            const d = await getJSON(API + '?grupo_id=' + grupoId + '&action=enviar', { method: 'POST', body: fd });
            if (d.success) a.enviado_en = d.enviado_en;
            return d;
        }

        // Un correo por petición: así se ve el avance y ninguna petición se pasa de tiempo
        document.getElementById('btnEnviarTodos').addEventListener('click', async (e) => {
            const btn = e.currentTarget;
            const lista = sem.alumnos.filter(a => a.tiene_correo);
            if (!sem.clases.length) { window.notify('warning', 'No hay clases capturadas en esta semana.'); return; }
            if (!lista.length)      { window.notify('warning', 'Ningún alumno tiene correo de tutor registrado.'); return; }

            const sin = sem.alumnos.length - lista.length;
            const yaEnviados = lista.filter(a => a.enviado_en).length;
            let msg = `Se enviará el reporte de la semana del ${document.getElementById('semLbl').textContent} a los papás de ${lista.length} alumno(s).`;
            if (yaEnviados) msg += ` ${yaEnviados} ya lo recibieron y les llegará de nuevo.`;
            if (sin)        msg += ` ${sin} alumno(s) no tienen correo y se omiten.`;
            if (!await window.confirmar(msg, { titulo: '¿Enviar reporte semanal?', confirmar: 'Enviar', peligro: false })) return;

            btn.disabled = true;
            let ok = 0, fallos = 0;
            for (const [i, a] of lista.entries()) {
                btn.textContent = `Enviando ${i + 1} de ${lista.length}…`;
                (await enviarUno(a)).success ? ok++ : fallos++;
            }
            btn.disabled = false;
            btn.textContent = 'Enviar reporte semanal';
            pintarEnvio();
            window.notify(fallos ? 'warning' : 'success',
                `${ok} reporte(s) enviado(s).` + (fallos ? ` ${fallos} no se pudieron enviar; inténtalo de nuevo desde su fila.` : ''));
        });

        selGrupo.addEventListener('change', async () => {
            grupoId = selGrupo.value;
            if (await cargarContexto()) cargarSemana(sem ? sem.inicio : '');
        });

        (async () => { if (await cargarContexto()) cargarSemana(); })();
    </script>
</body>
</html>
