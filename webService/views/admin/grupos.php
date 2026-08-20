<?php
$rol = 1;
require __DIR__ . '/../partials/guard.php';
$base = BASE_URL;
$title = 'Grupos | Admin';
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
            <h2>Grupos</h2>
            <button class="btn btn-primario" onclick="abrirAlta()">+ Nuevo grupo</button>
        </div>
        <div class="filtros">
            <div class="f"><label>Ciclo escolar</label><select id="fCiclo"><option value="">Todos los ciclos</option></select></div>
        </div>
        <div class="tabla-scroll">
            <table class="tabla">
                <thead><tr><th>Grado</th><th>Ciclo</th><th>Nivel</th><th>Materias</th><th></th></tr></thead>
                <tbody id="filas"></tbody>
            </table>
        </div>
        <p class="tabla-vacia" id="vacio" style="display:none;">Aún no hay grupos.</p>
    </section>

    <!-- Modal grupo -->
    <dialog class="modal" id="modal">
        <div class="modal-in">
            <h3 id="modalTitulo">Nuevo grupo</h3>
            <form id="form">
                <input type="hidden" id="id_grupo">
                <div class="campo"><label>Grado</label><input type="text" id="grado" placeholder="Ej. 1° A" required></div>
                <div class="fila-2">
                    <div class="campo"><label>Ciclo escolar</label><select id="ciclo_id"><option value="">Sin ciclo</option></select></div>
                    <div class="campo"><label>Nivel (orden)</label><input type="number" id="nivel" min="1" placeholder="Ej. 1 = 1°"></div>
                </div>

                <div class="campo" id="campoMaestra" style="display:none;">
                    <label>Maestra asignada</label>
                    <select id="maestra"><option value="">Sin asignar</option></select>
                </div>

                <div class="campo">
                    <label>Materias</label>
                    <div style="display:flex;gap:8px;">
                        <input type="text" id="materiaInput" placeholder="Nombre de la materia">
                        <button type="button" class="btn btn-fantasma" onclick="agregarMateria()">Agregar</button>
                    </div>
                    <div class="chips" id="chips"></div>
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
        const chips = document.getElementById('chips');

        let materiasNuevas = [];      // nombres a insertar
        let materiasExistentes = [];  // {id_materia, nombre} (modo edición)
        let materiasEliminar = [];    // ids a borrar

        function cerrarSesion() {
            fetch(window.BASE_URL + '/api/auth?action=logout', { method: 'POST' })
                .finally(() => location.href = window.BASE_URL + '/inicio-sesion');
        }

        let ciclos = [], cicloActivo = '';

        async function cargarCiclos() {
            const d = await (await fetch(window.BASE_URL + '/api/ciclos?action=listar')).json();
            ciclos = d.items || [];
            const act = ciclos.find(c => Number(c.activo) === 1);
            cicloActivo = act ? String(act.id_ciclo) : '';
            const opts = ciclos.map(c => `<option value="${c.id_ciclo}">${c.nombre}${Number(c.activo) === 1 ? ' (activo)' : ''}</option>`).join('');
            document.getElementById('fCiclo').innerHTML = '<option value="">Todos los ciclos</option>' + opts;
            document.getElementById('ciclo_id').innerHTML = '<option value="">Sin ciclo</option>' + opts;
        }

        async function cargar() {
            const fc = document.getElementById('fCiclo').value;
            const r = await fetch(API + '?action=grupos_listar' + (fc ? '&ciclo_id=' + fc : ''));
            const d = await r.json();
            const items = d.items || [];
            filas.innerHTML = '';
            vacio.style.display = items.length ? 'none' : 'block';
            items.forEach(g => {
                const tr = document.createElement('tr');
                tr.innerHTML = `
                    <td><strong>${g.grado}</strong></td>
                    <td>${g.ciclo_nombre ?? '—'}</td>
                    <td>${g.nivel ?? '—'}</td>
                    <td>${g.num_materias} materia(s)</td>
                    <td><div class="acciones">
                        <button class="icon-btn editar" title="Editar"><svg class="ic-s" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 20h9"/><path d="M16.5 3.5a2.1 2.1 0 0 1 3 3L7 19l-4 1 1-4 12.5-12.5Z"/></svg></button>
                        <button class="icon-btn borrar" title="Eliminar"><svg class="ic-s" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 6h18"/><path d="M8 6V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/><path d="M19 6l-1 14a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2L5 6"/></svg></button>
                    </div></td>`;
                tr.querySelector('.editar').addEventListener('click', () => abrirEdicion(g.id_grupo));
                tr.querySelector('.borrar').addEventListener('click', () => eliminar(g));
                filas.appendChild(tr);
            });
        }

        function pintarChips() {
            chips.innerHTML = '';
            materiasExistentes.forEach(m => chips.appendChild(chip(m.nombre, () => {
                materiasEliminar.push(m.id_materia);
                materiasExistentes = materiasExistentes.filter(x => x !== m);
                pintarChips();
            })));
            materiasNuevas.forEach((nombre, i) => chips.appendChild(chip(nombre + ' (nueva)', () => {
                materiasNuevas.splice(i, 1); pintarChips();
            })));
        }
        function chip(texto, onDel) {
            const el = document.createElement('span');
            el.className = 'chip';
            el.innerHTML = `${texto} <button type="button">✕</button>`;
            el.querySelector('button').addEventListener('click', onDel);
            return el;
        }
        function agregarMateria() {
            const v = document.getElementById('materiaInput').value.trim();
            if (!v) return;
            materiasNuevas.push(v);
            document.getElementById('materiaInput').value = '';
            pintarChips();
        }

        function reset() {
            materiasNuevas = []; materiasExistentes = []; materiasEliminar = [];
            form.reset(); pintarChips();
        }

        function abrirAlta() {
            document.getElementById('modalTitulo').textContent = 'Nuevo grupo';
            document.getElementById('id_grupo').value = '';
            document.getElementById('campoMaestra').style.display = 'none';
            reset();
            document.getElementById('ciclo_id').value = cicloActivo; // por defecto el ciclo activo
            modal.showModal();
        }

        async function abrirEdicion(id) {
            reset();
            document.getElementById('modalTitulo').textContent = 'Editar grupo';
            document.getElementById('id_grupo').value = id;
            const r = await fetch(API + '?action=grupo_detalle&id_grupo=' + id);
            const d = await r.json();
            if (!d.success) { window.notify('error', d.message); return; }
            document.getElementById('grado').value = d.grupo.grado;
            document.getElementById('ciclo_id').value = d.grupo.ciclo_id ?? '';
            document.getElementById('nivel').value = d.grupo.nivel ?? '';
            materiasExistentes = d.materias.slice();
            // maestra select
            const sel = document.getElementById('maestra');
            sel.innerHTML = '<option value="">Sin asignar</option>' +
                d.maestros.map(m => `<option value="${m.id_cuenta}">${m.nombre}</option>`).join('');
            sel.value = d.grupo.maestra_id ?? '';
            document.getElementById('campoMaestra').style.display = 'block';
            pintarChips();
            modal.showModal();
        }

        form.addEventListener('submit', async (e) => {
            e.preventDefault();
            const id = document.getElementById('id_grupo').value;
            const fd = new FormData();
            fd.append('ciclo_id', document.getElementById('ciclo_id').value);
            fd.append('nivel', document.getElementById('nivel').value);
            if (id) {
                fd.append('id_grupo', id);
                fd.append('grado', document.getElementById('grado').value);
                fd.append('maestra_id', document.getElementById('maestra').value);
                fd.append('materias', JSON.stringify(materiasNuevas));
                fd.append('materiasEliminar', JSON.stringify(materiasEliminar.map(x => ({ id: x }))));
                const r = await fetch(API + '?action=grupo_editar', { method: 'POST', body: fd });
                const d = await r.json();
                window.notifyResponse(d);
                if (d.success) { modal.close(); cargar(); }
            } else {
                fd.append('grado', document.getElementById('grado').value);
                fd.append('materias', JSON.stringify(materiasNuevas));
                const r = await fetch(API + '?action=grupo_crear', { method: 'POST', body: fd });
                const d = await r.json();
                window.notifyResponse(d);
                if (d.success) { modal.close(); cargar(); }
            }
        });

        async function eliminar(g) {
            const n = Number(g.num_alumnos || 0);
            const aviso = n > 0
                ? `¿Eliminar el grupo "${g.grado}"? Sus ${n} alumno(s) quedarán en «Sin grupo» hasta que los reasignes.`
                : `¿Eliminar el grupo "${g.grado}" y todas sus materias?`;
            if (!await window.confirmar(aviso)) return;
            const fd = new FormData();
            fd.append('id_grupo', g.id_grupo);
            const r = await fetch(API + '?action=grupo_eliminar', { method: 'POST', body: fd });
            const d = await r.json();
            window.notifyResponse(d);
            if (d.success) cargar();
        }

        document.getElementById('fCiclo').addEventListener('change', cargar);

        (async () => { await cargarCiclos(); cargar(); })();
    </script>
</body>
</html>
