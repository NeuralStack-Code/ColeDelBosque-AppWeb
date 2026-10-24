<?php
/**
 * Recibo de pago imprimible. Lo pinta ReciboController::imprimir() (que ya revisó quién lo puede ver).
 * Recibe $base, $recibo (model Recibo) y $volver (a dónde regresa el botón).
 */
$folio = $recibo->folio();
$fecha = date('d/m/Y H:i', strtotime($recibo->Fecha));
$esIngreso = $recibo->esIngreso();
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
        <a class="btn btn-g" href="<?= $base . $volver ?>">← Volver</a>
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

        <div class="tipo"><?= $esIngreso ? 'Recibo de pago' : 'Comprobante de egreso' ?> · <?= e($recibo->TipoNombre ?? 'General') ?></div>

        <div class="body">
            <div class="fila"><span class="k">Fecha</span><span class="v"><?= $fecha ?></span></div>
            <div class="fila"><span class="k"><?= e($recibo->etiquetaDestinatario()) ?></span><span class="v"><?= e($recibo->Destinatario) ?></span></div>
            <div class="fila"><span class="k">Concepto</span><span class="v"><?= e($recibo->concepto()) ?></span></div>
            <div class="fila"><span class="k">Método de pago</span><span class="v"><?= e($recibo->TipoPago ?: '—') ?></span></div>

            <div class="monto">
                <span>Monto</span>
                <strong><?= money($recibo->Monto) ?></strong>
            </div>
        </div>

        <div class="pie">Este documento es un comprobante interno del Colegio del Bosque · Folio #<?= $folio ?> · <?= $fecha ?></div>
    </div>
</body>
</html>
