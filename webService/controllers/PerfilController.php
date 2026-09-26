<?php

/**
 * Recurso: perfiles.  Ruta: /api/perfiles?action=catalogos|listar|crear|editar|eliminar
 * Panel del desarrollador (permiso 4): alta/edición/baja de cuentas de cualquier rol.
 */
class PerfilController
{
    private PerfilBusiness $perfiles;

    public function __construct(mysqli $conexion)
    {
        requireDev();
        $this->perfiles = new PerfilBusiness($conexion);
    }

    /** Roles con grupo (maestro=2, alumno=3). Admin/dev no llevan grupo. */
    private function rolLlevaGrupo(int $permiso): bool
    {
        return $permiso === 2 || $permiso === 3;
    }

    public function catalogos(): void
    {
        response(200, true, 'Catálogos.', [
            'permisos' => $this->perfiles->permisos(),
            'grupos'   => $this->perfiles->grupos(),
            'estatus'  => $this->perfiles->estatusAlumno(),
        ]);
    }

    public function listar(): void
    {
        $filtro = (int) ($_GET['permiso_id'] ?? 0);
        response(200, true, 'Perfiles obtenidos.', ['items' => $this->perfiles->listar($filtro)]);
    }

    public function crear(): void
    {
        $nombre   = trim($_POST['nombre'] ?? '');
        $paterno  = trim($_POST['paterno'] ?? '');
        $materno  = trim($_POST['materno'] ?? '');
        $mat      = trim($_POST['matricula'] ?? '');
        $permiso  = (int) ($_POST['permiso_id'] ?? 0);
        $grupo    = $this->rolLlevaGrupo($permiso) ? (int) ($_POST['grupo_id'] ?? 0) : 0;
        $claveEst = trim($_POST['estatus'] ?? 'activo');

        if ($nombre === '' || $paterno === '') response(400, false, 'Nombre y apellido paterno son obligatorios.');
        if (strlen($mat) < 4)                  response(400, false, 'La matrícula debe tener al menos 4 caracteres.');
        if (!$this->perfiles->permisoExiste($permiso)) response(400, false, 'Rol (permiso) no válido.');
        if ($this->perfiles->matriculaExiste($mat))    response(409, false, 'La matrícula ya existe.');

        if (!$this->perfiles->crear($nombre, $paterno, $materno, $mat, $permiso, $grupo, $claveEst)) {
            response(500, false, 'No se pudo crear la cuenta.');
        }
        response(201, true, 'Perfil creado.');
    }

    public function editar(): void
    {
        $id       = (int) ($_POST['id_cuenta'] ?? 0);
        $nombre   = trim($_POST['nombre'] ?? '');
        $paterno  = trim($_POST['paterno'] ?? '');
        $materno  = trim($_POST['materno'] ?? '');
        $mat      = trim($_POST['matricula'] ?? '');
        $permiso  = (int) ($_POST['permiso_id'] ?? 0);
        $grupo    = $this->rolLlevaGrupo($permiso) ? (int) ($_POST['grupo_id'] ?? 0) : 0;
        $claveEst = trim($_POST['estatus'] ?? 'activo');

        if ($id <= 0)                          response(400, false, 'Registro no válido.');
        if ($nombre === '' || $paterno === '') response(400, false, 'Nombre y apellido paterno son obligatorios.');
        if (strlen($mat) < 4)                  response(400, false, 'La matrícula debe tener al menos 4 caracteres.');

        $usuarioId = $this->perfiles->obtenerUsuarioId($id);
        if ($usuarioId === null) response(404, false, 'No se encontró la cuenta.');

        if ($this->perfiles->matriculaExiste($mat, $id)) response(409, false, 'La matrícula ya existe en otra cuenta.');

        if (!$this->perfiles->editar($id, $usuarioId, $nombre, $paterno, $materno, $mat, $permiso, $grupo, $claveEst)) {
            response(500, false, 'No se pudo actualizar la cuenta.');
        }
        response(200, true, 'Perfil actualizado.');
    }

    public function eliminar(): void
    {
        $id = (int) ($_POST['id_cuenta'] ?? 0);
        if ($id <= 0) response(400, false, 'Registro no válido.');

        $usuarioId = $this->perfiles->obtenerUsuarioId($id);
        if ($usuarioId === null) response(404, false, 'No se encontró el registro.');

        if ($usuarioId === (int) ($_SESSION['id'] ?? 0)) response(409, false, 'No puedes eliminar tu propia cuenta.');

        if (!$this->perfiles->eliminarCascada($id, $usuarioId)) {
            response(500, false, 'No se pudo eliminar: hay datos relacionados que lo impiden.');
        }
        response(200, true, 'Perfil y sus datos relacionados eliminados.');
    }
}
