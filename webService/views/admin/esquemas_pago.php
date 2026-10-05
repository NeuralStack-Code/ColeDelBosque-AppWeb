<?php
$rol = 1;
require __DIR__ . '/../partials/guard.php';
$base = BASE_URL;
$title = 'Esquemas de pago | Admin';
$extraCss = ['admin.css'];
$img = $base . '/webService/wwwroot/img';
?>
<!DOCTYPE html>
<html lang="es">
<head><?php require __DIR__ . '/../partials/head.php'; ?></head>
<body>
<?php require __DIR__ . '/../partials/notificador.php'; ?>

    <header class="panel-top">
        <div class="contenedor inner">
            <a href="<?= $base ?>/administrador" class="marca">
                <img src="<?= $img ?>/logo.png" alt="Colegio del Bosque">
                <span>Colegio del Bosque<small>Panel de administración</small></span>
            </a>
            <button class="btn-salir" onclick="cerrarSesion()">Cerrar sesión</button>
        </div>
    </header>

    <a class="volver-panel" href="<?= $base ?>/administrador/colegiaturas">
        <svg class="ic-s" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m15 18-6-6 6-6"/></svg>
        Volver a colegiaturas
    </a>

    <section class="admin-wrap">
        <div class="admin-cab">
            <h2>Esquemas de pago</h2>
            <button class="btn btn-primario" onclick="abrirAlta()">+ Nuevo esquema</button>
        </div>
        <p style="color:var(--texto-suave);margin-bottom:20px;">
            Cada esquema define la inscripción y las mensualidades de un ciclo escolar. Al aplicarlo se generan los pagos de los alumnos.
        </p>

        <div class="filtros">
            <div class="f"><label for="fCiclo">Ciclo escolar</label><select id="fCiclo"></select></div>
        </div>

        <div class="tabla-scroll">
            <table class="tabla">
                <thead><tr><th>Esquema</th><th>Ciclo</th><th>Inscripción</th><th>Colegiatura</th><th>Mensualidades</th><th>Vence</th><th>Recargo</th><th></th></tr></thead>
                <tbody id="filas"></tbody>
            </table>
        </div>
        <p class="tabla-vacia" id="vacio" style="display:none;">Aún no hay esquemas de pago en este ciclo.</p>
    </section>

    <!-- Alta / edición -->
    <dialog class="modal" id="modal">
        <div class="modal-in">
            <h3 id="modalTitulo">Nuevo esquema</h3>
            <form id="form">
                <input type="hidden" id="id_esquema">
                <div class="campo"><label>Ciclo escolar</label><select id="ciclo_id" required></select></div>
                <div class="campo"><label>Nombre</label><input type="text" id="nombre" maxlength="80" placeholder="Ej. Primaria" required></div>
                <div class="fila-2">
                    <div class="campo"><label>Inscripción ($)</label><input type="number" id="monto_inscripcion" min="0" step="0.01" placeholder="0 = sin inscripción"></div>
                    <div class="campo"><label>Colegiatura mensual ($)</label><input type="number" id="monto_colegiatura" min="0.01" step="0.01" required></div>
                </div>
                <div class="fila-2">
                    <div class="campo"><label>Primera mensualidad</label><select id="primer_mes" required></select></div>
                    <div class="campo"><label># de mensualidades</label><select id="num_meses" required></select></div>
                </div>
                <div class="fila-2">
                    <div class="campo"><label>Día de vencimiento</label><input type="number" id="dia_vencimiento" min="1" max="28" value="10" required></div>
                    <div class="campo"><label>Recargo por mes vencido ($)</label><input type="number" id="recargo_monto" min="0" step="0.01" placeholder="0 = sin recargo"></div>
                </div>
                <p id="resumenMeses" style="color:var(--texto-suave);font-size:.85rem;"></p>
                <div class="modal-acciones">
                    <button type="button" class="btn btn-fantasma" onclick="modal.close()">Cancelar</button>
                    <button type="submit" class="btn btn-primario">Guardar</button>
                </div>
            </form>
        </div>
    </dialog>

    <!-- Aplicar -->
    <dialog class="modal ancho" id="mAplicar">
        <div class="modal-in">
            <h3>Aplicar esquema</h3>
            <p id="aplicarInfo" style="color:var(--texto-suave);margin-bottom:14px;"></p>
            <div class="desglose-scroll">
                <table class="tabla-desglose">
                    <thead><tr><th>Concepto</th><th>Vence</th><th class="num">Monto</th><th class="num">Recargo si se atrasa</th></tr></thead>
                    <tbody id="desgloseFilas"></tbody>
                    <tfoot><tr><td colspan="2">Total por alumno</td><td class="num" id="desgloseTotal"></td><td></td></tr></tfoot>
                </table>
            </div>
            <div class="campo"><label>Aplicar a</label>
                <select id="aplicarA">
                    <option value="todos">Todos los alumnos del ciclo</option>
                    <option value="grupo">Un grupo</option>
                    <option value="alumno">Un alumno</option>
                </select>
            </div>
            <div class="campo" id="campoGrupo" style="display:none;"><label>Grupo</label><select id="aGrupo"></select></div>
            <div id="campoAlumno" style="display:none;">
                <div class="campo"><label>Buscar (nombre o matrícula)</label><input type="text" id="aBuscar" placeholder="Escribe para filtrar…"></div>
                <div class="campo"><label>Alumno</label><select id="aAlumno" size="6" style="height:auto;"></select></div>
            </div>
            <p id="aplicarCuantos" style="font-weight:700;margin-bottom:6px;"></p>
            <p style="color:var(--texto-suave);font-size:.85rem;">Los pagos ya saldados no se tocan; los pendientes se actualizan al monto y recargo del esquema.</p>
            <div class="modal-acciones">
                <button type="button" class="btn btn-fantasma" onclick="mAplicar.close()">Cancelar</button>
                <button type="button" class="btn btn-primario" id="btnAplicar">Aplicar</button>
            </div>
        </div>
    </dialog>

    <script>
        const API = window.BASE_URL + '/api/esquemas-pago';
        const MESES = ['', 'Enero', 'Febrero', 'Marzo', 'Abril', 'Mayo', 'Junio', 'Julio', 'Agosto', 'Septiembre', 'Octubre', 'Noviembre', 'Diciembre'];
        const money = n => '$' + Number(n || 0).toLocaleString('es-MX', { minimumFractionDigits: 2 });
        const esc = s => String(s ?? '').replace(/[&<>"']/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
        const filas = document.getElementById('filas'), vacio = document.getElementById('vacio');
        const modal = document.getElementById('modal'), form = document.getElementById('form');
        const mAplicar = document.getElementById('mAplicar');
        const fCiclo = document.getElementById('fCiclo'), selCiclo = document.getElementById('ciclo_id');
        const selPrimer = document.getElementById('primer_mes'), selNum = document.getElementById('num_meses');
        let ciclos = [], grupos = [], alumnos = [], esquemas = [];
        let porAplicar = null;

        function cerrarSesion() {
            fetch(window.BASE_URL + '/api/auth?action=logout', { method: 'POST' })
                .finally(() => location.href = window.BASE_URL + '/inicio-sesion');
        }

        /* ---------- Meses del ciclo ---------- */

        // 'Y-m' de cada mes entre el inicio y el fin del ciclo
        function mesesDelCiclo(cicloId) {
            const c = ciclos.find(x => String(x.id_ciclo) === String(cicloId));
            if (!c || !c.fecha_inicio || !c.fecha_fin) return [];
            let [a, m] = c.fecha_inicio.split('-').map(Number);
            const [af, mf] = c.fecha_fin.split('-').map(Number);
            const out = [];
            while (a < af || (a === af && m <= mf)) {
                out.push(`${a}-${String(m).padStart(2, '0')}`);
                if (++m > 12) { m = 1; a++; }
            }
            return out;
        }
        const rotuloMes = ym => { const [a, m] = ym.split('-').map(Number); return `${MESES[m]} ${a}`; };
        // Último mes cobrado: primer mes + n − 1
        function ultimoMes(ym, n) {
            const [a, m] = ym.split('-').map(Number);
            const t = a * 12 + (m - 1) + (n - 1);
            return `${Math.floor(t / 12)}-${String(t % 12 + 1).padStart(2, '0')}`;
        }

        function pintarPrimerMes(valor) {
            const meses = mesesDelCiclo(selCiclo.value);
            selPrimer.innerHTML = meses.length
                ? meses.map(ym => `<option value="${ym}">${rotuloMes(ym)}</option>`).join('')
                : '<option value="">El ciclo no tiene fechas</option>';
            if (valor && meses.includes(valor)) selPrimer.value = valor;
            pintarNumMeses();
        }
        // Las mensualidades posibles llegan hasta el último mes del ciclo
        function pintarNumMeses(valor) {
            const meses = mesesDelCiclo(selCiclo.value);
            const max = meses.length - meses.indexOf(selPrimer.value);
            const previo = Number(valor ?? selNum.value) || max;
            selNum.innerHTML = '';
            for (let n = 1; n <= (meses.length ? max : 0); n++) selNum.insertAdjacentHTML('beforeend', `<option value="${n}">${n}</option>`);
            if (selNum.options.length) selNum.value = Math.min(previo, max);
            pintarResumen();
        }
        function pintarResumen() {
            const p = document.getElementById('resumenMeses');
            p.textContent = (selPrimer.value && selNum.value)
                ? `Se cobrará de ${rotuloMes(selPrimer.value)} a ${rotuloMes(ultimoMes(selPrimer.value, Number(selNum.value)))}.`
                : '';
        }
        selCiclo.addEventListener('change', () => pintarPrimerMes());
        selPrimer.addEventListener('change', () => pintarNumMeses());
        selNum.addEventListener('change', pintarResumen);

        /* ---------- Listado ---------- */

        async function cargarCatalogos() {
            const d = await (await fetch(API + '?action=catalogos')).json();
            if (!d.success) { window.notify('error', d.message); return; }
            ciclos = d.ciclos || []; grupos = d.grupos || []; alumnos = d.alumnos || [];
            const opts = ciclos.map(c => `<option value="${c.id_ciclo}">${esc(c.nombre)}${Number(c.activo) ? ' (activo)' : ''}</option>`).join('');
            fCiclo.innerHTML = '<option value="">Todos</option>' + opts;
            selCiclo.innerHTML = opts;
            const activo = ciclos.find(c => Number(c.activo));
            if (activo) fCiclo.value = activo.id_ciclo;
        }

        async function cargar() {
            const d = await (await fetch(API + '?action=listar')).json();
            if (!d.success) { window.notify('error', d.message); return; }
            esquemas = d.items || [];
            render();
        }

        function render() {
            const f = esquemas.filter(e => !fCiclo.value || String(e.ciclo_id) === fCiclo.value);
            filas.innerHTML = '';
            vacio.style.display = f.length ? 'none' : 'block';
            f.forEach(e => {
                const ini = e.primer_mes.slice(0, 7);
                const tr = document.createElement('tr');
                tr.innerHTML = `
                    <td><strong>${esc(e.nombre)}</strong></td>
                    <td>${esc(e.ciclo_nombre)}</td>
                    <td>${Number(e.monto_inscripcion) ? money(e.monto_inscripcion) : '—'}</td>
                    <td>${money(e.monto_colegiatura)}</td>
                    <td>${e.num_meses} <small style="color:var(--texto-suave)">· ${rotuloMes(ini)} a ${rotuloMes(ultimoMes(ini, Number(e.num_meses)))}</small></td>
                    <td>Día ${e.dia_vencimiento}</td>
                    <td>${Number(e.recargo_monto) ? money(e.recargo_monto) + ' <small style="color:var(--texto-suave)">por mes vencido</small>' : '—'}</td>
                    <td><div class="acciones">
                        <button class="icon-btn aplicar" title="Aplicar a alumnos"><svg class="ic-s" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6 9 17l-5-5"/></svg></button>
                        <button class="icon-btn editar" title="Editar"><svg class="ic-s" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 20h9"/><path d="M16.5 3.5a2.1 2.1 0 0 1 3 3L7 19l-4 1 1-4 12.5-12.5Z"/></svg></button>
                        <button class="icon-btn borrar" title="Eliminar"><svg class="ic-s" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 6h18"/><path d="M8 6V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/><path d="M19 6l-1 14a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2L5 6"/></svg></button>
                    </div></td>`;
                tr.querySelector('.aplicar').addEventListener('click', () => abrirAplicar(e));
                tr.querySelector('.editar').addEventListener('click', () => abrirEdicion(e));
                tr.querySelector('.borrar').addEventListener('click', () => eliminar(e));
                filas.appendChild(tr);
            });
        }
        fCiclo.addEventListener('change', render);

        /* ---------- Alta / edición ---------- */

        function abrirAlta() {
            if (!ciclos.length) { window.notify('warning', 'Primero registra un ciclo escolar.'); return; }
            document.getElementById('modalTitulo').textContent = 'Nuevo esquema';
            form.reset(); document.getElementById('id_esquema').value = '';
            if (fCiclo.value) selCiclo.value = fCiclo.value;
            pintarPrimerMes();
            modal.showModal();
        }
        function abrirEdicion(e) {
            document.getElementById('modalTitulo').textContent = 'Editar esquema';
            document.getElementById('id_esquema').value = e.id_esquema;
            selCiclo.value = e.ciclo_id;
            document.getElementById('nombre').value = e.nombre;
            document.getElementById('monto_inscripcion').value = Number(e.monto_inscripcion) || '';
            document.getElementById('monto_colegiatura').value = e.monto_colegiatura;
            document.getElementById('dia_vencimiento').value = e.dia_vencimiento;
            document.getElementById('recargo_monto').value = Number(e.recargo_monto) || '';
            pintarPrimerMes(e.primer_mes.slice(0, 7));
            pintarNumMeses(e.num_meses);
            modal.showModal();
        }

        form.addEventListener('submit', async (ev) => {
            ev.preventDefault();
            const id = document.getElementById('id_esquema').value;
            const fd = new FormData();
            ['ciclo_id', 'nombre', 'monto_colegiatura', 'primer_mes', 'num_meses', 'dia_vencimiento'].forEach(f => fd.append(f, document.getElementById(f).value));
            fd.append('monto_inscripcion', document.getElementById('monto_inscripcion').value || 0);
            fd.append('recargo_monto', document.getElementById('recargo_monto').value || 0);
            let accion = 'crear';
            if (id) { accion = 'editar'; fd.append('id_esquema', id); }
            const d = await (await fetch(API + '?action=' + accion, { method: 'POST', body: fd })).json();
            window.notifyResponse(d);
            if (d.success) { modal.close(); cargar(); }
        });

        async function eliminar(e) {
            if (!await window.confirmar(`¿Eliminar el esquema "${e.nombre}"? Las colegiaturas que ya generó se conservan.`)) return;
            const fd = new FormData(); fd.append('id_esquema', e.id_esquema);
            const d = await (await fetch(API + '?action=eliminar', { method: 'POST', body: fd })).json();
            window.notifyResponse(d);
            if (d.success) cargar();
        }

        /* ---------- Aplicar ---------- */

        async function abrirAplicar(e) {
            // Desglose mes por mes: es exactamente lo que se guardará por alumno
            const dg = await (await fetch(API + '?action=desglose&id_esquema=' + e.id_esquema)).json();
            if (!dg.success) { window.notify('error', dg.message); return; }
            document.getElementById('desgloseFilas').innerHTML = dg.pagos.map(p => `
                <tr>
                    <td>${p.tipo === 'inscripcion' ? 'Inscripción' : esc(p.mes) + ' ' + p.anio}</td>
                    <td>${p.fecha_vencimiento.split('-').reverse().join('/')}</td>
                    <td class="num">${money(p.monto)}</td>
                    <td class="num">${Number(p.recargo_monto) ? money(p.recargo_monto) + ' por mes' : '—'}</td>
                </tr>`).join('');
            document.getElementById('desgloseTotal').textContent = money(dg.total);
            porAplicar = e;
            document.getElementById('aplicarInfo').innerHTML = `<strong>${esc(e.nombre)}</strong> · ${esc(e.ciclo_nombre)}`;
            document.getElementById('aplicarA').value = 'todos';
            document.getElementById('aGrupo').innerHTML = grupos.filter(g => String(g.ciclo_id) === String(e.ciclo_id))
                .map(g => `<option value="${g.id_grupo}">${esc(g.grado)}</option>`).join('') || '<option value="">Sin grupos en este ciclo</option>';
            document.getElementById('aBuscar').value = '';
            verDestino(); pintarAlumnos(); pintarCuantos();
            mAplicar.showModal();
        }
        function verDestino() {
            const v = document.getElementById('aplicarA').value;
            document.getElementById('campoGrupo').style.display = v === 'grupo' ? '' : 'none';
            document.getElementById('campoAlumno').style.display = v === 'alumno' ? '' : 'none';
        }
        // A cuántos alumnos se les generarán los pagos con la selección actual
        function pintarCuantos() {
            const v = document.getElementById('aplicarA').value;
            const delCiclo = alumnos.filter(a => String(a.ciclo_id) === String(porAplicar.ciclo_id));
            const n = v === 'todos' ? delCiclo.length
                : v === 'grupo' ? delCiclo.filter(a => String(a.grupo_id) === document.getElementById('aGrupo').value).length
                : (document.getElementById('aAlumno').value ? 1 : 0);
            document.getElementById('aplicarCuantos').textContent = `Se aplicará a ${n} alumno(s).`;
        }
        ['aplicarA', 'aGrupo', 'aAlumno'].forEach(id => document.getElementById(id).addEventListener('change', pintarCuantos));

        function pintarAlumnos() {
            const q = document.getElementById('aBuscar').value.toLowerCase().trim();
            const f = alumnos.filter(a => String(a.ciclo_id) === String(porAplicar.ciclo_id)
                && (!q || `${a.nombre} ${a.matricula}`.toLowerCase().includes(q)));
            document.getElementById('aAlumno').innerHTML = f.length
                ? f.map(a => `<option value="${a.id_cuenta}">${esc(a.nombre)} — ${esc(a.matricula)} (${esc(a.grado)})</option>`).join('')
                : '<option value="" disabled>Sin coincidencias</option>';
        }
        document.getElementById('aplicarA').addEventListener('change', verDestino);
        document.getElementById('aBuscar').addEventListener('input', pintarAlumnos);

        document.getElementById('btnAplicar').addEventListener('click', async (ev) => {
            const aplicarA = document.getElementById('aplicarA').value;
            const fd = new FormData();
            fd.append('id_esquema', porAplicar.id_esquema);
            fd.append('aplicar_a', aplicarA);
            if (aplicarA === 'grupo') {
                const g = document.getElementById('aGrupo').value;
                if (!g) { window.notify('error', 'Selecciona un grupo.'); return; }
                fd.append('grupo_id', g);
            }
            if (aplicarA === 'alumno') {
                const c = document.getElementById('aAlumno').value;
                if (!c) { window.notify('error', 'Selecciona un alumno.'); return; }
                fd.append('cuenta_id', c);
            }
            ev.target.disabled = true;
            const d = await (await fetch(API + '?action=aplicar', { method: 'POST', body: fd })).json();
            ev.target.disabled = false;
            window.notifyResponse(d);
            if (d.success) mAplicar.close();
        });

        (async () => { await cargarCatalogos(); await cargar(); })();
    </script>
</body>
</html>
