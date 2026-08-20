<?php
$rol = 1;
require __DIR__ . '/../partials/guard.php';
$base = BASE_URL;
$title = 'Ciclo escolar | Admin';
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
            <h2>Ciclo escolar</h2>
            <button class="btn btn-primario" onclick="abrirAlta()">+ Nuevo ciclo</button>
        </div>
        <div class="tabla-scroll">
            <table class="tabla">
                <thead><tr><th>Ciclo</th><th>Inicio</th><th>Fin</th><th>Estado</th><th></th></tr></thead>
                <tbody id="filas"></tbody>
            </table>
        </div>
        <p class="tabla-vacia" id="vacio" style="display:none;">Aún no hay ciclos. Crea el primero.</p>
    </section>

    <dialog class="modal" id="modal">
        <div class="modal-in">
            <h3 id="modalTitulo">Nuevo ciclo</h3>
            <form id="form">
                <input type="hidden" id="id_ciclo">
                <div class="campo"><label>Nombre del ciclo</label><input type="text" id="nombre" placeholder="Ej. 2024-2025" required></div>
                <div class="fila-2">
                    <div class="campo"><label>Fecha de inicio</label><input type="date" id="fecha_inicio" required></div>
                    <div class="campo"><label>Fecha de fin</label><input type="date" id="fecha_fin" required></div>
                </div>
                <div class="modal-acciones">
                    <button type="button" class="btn btn-fantasma" onclick="modal.close()">Cancelar</button>
                    <button type="submit" class="btn btn-primario">Guardar</button>
                </div>
            </form>
        </div>
    </dialog>

    <script>
        const API = window.BASE_URL + '/api/ciclos';
        const filas = document.getElementById('filas'), vacio = document.getElementById('vacio');
        const modal = document.getElementById('modal'), form = document.getElementById('form');

        function cerrarSesion() {
            fetch(window.BASE_URL + '/api/auth?action=logout', { method: 'POST' })
                .finally(() => location.href = window.BASE_URL + '/inicio-sesion');
        }

        async function cargar() {
            const d = await (await fetch(API + '?action=listar')).json();
            const items = d.items || [];
            filas.innerHTML = '';
            vacio.style.display = items.length ? 'none' : 'block';
            items.forEach(c => {
                const activo = Number(c.activo) === 1;
                const estado = activo
                    ? `<span class="badge-estatus pagado">activo</span>`
                    : `<button class="btn btn-fantasma" style="padding:.4em 1em;font-size:.85rem" data-a="activar">Activar</button>`;
                const tr = document.createElement('tr');
                tr.innerHTML = `
                    <td><strong>${c.nombre}</strong></td>
                    <td>${c.fecha_inicio ?? '—'}</td>
                    <td>${c.fecha_fin ?? '—'}</td>
                    <td>${estado}</td>
                    <td><div class="acciones">
                        <button class="icon-btn editar" title="Editar" data-a="editar"><svg class="ic-s" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 20h9"/><path d="M16.5 3.5a2.1 2.1 0 0 1 3 3L7 19l-4 1 1-4 12.5-12.5Z"/></svg></button>
                        <button class="icon-btn borrar" title="Eliminar" data-a="del"><svg class="ic-s" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 6h18"/><path d="M8 6V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/><path d="M19 6l-1 14a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2L5 6"/></svg></button>
                    </div></td>`;
                tr.querySelectorAll('[data-a]').forEach(b => b.addEventListener('click', () => {
                    if (b.dataset.a === 'activar') activar(c);
                    else if (b.dataset.a === 'editar') abrirEdicion(c);
                    else eliminar(c);
                }));
                filas.appendChild(tr);
            });
        }

        function abrirAlta() {
            document.getElementById('modalTitulo').textContent = 'Nuevo ciclo';
            form.reset(); document.getElementById('id_ciclo').value = ''; modal.showModal();
        }
        function abrirEdicion(c) {
            document.getElementById('modalTitulo').textContent = 'Editar ciclo';
            document.getElementById('id_ciclo').value = c.id_ciclo;
            document.getElementById('nombre').value = c.nombre;
            document.getElementById('fecha_inicio').value = c.fecha_inicio;
            document.getElementById('fecha_fin').value = c.fecha_fin;
            modal.showModal();
        }

        form.addEventListener('submit', async (e) => {
            e.preventDefault();
            const id = document.getElementById('id_ciclo').value;
            const fd = new FormData();
            fd.append('nombre', document.getElementById('nombre').value);
            fd.append('fecha_inicio', document.getElementById('fecha_inicio').value);
            fd.append('fecha_fin', document.getElementById('fecha_fin').value);
            let accion = 'crear';
            if (id) { accion = 'editar'; fd.append('id_ciclo', id); }
            const d = await (await fetch(API + '?action=' + accion, { method: 'POST', body: fd })).json();
            window.notifyResponse(d);
            if (d.success) { modal.close(); cargar(); }
        });

        async function activar(c) {
            if (!await window.confirmar(`¿Activar el ciclo "${c.nombre}"? Se desactivarán los demás.`, { peligro: false, confirmar: 'Activar' })) return;
            const fd = new FormData(); fd.append('id_ciclo', c.id_ciclo);
            const d = await (await fetch(API + '?action=activar', { method: 'POST', body: fd })).json();
            window.notifyResponse(d);
            if (d.success) cargar();
        }
        async function eliminar(c) {
            if (!await window.confirmar(`¿Eliminar el ciclo "${c.nombre}"?`)) return;
            const fd = new FormData(); fd.append('id_ciclo', c.id_ciclo);
            const d = await (await fetch(API + '?action=eliminar', { method: 'POST', body: fd })).json();
            window.notifyResponse(d);
            if (d.success) cargar();
        }

        cargar();
    </script>
</body>
</html>
