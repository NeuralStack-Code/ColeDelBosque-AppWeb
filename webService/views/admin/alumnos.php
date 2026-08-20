<?php
$rol = 1;
require __DIR__ . '/../partials/guard.php';
$base = BASE_URL;
$title = 'Alumnos | Admin';
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
            <h2>Alumnos</h2>
            <button class="btn btn-primario" onclick="abrirAlta()">+ Nuevo alumno</button>
        </div>

        <div class="filtros">
            <div class="f"><label>Matrícula</label><input type="text" id="fMat" placeholder="Buscar…"></div>
            <div class="f buscar"><label>Nombre</label><input type="text" id="fNom" placeholder="Buscar…"></div>
            <div class="f"><label>Grupo</label><select id="fGrupo"><option value="">Todos</option></select></div>
        </div>

        <div class="tabla-scroll">
            <table class="tabla">
                <thead><tr><th>Matrícula</th><th>Nombre completo</th><th>Grupo</th><th></th></tr></thead>
                <tbody id="filas"></tbody>
            </table>
        </div>
        <p class="tabla-vacia" id="vacio" style="display:none;">No hay alumnos con esos filtros.</p>
    </section>

    <dialog class="modal" id="modal">
        <div class="modal-in">
            <h3 id="modalTitulo">Nuevo alumno</h3>
            <form id="form">
                <input type="hidden" id="id_cuenta">
                <div class="campo"><label>Nombre</label><input type="text" id="nombre" required></div>
                <div class="fila-2">
                    <div class="campo"><label>Apellido paterno</label><input type="text" id="paterno" required></div>
                    <div class="campo"><label>Apellido materno</label><input type="text" id="materno" required></div>
                </div>
                <div class="fila-2">
                    <div class="campo"><label>Matrícula</label><input type="text" id="matricula" placeholder="AAA######" required></div>
                    <div class="campo"><label>Grupo</label><select id="grado" required></select></div>
                </div>
                <div class="modal-acciones">
                    <button type="button" class="btn btn-fantasma" onclick="modal.close()">Cancelar</button>
                    <button type="submit" class="btn btn-primario">Guardar</button>
                </div>
            </form>
        </div>
    </dialog>

    <script>
        const API = window.BASE_URL + '/api/control-escolar';
        const filas = document.getElementById('filas');
        const vacio = document.getElementById('vacio');
        const modal = document.getElementById('modal');
        const form = document.getElementById('form');
        let grupos = [];
        let todos = [];

        function cerrarSesion() {
            fetch(window.BASE_URL + '/api/auth?action=logout', { method: 'POST' })
                .finally(() => location.href = window.BASE_URL + '/inicio-sesion');
        }

        async function cargarGrupos() {
            const d = await (await fetch(API + '?action=grupos_listar')).json();
            grupos = d.items || [];
            const opts = grupos.map(g => `<option value="${g.id_grupo}">${g.grado}</option>`).join('');
            document.getElementById('grado').innerHTML = opts || '<option value="">Sin grupos</option>';
            document.getElementById('fGrupo').innerHTML =
                '<option value="">Todos</option><option value="__sin__">⚠ Sin grupo</option>' + opts;
        }

        // ids de grupos válidos (para detectar alumnos sin grupo)
        const idsValidos = () => new Set(grupos.map(g => Number(g.id_grupo)));

        async function cargarAlumnos() {
            const d = await (await fetch(API + '?action=alumnos_listar')).json();
            todos = d.items || [];
            render();
        }

        function render() {
            const mat = document.getElementById('fMat').value.toLowerCase();
            const nom = document.getElementById('fNom').value.toLowerCase();
            const grp = document.getElementById('fGrupo').value;
            const validos = idsValidos();
            const esHuerfano = a => !validos.has(Number(a.grupo_id));
            const f = todos.filter(a => {
                if (mat && !(a.matricula || '').toLowerCase().includes(mat)) return false;
                if (nom && !`${a.nombre} ${a.paterno} ${a.materno}`.toLowerCase().includes(nom)) return false;
                if (grp === '__sin__') { if (!esHuerfano(a)) return false; }
                else if (grp && String(a.grupo_id) !== grp) return false;
                return true;
            });
            filas.innerHTML = '';
            vacio.style.display = f.length ? 'none' : 'block';
            f.forEach(a => {
                const celdaGrupo = esHuerfano(a)
                    ? '<span class="pill-sin">Sin grupo</span>'
                    : (a.grado ?? '—');
                const tr = document.createElement('tr');
                tr.innerHTML = `
                    <td>${a.matricula ?? ''}</td>
                    <td>${a.nombre} ${a.paterno} ${a.materno}</td>
                    <td>${celdaGrupo}</td>
                    <td><div class="acciones">
                        <button class="icon-btn editar" title="Editar"><svg class="ic-s" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 20h9"/><path d="M16.5 3.5a2.1 2.1 0 0 1 3 3L7 19l-4 1 1-4 12.5-12.5Z"/></svg></button>
                        <button class="icon-btn borrar" title="Eliminar"><svg class="ic-s" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 6h18"/><path d="M8 6V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/><path d="M19 6l-1 14a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2L5 6"/></svg></button>
                    </div></td>`;
                tr.querySelector('.editar').addEventListener('click', () => abrirEdicion(a));
                tr.querySelector('.borrar').addEventListener('click', () => eliminar(a));
                filas.appendChild(tr);
            });
        }
        ['fMat', 'fNom', 'fGrupo'].forEach(id =>
            document.getElementById(id).addEventListener('input', render));

        function abrirAlta() {
            document.getElementById('modalTitulo').textContent = 'Nuevo alumno';
            form.reset(); document.getElementById('id_cuenta').value = ''; modal.showModal();
        }
        function abrirEdicion(a) {
            document.getElementById('modalTitulo').textContent = 'Editar alumno';
            document.getElementById('id_cuenta').value = a.id_cuenta;
            document.getElementById('nombre').value = a.nombre;
            document.getElementById('paterno').value = a.paterno;
            document.getElementById('materno').value = a.materno;
            document.getElementById('matricula').value = a.matricula ?? '';
            document.getElementById('grado').value = a.grupo_id ?? '';
            modal.showModal();
        }

        form.addEventListener('submit', async (e) => {
            e.preventDefault();
            const id = document.getElementById('id_cuenta').value;
            const fd = new FormData();
            fd.append('nombre', nombre.value);
            fd.append('paterno', paterno.value);
            fd.append('materno', materno.value);
            fd.append('matricula', matricula.value);
            fd.append('grado', grado.value);
            let accion = 'alumno_crear';
            if (id) { accion = 'alumno_editar'; fd.append('id_cuenta', id); }
            const d = await (await fetch(API + '?action=' + accion, { method: 'POST', body: fd })).json();
            window.notifyResponse(d);
            if (d.success) { modal.close(); cargarAlumnos(); }
        });

        async function eliminar(a) {
            if (!await window.confirmar(`¿Eliminar a ${a.nombre} ${a.paterno}? Esta acción no se puede deshacer.`)) return;
            const fd = new FormData(); fd.append('id_cuenta', a.id_cuenta);
            const d = await (await fetch(API + '?action=alumno_eliminar', { method: 'POST', body: fd })).json();
            window.notifyResponse(d);
            if (d.success) cargarAlumnos();
        }

        (async () => { await cargarGrupos(); await cargarAlumnos(); })();
    </script>
</body>
</html>
