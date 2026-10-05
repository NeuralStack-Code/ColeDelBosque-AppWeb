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
            <div class="f"><label>Tipo</label><select id="fTipo"><option value="">Todos</option><option value="inscripcion">Inscripción</option><option value="colegiatura">Colegiatura</option></select></div>
            <div class="f"><label>Mes</label><select id="fMes"><option value="">Todos</option></select></div>
            <div class="f"><label>Estatus</label><select id="fEstatus"><option value="">Todos</option><option value="pendiente">Pendientes</option><option value="pagado">Pagadas</option></select></div>
            <div class="f buscar"><label>Buscar alumno</label><input type="text" id="fBuscar" placeholder="Nombre…"></div>
        </div>

        <div class="tabla-scroll">
            <table class="tabla">
                <thead><tr><th>Alumno</th><th>Grupo</th><th>Concepto</th><th>Vence</th><th>Monto</th><th>Recargo</th><th>Descuento</th><th>Total</th><th>Estatus</th><th></th></tr></thead>
                <tbody id="filas"></tbody>
            </table>
        </div>
        <p class="tabla-vacia" id="vacio" style="display:none;">No hay colegiaturas. Aplica un esquema de pago para empezar.</p>
    </section>

    <!-- Estado de cuenta del alumno: todos sus pagos del ciclo, mes por mes -->
    <dialog class="modal ancho" id="mCuenta">
        <div class="modal-in">
            <h3 id="cuentaTitulo">Estado de cuenta</h3>
            <p id="cuentaSub" style="color:var(--texto-suave);margin-bottom:14px;"></p>
            <div class="desglose-scroll">
                <table class="tabla-desglose">
                    <thead><tr><th>Concepto</th><th>Vence</th><th class="num">Monto</th><th class="num">Descuento</th><th class="num">Recargo</th><th class="num">Total</th><th class="num">Abonado</th><th class="num">Saldo</th><th>Estatus</th></tr></thead>
                    <tbody id="cuentaFilas"></tbody>
                    <tfoot id="cuentaPie"></tfoot>
                </table>
            </div>
            <div class="modal-acciones">
                <button type="button" class="btn btn-fantasma" onclick="mCuenta.close()">Cerrar</button>
            </div>
        </div>
    </dialog>

    <!-- Modal registrar pago / abono -->
    <dialog class="modal" id="mPago">
        <div class="modal-in">
            <h3>Registrar pago</h3>
            <p class="sub" id="pagoInfo" style="color:var(--texto-suave);margin-bottom:12px;"></p>
            <div class="fila-2">
                <div class="campo"><label>Monto a pagar ($)</label><input type="number" id="pMonto" min="0" step="0.01"></div>
                <div class="campo"><label>Método de pago</label>
                    <select id="pTipo"><option>Efectivo</option><option>Transferencia</option><option>Tarjeta</option><option>Cheque</option><option>Otro</option></select>
                </div>
            </div>
            <p style="color:var(--texto-suave);font-size:.85rem;margin:2px 0 0;">Deja el saldo completo para saldar, o captura menos para registrar un <strong>abono</strong>.</p>
            <div class="modal-acciones">
                <button type="button" class="btn btn-fantasma" onclick="mPago.close()">Cancelar</button>
                <button type="button" class="btn btn-primario" onclick="confirmarPago()">Registrar y generar recibo</button>
            </div>
        </div>
    </dialog>

    <!-- Modal descuento -->
    <dialog class="modal" id="mDesc">
        <div class="modal-in">
            <h3>Aplicar descuento</h3>
            <p class="sub" id="descInfo" style="color:var(--texto-suave);margin-bottom:14px;"></p>
            <div class="campo"><label>Tipo de descuento</label>
                <select id="dTipo"><option value="0">Sin descuento</option></select>
            </div>
            <p id="descPreview" style="margin:4px 0 0;font-weight:700;color:var(--verde);"></p>
            <div class="modal-acciones">
                <button type="button" class="btn btn-fantasma" onclick="mDesc.close()">Cancelar</button>
                <button type="button" class="btn btn-primario" onclick="confirmarDescuento()">Aplicar</button>
            </div>
        </div>
    </dialog>

    <script>
        const API = window.BASE_URL + '/api/colegiaturas';
        const MESES = ['', 'Enero', 'Febrero', 'Marzo', 'Abril', 'Mayo', 'Junio', 'Julio', 'Agosto', 'Septiembre', 'Octubre', 'Noviembre', 'Diciembre'];
        const money = n => '$' + Number(n || 0).toLocaleString('es-MX', { minimumFractionDigits: 2 });
        const filas = document.getElementById('filas'), vacio = document.getElementById('vacio');
        let todos = [], grupos = [], alumnos = [], descuentos = [];
        let estatusMap = {};
        let pagoId = null, descRow = null;

        function cerrarSesion() {
            fetch(window.BASE_URL + '/api/auth?action=logout', { method: 'POST' })
                .finally(() => location.href = window.BASE_URL + '/inicio-sesion');
        }

        async function cargarCatalogos() {
            const d = await (await fetch(API + '?action=catalogos')).json();
            grupos = d.grupos || [];
            alumnos = d.alumnos || [];
            descuentos = d.descuentos || [];
            estatusMap = {};
            (d.estatus || []).forEach(s => estatusMap[s.clave] = s.nombre);
            const opts = grupos.map(g => `<option value="${g.id_grupo}">${g.grado}</option>`).join('');
            document.getElementById('fGrupo').innerHTML = '<option value="">Todos</option>' + opts;
            document.getElementById('cicloRotulo').textContent = d.ciclo ? '· ' + d.ciclo.nombre : '· sin ciclo activo';
        }

        async function cargar() {
            const d = await (await fetch(API + '?action=listar')).json();
            todos = d.items || [];
            actualizarMeses();
            render();
        }

        // Cascada: los meses disponibles dependen del grupo seleccionado
        function actualizarMeses() {
            const grp = document.getElementById('fGrupo').value;
            const base = grp ? todos.filter(c => String(c.grupo_id) === grp) : todos;
            const meses = [...new Set(base.map(c => c.mes).filter(m => m && m !== 'Inscripción'))]
                .sort((a, b) => MESES.indexOf(a) - MESES.indexOf(b));
            const sel = document.getElementById('fMes');
            const actual = sel.value;
            sel.innerHTML = '<option value="">Todos</option>' + meses.map(m => `<option>${m}</option>`).join('');
            sel.value = meses.includes(actual) ? actual : '';
        }

        function render() {
            const q = document.getElementById('fBuscar').value.toLowerCase();
            const grp = document.getElementById('fGrupo').value;
            const tipo = document.getElementById('fTipo').value;
            const mes = document.getElementById('fMes').value;
            const est = document.getElementById('fEstatus').value;
            const f = todos.filter(c => {
                if (grp && String(c.grupo_id) !== grp) return false;
                if (tipo && (c.tipo || 'colegiatura') !== tipo) return false;
                if (mes && (c.mes || '') !== mes) return false;
                if (est && (c.estatus || '').toLowerCase() !== est) return false;
                if (q && !(c.alumno || '').toLowerCase().includes(q)) return false;
                return true;
            });
            filas.innerHTML = '';
            vacio.style.display = f.length ? 'none' : 'block';
            f.forEach(c => {
                const estClave = (c.estatus || 'pendiente').toLowerCase();
                const estLabel = estatusMap[estClave] || c.estatus;
                const pagado = estClave === 'pagado';
                const esIns = c.tipo === 'inscripcion';
                const concepto = esIns
                    ? '<span class="badge-estatus parcial">Inscripción</span>'
                    : (c.mes ?? '');
                const reciboLink = c.recibo_id
                    ? `<a class="icon-btn" title="Ver recibo" href="${window.BASE_URL}/recibo?id=${c.recibo_id}" target="_blank">🧾</a>` : '';
                const acciones = pagado
                    ? (c.recibo_id ? `<a class="badge-estatus pagado" href="${window.BASE_URL}/recibo?id=${c.recibo_id}" target="_blank" style="text-decoration:none">pagado 🧾</a>` : `<span class="badge-estatus pagado">pagado</span>`)
                    : `<button class="icon-btn editar" title="Registrar pago o abono" data-a="pago"><svg class="ic-s" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 1v22"/><path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/></svg></button>
                       <button class="icon-btn editar" title="Descuento" data-a="desc"><svg class="ic-s" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M9 9h.01M15 15h.01M17 7 7 17"/><path d="M20.9 12a9 9 0 1 1-9-9"/></svg></button>${reciboLink}`;
                const saldoLinea = (!pagado && Number(c.abonado) > 0)
                    ? `<br><small style="color:var(--texto-suave)">Abonado ${money(c.abonado)} · Saldo <strong>${money(c.saldo)}</strong></small>` : '';
                const tr = document.createElement('tr');
                tr.innerHTML = `
                    <td><button type="button" class="enlace-alumno" title="Ver estado de cuenta">${c.alumno}</button></td>
                    <td>${c.grado ?? '—'}</td>
                    <td>${concepto}</td>
                    <td>${c.fecha_vencimiento ?? '—'}</td>
                    <td>${money(c.monto)}</td>
                    <td style="color:${Number(c.recargo_calc) ? 'var(--rojo)' : 'inherit'}">${Number(c.recargo_calc) ? money(c.recargo_calc) : '—'}</td>
                    <td style="color:${Number(c.descuento) ? 'var(--verde)' : 'inherit'}">${Number(c.descuento) ? '−' + money(c.descuento) : '—'}${c.concepto_descuento ? ' <small style="color:var(--texto-suave)">(' + c.concepto_descuento + ')</small>' : ''}</td>
                    <td><strong>${money(c.total)}</strong>${saldoLinea}</td>
                    <td><span class="badge-estatus ${estClave}">${estLabel}</span></td>
                    <td><div class="acciones">${acciones}
                        <button class="icon-btn borrar" title="Eliminar" data-a="del"><svg class="ic-s" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 6h18"/><path d="M8 6V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/><path d="M19 6l-1 14a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2L5 6"/></svg></button>
                    </div></td>`;
                tr.querySelector('.enlace-alumno').addEventListener('click', () => abrirCuenta(c));
                tr.querySelectorAll('[data-a]').forEach(b => b.addEventListener('click', () => {
                    if (b.dataset.a === 'pago') abrirPago(c);
                    else if (b.dataset.a === 'desc') abrirDesc(c);
                    else eliminar(c);
                }));
                filas.appendChild(tr);
            });
        }
        document.getElementById('fGrupo').addEventListener('change', () => { actualizarMeses(); render(); });
        ['fTipo', 'fMes', 'fEstatus', 'fBuscar'].forEach(id => document.getElementById(id).addEventListener('input', render));

        /* ---------- Estado de cuenta ---------- */
        function abrirCuenta(c) {
            const suyos = todos.filter(x => String(x.cuenta_id) === String(c.cuenta_id))
                .sort((a, b) => (a.tipo === 'inscripcion' ? 0 : 1) - (b.tipo === 'inscripcion' ? 0 : 1)
                    || String(a.fecha_vencimiento).localeCompare(String(b.fecha_vencimiento)));
            const t = { monto: 0, descuento: 0, recargo: 0, total: 0, abonado: 0, saldo: 0 };
            const o = v => Number(v) ? money(v) : '—';
            document.getElementById('cuentaFilas').innerHTML = suyos.map(x => {
                const est = (x.estatus || 'pendiente').toLowerCase();
                const saldo = est === 'pagado' ? 0 : Number(x.saldo);
                const abonado = est === 'pagado' ? Number(x.total) : Number(x.abonado);
                t.monto += Number(x.monto); t.descuento += Number(x.descuento); t.recargo += Number(x.recargo_calc);
                t.total += Number(x.total); t.abonado += abonado; t.saldo += saldo;
                const venc = x.fecha_vencimiento ? x.fecha_vencimiento.split('-').reverse().join('/') : '—';
                return `<tr>
                    <td>${x.tipo === 'inscripcion' ? 'Inscripción' : (x.mes ?? '') + (x.fecha_vencimiento ? ' ' + x.fecha_vencimiento.slice(0, 4) : '')}</td>
                    <td>${venc}</td>
                    <td class="num">${money(x.monto)}</td>
                    <td class="num" style="color:${Number(x.descuento) ? 'var(--verde)' : 'inherit'}">${Number(x.descuento) ? '−' + money(x.descuento) : '—'}</td>
                    <td class="num" style="color:${Number(x.recargo_calc) ? 'var(--rojo)' : 'inherit'}">${o(x.recargo_calc)}</td>
                    <td class="num"><strong>${money(x.total)}</strong></td>
                    <td class="num">${o(abonado)}</td>
                    <td class="num">${o(saldo)}</td>
                    <td><span class="badge-estatus ${est}">${estatusMap[est] || est}</span></td>
                </tr>`;
            }).join('');
            document.getElementById('cuentaPie').innerHTML = `<tr>
                <td colspan="2">Totales</td>
                <td class="num">${money(t.monto)}</td>
                <td class="num">${t.descuento ? '−' + money(t.descuento) : '—'}</td>
                <td class="num">${o(t.recargo)}</td>
                <td class="num">${money(t.total)}</td>
                <td class="num">${o(t.abonado)}</td>
                <td class="num">${money(t.saldo)}</td>
                <td></td></tr>`;
            document.getElementById('cuentaTitulo').textContent = 'Estado de cuenta · ' + c.alumno;
            document.getElementById('cuentaSub').textContent = (c.grado ?? 'Sin grupo') + ' · ' + suyos.length + ' pago(s)';
            mCuenta.showModal();
        }

        /* ---------- Pago / abono ---------- */
        function abrirPago(c) {
            pagoId = c.id_pago;
            const concepto = c.tipo === 'inscripcion' ? 'Inscripción' : c.mes;
            const saldo = Number(c.saldo != null ? c.saldo : c.total);
            const abon = Number(c.abonado || 0);
            const pm = document.getElementById('pMonto');
            pm.value = saldo.toFixed(2);
            pm.max = saldo.toFixed(2);
            document.getElementById('pagoInfo').innerHTML =
                `<strong>${c.alumno}</strong> — ${concepto}<br>Total: ${money(c.total)}${abon ? ` · Abonado: ${money(abon)}` : ''} · Saldo: <strong>${money(saldo)}</strong>`;
            mPago.showModal();
        }
        async function confirmarPago() {
            const monto = Number(document.getElementById('pMonto').value);
            if (!monto || monto <= 0) { window.notify('error', 'Captura un monto válido.'); return; }
            const fd = new FormData();
            fd.append('id_pago', pagoId);
            fd.append('monto', monto);
            fd.append('tipo_pago', document.getElementById('pTipo').value);
            const d = await (await fetch(API + '?action=registrar_pago', { method: 'POST', body: fd })).json();
            window.notifyResponse(d);
            if (d.success) { mPago.close(); cargar(); }
        }

        /* ---------- Descuento (desde catálogo) ---------- */
        function abrirDesc(c) {
            descRow = c;
            const aplica = c.tipo === 'inscripcion' ? 'inscripcion' : 'colegiatura';
            const opciones = descuentos.filter(d => d.aplica_a === aplica);
            const sel = document.getElementById('dTipo');
            sel.innerHTML = '<option value="0">Sin descuento</option>' +
                opciones.map(d => `<option value="${d.id_descuento}">${d.nombre} (${Number(d.porcentaje)}%)</option>`).join('');
            sel.value = c.tipo_descuento_id ? String(c.tipo_descuento_id) : '0';
            document.getElementById('descInfo').textContent =
                `${c.alumno} — ${aplica === 'inscripcion' ? 'Inscripción' : c.mes} · Monto: ${money(c.monto)}`;
            actualizarPreview();
            if (!opciones.length) window.notify('info', 'No hay descuentos para este tipo. Créalos en «Tipos de descuento».');
            mDesc.showModal();
        }
        document.getElementById('dTipo').addEventListener('change', actualizarPreview);
        function actualizarPreview() {
            const id = document.getElementById('dTipo').value;
            const d = descuentos.find(x => String(x.id_descuento) === id);
            const prev = document.getElementById('descPreview');
            if (!d || !descRow) { prev.textContent = ''; return; }
            const monto = Number(descRow.monto) * Number(d.porcentaje) / 100;
            prev.textContent = `Se aplicará −${money(monto)} (${Number(d.porcentaje)}%)`;
        }
        async function confirmarDescuento() {
            const fd = new FormData();
            fd.append('id_pago', descRow.id_pago);
            fd.append('tipo_descuento_id', document.getElementById('dTipo').value);
            const d = await (await fetch(API + '?action=descuento', { method: 'POST', body: fd })).json();
            window.notifyResponse(d);
            if (d.success) { mDesc.close(); cargar(); }
        }

        async function eliminar(c) {
            const concepto = c.tipo === 'inscripcion' ? 'la inscripción' : `la colegiatura (${c.mes})`;
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
