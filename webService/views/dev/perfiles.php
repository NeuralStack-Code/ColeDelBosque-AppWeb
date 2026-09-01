<?php
$rol = 4;
require __DIR__ . '/../partials/guard.php';
$base = BASE_URL;
$title = 'Perfiles | Desarrollador';
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
            <h2>Perfiles</h2>
            <button class="btn btn-primario" onclick="abrirAlta()">+ Nuevo perfil</button>
        </div>

        <div class="filtros">
            <div class="f"><label>Rol</label><select id="fRol"><option value="">Todos</option></select></div>
            <div class="f buscar"><label>Buscar</label><input type="text" id="fBuscar" placeholder="Nombre o matrícula…"></div>
        </div>

        <div class="tabla-scroll">
            <table class="tabla">
                <thead><tr><th>Matrícula</th><th>Nombre completo</th><th>Rol</th><th>Grupo</th><th>Estatus</th><th></th></tr></thead>
                <tbody id="filas"></tbody>
            </table>
        </div>
        <p class="tabla-vacia" id="vacio" style="display:none;">No hay perfiles con esos filtros.</p>
    </section>

    <dialog class="modal" id="modal">
        <div class="modal-in">
            <h3 id="modalTitulo">Nuevo perfil</h3>
            <form id="form">
                <input type="hidden" id="id_cuenta">
                <div class="campo"><label>Nombre</label><input type="text" id="nombre" required></div>
                <div class="fila-2">
                    <div class="campo"><label>Apellido paterno</label><input type="text" id="paterno" required></div>
                    <div class="campo"><label>Apellido materno</label><input type="text" id="materno"></div>
                </div>
                <div class="fila-2">
                    <div class="campo"><label>Matrícula (ID de acceso)</label><input type="text" id="matricula" placeholder="Ej. ADM000001" required></div>
                    <div class="campo"><label>Rol</label><select id="permiso_id"></select></div>
                </div>
                <div class="fila-2">
                    <div class="campo" id="campoGrupo"><label>Grupo</label><select id="grupo_id"><option value="0">Sin grupo</option></select></div>
                    <div class="campo"><label>Estatus</label><select id="estatus"></select></div>
                </div>
                <div class="modal-acciones">
                    <button type="button" class="btn btn-fantasma" onclick="modal.close()">Cancelar</button>
                    <button type="submit" class="btn btn-primario">Guardar</button>
                </div>
            </form>
        </div>
    </dialog>

    <script>
        const API = window.BASE_URL + '/api/perfiles';
        const filas = document.getElementById('filas');
        const vacio = document.getElementById('vacio');
        const modal = document.getElementById('modal');
        const form = document.getElementById('form');
        let permisos = [], grupos = [], estatus = [], todos = [];

        function cerrarSesion() {
            fetch(window.BASE_URL + '/api/auth?action=logout', { method: 'POST' })
                .finally(() => location.href = window.BASE_URL + '/inicio-sesion');
        }

        // roles que llevan grupo: maestro (2) y alumno (3)
        const llevaGrupo = id => Number(id) === 2 || Number(id) === 3;

        async function cargarCatalogos() {
            const d = await (await fetch(API + '?action=catalogos')).json();
            permisos = d.permisos || [];
            grupos = d.grupos || [];
            estatus = d.estatus || [];
            const optP = permisos.map(p => `<option value="${p.id_permiso}">${p.nombre}</option>`).join('');
            document.getElementById('permiso_id').innerHTML = optP;
            document.getElementById('fRol').innerHTML = '<option value="">Todos</option>' + optP;
            document.getElementById('grupo_id').innerHTML = '<option value="0">Sin grupo</option>' +
                grupos.map(g => `<option value="${g.id_grupo}">${g.grado}${g.ciclo ? ' · ' + g.ciclo : ''}</option>`).join('');
            document.getElementById('estatus').innerHTML = estatus.map(s => `<option value="${s.clave}">${s.nombre}</option>`).join('');
        }

        function nombreRol(id) { const p = permisos.find(x => Number(x.id_permiso) === Number(id)); return p ? p.nombre : '—'; }

        async function cargar() {
            const d = await (await fetch(API + '?action=listar')).json();
            todos = d.items || [];
            render();
        }

        function render() {
            const rol = document.getElementById('fRol').value;
            const q = document.getElementById('fBuscar').value.toLowerCase();
            const f = todos.filter(a => {
                if (rol && String(a.permiso_id) !== rol) return false;
                if (q && !`${a.nombre_completo} ${a.matricula}`.toLowerCase().includes(q)) return false;
                return true;
            });
            filas.innerHTML = '';
            vacio.style.display = f.length ? 'none' : 'block';
            f.forEach(a => {
                const grupoCel = llevaGrupo(a.permiso_id) ? (a.grado ?? '<span class="pill-sin">Sin grupo</span>') : '—';
                const est = (a.estatus || 'activo');
                const estClass = est === 'baja' ? 'pill-sin' : 'badge-estatus pagado';
                const tr = document.createElement('tr');
                tr.innerHTML = `
                    <td><code>${a.matricula ?? ''}</code></td>
                    <td>${a.nombre_completo}</td>
                    <td><span class="badge-estatus parcial">${nombreRol(a.permiso_id)}</span></td>
                    <td>${grupoCel}</td>
                    <td><span class="${estClass}">${a.estatus_nombre ?? est}</span></td>
                    <td><div class="acciones">
                        <button class="icon-btn editar" title="Editar"><svg class="ic-s" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 20h9"/><path d="M16.5 3.5a2.1 2.1 0 0 1 3 3L7 19l-4 1 1-4 12.5-12.5Z"/></svg></button>
                        <button class="icon-btn borrar" title="Eliminar"><svg class="ic-s" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 6h18"/><path d="M8 6V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/><path d="M19 6l-1 14a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2L5 6"/></svg></button>
                    </div></td>`;
                tr.querySelector('.editar').addEventListener('click', () => abrirEdicion(a));
                tr.querySelector('.borrar').addEventListener('click', () => eliminar(a));
                filas.appendChild(tr);
            });
        }
        ['fRol', 'fBuscar'].forEach(id => document.getElementById(id).addEventListener('input', render));

        // Mostrar/ocultar grupo según el rol elegido
        document.getElementById('permiso_id').addEventListener('change', toggleGrupo);
        function toggleGrupo() {
            const p = document.getElementById('permiso_id').value;
            document.getElementById('campoGrupo').style.display = llevaGrupo(p) ? 'block' : 'none';
        }

        function abrirAlta() {
            document.getElementById('modalTitulo').textContent = 'Nuevo perfil';
            form.reset();
            document.getElementById('id_cuenta').value = '';
            document.getElementById('grupo_id').value = '0';
            toggleGrupo();
            modal.showModal();
        }
        function abrirEdicion(a) {
            document.getElementById('modalTitulo').textContent = 'Editar perfil';
            document.getElementById('id_cuenta').value = a.id_cuenta;
            document.getElementById('nombre').value = a.nombre;
            document.getElementById('paterno').value = a.paterno;
            document.getElementById('materno').value = a.materno ?? '';
            document.getElementById('matricula').value = a.matricula ?? '';
            document.getElementById('permiso_id').value = a.permiso_id;
            document.getElementById('grupo_id').value = a.grupo_id ?? '0';
            document.getElementById('estatus').value = a.estatus ?? 'activo';
            toggleGrupo();
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
            fd.append('permiso_id', document.getElementById('permiso_id').value);
            fd.append('grupo_id', document.getElementById('grupo_id').value);
            fd.append('estatus', document.getElementById('estatus').value);
            let accion = 'crear';
            if (id) { accion = 'editar'; fd.append('id_cuenta', id); }
            const d = await (await fetch(API + '?action=' + accion, { method: 'POST', body: fd })).json();
            window.notifyResponse(d);
            if (d.success) { modal.close(); cargar(); }
        });

        async function eliminar(a) {
            if (!await window.confirmar(`¿Eliminar el perfil de ${a.nombre_completo} (${nombreRol(a.permiso_id)})? Se borrarán también sus colegiaturas, calificaciones y recibos.`)) return;
            const fd = new FormData(); fd.append('id_cuenta', a.id_cuenta);
            const d = await (await fetch(API + '?action=eliminar', { method: 'POST', body: fd })).json();
            window.notifyResponse(d);
            if (d.success) cargar();
        }

        (async () => { await cargarCatalogos(); cargar(); })();
    </script>
</body>
</html>
