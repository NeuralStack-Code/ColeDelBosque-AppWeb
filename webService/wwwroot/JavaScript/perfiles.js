/**
 * Perfiles y permisos (views/dev/perfiles.php). Rutas: /perfilacceso/<método> → PerfilAccesoController.
 * El árbol sale de window.CATALOGO.objetos:
 *   Módulo (grupo del menú) → Pantalla de menú → Pantalla sin menú (cuelga de otra) → Acciones (métodos)
 * Las acciones que no dependen de su pantalla (IdPadre vacío, p. ej. reporte/grupos) se muestran dentro de la
 * pantalla de su controller, en "Aparte": marcar la pantalla no las marca.
 * Solo se muestra lo que concede un perfil (Acceso = 'perfil'); lo público o de sesión lo tiene cualquiera.
 * Quien no es administrador no puede marcar lo Sensible ni lo que él mismo no tiene (el servidor lo vuelve a revisar).
 * Nada de alert/confirm nativos: window.notifyResponse y window.confirmar.
 */
(function () {
    const C      = window.CATALOGO;
    const API    = window.BASE_URL + '/perfilacceso';
    const modal  = document.getElementById('modal');
    const form   = document.getElementById('form');
    const modalP = document.getElementById('modalPermisos');
    const tree   = document.getElementById('permisosArbol');
    const save   = document.getElementById('permisosGuardar');
    const search = document.getElementById('permisosBuscar');
    const tools  = document.getElementById('permisosHerramientas');
    let current = null;

    const esc  = s => { const d = document.createElement('div'); d.textContent = s ?? ''; return d.innerHTML; };
    const norm = s => (s || '').toString().normalize('NFD').replace(/[̀-ͯ]/g, '').toLowerCase();
    const LOCK = '<svg class="arbol-lock" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="4" y="11" width="16" height="10" rx="2"/><path d="M8 11V7a4 4 0 0 1 8 0v4"/></svg>';

    /** ¿Quien entra puede marcar/desmarcar este objeto? */
    const editable = o => C.esAdmin || (!o.Sensible && C.concedibles.includes(o.IdObjeto));

    const TIPOS = {
        modulo: ['Módulo',            'arbol-tag-modulo'],
        menu:   ['Pantalla de menú',  'arbol-tag-menu'],
        vista:  ['Pantalla',          'arbol-tag-vista'],
        accion: ['Acción',            'arbol-tag-accion'],
    };

    /* ───────────── Peticiones ───────────── */

    async function post(metodo, datos) {
        const fd = datos instanceof FormData ? datos : new FormData();
        if (!(datos instanceof FormData)) Object.entries(datos).forEach(([k, v]) => fd.append(k, v));
        try {
            const result = await (await fetch(API + '/' + metodo, { method: 'POST', body: fd })).json();
            window.notifyResponse(result);
            return result.success;
        } catch (e) {
            window.notifyResponse({ success: false, message: 'No se pudo conectar con el servidor.' });
            return false;
        }
    }
    const recargar = () => setTimeout(() => location.reload(), 700);

    /* ───────────── Crear / editar / eliminar ───────────── */

    function abrirForm(perfil) {
        form.reset();
        document.getElementById('modalTitulo').textContent = perfil ? 'Editar perfil' : 'Nuevo perfil';
        const p = perfil || { IdPerfil: 0, Nombre: '', Descripcion: '', Inicio: '', EsAdmin: false, Activo: true };
        form.elements.IdPerfil.value    = p.IdPerfil;
        form.elements.Nombre.value      = p.Nombre;
        form.elements.Descripcion.value = p.Descripcion || '';
        form.elements.Inicio.value      = p.Inicio || '';
        form.elements.EsAdmin.value     = p.EsAdmin ? '1' : '0';
        form.elements.Activo.value      = p.Activo ? '1' : '0';
        modal.showModal();
        form.elements.Nombre.focus();
    }

    form.addEventListener('submit', async event => {
        event.preventDefault();
        const fd = new FormData(form);
        if (form.elements.EsAdmin.disabled) fd.set('EsAdmin', form.elements.EsAdmin.value);   // un select deshabilitado no viaja
        if (await post('guardar', fd)) { modal.close(); recargar(); }
    });

    async function eliminar(p) {
        if (!await window.confirmar(`¿Eliminar el perfil "${p.Nombre}"? No lo tiene ninguna cuenta.`, { titulo: 'Eliminar perfil', confirmar: 'Eliminar', peligro: true })) return;
        if (await post('eliminar', { IdPerfil: p.IdPerfil })) recargar();
    }

    /* ───────────── Armar el árbol ───────────── */

    function armar() {
        const visibles = C.objetos.filter(o => o.Tipo === 1 || o.Acceso === 'perfil');
        const hijos = {};
        visibles.forEach(o => { const p = o.IdPadre ?? 0; (hijos[p] = hijos[p] || []).push(o); });

        const nodoAccion = m => ({ tipo: 'accion', obj: m, hijos: [] });
        const nodoVista = (v, nivel) => {
            const suyos = hijos[v.IdObjeto] || [];
            return {
                tipo: nivel, obj: v,
                hijos: [
                    ...suyos.filter(o => o.Tipo === 2).map(s => nodoVista(s, 'vista')),
                    ...suyos.filter(o => o.Tipo === 3).map(nodoAccion),
                ],
                aparte: [],
            };
        };

        const modulos = (hijos[0] || []).filter(o => o.Tipo === 1)
            .map(g => ({ tipo: 'modulo', obj: g, hijos: (hijos[g.IdObjeto] || []).filter(o => o.Tipo === 2).map(v => nodoVista(v, 'menu')) }));
        const sinModulo = (hijos[0] || []).filter(o => o.Tipo === 2).map(v => nodoVista(v, 'menu'));
        if (sinModulo.length) modulos.push({ tipo: 'modulo', obj: { Nombre: 'Sin módulo' }, hijos: sinModulo });

        // Acciones sin pantalla: a la pantalla principal de su controller ('index' o la primera), en "Aparte".
        const vistasDe = {};
        const recorrer = n => { if (n.tipo !== 'modulo' && n.tipo !== 'accion') (vistasDe[n.obj.Componente] = vistasDe[n.obj.Componente] || []).push(n); n.hijos.forEach(recorrer); };
        modulos.forEach(recorrer);
        const huerfanas = [];
        (hijos[0] || []).filter(o => o.Tipo === 3).forEach(m => {
            const vs = vistasDe[m.Componente] || [];
            const destino = vs.find(n => n.obj.Clave === 'index') || vs[0];
            destino ? destino.aparte.push(nodoAccion(m)) : huerfanas.push(nodoAccion(m));
        });
        if (huerfanas.length) modulos.push({ tipo: 'modulo', obj: { Nombre: 'Acciones sin pantalla' }, hijos: huerfanas });

        return modulos.filter(m => m.hijos.length);
    }

    /* ───────────── Pintar ───────────── */

    function fila(n, marcado) {
        const [tag, clase] = TIPOS[n.tipo];
        const o = n.obj;
        const ruta = n.tipo === 'modulo' ? '' : `<code class="arbol-ruta">/${esc(o.Componente)}/${esc(o.Clave)}</code>`;
        const lock = o.Sensible ? `<span title="Sensible: solo un administrador lo concede">${LOCK}</span>` : '';
        const tieneHijos = n.hijos.length || (n.aparte && n.aparte.length);
        const toggle = tieneHijos
            ? `<button type="button" class="arbol-toggle" aria-label="Abrir o cerrar">${C.chevron}</button>`
            : '<span class="arbol-toggle-vacio"></span>';
        const casilla = n.tipo === 'modulo'
            ? '<input type="checkbox" class="arbol-todo" title="Marcar todo el módulo">'
            : `<input type="checkbox" data-obj="${o.IdObjeto}"${marcado ? ' checked' : ''}${editable(o) ? '' : ' disabled title="No lo puedes conceder"'}>`;
        const busca = norm([o.Nombre, o.Clave, o.Componente, o.Componente && o.Clave ? o.Componente + '/' + o.Clave : '', tag].join(' '));
        return `<div class="arbol-fila" data-busca="${esc(busca)}">${toggle}<label class="arbol-label">${casilla}`
             + `<span class="arbol-nombre">${esc(o.Nombre || o.Clave)}</span><span class="arbol-tag ${clase}">${tag}</span>${ruta}${lock}</label>`
             + (tieneHijos ? '<span class="arbol-cuenta"></span>' : '') + '</div>';
    }

    function nodoHtml(n, concede) {
        const marcado = n.obj.IdObjeto !== undefined && concede.includes(n.obj.IdObjeto);
        let html = `<div class="arbol-nodo arbol-${n.tipo}">` + fila(n, marcado);     // todo empieza colapsado
        if (n.hijos.length || (n.aparte && n.aparte.length)) {
            html += '<div class="arbol-hijos">' + n.hijos.map(h => nodoHtml(h, concede)).join('');
            if (n.aparte && n.aparte.length) {
                html += '<div class="arbol-aparte"><div class="arbol-aparte-titulo">Aparte · marcar la pantalla no las incluye</div>'
                      + n.aparte.map(h => nodoHtml(h, concede)).join('') + '</div>';
            }
            html += '</div>';
        }
        return html + '</div>';
    }

    function pintar(perfil) {
        const arbol = armar();
        tree.innerHTML = arbol.length
            ? arbol.map(m => nodoHtml(m, perfil.Concede || [])).join('') + '<p class="arbol-vacio arbol-sin-coincidencias" hidden>Nada coincide con la búsqueda.</p>'
            : '<p class="arbol-vacio">El catálogo está vacío: corre la semilla del catálogo (apiService/tools/catalogo).</p>';
        recalcular();
    }

    /* ───────────── Marcar ───────────── */

    /** Casillas que arrastra la de este nodo: un módulo, todo lo suyo; una pantalla, lo suyo menos lo de "Aparte". */
    function arrastradas(nodo) {
        const hijos = nodo.querySelector(':scope > .arbol-hijos');
        if (!hijos) return [];
        const esModulo = nodo.classList.contains('arbol-modulo');
        return [...hijos.querySelectorAll('input[data-obj]')].filter(i => !i.disabled && (esModulo || !i.closest('.arbol-aparte')));
    }

    /** Estado a medias, cuentas "marcadas / total" y la casilla del módulo. */
    function recalcular() {
        [...tree.querySelectorAll('.arbol-nodo')].reverse().forEach(nodo => {
            const hijos = nodo.querySelector(':scope > .arbol-hijos');
            if (!hijos) return;
            const todas = [...hijos.querySelectorAll('input[data-obj]')];
            const marcadas = todas.filter(i => i.checked).length;
            const box = nodo.querySelector(':scope > .arbol-fila input');
            const cuenta = nodo.querySelector(':scope > .arbol-fila .arbol-cuenta');
            if (cuenta) cuenta.textContent = `${marcadas}/${todas.length}`;
            if (box.classList.contains('arbol-todo')) {
                box.checked = todas.length > 0 && marcadas === todas.length;
                box.indeterminate = marcadas > 0 && marcadas < todas.length;
                box.disabled = todas.every(i => i.disabled);
            } else {
                box.indeterminate = !box.checked && marcadas > 0;
            }
        });
    }

    tree.addEventListener('change', event => {
        const box = event.target;
        if (box.type !== 'checkbox') return;
        const nodo = box.closest('.arbol-nodo');
        if (box.classList.contains('arbol-todo') || nodo.querySelector(':scope > .arbol-hijos')) {
            arrastradas(nodo).forEach(i => { i.checked = box.checked; });
        }
        recalcular();
    });

    /* ───────────── Abrir/cerrar y buscar ───────────── */

    tree.addEventListener('click', event => {
        const t = event.target.closest('.arbol-toggle');
        if (t) t.closest('.arbol-nodo').classList.toggle('abierto');
    });

    const abrirTodo  = () => tree.querySelectorAll('.arbol-nodo').forEach(n => n.classList.add('abierto'));
    const cerrarTodo = () => tree.querySelectorAll('.arbol-nodo').forEach(n => n.classList.remove('abierto'));

    /** Deja a la vista lo que coincide y el camino hasta ello (módulo → pantalla → …), abierto. */
    function buscar() {
        const q = norm(search.value.trim());
        const nodos = [...tree.querySelectorAll('.arbol-nodo')];
        nodos.forEach(n => n.classList.remove('arbol-oculto', 'arbol-coincide'));
        tree.querySelectorAll('.arbol-aparte').forEach(a => a.classList.remove('arbol-oculto'));
        const vacio = tree.querySelector('.arbol-sin-coincidencias');
        if (!q) { cerrarTodo(); if (vacio) vacio.hidden = true; return; }

        const coincide = n => n.querySelector(':scope > .arbol-fila').dataset.busca.includes(q);
        let alguno = false;
        nodos.reverse().forEach(n => {
            const propio = coincide(n);
            const hijoVisible = [...n.querySelectorAll(':scope > .arbol-hijos .arbol-nodo')].some(h => !h.classList.contains('arbol-oculto'));
            let ancestro = false;
            for (let p = n.parentElement.closest('.arbol-nodo'); p && !ancestro; p = p.parentElement.closest('.arbol-nodo')) ancestro = coincide(p);
            n.classList.toggle('arbol-oculto', !(propio || hijoVisible || ancestro));
            n.classList.toggle('arbol-coincide', propio);
            n.classList.toggle('abierto', hijoVisible || (propio && n.classList.contains('arbol-modulo')));
            if (propio) alguno = true;
        });
        tree.querySelectorAll('.arbol-aparte').forEach(a => {
            a.classList.toggle('arbol-oculto', ![...a.querySelectorAll('.arbol-nodo')].some(h => !h.classList.contains('arbol-oculto')));
        });
        if (vacio) vacio.hidden = alguno;
    }
    search.addEventListener('input', buscar);
    search.addEventListener('keydown', e => { if (e.key === 'Escape' && search.value) { e.preventDefault(); search.value = ''; buscar(); } });

    /* ───────────── Modal de permisos ───────────── */

    function abrirPermisos(perfil) {
        current = perfil;
        document.getElementById('permisosPerfil').textContent = perfil.Nombre;
        const propio = C.mios.includes(perfil.IdPerfil) && !C.esAdmin;
        document.getElementById('permisosAdmin').hidden     = !perfil.EsAdmin;
        document.getElementById('permisosNota').hidden      = perfil.EsAdmin;
        document.getElementById('permisosBloqueado').hidden = !propio || perfil.EsAdmin;
        tree.hidden  = perfil.EsAdmin;
        tools.hidden = perfil.EsAdmin;
        save.hidden  = perfil.EsAdmin || propio;
        search.value = '';
        if (!perfil.EsAdmin) pintar(perfil);
        if (propio) tree.querySelectorAll('input').forEach(i => { i.disabled = true; });
        save.disabled = false;
        modalP.showModal();
        if (!perfil.EsAdmin) setTimeout(() => search.focus(), 50);
    }

    async function guardarPermisos() {
        const ids = [...tree.querySelectorAll('input[data-obj]:checked')].map(box => Number(box.dataset.obj));
        save.disabled = true;
        if (await post('objetos', { IdPerfil: current.IdPerfil, Objetos: JSON.stringify(ids) })) { modalP.close(); recargar(); }
        else save.disabled = false;
    }

    /* ───────────── Botones (data-action) ───────────── */

    const acciones = {
        salir: () => fetch(window.BASE_URL + '/auth/salir', { method: 'POST' }).finally(() => { location.href = window.BASE_URL + '/auth/entrar'; }),
        nuevo: () => abrirForm(null),
        editar: abrirForm,
        eliminar,
        permisos: abrirPermisos,
        cerrar: () => modal.close(),
        cerrarPermisos: () => modalP.close(),
        guardarPermisos,
        abrirTodo,
        cerrarTodo,
    };
    document.addEventListener('click', event => {
        const b = event.target.closest('[data-action]');
        if (!b || !acciones[b.dataset.action]) return;
        const tr = b.closest('tr[data-record]');
        acciones[b.dataset.action](tr ? JSON.parse(tr.dataset.record) : undefined);
    });
    [modal, modalP].forEach(d => d.addEventListener('click', event => { if (event.target === d) d.close(); }));
})();
