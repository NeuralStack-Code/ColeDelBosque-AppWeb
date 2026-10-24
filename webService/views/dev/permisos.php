<?php
/** Pantalla de permisos (roles). La pinta PermisoController::index(). Recibe $base. Su JavaScript llama a /permiso/<método>. */
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
                <input type="hidden" name="IdPermiso">
                <div class="campo"><label>Nombre del rol</label><input type="text" name="Nombre" maxlength="25" placeholder="Ej. Coordinador" required></div>
                <div class="modal-acciones">
                    <button type="button" class="btn btn-fantasma" onclick="modal.close()">Cancelar</button>
                    <button type="submit" class="btn btn-primario">Guardar</button>
                </div>
            </form>
        </div>
    </dialog>

    <script>
        const API = window.BASE_URL + '/permiso';   // cada método del controller es una URL: /permiso/listar…
        const filas = document.getElementById('filas');
        const vacio = document.getElementById('vacio');
        const modal = document.getElementById('modal');
        const form = document.getElementById('form');

        function cerrarSesion() {
            fetch(window.BASE_URL + '/auth/salir', { method: 'POST' })
                .finally(() => location.href = window.BASE_URL + '/auth/entrar');
        }

        async function cargar() {
            const result = await (await fetch(API + '/listar')).json();
            const items = result.data.items || [];
            filas.innerHTML = '';
            vacio.style.display = items.length ? 'none' : 'block';
            items.forEach(p => {
                const base = p.IdPermiso >= 1 && p.IdPermiso <= 4;      // roles base del sistema: no se borran
                const tr = document.createElement('tr');
                tr.innerHTML = `
                    <td><code>${p.IdPermiso}</code></td>
                    <td><strong>${p.Nombre}</strong>${base ? ' <small style="color:var(--texto-suave)">(sistema)</small>' : ''}</td>
                    <td>${p.NumCuentas}</td>
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
            form.reset(); form.elements.IdPermiso.value = ''; modal.showModal();
        }

        function abrirEdicion(p) {
            document.getElementById('modalTitulo').textContent = 'Editar rol';
            form.elements.IdPermiso.value = p.IdPermiso;
            form.elements.Nombre.value = p.Nombre;
            modal.showModal();
        }

        // Guardar: con IdPermiso es un cambio; sin él, un alta.
        form.addEventListener('submit', async (e) => {
            e.preventDefault();
            const result = await (await fetch(API + '/guardar', { method: 'POST', body: new FormData(form) })).json();
            window.notifyResponse(result);
            if (result.success) { modal.close(); cargar(); }
        });

        async function eliminar(p) {
            if (!await window.confirmar(`¿Eliminar el rol "${p.Nombre}"?`)) return;
            const fd = new FormData(); fd.append('IdPermiso', p.IdPermiso);
            const result = await (await fetch(API + '/eliminar', { method: 'POST', body: fd })).json();
            window.notifyResponse(result);
            if (result.success) cargar();
        }

        cargar();
    </script>
</body>
</html>
