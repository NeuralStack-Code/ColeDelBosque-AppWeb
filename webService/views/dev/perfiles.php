<?php
/** Pantalla de perfiles (cuentas de cualquier rol). La pinta PerfilController::index(). Recibe $base. Su JavaScript llama a /perfil/<método>. */
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
                <input type="hidden" name="IdCuenta">
                <div class="campo"><label>Nombre</label><input type="text" name="Nombre" maxlength="25" required></div>
                <div class="fila-2">
                    <div class="campo"><label>Apellido paterno</label><input type="text" name="Paterno" maxlength="25" required></div>
                    <div class="campo"><label>Apellido materno</label><input type="text" name="Materno" maxlength="25"></div>
                </div>
                <div class="fila-2">
                    <div class="campo"><label>Matrícula (ID de acceso)</label><input type="text" name="Matricula" minlength="4" maxlength="60" placeholder="Ej. ADM000001" required></div>
                    <div class="campo"><label>Rol</label><select name="IdPermiso"></select></div>
                </div>
                <div class="fila-2">
                    <div class="campo" id="campoGrupo"><label>Grupo</label><select name="IdGrupo"><option value="0">Sin grupo</option></select></div>
                    <div class="campo"><label>Estatus</label><select name="Estatus"></select></div>
                </div>
                <div class="modal-acciones">
                    <button type="button" class="btn btn-fantasma" onclick="modal.close()">Cancelar</button>
                    <button type="submit" class="btn btn-primario">Guardar</button>
                </div>
            </form>
        </div>
    </dialog>

    <script>
        const API = window.BASE_URL + '/perfil';   // cada método del controller es una URL: /perfil/listar…
        const filas = document.getElementById('filas');
        const vacio = document.getElementById('vacio');
        const modal = document.getElementById('modal');
        const form = document.getElementById('form');
        let permisos = [], grupos = [], estatus = [], todos = [];

        function cerrarSesion() {
            fetch(window.BASE_URL + '/auth/salir', { method: 'POST' })
                .finally(() => location.href = window.BASE_URL + '/auth/entrar');
        }

        // roles que llevan grupo: maestro (2) y alumno (3)
        const llevaGrupo = id => Number(id) === 2 || Number(id) === 3;

        async function cargarCatalogos() {
            const result = await (await fetch(API + '/catalogos')).json();
            permisos = result.data.permisos || [];
            grupos = result.data.grupos || [];
            estatus = result.data.estatus || [];
            const optP = permisos.map(p => `<option value="${p.IdPermiso}">${p.Nombre}</option>`).join('');
            form.elements.IdPermiso.innerHTML = optP;
            document.getElementById('fRol').innerHTML = '<option value="">Todos</option>' + optP;
            form.elements.IdGrupo.innerHTML = '<option value="0">Sin grupo</option>' +
                grupos.map(g => `<option value="${g.IdGrupo}">${g.Grado}${g.Ciclo ? ' · ' + g.Ciclo : ''}</option>`).join('');
            form.elements.Estatus.innerHTML = estatus.map(s => `<option value="${s.Clave}">${s.Nombre}</option>`).join('');
        }

        function nombreRol(id) { const p = permisos.find(x => x.IdPermiso === Number(id)); return p ? p.Nombre : '—'; }
        async function cargar() {
            const result = await (await fetch(API + '/listar')).json();
            todos = result.data.items || [];
            render();
        }

        function render() {
            const rol = document.getElementById('fRol').value;
            const q = document.getElementById('fBuscar').value.toLowerCase();
            const f = todos.filter(a => {
                if (rol && String(a.IdPermiso) !== rol) return false;
                if (q && !`${a.NombreCompleto} ${a.Matricula}`.toLowerCase().includes(q)) return false;
                return true;
            });
            filas.innerHTML = '';
            vacio.style.display = f.length ? 'none' : 'block';
            f.forEach(a => {
                const grupoCel = llevaGrupo(a.IdPermiso) ? (a.Grado ?? '<span class="pill-sin">Sin grupo</span>') : '—';
                const est = (a.Estatus || 'activo');
                const estClass = est === 'baja' ? 'pill-sin' : 'badge-estatus pagado';
                const tr = document.createElement('tr');
                tr.innerHTML = `
                    <td><code>${a.Matricula ?? ''}</code></td>
                    <td>${a.NombreCompleto}</td>
                    <td><span class="badge-estatus parcial">${nombreRol(a.IdPermiso)}</span></td>
                    <td>${grupoCel}</td>
                    <td><span class="${estClass}">${a.EstatusNombre ?? est}</span></td>
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
        form.elements.IdPermiso.addEventListener('change', toggleGrupo);
        function toggleGrupo() {
            document.getElementById('campoGrupo').style.display = llevaGrupo(form.elements.IdPermiso.value) ? 'block' : 'none';
        }

        function abrirAlta() {
            document.getElementById('modalTitulo').textContent = 'Nuevo perfil';
            form.reset();
            form.elements.IdCuenta.value = '';
            form.elements.IdGrupo.value = '0';
            toggleGrupo();
            modal.showModal();
        }

        function abrirEdicion(a) {
            document.getElementById('modalTitulo').textContent = 'Editar perfil';
            // Los campos del formulario se llaman como las propiedades del perfil.
            for (const campo of ['IdCuenta', 'Nombre', 'Paterno', 'Materno', 'Matricula', 'IdPermiso', 'IdGrupo', 'Estatus']) {
                form.elements[campo].value = a[campo] ?? '';
            }
            toggleGrupo();
            modal.showModal();
        }

        // Guardar: con IdCuenta es un cambio; sin ella, un alta.
        form.addEventListener('submit', async (e) => {
            e.preventDefault();
            const result = await (await fetch(API + '/guardar', { method: 'POST', body: new FormData(form) })).json();
            window.notifyResponse(result);
            if (result.success) { modal.close(); cargar(); }
        });

        async function eliminar(a) {
            if (!await window.confirmar(`¿Eliminar el perfil de ${a.NombreCompleto} (${nombreRol(a.IdPermiso)})? Se borrarán también sus colegiaturas, calificaciones y recibos.`)) return;
            const fd = new FormData(); fd.append('IdCuenta', a.IdCuenta);
            const result = await (await fetch(API + '/eliminar', { method: 'POST', body: fd })).json();
            window.notifyResponse(result);
            if (result.success) cargar();
        }

        (async () => { await cargarCatalogos(); cargar(); })();
    </script>
</body>
</html>
