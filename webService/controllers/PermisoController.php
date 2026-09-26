<?php

/**
 * Recurso: permisos.  Ruta: /api/permisos?action=listar|crear|editar|eliminar
 * Catálogo de roles (solo desarrollador, permiso 4).
 */
class PermisoController
{
    private PermisoBusiness $permisos;

    public function __construct(mysqli $conexion)
    {
        requireDev();
        $this->permisos = new PermisoBusiness($conexion);
    }

    public function listar(): void
    {
        response(200, true, 'Permisos obtenidos.', ['items' => $this->permisos->listar()]);
    }

    public function crear(): void
    {
        $nombre = trim($_POST['nombre'] ?? '');
        if ($nombre === '') response(400, false, 'El nombre del rol es obligatorio.');
        if (!$this->permisos->crear($nombre)) response(500, false, 'No se pudo crear el rol.');
        response(201, true, 'Rol creado.');
    }

    public function editar(): void
    {
        $id     = (int) ($_POST['id_permiso'] ?? 0);
        $nombre = trim($_POST['nombre'] ?? '');
        if ($id <= 0)       response(400, false, 'Registro no válido.');
        if ($nombre === '') response(400, false, 'El nombre del rol es obligatorio.');
        if (!$this->permisos->editar($id, $nombre)) response(500, false, 'No se pudo actualizar el rol.');
        response(200, true, 'Rol actualizado.');
    }

    public function eliminar(): void
    {
        $id = (int) ($_POST['id_permiso'] ?? 0);
        if ($id <= 0) response(400, false, 'Registro no válido.');
        if ($id >= 1 && $id <= 4) response(409, false, 'No se pueden eliminar los roles base del sistema.');
        if ($this->permisos->tieneCuentas($id)) response(409, false, 'No se puede eliminar: hay cuentas con este rol.');
        $this->permisos->eliminar($id);
        response(200, true, 'Rol eliminado.');
    }
}
