<?php
/** Pantalla de tipos de recibo. La pinta TipoReciboController::index(). Recibe $base. Su JavaScript llama a /tiporecibo/<método>. */
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
            <a href="<?= $base ?>/dashboard/index" class="marca">
                <img src="<?= $img ?>/logo.png" alt="Colegio del Bosque">
                <span>Colegio del Bosque<small>Panel de administración</small></span>
            </a>
            <button class="btn-salir" onclick="cerrarSesion()">Cerrar sesión</button>
        </div>
    </header>

    <a class="volver-panel" href="<?= $base ?>/recibo/index">
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
                <input type="hidden" name="IdTipo">
                <div class="campo"><label>Nombre</label><input type="text" name="Nombre" maxlength="50" placeholder="Ej. Colegiatura" required></div>
                <div class="campo"><label>Naturaleza por defecto</label>
                    <select name="Naturaleza"><option value="ingreso">Ingreso</option><option value="gasto">Gasto</option></select>
                </div>
                <div class="modal-acciones">
                    <button type="button" class="btn btn-fantasma" onclick="modal.close()">Cancelar</button>
                    <button type="submit" class="btn btn-primario">Guardar</button>
                </div>
            </form>
        </div>
    </dialog>

    <script>
        const API = window.BASE_URL + '/tiporecibo';   // cada método del controller es una URL: /tiporecibo/listar…
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
            items.forEach(t => {
                const tr = document.createElement('tr');
                tr.innerHTML = `
                    <td><strong>${t.Nombre}</strong></td>
                    <td><span class="badge-estatus ${t.Naturaleza === 'gasto' ? 'pendiente' : 'pagado'}">${t.Naturaleza}</span></td>
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
            form.reset(); form.elements.IdTipo.value = ''; modal.showModal();
        }

        function abrirEdicion(t) {
            document.getElementById('modalTitulo').textContent = 'Editar tipo';
            // Los campos del formulario se llaman como las propiedades del tipo.
            for (const campo of ['IdTipo', 'Nombre', 'Naturaleza']) form.elements[campo].value = t[campo];
            modal.showModal();
        }

        // Guardar: con IdTipo es un cambio; sin él, un alta.
        form.addEventListener('submit', async (e) => {
            e.preventDefault();
            const result = await (await fetch(API + '/guardar', { method: 'POST', body: new FormData(form) })).json();
            window.notifyResponse(result);
            if (result.success) { modal.close(); cargar(); }
        });

        async function eliminar(t) {
            if (!await window.confirmar(`¿Eliminar el tipo "${t.Nombre}"?`)) return;
            const fd = new FormData(); fd.append('IdTipo', t.IdTipo);
            const result = await (await fetch(API + '/eliminar', { method: 'POST', body: fd })).json();
            window.notifyResponse(result);
            if (result.success) cargar();
        }

        cargar();
    </script>
</body>
</html>
