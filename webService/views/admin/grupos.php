<?php
/** Pantalla de grupos. La pinta GrupoController::index(). Recibe $base. Su JavaScript llama a /grupo/<método>. */
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

                <div class="campo">
                    <label>Nivel de inglés</label>
                    <select id="nivel_ingles">
                        <option value="">Sin nivel</option>
                        <option value="basico">Básico</option>
                        <option value="medio">Medio</option>
                        <option value="avanzado">Avanzado</option>
                    </select>
                </div>

                <div class="campo" id="campoMaestra" style="display:none;">
                    <label>Maestra asignada</label>
                    <select id="maestra"><option value="">Sin asignar</option></select>
                </div>

                <div class="campo">
                    <label>Materias del grupo</label>
                    <div class="lista-check" id="materiasCheck"></div>
                    <small id="sinCatalogo" style="color:var(--texto-suave);display:none;">Aún no hay materias en el catálogo.</small>
                    <div style="display:flex;gap:8px;margin-top:10px;">
                        <input type="text" id="materiaInput" placeholder="Agregar materia nueva…">
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
        const API = window.BASE_URL + '/grupo';
        const filas = document.getElementById('filas');
        const vacio = document.getElementById('vacio');
        const modal = document.getElementById('modal');
        const form = document.getElementById('form');
        const chips = document.getElementById('chips');

        let materiasNuevas = [];        // nombres nuevos a crear + vincular
        let catalogoMaterias = [];      // {id_materia, nombre} catálogo completo

        function cerrarSesion() {
            fetch(window.BASE_URL + '/auth/salir', { method: 'POST' })
                .finally(() => location.href = window.BASE_URL + '/auth/entrar');
        }

        let ciclos = [], cicloActivo = '';

        async function cargarCiclos() {
            const result = await (await fetch(window.BASE_URL + '/ciclo/listar')).json();
            ciclos = result.data.items || [];
            const act = ciclos.find(c => c.Activo);
            cicloActivo = act ? String(act.IdCiclo) : '';
            const opts = ciclos.map(c => `<option value="${c.IdCiclo}">${c.Nombre}${c.Activo ? ' (activo)' : ''}</option>`).join('');
            document.getElementById('fCiclo').innerHTML = '<option value="">Todos los ciclos</option>' + opts;
            document.getElementById('ciclo_id').innerHTML = '<option value="">Sin ciclo</option>' + opts;
        }

        async function cargar() {
            const fc = document.getElementById('fCiclo').value;
            const r = await fetch(API + '/listar' + (fc ? '?IdCiclo=' + fc : ''));
            const d = await r.json();
            const items = d.data?.items || [];
            filas.innerHTML = '';
            vacio.style.display = items.length ? 'none' : 'block';
            items.forEach(g => {
                const tr = document.createElement('tr');
                tr.innerHTML = `
                    <td><strong>${g.Grado}</strong></td>
                    <td>${g.CicloNombre ?? '—'}</td>
                    <td>${g.Nivel ?? '—'}</td>
                    <td>${g.NumMaterias} materia(s)</td>
                    <td><div class="acciones">
                        <button class="icon-btn editar" title="Editar"><svg class="ic-s" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 20h9"/><path d="M16.5 3.5a2.1 2.1 0 0 1 3 3L7 19l-4 1 1-4 12.5-12.5Z"/></svg></button>
                        <button class="icon-btn borrar" title="Eliminar"><svg class="ic-s" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 6h18"/><path d="M8 6V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/><path d="M19 6l-1 14a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2L5 6"/></svg></button>
                    </div></td>`;
                tr.querySelector('.editar').addEventListener('click', () => abrirEdicion(g.IdGrupo));
                tr.querySelector('.borrar').addEventListener('click', () => eliminar(g));
                filas.appendChild(tr);
            });
        }

        async function cargarCatalogo() {
            const result = await (await fetch(window.BASE_URL + '/materia/listar')).json();
            catalogoMaterias = (result.data.items || []).map(m => ({ id_materia: m.IdMateria, nombre: m.Nombre }));
        }

        function pintarChecklist(seleccionados = []) {
            const cont = document.getElementById('materiasCheck');
            const sel = new Set(seleccionados.map(Number));
            document.getElementById('sinCatalogo').style.display = catalogoMaterias.length ? 'none' : 'block';
            cont.innerHTML = catalogoMaterias.map(m => `
                <label>
                    <input type="checkbox" value="${m.id_materia}" ${sel.has(Number(m.id_materia)) ? 'checked' : ''}>
                    <span>${m.nombre}</span>
                </label>`).join('');
        }
        function materiasMarcadas() {
            return [...document.querySelectorAll('#materiasCheck input:checked')].map(c => Number(c.value));
        }

        function pintarChipsNuevas() {
            chips.innerHTML = '';
            materiasNuevas.forEach((nombre, i) => {
                const el = document.createElement('span');
                el.className = 'chip';
                el.innerHTML = `${nombre} (nueva) <button type="button">✕</button>`;
                el.querySelector('button').addEventListener('click', () => { materiasNuevas.splice(i, 1); pintarChipsNuevas(); });
                chips.appendChild(el);
            });
        }
        function agregarMateria() {
            const v = document.getElementById('materiaInput').value.trim();
            if (!v) return;
            materiasNuevas.push(v);
            document.getElementById('materiaInput').value = '';
            pintarChipsNuevas();
        }

        function reset() {
            materiasNuevas = [];
            form.reset();
            pintarChecklist([]);
            pintarChipsNuevas();
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
            const r = await fetch(API + '/detalle?IdGrupo=' + id);
            const res = await r.json();
            if (!res.success) { window.notify('error', res.message); return; }
            const d = res.data;
            document.getElementById('grado').value = d.grupo.Grado;
            document.getElementById('ciclo_id').value = d.grupo.IdCiclo ?? '';
            document.getElementById('nivel').value = d.grupo.Nivel ?? '';
            document.getElementById('nivel_ingles').value = d.grupo.NivelIngles ?? '';
            materiasNuevas = [];
            pintarChecklist((d.materias || []).map(m => m.IdMateria));
            pintarChipsNuevas();
            // maestra select
            const sel = document.getElementById('maestra');
            sel.innerHTML = '<option value="">Sin asignar</option>' +
                d.maestros.map(m => `<option value="${m.IdCuenta}">${m.Nombre}</option>`).join('');
            sel.value = d.grupo.IdMaestra ?? '';
            document.getElementById('campoMaestra').style.display = 'block';
            modal.showModal();
        }

        form.addEventListener('submit', async (e) => {
            e.preventDefault();
            const id = document.getElementById('id_grupo').value;
            const fd = new FormData();
            // Cada campo viaja con el nombre de la propiedad del model Grupo; las materias, como Materias[] y MateriasNuevas[]
            fd.append('IdCiclo', document.getElementById('ciclo_id').value);
            fd.append('Nivel', document.getElementById('nivel').value);
            fd.append('NivelIngles', document.getElementById('nivel_ingles').value);
            fd.append('Grado', document.getElementById('grado').value);
            materiasMarcadas().forEach(m => fd.append('Materias[]', m));
            materiasNuevas.forEach(m => fd.append('MateriasNuevas[]', m));
            if (id) {
                fd.append('IdGrupo', id);
                fd.append('IdMaestra', document.getElementById('maestra').value);
            }
            const r = await fetch(API + '/guardar', { method: 'POST', body: fd });
            const d = await r.json();
            window.notifyResponse(d);
            if (d.success) { modal.close(); cargar(); }
        });

        async function eliminar(g) {
            const n = Number(g.NumAlumnos || 0);
            const aviso = n > 0
                ? `¿Eliminar el grupo "${g.Grado}"? Sus ${n} alumno(s) quedarán en «Sin grupo» hasta que los reasignes.`
                : `¿Eliminar el grupo "${g.Grado}"? Las materias del catálogo no se borran.`;
            if (!await window.confirmar(aviso)) return;
            const fd = new FormData();
            fd.append('IdGrupo', g.IdGrupo);
            const r = await fetch(API + '/eliminar', { method: 'POST', body: fd });
            const d = await r.json();
            window.notifyResponse(d);
            if (d.success) cargar();
        }

        document.getElementById('fCiclo').addEventListener('change', cargar);

        (async () => { await Promise.all([cargarCiclos(), cargarCatalogo()]); cargar(); })();
    </script>
</body>
</html>
