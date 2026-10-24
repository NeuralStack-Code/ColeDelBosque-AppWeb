<?php
/** Visor de tablas (solo lectura). Lo pinta TablasController::index(). Recibe $base. Su JavaScript llama a /tablas/<método>. */
$title = 'Tablas | Desarrollador';
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
            <h2>Tablas de la base de datos</h2>
            <span style="color:var(--texto-suave);font-size:.9rem;">Solo lectura</span>
        </div>

        <div class="filtros">
            <div class="f"><label>Tabla</label><select id="fTabla"><option value="">Selecciona…</option></select></div>
            <div class="f"><label>Máx. filas</label><select id="fLimite"><option>100</option><option selected>200</option><option>500</option></select></div>
        </div>

        <p id="meta" style="color:var(--texto-suave);font-size:.9rem;margin:0 0 10px;"></p>
        <div class="tabla-scroll" style="max-height:60vh;">
            <table class="tabla" id="tabla"><thead id="thead"></thead><tbody id="filas"></tbody></table>
        </div>
        <p class="tabla-vacia" id="vacio" style="display:none;">Selecciona una tabla para ver sus datos.</p>
    </section>

    <script>
        const API = window.BASE_URL + '/tablas';   // cada método del controller es una URL: /tablas/listar, /tablas/ver
        const thead = document.getElementById('thead');
        const filas = document.getElementById('filas');
        const vacio = document.getElementById('vacio');
        const meta = document.getElementById('meta');

        function cerrarSesion() {
            fetch(window.BASE_URL + '/auth/salir', { method: 'POST' })
                .finally(() => location.href = window.BASE_URL + '/auth/entrar');
        }
        const esc = s => String(s ?? '').replace(/[&<>"]/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;' }[c]));

        async function cargarTablas() {
            const result = await (await fetch(API + '/listar')).json();
            const items = result.data.items || [];
            document.getElementById('fTabla').innerHTML = '<option value="">Selecciona…</option>' +
                items.map(t => `<option value="${t.Tabla}">${t.Tabla} (${t.Filas})</option>`).join('');
        }

        async function verTabla() {
            const tabla = document.getElementById('fTabla').value;
            const limite = document.getElementById('fLimite').value;
            if (!tabla) { thead.innerHTML = ''; filas.innerHTML = ''; meta.textContent = ''; vacio.style.display = 'block'; return; }
            const result = await (await fetch(`${API}/ver?Tabla=${encodeURIComponent(tabla)}&Limite=${limite}`)).json();
            if (!result.success) { window.notify('error', result.message); return; }
            const cols = result.data.columnas || [], rows = result.data.filas || [];
            thead.innerHTML = '<tr>' + cols.map(c => `<th>${esc(c)}</th>`).join('') + '</tr>';
            filas.innerHTML = rows.map(r =>
                '<tr>' + cols.map(c => `<td>${esc(r[c])}</td>`).join('') + '</tr>').join('');
            meta.textContent = `${rows.length} fila(s) · ${cols.length} columna(s)` + (rows.length >= Number(limite) ? ' (limitado)' : '');
            vacio.style.display = rows.length ? 'none' : 'block';
        }

        document.getElementById('fTabla').addEventListener('change', verTabla);
        document.getElementById('fLimite').addEventListener('change', verTabla);
        cargarTablas();
    </script>
</body>
</html>
