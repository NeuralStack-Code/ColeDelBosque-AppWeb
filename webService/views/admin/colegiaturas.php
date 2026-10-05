<?php
$rol = 1;
require __DIR__ . '/../partials/guard.php';
$base = BASE_URL;
$title = 'Colegiaturas | Admin';
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

    <a class="volver-panel" href="<?= $base ?>/administrador">
        <svg class="ic-s" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m15 18-6-6 6-6"/></svg>
        Regresar al panel
    </a>

    <section class="admin-wrap">
        <div class="admin-cab">
            <h2>Colegiaturas <small id="cicloRotulo" style="font-size:.9rem;color:var(--texto-suave);font-weight:600;"></small></h2>
            <div style="display:flex;gap:10px;">
                <a class="btn btn-fantasma" href="<?= $base ?>/administrador/tipos-descuento">Tipos de descuento</a>
                <a class="btn btn-primario" href="<?= $base ?>/administrador/esquemas-pago">Esquemas de pago</a>
            </div>
        </div>

        <div class="filtros">
            <div class="f"><label>Grupo</label><select id="fGrupo"><option value="">Todos</option></select></div>
            <div class="f"><label>Estatus</label>
                <select id="fEstatus">
                    <option value="">Todos</option>
                    <option value="adeudo">Con adeudo vencido</option>
                    <option value="corriente">Al corriente</option>
                    <option value="pagado">Todo pagado</option>
                </select>
            </div>
            <div class="f buscar"><label>Buscar alumno</label><input type="text" id="fBuscar" placeholder="Nombre…"></div>
        </div>

        <!-- Un renglón por alumno; el detalle mes por mes se administra en su ventana -->
        <div class="tabla-scroll">
            <table class="tabla">
                <thead><tr><th>Alumno</th><th>Grupo</th><th>Pagos cubiertos</th><th>Total</th><th>Abonado</th><th>Saldo</th><th>Vencido</th><th>Estatus</th><th></th></tr></thead>
                <tbody id="filas"></tbody>
            </table>
        </div>
        <p class="tabla-vacia" id="vacio" style="display:none;">No hay colegiaturas. Aplica un esquema de pago para empezar.</p>
    </section>

    <!-- Cuenta del alumno: se marcan los meses y se les aplica descuento, recargo o pago de una vez -->
    <dialog class="modal ancho" id="mAlumno">
        <div class="modal-in">
            <h3 id="alTitulo">Cuenta del alumno</h3>
            <p id="alSub" style="color:var(--texto-suave);margin-bottom:14px;"></p>

            <div class="desglose-scroll">
                <table class="tabla-desglose">
                    <thead><tr>
                        <th><input type="checkbox" id="alTodos" title="Marcar todos los pendientes" aria-label="Marcar todos los pendientes"></th>
                        <th>Concepto</th><th>Vence</th><th class="num">Monto</th><th class="num">Descuento</th><th class="num">Recargo</th>
                        <th class="num">Total</th><th class="num">Abonado</th><th class="num">Saldo</th><th>Estatus</th><th></th>
                    </tr></thead>
                    <tbody id="alFilas"></tbody>
                    <tfoot id="alPie"></tfoot>
                </table>
            </div>

            <p id="alSeleccion" style="font-weight:700;margin-bottom:10px;"></p>
            <div class="cuenta-acciones">
                <div class="cuenta-bloque">
                    <label for="aDesc">Descuento</label>
                    <select id="aDesc"></select>
                    <button type="button" class="btn btn-fantasma" id="btnDesc">Aplicar descuento</button>
                </div>
                <div class="cuenta-bloque">
                    <label for="aRec">Recargo por mes vencido ($)</label>
                    <input type="number" id="aRec" min="0" step="0.01">
                    <small id="aRecNota" style="color:var(--texto-suave);font-size:.8rem;"></small>
                    <button type="button" class="btn btn-fantasma" id="btnRec">Cambiar recargo</button>
                </div>
                <div class="cuenta-bloque">
                    <label for="aMonto">Pago ($)</label>
                    <div class="fila-2">
                        <input type="number" id="aMonto" min="0" step="0.01" aria-label="Monto a pagar">
                        <select id="aMetodo" aria-label="Método de pago"><option>Efectivo</option><option>Transferencia</option><option>Tarjeta</option><option>Cheque</option><option>Otro</option></select>
                    </div>
                    <button type="button" class="btn btn-primario" id="btnPago">Registrar pago</button>
                </div>
            </div>
            <p id="alAyuda" style="color:var(--texto-suave);font-size:.85rem;margin-top:10px;"></p>

            <div class="modal-acciones">
                <button type="button" class="btn btn-fantasma" onclick="mAlumno.close()">Cerrar</button>
            </div>
        </div>
    </dialog>

    <script>
        const API = window.BASE_URL + '/api/colegiaturas';
        const money = n => '$' + Number(n || 0).toLocaleString('es-MX', { minimumFractionDigits: 2 });
        const o = v => Number(v) ? money(v) : '—';   // monto opcional: 0 → raya
        const esc = s => String(s ?? '').replace(/[&<>"']/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
        const filas = document.getElementById('filas'), vacio = document.getElementById('vacio');
        const mAlumno = document.getElementById('mAlumno');
        const ESTATUS_AL = { adeudo: ['pendiente', 'Con adeudo'], corriente: ['parcial', 'Al corriente'], pagado: ['pagado', 'Todo pagado'] };
        let todos = [], descuentos = [], estatusMap = {};
        let cuentaAbierta = null;   // cuenta_id del alumno en la ventana

        function cerrarSesion() {
            fetch(window.BASE_URL + '/api/auth?action=logout', { method: 'POST' })
                .finally(() => location.href = window.BASE_URL + '/inicio-sesion');
        }

        /* ---------- Datos ---------- */

        const estDe = c => (c.estatus || 'pendiente').toLowerCase();
        const saldoDe = c => estDe(c) === 'pagado' ? 0 : Number(c.saldo);
        const abonadoDe = c => estDe(c) === 'pagado' ? Number(c.total) : Number(c.abonado);
        const hoy = (() => { const d = new Date(); return `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}-${String(d.getDate()).padStart(2, '0')}`; })();
        const conceptoDe = c => c.tipo === 'inscripcion' ? 'Inscripción'
            : (c.mes ?? '') + (c.fecha_vencimiento ? ' ' + c.fecha_vencimiento.slice(0, 4) : '');

        // Pagos de un alumno: inscripción primero y luego por vencimiento
        function pagosDe(cuentaId) {
            return todos.filter(c => String(c.cuenta_id) === String(cuentaId))
                .sort((a, b) => (a.tipo === 'inscripcion' ? 0 : 1) - (b.tipo === 'inscripcion' ? 0 : 1)
                    || String(a.fecha_vencimiento).localeCompare(String(b.fecha_vencimiento)));
        }

        // Un resumen por alumno a partir de sus pagos
        function resumenes() {
            const mapa = new Map();
            todos.forEach(c => {
                let r = mapa.get(c.cuenta_id);
                if (!r) mapa.set(c.cuenta_id, r = { cuenta_id: c.cuenta_id, alumno: c.alumno, grado: c.grado, grupo_id: c.grupo_id,
                    pagos: 0, cubiertos: 0, total: 0, abonado: 0, saldo: 0, vencido: 0 });
                r.pagos++;
                if (estDe(c) === 'pagado') r.cubiertos++;
                r.total += Number(c.total); r.abonado += abonadoDe(c); r.saldo += saldoDe(c);
                if (saldoDe(c) > 0 && c.fecha_vencimiento && c.fecha_vencimiento < hoy) r.vencido += saldoDe(c);
            });
            return [...mapa.values()].map(r => ({ ...r, estatus: r.saldo <= 0.005 ? 'pagado' : (r.vencido > 0 ? 'adeudo' : 'corriente') }));
        }

        async function cargarCatalogos() {
            const d = await (await fetch(API + '?action=catalogos')).json();
            descuentos = d.descuentos || [];
            estatusMap = {};
            (d.estatus || []).forEach(s => estatusMap[s.clave] = s.nombre);
            document.getElementById('fGrupo').innerHTML = '<option value="">Todos</option>'
                + (d.grupos || []).map(g => `<option value="${g.id_grupo}">${esc(g.grado)}</option>`).join('');
            document.getElementById('cicloRotulo').textContent = d.ciclo ? '· ' + d.ciclo.nombre : '· sin ciclo activo';
        }

        async function cargar() {
            const d = await (await fetch(API + '?action=listar')).json();
            todos = d.items || [];
            render();
            if (cuentaAbierta !== null && mAlumno.open) pintarCuenta();
        }

        /* ---------- Listado: un renglón por alumno ---------- */

        function render() {
            const q = document.getElementById('fBuscar').value.toLowerCase();
            const grp = document.getElementById('fGrupo').value;
            const est = document.getElementById('fEstatus').value;
            const f = resumenes().filter(r => {
                if (grp && String(r.grupo_id) !== grp) return false;
                if (est && r.estatus !== est) return false;
                if (q && !(r.alumno || '').toLowerCase().includes(q)) return false;
                return true;
            });
            filas.innerHTML = '';
            vacio.style.display = f.length ? 'none' : 'block';
            f.forEach(r => {
                const [clase, rotulo] = ESTATUS_AL[r.estatus];
                const tr = document.createElement('tr');
                tr.innerHTML = `
                    <td><button type="button" class="enlace-alumno">${esc(r.alumno)}</button></td>
                    <td>${esc(r.grado ?? '—')}</td>
                    <td>${r.cubiertos} de ${r.pagos}</td>
                    <td>${money(r.total)}</td>
                    <td>${o(r.abonado)}</td>
                    <td><strong>${money(r.saldo)}</strong></td>
                    <td style="color:${r.vencido ? 'var(--rojo)' : 'inherit'}">${o(r.vencido)}</td>
                    <td><span class="badge-estatus ${clase}" style="text-transform:none">${rotulo}</span></td>
                    <td><button type="button" class="btn btn-fantasma administrar" style="padding:.4em 1em;font-size:.85rem;">Administrar</button></td>`;
                tr.querySelector('.enlace-alumno').addEventListener('click', () => abrirCuenta(r.cuenta_id));
                tr.querySelector('.administrar').addEventListener('click', () => abrirCuenta(r.cuenta_id));
                filas.appendChild(tr);
            });
        }
        ['fGrupo', 'fEstatus', 'fBuscar'].forEach(id => document.getElementById(id).addEventListener('input', render));

        /* ---------- Cuenta del alumno ---------- */

        function abrirCuenta(cuentaId) {
            cuentaAbierta = cuentaId;
            document.getElementById('aDesc').innerHTML = '<option value="0">Quitar descuento</option>'
                + descuentos.map(d => `<option value="${d.id_descuento}">${esc(d.nombre)} (${Number(d.porcentaje)}% · ${d.aplica_a === 'inscripcion' ? 'inscripción' : 'colegiatura'})</option>`).join('');
            pintarCuenta(true);
            mAlumno.showModal();
        }

        // marcarPendientes: al abrir, deja listos los meses ya vencidos (lo más común es cobrarlos)
        function pintarCuenta(marcarVencidos = false) {
            const suyos = pagosDe(cuentaAbierta);
            if (!suyos.length) { mAlumno.close(); return; }
            const marcados = new Set(seleccion().map(c => String(c.id_pago)));
            const t = { monto: 0, descuento: 0, recargo: 0, total: 0, abonado: 0, saldo: 0 };

            document.getElementById('alFilas').innerHTML = suyos.map(c => {
                const est = estDe(c), pagado = est === 'pagado';
                t.monto += Number(c.monto); t.descuento += Number(c.descuento); t.recargo += Number(c.recargo_calc);
                t.total += Number(c.total); t.abonado += abonadoDe(c); t.saldo += saldoDe(c);
                const vencido = !pagado && c.fecha_vencimiento && c.fecha_vencimiento < hoy;
                const marcar = !pagado && (marcarVencidos ? vencido : marcados.has(String(c.id_pago)));
                const recibo = c.recibo_id ? `<a class="icon-btn" title="Ver recibo" href="${window.BASE_URL}/recibo?id=${c.recibo_id}" target="_blank">🧾</a>` : '';
                return `<tr data-id="${c.id_pago}">
                    <td><input type="checkbox" class="alChk" ${pagado ? 'disabled' : ''} ${marcar ? 'checked' : ''} aria-label="Seleccionar ${esc(conceptoDe(c))}"></td>
                    <td>${esc(conceptoDe(c))}</td>
                    <td style="color:${vencido ? 'var(--rojo)' : 'inherit'}">${c.fecha_vencimiento ? c.fecha_vencimiento.split('-').reverse().join('/') : '—'}</td>
                    <td class="num">${money(c.monto)}</td>
                    <td class="num" style="color:${Number(c.descuento) ? 'var(--verde)' : 'inherit'}" title="${esc(c.concepto_descuento ?? '')}">${Number(c.descuento) ? '−' + money(c.descuento) : '—'}</td>
                    <td class="num" style="color:${Number(c.recargo_calc) ? 'var(--rojo)' : 'inherit'}" title="${Number(c.recargo_monto) ? money(c.recargo_monto) + ' por mes vencido' : ''}">${o(c.recargo_calc)}</td>
                    <td class="num"><strong>${money(c.total)}</strong></td>
                    <td class="num">${o(abonadoDe(c))}</td>
                    <td class="num">${o(saldoDe(c))}</td>
                    <td><span class="badge-estatus ${est}">${esc(estatusMap[est] || est)}</span></td>
                    <td><div class="acciones">${recibo}<button type="button" class="icon-btn borrar" title="Eliminar este pago"><svg class="ic-s" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 6h18"/><path d="M8 6V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/><path d="M19 6l-1 14a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2L5 6"/></svg></button></div></td>
                </tr>`;
            }).join('');

            document.getElementById('alPie').innerHTML = `<tr>
                <td colspan="3">Totales</td>
                <td class="num">${money(t.monto)}</td>
                <td class="num">${t.descuento ? '−' + money(t.descuento) : '—'}</td>
                <td class="num">${o(t.recargo)}</td>
                <td class="num">${money(t.total)}</td>
                <td class="num">${o(t.abonado)}</td>
                <td class="num">${money(t.saldo)}</td>
                <td colspan="2"></td></tr>`;
            document.getElementById('alTitulo').textContent = suyos[0].alumno;
            document.getElementById('alSub').textContent = (suyos[0].grado ?? 'Sin grupo') + ' · ' + suyos.length + ' pago(s)';

            document.querySelectorAll('#alFilas .alChk').forEach(ch => ch.addEventListener('change', pintarSeleccion));
            document.querySelectorAll('#alFilas .borrar').forEach(b => b.addEventListener('click', () =>
                eliminar(todos.find(c => String(c.id_pago) === b.closest('tr').dataset.id))));
            pintarSeleccion();
        }

        // Pagos marcados en la ventana
        function seleccion() {
            const ids = [...document.querySelectorAll('#alFilas .alChk:checked')].map(ch => ch.closest('tr').dataset.id);
            return todos.filter(c => ids.includes(String(c.id_pago)));
        }

        function pintarSeleccion() {
            const sel = seleccion();
            const saldo = sel.reduce((s, c) => s + saldoDe(c), 0);
            const libres = document.querySelectorAll('#alFilas .alChk:not(:disabled)');
            document.getElementById('alTodos').checked = libres.length > 0 && sel.length === libres.length;
            document.getElementById('alSeleccion').textContent = sel.length
                ? `${sel.length} pago(s) seleccionado(s) · saldo ${money(saldo)}`
                : 'Marca los pagos a los que quieras aplicar un descuento, un recargo o un pago.';

            // El recargo viene del esquema de pago: aquí se muestra el que ya tienen y solo se cambia como excepción
            const recargos = [...new Set(sel.map(c => Number(c.recargo_monto)))];
            const rec = document.getElementById('aRec');
            rec.value = recargos.length === 1 ? recargos[0].toFixed(2) : '';
            rec.placeholder = recargos.length > 1 ? 'Varios' : '';
            document.getElementById('aRecNota').textContent = !sel.length ? 'Lo define el esquema de pago.'
                : recargos.length > 1 ? 'Los pagos marcados tienen recargos distintos.'
                : (recargos[0] > 0 ? 'Es el del esquema de pago; cámbialo solo como excepción (0 lo quita).'
                                   : 'El esquema no tiene recargo; aquí puedes poner uno a estos pagos.');

            // Con un solo pago se puede abonar menos; con varios se salda cada uno completo
            const monto = document.getElementById('aMonto');
            monto.value = sel.length ? saldo.toFixed(2) : '';
            monto.readOnly = sel.length !== 1;
            document.getElementById('alAyuda').textContent = sel.length > 1
                ? 'Al pagar varios a la vez se salda cada uno completo y se genera un recibo por pago.'
                : (sel.length === 1 ? 'Deja el saldo completo para saldar, o captura menos para registrar un abono.' : '');
        }
        document.getElementById('alTodos').addEventListener('change', e => {
            document.querySelectorAll('#alFilas .alChk:not(:disabled)').forEach(ch => ch.checked = e.target.checked);
            pintarSeleccion();
        });

        /* ---------- Acciones en lote ---------- */

        // Llama la acción para cada pago y avisa una sola vez con el resultado
        // Los botones nunca se apagan: sin pagos marcados avisan qué falta
        function haySeleccion() {
            if (seleccion().length) return true;
            window.notify('warning', 'Primero marca en la tabla los pagos a los que se aplicará.');
            return false;
        }

        async function enLote(pagos, accion, datos, hecho) {
            if (!pagos.length) return;
            const botones = ['btnDesc', 'btnRec', 'btnPago'].map(id => document.getElementById(id));
            botones.forEach(b => b.disabled = true);
            let ok = 0, error = '';
            for (const c of pagos) {
                const fd = new FormData();
                fd.append('id_pago', c.id_pago);
                Object.entries(datos(c)).forEach(([k, v]) => fd.append(k, v));
                try {
                    const d = await (await fetch(`${API}?action=${accion}`, { method: 'POST', body: fd })).json();
                    if (d.success) ok++; else error = error || d.message;
                } catch { error = error || 'No se pudo conectar con el servidor.'; }
            }
            const fallos = pagos.length - ok;
            window.notify(fallos ? (ok ? 'warning' : 'error') : 'success',
                (ok ? `${hecho} en ${ok} pago(s).` : '') + (fallos ? ` ${fallos} no se pudieron: ${error}` : ''));
            await cargar();
            botones.forEach(b => b.disabled = false);
        }

        document.getElementById('btnDesc').addEventListener('click', () => {
            if (!haySeleccion()) return;
            const id = document.getElementById('aDesc').value;
            const desc = descuentos.find(d => String(d.id_descuento) === id);
            let sel = seleccion();
            if (desc) {
                // Cada descuento del catálogo es para inscripción o para colegiatura
                const aplican = sel.filter(c => (c.tipo === 'inscripcion' ? 'inscripcion' : 'colegiatura') === desc.aplica_a);
                if (!aplican.length) { window.notify('warning', 'Ese descuento no aplica a los pagos seleccionados.'); return; }
                if (aplican.length < sel.length) window.notify('info', `${sel.length - aplican.length} pago(s) se omiten: el descuento no aplica a ese tipo.`);
                sel = aplican;
            }
            enLote(sel, 'descuento', () => ({ tipo_descuento_id: id }), desc ? 'Descuento aplicado' : 'Descuento quitado');
        });

        document.getElementById('btnRec').addEventListener('click', () => {
            if (!haySeleccion()) return;
            const v = document.getElementById('aRec').value;
            const monto = v === '' ? NaN : Number(v);
            if (isNaN(monto) || monto < 0) { window.notify('error', 'Captura el recargo en pesos (0 para quitarlo).'); return; }
            enLote(seleccion(), 'recargo', () => ({ recargo_monto: monto }), monto > 0 ? 'Recargo actualizado' : 'Recargo quitado');
        });

        document.getElementById('btnPago').addEventListener('click', async () => {
            if (!haySeleccion()) return;
            const sel = seleccion();
            const metodo = document.getElementById('aMetodo').value;
            const monto = Number(document.getElementById('aMonto').value);
            if (sel.length === 1) {
                if (!monto || monto <= 0) { window.notify('error', 'Captura un monto válido.'); return; }
                return enLote(sel, 'registrar_pago', () => ({ monto, tipo_pago: metodo }), 'Pago registrado');
            }
            const total = sel.reduce((s, c) => s + saldoDe(c), 0);
            if (!await window.confirmar(`Se saldarán ${sel.length} pagos por ${money(total)} en ${metodo.toLowerCase()} y se generará un recibo por cada uno.`,
                { titulo: '¿Registrar los pagos?', confirmar: 'Registrar', peligro: false })) return;
            enLote(sel, 'registrar_pago', () => ({ tipo_pago: metodo }), 'Pago registrado');
        });

        async function eliminar(c) {
            const concepto = c.tipo === 'inscripcion' ? 'la inscripción' : `la colegiatura de ${conceptoDe(c)}`;
            if (!await window.confirmar(`¿Eliminar ${concepto} de ${c.alumno}?`)) return;
            const fd = new FormData(); fd.append('id_pago', c.id_pago);
            const d = await (await fetch(API + '?action=eliminar', { method: 'POST', body: fd })).json();
            window.notifyResponse(d);
            if (d.success) cargar();
        }

        (async () => { await cargarCatalogos(); cargar(); })();
    </script>
</body>
</html>
