<?php
$rol = 4;
require __DIR__ . '/../partials/guard.php';
$base = BASE_URL;
$title = 'Permisos | Desarrollador';
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
            <a href="<?= $base ?>/desarrollador" class="marca">
                <img src="<?= $img ?>/logo.png" alt="Colegio del Bosque">
                <span>Colegio del Bosque<small>Panel del desarrollador</small></span>
            </a>
            <button class="btn-salir" onclick="cerrarSesion()">Cerrar sesión</button>
        </div>
    </header>

    <a class="volver-panel" href="<?= $base ?>/desarrollador">
        <svg class="ic-s" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m15 18-6-6 6-6"/></svg>
        Regresar al panel
    </a>

    <section class="admin-wrap">
        <div class="admin-cab">
            <h2>Permisos (roles)</h2>
            <button class="btn btn-primario" onclick="abrirAlta()">+ Nuevo rol</button>
        </div>
        <div class="tabla-scroll">
            <table class="tabla">
                <thead><tr><th>ID</th><th>Rol</th><th>Cuentas</th><th></th></tr></thead>
                <tbody id="filas"></tbody>
            </table>
        </div>
        <p class="tabla-vacia" id="vacio" style="display:none;">Aún no hay roles.</p>
    </section>

    <dialog class="modal" id="modal">
        <div class="modal-in">
            <h3 id="modalTitulo">Nuevo rol</h3>
            <form id="form">
                <input type="hidden" id="id_permiso">
                <div class="campo"><label>Nombre del rol</label><input type="text" id="nombre" placeholder="Ej. Coordinador" required></div>
                <div class="modal-acciones">
                    <button type="button" class="btn btn-fantasma" onclick="modal.close()">Cancelar</button>
                    <button type="submit" class="btn btn-primario">Guardar</button>
                </div>
            </form>
        </div>
    </dialog>

    <script>
        const API = window.BASE_URL + '/api/permisos';
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
            items.forEach(p => {
                const base = Number(p.id_permiso) >= 1 && Number(p.id_permiso) <= 4;
                const tr = document.createElement('tr');
                tr.innerHTML = `
                    <td><code>${p.id_permiso}</code></td>
                    <td><strong>${p.nombre}</strong>${base ? ' <small style="color:var(--texto-suave)">(sistema)</small>' : ''}</td>
                    <td>${p.num_cuentas}</td>
                    <td><div class="acciones">
                        <button class="icon-btn editar" title="Editar"><svg class="ic-s" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 20h9"/><path d="M16.5 3.5a2.1 2.1 0 0 1 3 3L7 19l-4 1 1-4 12.5-12.5Z"/></svg></button>
                        ${base ? '' : `<button class="icon-btn borrar" title="Eliminar"><svg class="ic-s" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 6h18"/><path d="M8 6V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/><path d="M19 6l-1 14a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2L5 6"/></svg></button>`}
                    </div></td>`;
                tr.querySelector('.editar').addEventListener('click', () => abrirEdicion(p));
                const del = tr.querySelector('.borrar');
                if (del) del.addEventListener('click', () => eliminar(p));
                filas.appendChild(tr);
            });
        }

        function abrirAlta() {
            document.getElementById('modalTitulo').textContent = 'Nuevo rol';
            form.reset(); document.getElementById('id_permiso').value = ''; modal.showModal();
        }
        function abrirEdicion(p) {
            document.getElementById('modalTitulo').textContent = 'Editar rol';
            document.getElementById('id_permiso').value = p.id_permiso;
            document.getElementById('nombre').value = p.nombre;
            modal.showModal();
        }

        form.addEventListener('submit', async (e) => {
            e.preventDefault();
            const id = document.getElementById('id_permiso').value;
            const fd = new FormData();
            fd.append('nombre', document.getElementById('nombre').value);
            let accion = 'crear';
            if (id) { accion = 'editar'; fd.append('id_permiso', id); }
            const d = await (await fetch(API + '?action=' + accion, { method: 'POST', body: fd })).json();
            window.notifyResponse(d);
            if (d.success) { modal.close(); cargar(); }
        });

        async function eliminar(p) {
            if (!await window.confirmar(`¿Eliminar el rol "${p.nombre}"?`)) return;
            const fd = new FormData(); fd.append('id_permiso', p.id_permiso);
            const d = await (await fetch(API + '?action=eliminar', { method: 'POST', body: fd })).json();
            window.notifyResponse(d);
            if (d.success) cargar();
        }

        cargar();
    </script>
</body>
</html>
