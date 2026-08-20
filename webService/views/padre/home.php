<?php
$rol = 3; // solo padre/alumno
require __DIR__ . '/../partials/guard.php';
$base = BASE_URL;
$title = 'Mi portal | Colegio del Bosque';
$extraCss = ['admin.css'];
$img = $base . '/webService/wwwroot/img';
$nombre = $_SESSION['usuario'] ?? 'Alumno';
?>
<!DOCTYPE html>
<html lang="es">
<head><?php require __DIR__ . '/../partials/head.php'; ?></head>
<body>
<?php require __DIR__ . '/../partials/notificador.php'; ?>

    <header class="panel-top">
        <div class="contenedor inner">
            <a href="<?= $base ?>/padre" class="marca">
                <img src="<?= $img ?>/logo.png" alt="Colegio del Bosque">
                <span>Colegio del Bosque<small>Portal del alumno</small></span>
            </a>
            <div style="display:flex;align-items:center;gap:1.2em;">
                <span class="user">Hola, <strong><?= htmlspecialchars($nombre) ?></strong></span>
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
    </section>

    <script>
        const API = window.BASE_URL + '/api/padre';
        const money = n => '$' + Number(n || 0).toLocaleString('es-MX', { minimumFractionDigits: 2 });

        function cerrarSesion() {
            fetch(window.BASE_URL + '/api/auth?action=logout', { method: 'POST' })
                .finally(() => location.href = window.BASE_URL + '/inicio-sesion');
        }

        async function cargar() {
            let d;
            try { d = await (await fetch(API + '?action=resumen')).json(); }
            catch { window.notify('error', 'No se pudo cargar tu información.'); return; }
            if (!d.success) { window.notify('error', d.message); return; }

            document.getElementById('grado').textContent = d.grado || '—';

            // Calificaciones
            const fc = document.getElementById('filasCalif');
            fc.innerHTML = '';
            document.getElementById('vacioCalif').style.display = d.calificaciones.length ? 'none' : 'block';
            d.calificaciones.forEach(c => {
                const cel = v => `<td>${(v === null || v === '') ? '—' : v}</td>`;
                const tr = document.createElement('tr');
                tr.innerHTML = `<td><strong>${c.materia}</strong></td>
                    ${cel(c.p1)}${cel(c.p2)}${cel(c.p3)}
                    <td><strong>${c.promedio ?? '—'}</strong></td>
                    <td>${c.reporte || '—'}</td>`;
                fc.appendChild(tr);
            });

            // Pagos
            const fp = document.getElementById('filasPagos');
            fp.innerHTML = '';
            document.getElementById('vacioPagos').style.display = d.pagos.length ? 'none' : 'block';
            const ESTLBL = { pendiente: 'Pendiente', parcial: 'Pago parcial', pagado: 'Pagado' };
            d.pagos.forEach(p => {
                const est = (p.estatus || 'pendiente').toLowerCase();
                const rec = Number(p.recargo_calc) ? `<span style="color:var(--rojo)">${money(p.recargo_calc)}</span>` : '—';
                const des = Number(p.descuento)
                    ? `<span style="color:var(--verde)">−${money(p.descuento)}</span>${p.concepto_descuento ? ' <small style="color:var(--texto-suave)">(' + p.concepto_descuento + ')</small>' : ''}`
                    : '—';
                const saldoLinea = (est !== 'pagado' && Number(p.abonado) > 0)
                    ? `<br><small style="color:var(--texto-suave)">Abonado ${money(p.abonado)} · Saldo <strong>${money(p.saldo)}</strong></small>` : '';
                const reciboLink = p.recibo_id
                    ? ` <a href="${window.BASE_URL}/recibo?id=${p.recibo_id}" target="_blank" style="color:var(--azul-marca);font-size:.8rem;margin-left:6px;white-space:nowrap;">🧾 recibo</a>` : '';
                const tr = document.createElement('tr');
                tr.innerHTML = `<td>${p.mes ?? ''}</td>
                    <td>${p.fecha_vencimiento ?? '—'}</td>
                    <td>${money(p.monto)}</td>
                    <td>${rec}</td>
                    <td>${des}</td>
                    <td><strong>${money(p.total)}</strong>${saldoLinea}</td>
                    <td><span class="badge-estatus ${est}">${ESTLBL[est] || est}</span>${reciboLink}</td>`;
                fp.appendChild(tr);
            });
        }

        cargar();
    </script>
</body>
</html>
