<?php
/** Portal del alumno. La pinta PadreController::index(). Recibe $base y $nombre. Su JavaScript llama a /padre/<método>. */
$title = 'Mi portal | Colegio del Bosque';
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
            <a href="<?= $base ?>/padre/index" class="marca">
                <img src="<?= $img ?>/logo.png" alt="Colegio del Bosque">
                <span>Colegio del Bosque<small>Portal del alumno</small></span>
            </a>
            <div style="display:flex;align-items:center;gap:1.2em;">
                <span class="user">Hola, <strong><?= e($nombre ?: 'Alumno') ?></strong></span>
                <button class="btn-salir" onclick="cerrarSesion()">Cerrar sesión</button>
            </div>
        </div>
    </header>

    <section class="admin-wrap" style="margin-top:40px;">
        <p style="color:var(--texto-suave);margin-bottom:32px;">Grupo: <strong id="grado" style="color:var(--texto);">—</strong></p>

        <!-- Calificaciones -->
        <div class="panel-seccion">
            <h2><svg class="ic-s" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 3v18h18"/><rect x="7" y="12" width="3" height="6"/><rect x="12" y="8" width="3" height="10"/><rect x="17" y="5" width="3" height="13"/></svg> Mis calificaciones</h2>
            <div class="tabla-scroll">
                <table class="tabla">
                    <thead><tr><th>Materia</th><th>P1</th><th>P2</th><th>P3</th><th>Final</th><th>Reporte</th></tr></thead>
                    <tbody id="filasCalif"></tbody>
                </table>
            </div>
            <p class="tabla-vacia" id="vacioCalif" style="display:none;">Aún no hay calificaciones registradas.</p>
        </div>

        <!-- Pagos -->
        <div class="panel-seccion">
            <h2><svg class="ic-s" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 1v22"/><path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/></svg> Mis pagos</h2>
            <div class="tabla-scroll">
                <table class="tabla">
                    <thead><tr><th>Concepto</th><th>Vence</th><th>Monto</th><th>Recargo</th><th>Descuento</th><th>Total</th><th>Estatus</th></tr></thead>
                    <tbody id="filasPagos"></tbody>
                </table>
            </div>
            <p class="tabla-vacia" id="vacioPagos" style="display:none;">No hay colegiaturas registradas.</p>
        </div>

        <!-- Correos del tutor -->
        <div class="panel-seccion">
            <h2><svg class="ic-s" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="2" y="4" width="20" height="16" rx="2"/><path d="m22 7-10 6L2 7"/></svg> Correo para el reporte semanal</h2>
            <form class="filtros" id="formCorreos">
                <div class="f buscar"><label for="correo_tutor">Correo del tutor</label><input type="email" id="correo_tutor" maxlength="100" placeholder="nombre@correo.com"></div>
                <div class="f buscar"><label for="correo_tutor2">Segundo correo (opcional)</label><input type="email" id="correo_tutor2" maxlength="100"></div>
                <div class="f-btns"><button type="submit" class="btn btn-primario">Guardar</button></div>
            </form>
        </div>
    </section>

    <script>
        const API = window.BASE_URL + '/padre';
        const money = n => '$' + Number(n || 0).toLocaleString('es-MX', { minimumFractionDigits: 2 });

        function cerrarSesion() {
            fetch(window.BASE_URL + '/auth/salir', { method: 'POST' })
                .finally(() => location.href = window.BASE_URL + '/auth/entrar');
        }

        async function cargar() {
            let d;
            try { d = await (await fetch(API + '/resumen')).json(); }
            catch { window.notify('error', 'No se pudo cargar tu información.'); return; }
            if (!d.success) { window.notify('error', d.message); return; }
            d = d.data;

            document.getElementById('grado').textContent = d.grado || '—';
            document.getElementById('correo_tutor').value = d.correoTutor || '';
            document.getElementById('correo_tutor2').value = d.correoTutor2 || '';

            // Calificaciones
            const fc = document.getElementById('filasCalif');
            fc.innerHTML = '';
            document.getElementById('vacioCalif').style.display = d.calificaciones.length ? 'none' : 'block';
            d.calificaciones.forEach(c => {
                const cel = v => `<td>${(v === null || v === '') ? '—' : v}</td>`;
                const tr = document.createElement('tr');
                tr.innerHTML = `<td><strong>${c.Materia}</strong></td>
                    ${cel(c.P1)}${cel(c.P2)}${cel(c.P3)}
                    <td><strong>${c.Promedio ?? '—'}</strong></td>
                    <td>${c.Reporte || '—'}</td>`;
                fc.appendChild(tr);
            });

            // Pagos
            const fp = document.getElementById('filasPagos');
            fp.innerHTML = '';
            document.getElementById('vacioPagos').style.display = d.pagos.length ? 'none' : 'block';
            const ESTLBL = { pendiente: 'Pendiente', parcial: 'Pago parcial', pagado: 'Pagado' };
            d.pagos.forEach(p => {
                const est = (p.Estatus || 'pendiente').toLowerCase();
                const rec = Number(p.RecargoCalc) ? `<span style="color:var(--rojo)">${money(p.RecargoCalc)}</span>` : '—';
                const des = Number(p.Descuento)
                    ? `<span style="color:var(--verde)">−${money(p.Descuento)}</span>${p.ConceptoDescuento ? ' <small style="color:var(--texto-suave)">(' + p.ConceptoDescuento + ')</small>' : ''}`
                    : '—';
                const saldoLinea = (est !== 'pagado' && Number(p.Abonado) > 0)
                    ? `<br><small style="color:var(--texto-suave)">Abonado ${money(p.Abonado)} · Saldo <strong>${money(p.Saldo)}</strong></small>` : '';
                const reciboLink = p.IdRecibo
                    ? ` <a href="${window.BASE_URL}/recibo/imprimir?IdRecibo=${p.IdRecibo}" target="_blank" style="color:var(--azul-marca);font-size:.8rem;margin-left:6px;white-space:nowrap;">🧾 recibo</a>` : '';
                const tr = document.createElement('tr');
                tr.innerHTML = `<td>${p.Mes ?? ''}</td>
                    <td>${p.FechaVencimiento ?? '—'}</td>
                    <td>${money(p.Monto)}</td>
                    <td>${rec}</td>
                    <td>${des}</td>
                    <td><strong>${money(p.Total)}</strong>${saldoLinea}</td>
                    <td><span class="badge-estatus ${est}">${ESTLBL[est] || est}</span>${reciboLink}</td>`;
                fp.appendChild(tr);
            });
        }

        document.getElementById('formCorreos').addEventListener('submit', async (e) => {
            e.preventDefault();
            const fd = new FormData();
            fd.append('CorreoTutor', document.getElementById('correo_tutor').value);
            fd.append('CorreoTutor2', document.getElementById('correo_tutor2').value);
            try {
                const r = await fetch(API + '/guardarCorreos', { method: 'POST', body: fd });
                window.notifyResponse(await r.json());
            } catch { window.notify('error', 'No se pudieron guardar los correos.'); }
        });

        cargar();
    </script>
</body>
</html>
