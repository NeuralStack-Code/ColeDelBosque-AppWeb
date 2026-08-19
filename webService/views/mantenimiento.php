<?php
if (!defined('BASE_URL')) require_once __DIR__ . '/../../apiService/core/config.php';
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sitio en Mantenimiento</title>
    <link href="https://fonts.googleapis.com/css2?family=Chewy&family=Martian+Mono:wght@400;600&display=swap" rel="stylesheet">
    <style>
        *,*::before,*::after{box-sizing:border-box;margin:0;padding:0;}
        body{min-height:100vh;display:flex;align-items:center;justify-content:center;
             background:var(--ns-color-bg,#f5f0e8);font-family:var(--ns-font-body,'Martian Mono',monospace);padding:2rem;}
        .card{background:var(--ns-color-white,#fff);max-width:560px;width:100%;padding:3rem 2.5rem;
              border:5px solid var(--ns-color-border,#3d2b1f);box-shadow:8px 8px 0 var(--ns-color-border,#3d2b1f);
              text-align:center;position:relative;}
        .card::before{content:'';position:absolute;top:-14px;left:15%;right:15%;height:24px;
              background:repeating-linear-gradient(45deg,var(--ns-warning,#f4c430) 0 15px,var(--ns-success,#4a7c59) 15px 30px,var(--ns-error,#c0392b) 30px 45px);
              opacity:.8;}
        h1{font-family:var(--ns-font-display,'Chewy',cursive);font-size:2.8rem;color:var(--ns-color-text,#3d2b1f);margin:1rem 0 .75rem;}
        p{color:var(--ns-color-text,#3d2b1f);opacity:.75;line-height:1.7;margin-bottom:2rem;}
        hr{border:none;border-top:3px dashed #d4c4a8;margin:1.5rem 0;}
        .label{font-size:.75rem;text-transform:uppercase;letter-spacing:2px;opacity:.5;margin-bottom:1rem;}
        .nsc-card{background:var(--ns-color-text,#3d2b1f);color:var(--ns-color-white,#fff);
                  padding:1.25rem 1.5rem;display:flex;align-items:center;gap:1rem;text-decoration:none;}
        .nsc-card:hover{background:var(--ns-success,#4a7c59);}
        .nsc-logo{font-family:var(--ns-font-display,'Chewy',cursive);font-size:1.6rem;color:var(--ns-warning,#f4c430);flex-shrink:0;}
        .nsc-info{flex:1;} .nsc-name{font-weight:600;} .nsc-url{font-size:.78rem;opacity:.7;margin-top:.2rem;}
        .nsc-arrow{font-size:1.5rem;color:var(--ns-warning,#f4c430);}
    </style>
</head>
<body>
<div class="card">
    <div style="font-size:4rem;">🔧</div>
    <h1>Sitio en Mantenimiento</h1>
    <p>Estamos realizando mejoras para ofrecerte una mejor experiencia.<br>Por favor regresa más tarde.</p>
    <hr>
    <p class="label">¿Eres el administrador? Contacta a tu proveedor</p>
    <a href="https://neuralstackcode.com.mx" target="_blank" rel="noopener" class="nsc-card">
        <div class="nsc-logo">NS</div>
        <div class="nsc-info">
            <div class="nsc-name">NeuralStack Code</div>
            <div class="nsc-url">neuralstackcode.com.mx</div>
        </div>
        <div class="nsc-arrow">→</div>
    </a>
</div>
</body>
</html>