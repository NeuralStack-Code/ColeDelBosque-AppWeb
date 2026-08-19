<?php
$rol = 1;
require __DIR__ . '/../partials/guard.php';
$base = BASE_URL;
$title = 'Materias | Admin';
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
            <h2>Materias</h2>
            <button class="btn btn-primario" onclick="abrirAlta()">+ Nueva materia</button>
        </div>

        <div class="filtros">
            <div class="f buscar"><label>Buscar</label><input type="text" id="fBuscar" placeholder="Nombre de materia…"></div>
            <div class="f"><label>Grupo</label><select id="fGrupo"><option value="">Todos</option></select></div>
        </div>

        <div class="tabla-scroll">
            <table class="tabla">
                <thead><tr><th>Materia</th><th>Grupo</th><th></th></tr></thead>
                <tbody id="filas"></tbody>
            </table>
        </div>
        <p class="tabla-vacia" id="vacio" style="display:none;">No hay materias con esos filtros.</p>
    </section>

    <dialog class="modal" id="modal">
        <div class="modal-in">
            <h3 id="modalTitulo">Nueva materia</h3>
            <form id="form">
                <input type="hidden" id="id_materia">
                <div class="campo"><label>Nombre</label><input type="text" id="nombre" placeholder="Ej. Matemáticas" required></div>
                <div class="campo"><label>Grupo</label><select id="grupo_id" required></select></div>
                <div class="modal-acciones">
                    <button type="button" class="btn btn-fantasma" onclick="modal.close()">Cancelar</button>
                    <button type="submit" class="btn btn-primario">Guardar</button>
                </div>
            </form>
        </div>
    </dialog>

    <script>
        const API = window.BASE_URL + '/api/materias';
        const APIG = window.BASE_URL + '/api/control-escolar';
        const filas = document.getElementById('filas');
        const vacio = document.getElementById('vacio');
        const modal = document.getElementById('modal');
        const form = document.getElementById('form');
        let todos = [], grupos = [];

        function cerrarSesion() {
            fetch(window.BASE_URL + '/api/auth?action=logout', { method: 'POST' })
                .finally(() => location.href = window.BASE_URL + '/inicio-sesion');
        }

        async function cargarGrupos() {
            const d = await (await fetch(APIG + '?action=grupos_listar')).json();
            grupos = d.items || [];
            const opts = grupos.map(g => `<option value="${g.id_grupo}">${g.grado}</option>`).join('');
            document.getElementById('grupo_id').innerHTML = opts || '<option value="">Sin grupos</option>';
            document.getElementById('fGrupo').innerHTML = '<option value="">Todos</option>' + opts;
        }

        async function cargar() {
            const d = await (await fetch(API + '?action=listar')).json();
            todos = d.items || [];
            render();
        }

        function render() {
            const q = document.getElementById('fBuscar').value.toLowerCase();
            const grp = document.getElementById('fGrupo').value;
            const f = todos.filter(m => {
                if (q && !(m.nombre || '').toLowerCase().includes(q)) return false;
                if (grp && String(m.grupo_id) !== grp) return false;
                return true;
            });
            filas.innerHTML = '';
            vacio.style.display = f.length ? 'none' : 'block';
            f.forEach(m => {
                const tr = document.createElement('tr');
                tr.innerHTML = `
                    <td><strong>${m.nombre}</strong></td>
                    <td>${m.grado ?? '—'}</td>
                    <td><div class="acciones">
                        <button class="icon-btn editar" title="Editar"><svg class="ic-s" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 20h9"/><path d="M16.5 3.5a2.1 2.1 0 0 1 3 3L7 19l-4 1 1-4 12.5-12.5Z"/></svg></button>
                        <button class="icon-btn borrar" title="Eliminar"><svg class="ic-s" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 6h18"/><path d="M8 6V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/><path d="M19 6l-1 14a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2L5 6"/></svg></button>
                    </div></td>`;
                tr.querySelector('.editar').addEventListener('click', () => abrirEdicion(m));
                tr.querySelector('.borrar').addEventListener('click', () => eliminar(m));
                filas.appendChild(tr);
            });
        }
        ['fBuscar', 'fGrupo'].forEach(id => document.getElementById(id).addEventListener('input', render));

        function abrirAlta() {
            document.getElementById('modalTitulo').textContent = 'Nueva materia';
            form.reset(); document.getElementById('id_materia').value = ''; modal.showModal();
        }
        function abrirEdicion(m) {
            document.getElementById('modalTitulo').textContent = 'Editar materia';
            document.getElementById('id_materia').value = m.id_materia;
            document.getElementById('nombre').value = m.nombre;
            document.getElementById('grupo_id').value = m.grupo_id ?? '';
            modal.showModal();
        }

        form.addEventListener('submit', async (e) => {
            e.preventDefault();
            const id = document.getElementById('id_materia').value;
            const fd = new FormData();
            fd.append('nombre', document.getElementById('nombre').value);
            fd.append('grupo_id', document.getElementById('grupo_id').value);
            let accion = 'crear';
            if (id) { accion = 'editar'; fd.append('id_materia', id); }
            const d = await (await fetch(API + '?action=' + accion, { method: 'POST', body: fd })).json();
            window.notifyResponse(d);
            if (d.success) { modal.close(); cargar(); }
        });

        async function eliminar(m) {
            if (!await window.confirmar(`¿Eliminar la materia "${m.nombre}"?`)) return;
            const fd = new FormData(); fd.append('id_materia', m.id_materia);
            const d = await (await fetch(API + '?action=eliminar', { method: 'POST', body: fd })).json();
            window.notifyResponse(d);
            if (d.success) cargar();
        }

        cargarGrupos();
        cargar();
    </script>
</body>
</html>
