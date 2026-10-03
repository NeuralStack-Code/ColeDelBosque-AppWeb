<?php
$rol = 1;
require __DIR__ . '/../partials/guard.php';
$base = BASE_URL;
$title = 'Reporte semanal | Admin';
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
            <h2>Columnas del reporte semanal</h2>
            <button class="btn btn-primario" onclick="abrirAlta()">+ Nueva columna</button>
        </div>
        <p style="color:var(--texto-suave);margin-bottom:20px;">
            Lo que las maestras reportan de cada alumno, por clase. Fecha, materia y tema siempre van en el correo.
        </p>
        <div class="tabla-scroll">
            <table class="tabla">
                <thead><tr><th>Orden</th><th>Columna</th><th>Captura</th><th>Texto en el correo</th><th>Estado</th><th></th></tr></thead>
                <tbody id="filas"></tbody>
            </table>
        </div>
        <p class="tabla-vacia" id="vacio" style="display:none;">Aún no hay columnas.</p>
    </section>

    <dialog class="modal" id="modal">
        <div class="modal-in">
            <h3 id="modalTitulo">Nueva columna</h3>
            <form id="form">
                <input type="hidden" id="id_columna">
                <div class="campo"><label>Nombre</label><input type="text" id="nombre" maxlength="60" placeholder="Ej. Ausencias" required></div>
                <div class="campo"><label>Cómo la captura la maestra</label>
                    <select id="tipo">
                        <option value="casilla">Casilla (solo marcar)</option>
                        <option value="texto">Texto (nota corta)</option>
                    </select>
                </div>
                <div class="campo" id="campoEtiqueta"><label>Texto en el correo cuando está marcada</label><input type="text" id="etiqueta" maxlength="60" placeholder="Ej. Falta"></div>
                <div class="fila-2">
                    <div class="campo"><label>Orden</label><input type="number" id="orden" min="0" step="1" value="0"></div>
                    <div class="campo"><label>Estado</label>
                        <select id="activo"><option value="1">Activa</option><option value="0">Oculta</option></select>
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
        const API = window.BASE_URL + '/api/reporte-columnas';
        const filas = document.getElementById('filas');
        const vacio = document.getElementById('vacio');
        const modal = document.getElementById('modal');
        const form = document.getElementById('form');
        const tipo = document.getElementById('tipo');
        const esc = s => String(s ?? '').replace(/[&<>"']/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));

        function cerrarSesion() {
            fetch(window.BASE_URL + '/api/auth?action=logout', { method: 'POST' })
                .finally(() => location.href = window.BASE_URL + '/inicio-sesion');
        }

        // La etiqueta solo aplica a casillas
        function verEtiqueta() {
            document.getElementById('campoEtiqueta').style.display = tipo.value === 'casilla' ? '' : 'none';
        }
        tipo.addEventListener('change', verEtiqueta);

        async function cargar() {
            const d = await (await fetch(API + '?action=listar')).json();
            if (!d.success) { window.notify('error', d.message); return; }
            const items = d.items || [];
            filas.innerHTML = '';
            vacio.style.display = items.length ? 'none' : 'block';
            items.forEach(c => {
                const tr = document.createElement('tr');
                tr.innerHTML = `
                    <td>${c.orden}</td>
                    <td><strong>${esc(c.nombre)}</strong></td>
                    <td>${c.tipo === 'texto' ? 'Texto' : 'Casilla'}</td>
                    <td>${c.tipo === 'texto' ? '<span style="color:var(--texto-suave)">Lo que escriba la maestra</span>' : esc(c.etiqueta || 'Sí')}</td>
                    <td><span class="badge-estatus ${c.activo ? 'pagado' : 'pendiente'}">${c.activo ? 'Activa' : 'Oculta'}</span></td>
                    <td><div class="acciones">
                        <button class="icon-btn editar" title="Editar"><svg class="ic-s" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 20h9"/><path d="M16.5 3.5a2.1 2.1 0 0 1 3 3L7 19l-4 1 1-4 12.5-12.5Z"/></svg></button>
                        <button class="icon-btn borrar" title="Eliminar"><svg class="ic-s" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 6h18"/><path d="M8 6V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/><path d="M19 6l-1 14a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2L5 6"/></svg></button>
                    </div></td>`;
                tr.querySelector('.editar').addEventListener('click', () => abrirEdicion(c));
                tr.querySelector('.borrar').addEventListener('click', () => eliminar(c));
                filas.appendChild(tr);
            });
        }

        function abrirAlta() {
            document.getElementById('modalTitulo').textContent = 'Nueva columna';
            form.reset(); document.getElementById('id_columna').value = '';
            verEtiqueta(); modal.showModal();
        }
        function abrirEdicion(c) {
            document.getElementById('modalTitulo').textContent = 'Editar columna';
            document.getElementById('id_columna').value = c.id_columna;
            document.getElementById('nombre').value = c.nombre;
            tipo.value = c.tipo;
            document.getElementById('etiqueta').value = c.etiqueta ?? '';
            document.getElementById('orden').value = c.orden;
            document.getElementById('activo').value = c.activo ? '1' : '0';
            verEtiqueta(); modal.showModal();
        }

        form.addEventListener('submit', async (e) => {
            e.preventDefault();
            const id = document.getElementById('id_columna').value;
            const fd = new FormData();
            ['nombre', 'tipo', 'etiqueta', 'orden', 'activo'].forEach(f => fd.append(f, document.getElementById(f).value));
            let accion = 'crear';
            if (id) { accion = 'editar'; fd.append('id_columna', id); }
            const d = await (await fetch(API + '?action=' + accion, { method: 'POST', body: fd })).json();
            window.notifyResponse(d);
            if (d.success) { modal.close(); cargar(); }
        });

        async function eliminar(c) {
            if (!await window.confirmar(`¿Eliminar la columna "${c.nombre}"?`)) return;
            const fd = new FormData(); fd.append('id_columna', c.id_columna);
            const d = await (await fetch(API + '?action=eliminar', { method: 'POST', body: fd })).json();
            window.notifyResponse(d);
            if (d.success) cargar();
        }

        cargar();
    </script>
</body>
</html>
