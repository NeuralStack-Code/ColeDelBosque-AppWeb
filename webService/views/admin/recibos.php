<?php
$rol = 1;
require __DIR__ . '/../partials/guard.php';
$base = BASE_URL;
$title = 'Recibos | Admin';
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
            <h2>Finanzas</h2>
            <div style="display:flex;gap:10px;">
                <a class="btn btn-fantasma" href="<?= $base ?>/administrador/tipos-recibo">Tipos de recibo</a>
                <button class="btn btn-primario" onclick="abrirAlta()">+ Nuevo recibo</button>
            </div>
        </div>

        <div class="kpis">
            <div class="kpi ingreso"><div class="lbl"><svg class="ic-s" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 19V5"/><path d="m5 12 7-7 7 7"/></svg> Ingresos</div><div class="val" id="kIn">—</div></div>
            <div class="kpi egreso"><div class="lbl"><svg class="ic-s" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 5v14"/><path d="m19 12-7 7-7-7"/></svg> Egresos</div><div class="val" id="kEg">—</div></div>
            <div class="kpi saldo"><div class="lbl"><svg class="ic-s" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 1v22"/><path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/></svg> Balance</div><div class="val" id="kSaldo">—</div></div>
        </div>

        <div class="filtros">
            <div class="f"><label>Naturaleza</label><select id="fNat"><option value="">Todas</option><option value="ingreso">Ingresos</option><option value="gasto">Egresos</option></select></div>
            <div class="f"><label>Tipo</label><select id="fTipo"><option value="">Todos</option></select></div>
            <div class="f"><label>Año</label><select id="fAnio"><option value="">Todos</option></select></div>
            <div class="f"><label>Mes</label><select id="fMes"><option value="">Todos</option></select></div>
            <div class="f buscar"><label>Buscar</label><input type="text" id="fBuscar" placeholder="Comentario o destinatario…"></div>
            <div class="f-btns">
                <button class="btn btn-fantasma" onclick="limpiar()">Limpiar</button>
                <button class="btn btn-claro" onclick="exportarCSV()" style="box-shadow:var(--sombra-sm);">⬇ CSV</button>
            </div>
        </div>

        <div class="tabla-scroll">
            <table class="tabla">
                <thead><tr><th>Fecha</th><th>Tipo</th><th>Destinatario</th><th>Naturaleza</th><th>Monto</th><th>Pago</th><th>Comentario</th><th></th></tr></thead>
                <tbody id="filas"></tbody>
            </table>
        </div>
        <p class="tabla-vacia" id="vacio" style="display:none;">No hay recibos con esos filtros.</p>
    </section>

    <dialog class="modal" id="modal">
        <div class="modal-in">
            <h3 id="modalTitulo">Nuevo recibo</h3>
            <form id="form">
                <input type="hidden" id="id_recibo">
                <div class="fila-2">
                    <div class="campo"><label>Tipo de recibo</label><select id="tipo_recibo_id"></select></div>
                    <div class="campo"><label>Naturaleza</label><select id="naturaleza"><option value="ingreso">Ingreso</option><option value="gasto">Gasto</option></select></div>
                </div>
                <div class="fila-2">
                    <div class="campo"><label>Destinatario</label>
                        <select id="destinatario_tipo"><option value="escuela">Escuela</option><option value="alumno">Alumno</option><option value="docente">Docente</option></select>
                    </div>
                    <div class="campo" id="campoPersona" style="display:none;"><label id="lblPersona">Persona</label><select id="cuenta_id"></select></div>
                </div>
                <div class="fila-2">
                    <div class="campo"><label>Monto ($)</label><input type="number" id="monto" min="0" step="0.01" required></div>
                    <div class="campo"><label>Tipo de pago</label>
                        <select id="tipo_pago">
                            <option value="Efectivo">Efectivo</option>
                            <option value="Transferencia">Transferencia</option>
                            <option value="Tarjeta">Tarjeta</option>
                            <option value="Cheque">Cheque</option>
                            <option value="Otro">Otro</option>
                        </select>
                    </div>
                </div>
                <div class="campo"><label>Fecha y hora</label><input type="datetime-local" id="fecha" required></div>
                <div class="campo"><label>Comentario</label><textarea id="comentario" placeholder="Opcional"></textarea></div>
                <div class="modal-acciones">
                    <button type="button" class="btn btn-fantasma" onclick="modal.close()">Cancelar</button>
                    <button type="submit" class="btn btn-primario">Guardar</button>
                </div>
            </form>
        </div>
    </dialog>

    <script>
        const API = window.BASE_URL + '/api/recibos';
        const MESES = ['', 'Enero', 'Febrero', 'Marzo', 'Abril', 'Mayo', 'Junio', 'Julio', 'Agosto', 'Septiembre', 'Octubre', 'Noviembre', 'Diciembre'];
        const money = n => '$' + Number(n || 0).toLocaleString('es-MX', { minimumFractionDigits: 2 });
        const filas = document.getElementById('filas');
        const vacio = document.getElementById('vacio');
        const modal = document.getElementById('modal');
        const form = document.getElementById('form');
        let todos = [], tipos = [], alumnos = [], docentes = [];

        function cerrarSesion() {
            fetch(window.BASE_URL + '/api/auth?action=logout', { method: 'POST' })
                .finally(() => location.href = window.BASE_URL + '/inicio-sesion');
        }

        // ---- Catálogos ----
        async function cargarCatalogos() {
            const d = await (await fetch(API + '?action=catalogos')).json();
            tipos = d.tipos || []; alumnos = d.alumnos || []; docentes = d.docentes || [];
            document.getElementById('tipo_recibo_id').innerHTML = tipos.map(t => `<option value="${t.id_tipo}" data-nat="${t.naturaleza}">${t.nombre}</option>`).join('') || '<option value="">Sin tipos</option>';
            document.getElementById('fTipo').innerHTML = '<option value="">Todos</option>' + tipos.map(t => `<option value="${t.id_tipo}">${t.nombre}</option>`).join('');
            document.getElementById('fMes').innerHTML = '<option value="">Todos</option>' + MESES.slice(1).map((n, i) => `<option value="${String(i + 1).padStart(2, '0')}">${n}</option>`).join('');
        }

        // ---- Listar + filtros ----
        async function cargar() {
            const d = await (await fetch(API + '?action=listar')).json();
            todos = d.items || [];
            const anios = [...new Set(todos.map(r => (r.fecha || '').slice(0, 4)).filter(Boolean))].sort().reverse();
            document.getElementById('fAnio').innerHTML = '<option value="">Todos</option>' + anios.map(a => `<option>${a}</option>`).join('');
            render();
        }
        function filtrar() {
            const nat = fNat.value, tipo = fTipo.value, anio = fAnio.value, mes = fMes.value, q = fBuscar.value.toLowerCase();
            return todos.filter(r => {
                const f = r.fecha || '';
                if (nat && r.naturaleza !== nat) return false;
                if (tipo && String(r.tipo_recibo_id) !== tipo) return false;
                if (anio && f.slice(0, 4) !== anio) return false;
                if (mes && f.slice(5, 7) !== mes) return false;
                if (q && !`${r.comentario || ''} ${r.destinatario || ''}`.toLowerCase().includes(q)) return false;
                return true;
            });
        }
        function render() {
            const f = filtrar();
            const totIn = f.filter(r => r.naturaleza === 'ingreso').reduce((a, r) => a + Number(r.monto), 0);
            const totEg = f.filter(r => r.naturaleza === 'gasto').reduce((a, r) => a + Number(r.monto), 0);
            kIn.textContent = money(totIn); kEg.textContent = money(totEg); kSaldo.textContent = money(totIn - totEg);
            filas.innerHTML = '';
            vacio.style.display = f.length ? 'none' : 'block';
            f.forEach(r => {
                const badge = r.naturaleza === 'gasto' ? 'pendiente' : 'pagado';
                const signo = r.naturaleza === 'gasto' ? 'var(--rojo)' : 'var(--verde)';
                const tr = document.createElement('tr');
                tr.innerHTML = `
                    <td>${(r.fecha || '').replace('T', ' ').slice(0, 16)}</td>
                    <td>${r.tipo_nombre || '—'}</td>
                    <td>${r.destinatario || '—'}</td>
                    <td><span class="badge-estatus ${badge}">${r.naturaleza}</span></td>
                    <td style="color:${signo};font-weight:700;">${money(r.monto)}</td>
                    <td>${r.tipo_pago || '—'}</td>
                    <td>${r.comentario || '—'}</td>
                    <td><div class="acciones">
                        <a class="icon-btn" href="${window.BASE_URL}/recibo?id=${r.id_recibo}" target="_blank" title="Ver recibo" style="background:var(--azul-claro);color:var(--azul-marca)"><svg class="ic-s" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M6 9V2h12v7"/><path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"/><path d="M6 14h12v8H6z"/></svg></a>
                        <button class="icon-btn editar" title="Editar"><svg class="ic-s" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 20h9"/><path d="M16.5 3.5a2.1 2.1 0 0 1 3 3L7 19l-4 1 1-4 12.5-12.5Z"/></svg></button>
                        <button class="icon-btn borrar" title="Eliminar"><svg class="ic-s" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 6h18"/><path d="M8 6V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/><path d="M19 6l-1 14a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2L5 6"/></svg></button>
                    </div></td>`;
                tr.querySelector('.editar').addEventListener('click', () => abrirEdicion(r));
                tr.querySelector('.borrar').addEventListener('click', () => eliminar(r));
                filas.appendChild(tr);
            });
        }
        ['fNat', 'fTipo', 'fAnio', 'fMes', 'fBuscar'].forEach(id => document.getElementById(id).addEventListener('input', render));
        function limpiar() { ['fNat', 'fTipo', 'fAnio', 'fMes'].forEach(id => document.getElementById(id).value = ''); fBuscar.value = ''; render(); }

        // Exportar CSV según los filtros en pantalla
        function exportarCSV() {
            const f = filtrar();
            if (!f.length) { window.notify('info', 'No hay recibos para exportar.'); return; }
            const head = ['Fecha', 'Tipo', 'Destinatario', 'Naturaleza', 'Monto', 'Tipo de pago', 'Comentario'];
            const rows = f.map(r => [
                (r.fecha || '').replace('T', ' ').slice(0, 16),
                r.tipo_nombre || '', r.destinatario || '', r.naturaleza || '',
                r.monto, r.tipo_pago || '', r.comentario || ''
            ].map(v => `"${String(v ?? '').replace(/"/g, '""')}"`).join(','));
            const csv = '﻿' + [head.join(','), ...rows].join('\n');
            const url = URL.createObjectURL(new Blob([csv], { type: 'text/csv;charset=utf-8' }));
            const a = document.createElement('a');
            a.href = url; a.download = 'finanzas_' + new Date().toISOString().slice(0, 10) + '.csv';
            a.click(); URL.revokeObjectURL(url);
        }

        // ---- Form: tipo → naturaleza, destinatario → persona ----
        document.getElementById('tipo_recibo_id').addEventListener('change', function () {
            const nat = this.options[this.selectedIndex]?.dataset.nat;
            if (nat) document.getElementById('naturaleza').value = nat;
        });
        document.getElementById('destinatario_tipo').addEventListener('change', pintarPersona);
        function pintarPersona() {
            const dt = document.getElementById('destinatario_tipo').value;
            const campo = document.getElementById('campoPersona');
            const sel = document.getElementById('cuenta_id');
            if (dt === 'escuela') { campo.style.display = 'none'; return; }
            const lista = dt === 'alumno' ? alumnos : docentes;
            document.getElementById('lblPersona').textContent = dt === 'alumno' ? 'Alumno' : 'Docente';
            sel.innerHTML = lista.map(p => `<option value="${p.id_cuenta}">${p.nombre}</option>`).join('') || '<option value="">Sin registros</option>';
            campo.style.display = 'block';
        }

        function abrirAlta() {
            document.getElementById('modalTitulo').textContent = 'Nuevo recibo';
            form.reset();
            document.getElementById('id_recibo').value = '';
            document.getElementById('destinatario_tipo').value = 'escuela';
            pintarPersona();
            const t = document.getElementById('tipo_recibo_id');
            if (t.selectedIndex >= 0) document.getElementById('naturaleza').value = t.options[t.selectedIndex]?.dataset.nat || 'ingreso';
            modal.showModal();
        }
        function abrirEdicion(r) {
            document.getElementById('modalTitulo').textContent = 'Editar recibo';
            document.getElementById('id_recibo').value = r.id_recibo;
            document.getElementById('tipo_recibo_id').value = r.tipo_recibo_id ?? '';
            document.getElementById('naturaleza').value = r.naturaleza;
            document.getElementById('destinatario_tipo').value = r.destinatario_tipo;
            pintarPersona();
            if (r.destinatario_tipo !== 'escuela') document.getElementById('cuenta_id').value = r.cuenta_id ?? '';
            document.getElementById('monto').value = Number(r.monto) || '';
            document.getElementById('tipo_pago').value = r.tipo_pago || 'Efectivo';
            document.getElementById('comentario').value = r.comentario ?? '';
            document.getElementById('fecha').value = (r.fecha || '').replace(' ', 'T').slice(0, 16);
            modal.showModal();
        }

        form.addEventListener('submit', async (e) => {
            e.preventDefault();
            const id = document.getElementById('id_recibo').value;
            const fd = new FormData();
            fd.append('tipo_recibo_id', document.getElementById('tipo_recibo_id').value);
            fd.append('naturaleza', document.getElementById('naturaleza').value);
            fd.append('destinatario_tipo', document.getElementById('destinatario_tipo').value);
            fd.append('cuenta_id', document.getElementById('cuenta_id').value || '');
            fd.append('monto', document.getElementById('monto').value);
            fd.append('tipo_pago', document.getElementById('tipo_pago').value);
            fd.append('comentario', document.getElementById('comentario').value);
            fd.append('fecha', document.getElementById('fecha').value);
            let accion = 'crear';
            if (id) { accion = 'editar'; fd.append('id_recibo', id); }
            const d = await (await fetch(API + '?action=' + accion, { method: 'POST', body: fd })).json();
            window.notifyResponse(d);
            if (d.success) { modal.close(); cargar(); }
        });

        async function eliminar(r) {
            if (!await window.confirmar(`¿Eliminar este recibo de ${money(r.monto)}?`)) return;
            const fd = new FormData(); fd.append('id_recibo', r.id_recibo);
            const d = await (await fetch(API + '?action=eliminar', { method: 'POST', body: fd })).json();
            window.notifyResponse(d);
            if (d.success) cargar();
        }

        (async () => { await cargarCatalogos(); cargar(); })();
    </script>
</body>
</html>
