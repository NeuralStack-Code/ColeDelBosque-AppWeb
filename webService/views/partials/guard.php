<?php
/**
 * Guard de vistas por rol.
 * Uso (antes de renderizar la vista):
 *   $rol = 2; require __DIR__ . '/../partials/guard.php';
 *
 * Redirige a /inicio-sesion si no hay sesión o el permiso no coincide.
 * Roles: 1 = admin · 2 = maestro · 3 = padre/alumno
 */
if (!defined('BASE_URL')) require_once __DIR__ . '/../../../apiService/core/config.php';
if (session_status() !== PHP_SESSION_ACTIVE) session_start();

$permisoActual = (int) ($_SESSION['permiso'] ?? 0);
$rolRequerido  = (int) ($rol ?? 0);

if (!isset($_SESSION['usuario']) || $permisoActual !== $rolRequerido) {
    header('Location: ' . BASE_URL . '/inicio-sesion');
    exit;
}
