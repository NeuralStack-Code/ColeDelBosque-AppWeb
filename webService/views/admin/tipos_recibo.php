<?php
$rol = 1;
require __DIR__ . '/../partials/guard.php';
$base = BASE_URL;
$title = 'Tipos de recibo | Admin';
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

    <a class="volver-panel" href="<?= $base ?>/administrador/recibos">
        <svg class="ic-s" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m15 18-6-6 6-6"/></svg>
        Volver a recibos
    </a>

    <section class="admin-wrap">
        <div class="admin-cab">
            <h2>Tipos de recibo</h2>
            <button class="btn btn-primario" onclick="abrirAlta()">+ Nuevo tipo</button>
        </div>
        <div class="tabla-scroll">
            <table class="tabla">
                <thead><tr><th>Nombre</th><th>Naturaleza</th><th></th></tr></thead>
                <tbody id="filas"></tbody>
            </table>
        </div>
        <p class="tabla-vacia" id="vacio" style="display:none;">Aún no hay tipos de recibo.</p>
    </section>

    <dialog class="modal" id="modal">
        <div class="modal-in">
            <h3 id="modalTitulo">Nuevo tipo</h3>
            <form id="form">
                <input type="hidden" id="id_tipo">
                <div class="campo"><label>Nombre</label><input type="text" id="nombre" placeholder="Ej. Colegiatura" required></div>
                <div class="campo"><label>Naturaleza por defecto</label>
                    <select id="naturaleza"><option value="ingreso">Ingreso</option><option value="gasto">Gasto</option></select>
                </div>
                <div class="modal-acciones">
                    <button type="button" class="btn btn-fantasma" onclick="modal.close()">Cancelar</button>
                    <button type="submit" class="btn btn-primario">Guardar</button>
                </div>
            </form>
        </div>
    </dialog>

    <script>
        const API = window.BASE_URL + '/api/tipos-recibo';
        const filas = document.getElementById('filas');
        const vacio = document.getElementById('vacio');
        const modal = document.getElementById('modal');
        const form = document.getElementById('form');

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
                const nat = t.naturaleza === 'gasto' ? 'egreso' : 'pagado';
                const tr = document.createElement('tr');
                tr.innerHTML = `
                    <td><strong>${t.nombre}</strong></td>
                    <td><span class="badge-estatus ${t.naturaleza === 'gasto' ? 'pendiente' : 'pagado'}">${t.naturaleza}</span></td>
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
            document.getElementById('modalTitulo').textContent = 'Nuevo tipo';
            form.reset(); document.getElementById('id_tipo').value = ''; modal.showModal();
        }
        function abrirEdicion(t) {
            document.getElementById('modalTitulo').textContent = 'Editar tipo';
            document.getElementById('id_tipo').value = t.id_tipo;
            document.getElementById('nombre').value = t.nombre;
            document.getElementById('naturaleza').value = t.naturaleza;
            modal.showModal();
        }

        form.addEventListener('submit', async (e) => {
            e.preventDefault();
            const id = document.getElementById('id_tipo').value;
            const fd = new FormData();
            fd.append('nombre', document.getElementById('nombre').value);
            fd.append('naturaleza', document.getElementById('naturaleza').value);
            let accion = 'crear';
            if (id) { accion = 'editar'; fd.append('id_tipo', id); }
            const d = await (await fetch(API + '?action=' + accion, { method: 'POST', body: fd })).json();
            window.notifyResponse(d);
            if (d.success) { modal.close(); cargar(); }
        });

        async function eliminar(t) {
            if (!await window.confirmar(`¿Eliminar el tipo "${t.nombre}"?`)) return;
            const fd = new FormData(); fd.append('id_tipo', t.id_tipo);
            const d = await (await fetch(API + '?action=eliminar', { method: 'POST', body: fd })).json();
            window.notifyResponse(d);
            if (d.success) cargar();
        }

        cargar();
    </script>
</body>
</html>
