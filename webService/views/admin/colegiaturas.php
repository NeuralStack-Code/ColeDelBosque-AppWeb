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
            <h2>Colegiaturas</h2>
            <button class="btn btn-primario" onclick="abrirAlta()">+ Registrar pago</button>
        </div>
        <div class="tabla-scroll">
            <table class="tabla">
                <thead><tr><th>Alumno</th><th>Mes</th><th>Monto</th><th>Fecha</th><th>Estatus</th><th></th></tr></thead>
                <tbody id="filas"></tbody>
            </table>
        </div>
        <p class="tabla-vacia" id="vacio" style="display:none;">Aún no hay pagos registrados.</p>
    </section>

    <dialog class="modal" id="modal">
        <div class="modal-in">
            <h3 id="modalTitulo">Registrar pago</h3>
            <form id="form">
                <input type="hidden" id="id_pago">
                <div class="campo" id="campoAlumno">
                    <label>Alumno</label>
                    <select id="cuenta_id" required></select>
                </div>
                <div class="fila-2">
                    <div class="campo"><label>Mes</label>
                        <select id="mes" required>
                            <option value="Enero">Enero</option><option value="Febrero">Febrero</option>
                            <option value="Marzo">Marzo</option><option value="Abril">Abril</option>
                            <option value="Mayo">Mayo</option><option value="Junio">Junio</option>
                            <option value="Julio">Julio</option><option value="Agosto">Agosto</option>
                            <option value="Septiembre">Septiembre</option><option value="Octubre">Octubre</option>
                            <option value="Noviembre">Noviembre</option><option value="Diciembre">Diciembre</option>
                        </select>
                    </div>
                    <div class="campo"><label>Monto ($)</label><input type="number" id="monto" min="0" step="0.01" required></div>
                </div>
                <div class="fila-2">
                    <div class="campo"><label>Fecha de pago</label><input type="date" id="fecha_pago"></div>
                    <div class="campo"><label>Estatus</label>
                        <select id="estatus"><option value="pendiente">Pendiente</option><option value="pagado">Pagado</option></select>
                    </div>
                </div>
                <div class="modal-acciones">
                    <button type="button" class="btn btn-fantasma" onclick="modal.close()">Cancelar</button>
                    <button type="submit" class="btn btn-primario">Guardar</button>
                </div>
            </form>
        </div>
    </dialog>

    <script>
        const API = window.BASE_URL + '/api/colegiaturas';
        const APIA = window.BASE_URL + '/api/control-escolar';
        const filas = document.getElementById('filas');
        const vacio = document.getElementById('vacio');
        const modal = document.getElementById('modal');
        const form = document.getElementById('form');
        const money = n => '$' + Number(n || 0).toLocaleString('es-MX', { minimumFractionDigits: 2 });

        function cerrarSesion() {
            fetch(window.BASE_URL + '/api/auth?action=logout', { method: 'POST' })
                .finally(() => location.href = window.BASE_URL + '/inicio-sesion');
        }

        async function cargarAlumnos() {
            const d = await (await fetch(APIA + '?action=alumnos_listar')).json();
            const sel = document.getElementById('cuenta_id');
            sel.innerHTML = (d.items || []).map(a =>
                `<option value="${a.id_cuenta}">${a.nombre} ${a.paterno} ${a.materno}</option>`).join('')
                || '<option value="">Sin alumnos</option>';
        }

        async function cargar() {
            const d = await (await fetch(API + '?action=listar')).json();
            const items = d.items || [];
            filas.innerHTML = '';
            vacio.style.display = items.length ? 'none' : 'block';
            items.forEach(p => {
                const est = (p.estatus || 'pendiente').toLowerCase();
                const tr = document.createElement('tr');
                tr.innerHTML = `
                    <td>${p.alumno}</td>
                    <td>${p.mes ?? ''}</td>
                    <td>${money(p.monto)}</td>
                    <td>${p.fecha_pago ?? '—'}</td>
                    <td><span class="badge-estatus ${est}">${est}</span></td>
                    <td><div class="acciones">
                        <button class="icon-btn editar" title="Editar"><svg class="ic-s" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 20h9"/><path d="M16.5 3.5a2.1 2.1 0 0 1 3 3L7 19l-4 1 1-4 12.5-12.5Z"/></svg></button>
                        <button class="icon-btn borrar" title="Eliminar"><svg class="ic-s" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 6h18"/><path d="M8 6V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/><path d="M19 6l-1 14a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2L5 6"/></svg></button>
                    </div></td>`;
                tr.querySelector('.editar').addEventListener('click', () => abrirEdicion(p));
                tr.querySelector('.borrar').addEventListener('click', () => eliminar(p));
                filas.appendChild(tr);
            });
        }

        function abrirAlta() {
            document.getElementById('modalTitulo').textContent = 'Registrar pago';
            form.reset();
            document.getElementById('id_pago').value = '';
            document.getElementById('campoAlumno').style.display = 'block';
            modal.showModal();
        }
        function abrirEdicion(p) {
            document.getElementById('modalTitulo').textContent = 'Editar pago';
            document.getElementById('id_pago').value = p.id_pago;
            document.getElementById('campoAlumno').style.display = 'none'; // no se cambia el alumno
            document.getElementById('mes').value = p.mes ?? 'Enero';
            document.getElementById('monto').value = Number(p.monto) || '';
            document.getElementById('fecha_pago').value = p.fecha_pago ?? '';
            document.getElementById('estatus').value = (p.estatus || 'pendiente').toLowerCase();
            modal.showModal();
        }

        form.addEventListener('submit', async (e) => {
            e.preventDefault();
            const id = document.getElementById('id_pago').value;
            const fd = new FormData();
            fd.append('mes', mes.value);
            fd.append('monto', monto.value);
            fd.append('fecha_pago', fecha_pago.value);
            fd.append('estatus', estatus.value);
            let accion = 'crear';
            if (id) { accion = 'editar'; fd.append('id_pago', id); }
            else { fd.append('cuenta_id', document.getElementById('cuenta_id').value); }
            const d = await (await fetch(API + '?action=' + accion, { method: 'POST', body: fd })).json();
            window.notifyResponse(d);
            if (d.success) { modal.close(); cargar(); }
        });

        async function eliminar(p) {
            if (!await window.confirmar(`¿Eliminar el pago de ${p.alumno} (${p.mes})?`)) return;
            const fd = new FormData();
            fd.append('id_pago', p.id_pago);
            const d = await (await fetch(API + '?action=eliminar', { method: 'POST', body: fd })).json();
            window.notifyResponse(d);
            if (d.success) cargar();
        }

        cargarAlumnos();
        cargar();
    </script>
</body>
</html>
