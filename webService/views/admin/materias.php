<?php
/** Pantalla de materias. La pinta MateriaController::index(). Recibe $base. Su JavaScript llama a /materia/<método>. */
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
            <a href="<?= $base ?>/dashboard/index" class="marca">
                <img src="<?= $img ?>/logo.png" alt="Colegio del Bosque">
                <span>Colegio del Bosque<small>Panel de administración</small></span>
            </a>
            <button class="btn-salir" onclick="cerrarSesion()">Cerrar sesión</button>
        </div>
    </header>

    <a class="volver-panel" href="<?= $base ?>/dashboard/index">
        <svg class="ic-s" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m15 18-6-6 6-6"/></svg>
        Regresar al panel
    </a>

    <section class="admin-wrap">
        <div class="admin-cab">
            <h2>Materias</h2>
            <button class="btn btn-primario" onclick="abrirAlta()">+ Nueva materia</button>
        </div>
        <p class="sub" style="color:var(--texto-suave);margin:-6px 0 18px;">Una materia puede estar en varios grupos.</p>

        <div class="filtros">
            <div class="f buscar"><label>Buscar</label><input type="text" id="fBuscar" placeholder="Nombre de materia…"></div>
            <div class="f"><label>Grupo</label><select id="fGrupo"><option value="">Todos</option></select></div>
        </div>

        <div class="tabla-scroll">
            <table class="tabla">
                <thead><tr><th>Materia</th><th>Grupos</th><th></th></tr></thead>
                <tbody id="filas"></tbody>
            </table>
        </div>
        <p class="tabla-vacia" id="vacio" style="display:none;">No hay materias con esos filtros.</p>
    </section>

    <dialog class="modal" id="modal">
        <div class="modal-in">
            <h3 id="modalTitulo">Nueva materia</h3>
            <form id="form">
                <input type="hidden" name="IdMateria">
                <div class="campo"><label>Nombre</label><input type="text" name="Nombre" maxlength="25" placeholder="Ej. Matemáticas" required></div>
                <div class="campo">
                    <label>Grupos donde se imparte</label>
                    <div class="lista-check" id="gruposCheck"></div>
                    <small id="sinGrupos" style="color:var(--texto-suave);display:none;">No hay grupos aún.</small>
                </div>
                <div class="modal-acciones">
                    <button type="button" class="btn btn-fantasma" onclick="modal.close()">Cancelar</button>
                    <button type="submit" class="btn btn-primario">Guardar</button>
                </div>
            </form>
        </div>
    </dialog>

    <script>
        const API = window.BASE_URL + '/materia';       // cada método del controller es una URL: /materia/listar…
        const APIG = window.BASE_URL + '/grupo';
        const filas = document.getElementById('filas');
        const vacio = document.getElementById('vacio');
        const modal = document.getElementById('modal');
        const form = document.getElementById('form');
        let todos = [], grupos = [];

        function cerrarSesion() {
            fetch(window.BASE_URL + '/auth/salir', { method: 'POST' })
                .finally(() => location.href = window.BASE_URL + '/auth/entrar');
        }

        async function cargarGrupos() {
            const d = await (await fetch(APIG + '/listar')).json();
            grupos = d.data?.items || [];
            document.getElementById('fGrupo').innerHTML = '<option value="">Todos</option>' +
                grupos.map(g => `<option value="${g.IdGrupo}">${g.Grado}</option>`).join('');
        }

        function pintarChecklist(seleccionados = []) {
            const cont = document.getElementById('gruposCheck');
            const sel = new Set(seleccionados.map(Number));
            document.getElementById('sinGrupos').style.display = grupos.length ? 'none' : 'block';
            cont.innerHTML = grupos.map(g => `
                <label>
                    <input type="checkbox" name="Grupos[]" value="${g.IdGrupo}" ${sel.has(Number(g.IdGrupo)) ? 'checked' : ''}>
                    <span>${g.Grado}${g.CicloNombre ? ' · ' + g.CicloNombre : ''}</span>
                </label>`).join('');
        }
        async function cargar() {
            const result = await (await fetch(API + '/listar')).json();
            todos = result.data.items || [];
            render();
        }

        function render() {
            const q = document.getElementById('fBuscar').value.toLowerCase();
            const grp = document.getElementById('fGrupo').value;
            const f = todos.filter(m => {
                if (q && !(m.Nombre || '').toLowerCase().includes(q)) return false;
                if (grp && !(m.GrupoIds || []).includes(Number(grp))) return false;
                return true;
            });
            filas.innerHTML = '';
            vacio.style.display = f.length ? 'none' : 'block';
            f.forEach(m => {
                const chips = (m.GruposTxt || '').split(', ').filter(Boolean)
                    .map(g => `<span class="chip">${g}</span>`).join(' ') || '<span class="pill-sin">Sin grupos</span>';
                const tr = document.createElement('tr');
                tr.innerHTML = `
                    <td><strong>${m.Nombre}</strong></td>
                    <td><div class="chips">${chips}</div></td>
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
            form.reset(); form.elements.IdMateria.value = '';
            pintarChecklist([]);
            modal.showModal();
        }

        function abrirEdicion(m) {
            document.getElementById('modalTitulo').textContent = 'Editar materia';
            form.elements.IdMateria.value = m.IdMateria;
            form.elements.Nombre.value = m.Nombre;
            pintarChecklist(m.GrupoIds || []);
            modal.showModal();
        }

        // Guardar: con IdMateria es un cambio; sin ella, un alta. Los grupos marcados viajan como Grupos[].
        form.addEventListener('submit', async (e) => {
            e.preventDefault();
            const result = await (await fetch(API + '/guardar', { method: 'POST', body: new FormData(form) })).json();
            window.notifyResponse(result);
            if (result.success) { modal.close(); cargar(); }
        });

        async function eliminar(m) {
            if (!await window.confirmar(`¿Eliminar la materia "${m.Nombre}"? Se quitará de todos sus grupos.`)) return;
            const fd = new FormData(); fd.append('IdMateria', m.IdMateria);
            const result = await (await fetch(API + '/eliminar', { method: 'POST', body: fd })).json();
            window.notifyResponse(result);
            if (result.success) cargar();
        }

        (async () => { await cargarGrupos(); cargar(); })();
    </script>
</body>
</html>
