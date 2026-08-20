<?php
/**
 * Recibo de pago imprimible.  /recibo?id=<id_recibo>
 * Acceso: admin (cualquiera) · padre (solo los suyos).
 */
if (!defined('BASE_URL')) require_once __DIR__ . '/../../apiService/core/config.php';
if (session_status() !== PHP_SESSION_ACTIVE) session_start();
if (!isset($_SESSION['usuario'])) { header('Location: ' . BASE_URL . '/inicio-sesion'); exit; }

require_once __DIR__ . '/../../apiService/core/conexionBDD.php';

$permiso = (int) ($_SESSION['permiso'] ?? 0);
$id      = (int) ($_GET['id'] ?? 0);
$base    = BASE_URL;

$stmt = mysqli_prepare($conexion,
    'SELECT r.id_recibo, r.naturaleza, r.destinatario_tipo, r.cuenta_id, r.monto, r.tipo_pago, r.comentario, r.fecha,
            t.nombre AS tipo_nombre, u.nombre, u.paterno, u.materno
     FROM recibo r
     LEFT JOIN tipo_recibo t ON t.id_tipo = r.tipo_recibo_id
     LEFT JOIN cuenta c      ON c.id_cuenta = r.cuenta_id
     LEFT JOIN usuario u     ON u.id_usuario = c.usuario_id
     WHERE r.id_recibo = ? LIMIT 1');
mysqli_stmt_bind_param($stmt, 'i', $id);
mysqli_stmt_execute($stmt);
$r = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
mysqli_stmt_close($stmt);

if (!$r) { http_response_code(404); exit('Recibo no encontrado.'); }

// Autorización del padre: solo sus propios recibos
if ($permiso === 3) {
    $uid = (int) ($_SESSION['id'] ?? 0);
    $q = mysqli_prepare($conexion, 'SELECT 1 FROM cuenta WHERE usuario_id = ? AND id_cuenta = ? AND permiso_id = 3 LIMIT 1');
    mysqli_stmt_bind_param($q, 'ii', $uid, $r['cuenta_id']);
    mysqli_stmt_execute($q);
    $ok = (bool) mysqli_fetch_row(mysqli_stmt_get_result($q));
    mysqli_stmt_close($q);
    if (!$ok) { http_response_code(403); exit('No tienes acceso a este recibo.'); }
}

$destinatario = $r['destinatario_tipo'] === 'escuela' ? 'Escuela' : trim("$r[nombre] $r[paterno] $r[materno]");
$etiquetaDest = $r['destinatario_tipo'] === 'alumno' ? 'Alumno'
    : ($r['destinatario_tipo'] === 'docente' ? 'Docente'
    : ($r['naturaleza'] === 'ingreso' ? 'Recibí de' : 'Pagado a'));
$concepto = $r['comentario'] ?: ($r['tipo_nombre'] ?? '—');
$folio = str_pad((string) $r['id_recibo'], 6, '0', STR_PAD_LEFT);
$fecha = date('d/m/Y H:i', strtotime($r['fecha']));
$money = fn($n) => '$' . number_format((float) $n, 2);
$esIngreso = $r['naturaleza'] === 'ingreso';
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" href="<?= $base ?>/webService/wwwroot/img/logo.png">
    <title>Recibo <?= $folio ?> — Colegio del Bosque</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Fredoka:wght@400;500;600;700&family=Nunito:wght@400;600;700;800&display=swap" rel="stylesheet">
    <style>
        :root { --azul:#5b3ee0; --azul-osc:#3d2aa0; --texto:#1f2233; --suave:#5b607a; --borde:#eceafb; --verde:#22c55e; --rojo:#ef4444; }
        * { margin:0; padding:0; box-sizing:border-box; }
        body { font-family:"Nunito",sans-serif; color:var(--texto); background:#f4f3fb; padding:30px 16px; }
        .barra { max-width:640px; margin:0 auto 16px; display:flex; gap:10px; justify-content:flex-end; }
        .btn { font-family:"Fredoka",sans-serif; font-weight:600; font-size:.95rem; padding:.6em 1.3em; border-radius:999px; border:none; cursor:pointer; text-decoration:none; display:inline-flex; align-items:center; gap:.5em; }
        .btn-p { background:var(--azul); color:#fff; }
        .btn-g { background:#fff; color:var(--azul); border:2px solid var(--azul); }
        .hoja { max-width:640px; margin:0 auto; background:#fff; border-radius:16px; box-shadow:0 12px 40px rgba(59,42,160,.14); overflow:hidden; }
        .head { background:linear-gradient(135deg,var(--azul),var(--azul-osc)); color:#fff; padding:26px 34px; display:flex; align-items:center; gap:16px; }
        .head img { width:60px; height:60px; border-radius:50%; background:#fff; padding:4px; }
        .head .t h1 { font-family:"Fredoka",sans-serif; font-size:1.3rem; line-height:1.1; }
        .head .t small { opacity:.9; }
        .head .folio { margin-left:auto; text-align:right; }
        .head .folio span { display:block; font-size:.75rem; opacity:.8; }
        .head .folio strong { font-family:"Fredoka",sans-serif; font-size:1.2rem; }
        .tipo { text-align:center; padding:16px; font-family:"Fredoka",sans-serif; font-weight:600; letter-spacing:.1em; text-transform:uppercase; color:var(--azul); border-bottom:1px solid var(--borde); }
        .body { padding:28px 34px; }
        .fila { display:flex; justify-content:space-between; padding:12px 0; border-bottom:1px dashed var(--borde); }
        .fila .k { color:var(--suave); font-size:.9rem; }
        .fila .v { font-weight:700; text-align:right; }
        .monto { margin-top:22px; padding:20px; border-radius:12px; background:var(--borde); text-align:center; }
        .monto span { display:block; color:var(--suave); font-size:.85rem; }
        .monto strong { font-family:"Fredoka",sans-serif; font-size:2.2rem; color:<?= $esIngreso ? 'var(--verde)' : 'var(--rojo)' ?>; }
        .firma { margin-top:40px; display:flex; justify-content:space-around; gap:30px; }
        .firma div { flex:1; text-align:center; border-top:1px solid var(--texto); padding-top:8px; font-size:.85rem; color:var(--suave); }
        .pie { text-align:center; color:var(--suave); font-size:.78rem; padding:18px 34px 26px; }
        @media print {
            body { background:#fff; padding:0; }
            .barra { display:none; }
            .hoja { box-shadow:none; border-radius:0; max-width:100%; }
        }
    </style>
</head>
<body>
    <div class="barra">
        <a class="btn btn-g" href="<?= $base ?>/<?= $permiso === 1 ? 'administrador/recibos' : 'padre' ?>">← Volver</a>
        <button class="btn btn-p" onclick="window.print()">🖨️ Imprimir / Guardar PDF</button>
    </div>

    <div class="hoja">
        <div class="head">
            <img src="<?= $base ?>/webService/wwwroot/img/logo.png" alt="Logo">
            <div class="t">
                <h1>Colegio del Bosque</h1>
                <small>Comprobante interno de pago</small>
            </div>
            <div class="folio">
                <span>Folio</span>
                <strong>#<?= $folio ?></strong>
            </div>
        </div>

        <div class="tipo"><?= $esIngreso ? 'Recibo de pago' : 'Comprobante de egreso' ?> · <?= htmlspecialchars($r['tipo_nombre'] ?? 'General') ?></div>

        <div class="body">
            <div class="fila"><span class="k">Fecha</span><span class="v"><?= $fecha ?></span></div>
            <div class="fila"><span class="k"><?= $etiquetaDest ?></span><span class="v"><?= htmlspecialchars($destinatario) ?></span></div>
            <div class="fila"><span class="k">Concepto</span><span class="v"><?= htmlspecialchars($concepto) ?></span></div>
            <div class="fila"><span class="k">Método de pago</span><span class="v"><?= htmlspecialchars($r['tipo_pago'] ?: '—') ?></span></div>

            <div class="monto">
                <span>Monto</span>
                <strong><?= $money($r['monto']) ?></strong>
            </div>
        </div>

        <div class="pie">Este documento es un comprobante interno del Colegio del Bosque · Folio #<?= $folio ?> · <?= $fecha ?></div>
    </div>
</body>
</html>
