<?php
$rol = 1;
require __DIR__ . '/../partials/guard.php';
$base = BASE_URL;
$title = 'Tipos de descuento | Admin';
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
            <h2>Tipos de descuento</h2>
            <button class="btn btn-primario" onclick="abrirAlta()">+ Nuevo descuento</button>
        </div>
        <p class="sub" style="color:var(--texto-suave);margin:-6px 0 18px;">
            Estos son los descuentos que podrás aplicar a colegiaturas o a inscripciones. El porcentaje se calcula sobre el monto del pago.
        </p>
        <div class="tabla-scroll">
            <table class="tabla">
                <thead><tr><th>Nombre</th><th>Aplica a</th><th>Descuento</th><th>Estado</th><th></th></tr></thead>
                <tbody id="filas"></tbody>
            </table>
        </div>
        <p class="tabla-vacia" id="vacio" style="display:none;">Aún no hay tipos de descuento.</p>
    </section>

    <dialog class="modal" id="modal">
        <div class="modal-in">
            <h3 id="modalTitulo">Nuevo descuento</h3>
            <form id="form">
                <input type="hidden" id="id_descuento">
                <div class="campo"><label>Nombre</label><input type="text" id="nombre" placeholder="Ej. Buen Fin, Pronto pago" required></div>
                <div class="fila-2">
                    <div class="campo"><label>Porcentaje (%)</label><input type="number" id="porcentaje" min="0" max="100" step="0.01" required></div>
                    <div class="campo"><label>Aplica a</label>
                        <select id="aplica_a">
                            <option value="colegiatura">Colegiatura</option>
                            <option value="inscripcion">Inscripción</option>
                        </select>
                    </div>
                </div>
                <div class="campo" id="campoActivo" style="display:none;"><label>Estado</label>
                    <select id="activo"><option value="1">Activo</option><option value="0">Inactivo</option></select>
                </div>
                <div class="modal-acciones">
                    <button type="button" class="btn btn-fantasma" onclick="modal.close()">Cancelar</button>
                    <button type="submit" class="btn btn-primario">Guardar</button>
                </div>
            </form>
        </div>
    </dialog>

    <script>
        const API = window.BASE_URL + '/api/tipos-descuento';
        const filas = document.getElementById('filas');
        const vacio = document.getElementById('vacio');
        const modal = document.getElementById('modal');
        const form = document.getElementById('form');
        const pct = n => Number(n || 0).toLocaleString('es-MX', { maximumFractionDigits: 2 }) + '%';

        function cerrarSesion() {
            fetch(window.BASE_URL + '/api/auth?action=logout', { method: 'POST' })
                .finally(() => location.href = window.BASE_URL + '/inicio-sesion');
        }

        async function cargar() {
            const d = await (await fetch(API + '?action=listar')).json();
            const items = d.items || [];
            filas.innerHTML = '';
            vacio.style.display = items.length ? 'none' : 'block';
            items.forEach(t => {
                const inactivo = Number(t.activo) === 0;
                const tr = document.createElement('tr');
                if (inactivo) tr.style.opacity = '.55';
                tr.innerHTML = `
                    <td><strong>${t.nombre}</strong></td>
                    <td><span class="badge-estatus ${t.aplica_a === 'inscripcion' ? 'pendiente' : 'pagado'}">${t.aplica_a}</span></td>
                    <td><strong>${pct(t.porcentaje)}</strong></td>
                    <td>${inactivo ? '<span class="pill-sin">Inactivo</span>' : '<span class="badge-estatus pagado">activo</span>'}</td>
                    <td><div class="acciones">
                        <button class="icon-btn editar" title="Editar"><svg class="ic-s" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 20h9"/><path d="M16.5 3.5a2.1 2.1 0 0 1 3 3L7 19l-4 1 1-4 12.5-12.5Z"/></svg></button>
                        <button class="icon-btn borrar" title="Eliminar"><svg class="ic-s" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 6h18"/><path d="M8 6V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/><path d="M19 6l-1 14a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2L5 6"/></svg></button>
                    </div></td>`;
                tr.querySelector('.editar').addEventListener('click', () => abrirEdicion(t));
                tr.querySelector('.borrar').addEventListener('click', () => eliminar(t));
                filas.appendChild(tr);
            });
        }

        function abrirAlta() {
            document.getElementById('modalTitulo').textContent = 'Nuevo descuento';
            form.reset();
            document.getElementById('id_descuento').value = '';
            document.getElementById('campoActivo').style.display = 'none';
            modal.showModal();
        }
        function abrirEdicion(t) {
            document.getElementById('modalTitulo').textContent = 'Editar descuento';
            document.getElementById('id_descuento').value = t.id_descuento;
            document.getElementById('nombre').value = t.nombre;
            document.getElementById('porcentaje').value = t.porcentaje;
            document.getElementById('aplica_a').value = t.aplica_a;
            document.getElementById('activo').value = String(t.activo);
            document.getElementById('campoActivo').style.display = 'block';
            modal.showModal();
        }

        form.addEventListener('submit', async (e) => {
            e.preventDefault();
            const id = document.getElementById('id_descuento').value;
            const fd = new FormData();
            fd.append('nombre', document.getElementById('nombre').value);
            fd.append('porcentaje', document.getElementById('porcentaje').value);
            fd.append('aplica_a', document.getElementById('aplica_a').value);
            let accion = 'crear';
            if (id) {
                accion = 'editar';
                fd.append('id_descuento', id);
                fd.append('activo', document.getElementById('activo').value);
            }
            const d = await (await fetch(API + '?action=' + accion, { method: 'POST', body: fd })).json();
            window.notifyResponse(d);
            if (d.success) { modal.close(); cargar(); }
        });

        async function eliminar(t) {
            if (!await window.confirmar(`¿Eliminar el descuento "${t.nombre}"?`)) return;
            const fd = new FormData(); fd.append('id_descuento', t.id_descuento);
            const d = await (await fetch(API + '?action=eliminar', { method: 'POST', body: fd })).json();
            window.notifyResponse(d);
            if (d.success) cargar();
        }

        cargar();
    </script>
</body>
</html>
