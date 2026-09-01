<?php
/**
 * Middleware de autenticación por sesión.
 * requireAuth()  → cualquier usuario logueado
 * requireUser()  → usuario logueado que NO sea admin (permiso_id != 1)
 * requireAdmin() → solo admins (permiso_id == 1)
 */

function requireAuth(): void {
    if (!isset($_SESSION['usuario'])) {
        response(401, false, 'No autenticado.');
    }
}

function requireUser(): void {
    requireAuth();
    if ((int)($_SESSION['permiso'] ?? 0) === 1) {
        response(403, false, 'Los administradores no pueden realizar esta acción.');
    }
}

function requireAdmin(): void {
    requireAuth();
    if ((int)($_SESSION['permiso'] ?? 0) !== 1) {
        response(403, false, 'No tienes permisos de administrador.');
    }
}

function requireDev(): void {
    requireAuth();
    if ((int)($_SESSION['permiso'] ?? 0) !== 4) {
        response(403, false, 'Solo el desarrollador puede realizar esta acción.');
    }
}