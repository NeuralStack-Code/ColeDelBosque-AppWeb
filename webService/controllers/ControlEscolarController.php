<?php

/**
 * Recurso: control-escolar.  Ruta: /api/control-escolar?action=...
 * CRUD de Alumnos (3), Maestros (2) y Grupos/Materias (solo admin).
 */
class ControlEscolarController
{
    private ControlEscolarBusiness $ce;

    public function __construct(mysqli $conexion)
    {
        requireAdmin();
        $this->ce = new ControlEscolarBusiness($conexion);
    }

    /* ---------------- Helpers de validación ---------------- */

    private function validarNombres(string $nombre, string $paterno, string $materno): void
    {
        foreach (['nombre' => $nombre, 'apellido paterno' => $paterno] as $campo => $val) {
            if ($val === '')      response(400, false, "El $campo no puede estar vacío.");
            if (is_numeric($val)) response(400, false, "Revisa el $campo.");
        }
        // El apellido materno NUNCA es obligatorio (regla del colegio); solo se revisa si viene
        if (is_numeric($materno)) response(400, false, 'Revisa el apellido materno.');
    }

    /** La matrícula debe ser 3 mayúsculas + 6 números (AAA######). */
    private function matriculaValida(string $m): bool
    {
        return (bool) preg_match('/^[A-Z]{3}[0-9]{6}$/', $m);
    }

    /** Correo de tutor opcional: '' o un correo válido (a él llega el reporte semanal). */
    private function correoTutor(string $campo): string
    {
        $c = trim($_POST[$campo] ?? '');
        if ($c === '') return '';
        if (strlen($c) > 100 || !filter_var($c, FILTER_VALIDATE_EMAIL)) response(400, false, 'Revisa el correo del tutor.');
        return strtolower($c);
    }

    private function crearPersona(int $permiso): void
    {
        $nombre  = trim($_POST['nombre']  ?? '');
        $paterno = trim($_POST['paterno'] ?? '');
        $materno = trim($_POST['materno'] ?? '');
        $grupo   = (int) ($_POST['grado'] ?? 0);
        $mat     = trim($_POST['matricula'] ?? '');

        if ($grupo <= 0) response(400, false, 'Selecciona un grupo válido.');
        $this->validarNombres($nombre, $paterno, $materno);
        if (!$this->matriculaValida($mat)) response(400, false, 'La matrícula debe tener 3 mayúsculas y 6 números (AAA######).');
        if ($this->ce->matriculaExiste($mat)) response(409, false, 'La matrícula ya existe. Cambia algún dígito e inténtalo de nuevo.');
        $correo1 = $permiso === 3 ? $this->correoTutor('correo_tutor')  : '';
        $correo2 = $permiso === 3 ? $this->correoTutor('correo_tutor2') : '';

        if (!$this->ce->crearPersona($nombre, $paterno, $materno, $grupo, $mat, $permiso)) {
            response(500, false, 'No se pudo crear la cuenta.');
        }
        $etiqueta = $permiso === 2 ? 'maestro' : 'alumno';
        $msg = "¡Se ha registrado un nuevo $etiqueta!";
        if ($correo1 !== '' || $correo2 !== '') {
            $idCuenta = $this->ce->idCuentaPorMatricula($mat);
            $this->ce->guardarCorreosTutor($idCuenta, $correo1, $correo2);
            // Alta con correo: la familia recibe su matrícula de una vez
            $msg .= $this->mandarMatricula($idCuenta) > 0
                ? ' Se envió la matrícula al correo del tutor.'
                : ' No se pudo enviar la matrícula por correo; reenvíala desde la lista.';
        }
        response(201, true, $msg);
    }

    /** Envía la matrícula a los tutores. @return int correos enviados (-1 = sin correo registrado). */
    private function mandarMatricula(int $idCuenta): int
    {
        $a = $this->ce->alumnoParaCorreo($idCuenta);
        if (!$a) response(404, false, 'No se encontró al alumno.');
        if (!$a['correos']) return -1;

        $r = (new MatriculaCorreoBusiness())->enviar($a['correos'], $a['nombre'], $a['matricula']);
        if ($r['errores']) error_log('Matrícula no enviada: ' . implode(' | ', $r['errores']));
        return $r['enviados'];
    }

    /** Botón "enviar matrícula" de la lista de alumnos. */
    public function alumno_enviar_matricula(): void
    {
        $id = (int) ($_POST['id_cuenta'] ?? 0);
        if ($id <= 0) response(400, false, 'Registro no válido.');

        $enviados = $this->mandarMatricula($id);
        if ($enviados === -1) response(409, false, 'El alumno no tiene correo de tutor. Agrégalo en su ficha.');
        if ($enviados === 0)  response(502, false, 'No se pudo enviar el correo.');
        response(200, true, 'Matrícula enviada al correo del tutor.');
    }

    private function editarPersona(int $permiso): void
    {
        $id      = (int) ($_POST['id_cuenta'] ?? 0);
        $nombre  = trim($_POST['nombre']  ?? '');
        $paterno = trim($_POST['paterno'] ?? '');
        $materno = trim($_POST['materno'] ?? '');
        $grado   = (int) ($_POST['grado'] ?? 0);   // 0 = no cambiar grupo
        $mat     = trim($_POST['matricula'] ?? '');

        if ($id <= 0) response(400, false, 'Registro no válido.');
        $this->validarNombres($nombre, $paterno, $materno);
        if (!$this->matriculaValida($mat)) response(400, false, 'La matrícula debe tener 3 mayúsculas y 6 números (AAA######).');

        $usuarioId = $this->ce->obtenerUsuarioId($id);
        if ($usuarioId === null) response(404, false, 'No se encontró la cuenta.');
        $correo1 = $permiso === 3 ? $this->correoTutor('correo_tutor')  : '';
        $correo2 = $permiso === 3 ? $this->correoTutor('correo_tutor2') : '';

        if (!$this->ce->editarPersona($id, $usuarioId, $nombre, $paterno, $materno, $grado, $mat)) {
            response(500, false, 'No se pudo actualizar la cuenta.');
        }
        if ($permiso === 3) $this->ce->guardarCorreosTutor($id, $correo1, $correo2);
        response(200, true, 'Se ha actualizado la información.');
    }

    private function eliminarPersona(): void
    {
        $id = (int) ($_POST['id_cuenta'] ?? 0);
        if ($id <= 0) response(400, false, 'Registro no válido.');

        $usuarioId = $this->ce->obtenerUsuarioId($id);
        if ($usuarioId === null) response(404, false, 'No se encontró el registro.');

        if (!$this->ce->eliminarCuentaEnCascada($id, $usuarioId)) {
            response(500, false, 'No se pudo eliminar: hay datos relacionados que lo impiden.');
        }
        response(200, true, 'Se ha eliminado el registro y sus datos relacionados.');
    }

    /* ---------------- Alumnos (permiso 3) ---------------- */
    public function alumnos_listar(): void  { response(200, true, 'Listado obtenido.', ['items' => $this->ce->listarPersonas(3)]); }
    public function alumno_crear(): void    { $this->crearPersona(3); }
    public function alumno_editar(): void   { $this->editarPersona(3); }
    public function alumno_eliminar(): void { $this->eliminarPersona(); }

    /* ---------------- Maestros (permiso 2) ---------------- */
    public function maestros_listar(): void  { response(200, true, 'Listado obtenido.', ['items' => $this->ce->listarPersonas(2)]); }
    public function maestro_crear(): void    { $this->crearPersona(2); }
    public function maestro_editar(): void   { $this->editarPersona(2); }
    public function maestro_eliminar(): void { $this->eliminarPersona(); }

    /* ---------------- Grupos ---------------- */
    public function grupos_listar(): void
    {
        $cicloFiltro = (int) ($_GET['ciclo_id'] ?? 0);
        response(200, true, 'Grupos obtenidos.', ['items' => $this->ce->gruposListar($cicloFiltro)]);
    }

    public function grupo_detalle(): void
    {
        $gid = (int) ($_GET['id_grupo'] ?? 0);
        if ($gid <= 0) response(400, false, 'Grupo no válido.');

        $grupo = $this->ce->grupoBasico($gid);
        if (!$grupo) response(404, false, 'No se encontró el grupo.');

        response(200, true, 'Detalle del grupo.', [
            'grupo'    => $grupo,
            'materias' => $this->ce->materiasDeGrupo($gid),
            'catalogo' => $this->ce->catalogoMaterias(),
            'maestros' => $this->ce->maestrosParaSelect(),
            'ciclos'   => $this->ce->ciclosParaSelect(),
        ]);
    }

    public function grupo_crear(): void
    {
        $grado   = trim($_POST['grado'] ?? '');
        $cicloId = (int) ($_POST['ciclo_id'] ?? 0) ?: null;
        $nivel   = ($_POST['nivel'] ?? '') === '' ? null : (int) $_POST['nivel'];
        $materiaIds = $this->ce->resolverMaterias(
            json_decode($_POST['materia_ids'] ?? '[]', true) ?: [],
            json_decode($_POST['materias_nuevas'] ?? '[]', true) ?: []
        );
        if ($grado === '')      response(400, false, 'Indica el grado del grupo.');
        if (empty($materiaIds)) response(400, false, 'Agrega al menos una materia.');
        if ($this->ce->gradoExisteEnCiclo($grado, $cicloId)) response(409, false, 'Ya existe ese grupo en el ciclo seleccionado.');

        $grupoId = $this->ce->grupoCrear($grado, $cicloId, $nivel);
        if ($grupoId <= 0) response(500, false, 'No se pudo crear el grupo.');

        $this->ce->sincronizarMateriasGrupo($grupoId, $materiaIds);
        response(201, true, '¡Se ha creado el grupo y sus materias!');
    }

    public function grupo_editar(): void
    {
        $grupoId = (int) ($_POST['id_grupo'] ?? 0);
        if ($grupoId <= 0) response(400, false, 'Grupo no válido.');
        $cambios = 0;

        $nuevoGrado = trim($_POST['grado'] ?? '');
        if ($nuevoGrado !== '') { $this->ce->grupoSetGrado($grupoId, $nuevoGrado); $cambios++; }

        $maestraId = $_POST['maestra_id'] ?? null;
        if ($maestraId !== null && $maestraId !== '') { $this->ce->grupoSetMaestra($grupoId, (int) $maestraId); $cambios++; }

        if (isset($_POST['nivel']) && $_POST['nivel'] !== '')       { $this->ce->grupoSetNivel($grupoId, (int) $_POST['nivel']); $cambios++; }
        if (isset($_POST['ciclo_id']) && $_POST['ciclo_id'] !== '') { $this->ce->grupoSetCiclo($grupoId, (int) $_POST['ciclo_id']); $cambios++; }

        if (isset($_POST['materia_ids']) || isset($_POST['materias_nuevas'])) {
            $materiaIds = $this->ce->resolverMaterias(
                json_decode($_POST['materia_ids'] ?? '[]', true) ?: [],
                json_decode($_POST['materias_nuevas'] ?? '[]', true) ?: []
            );
            $this->ce->sincronizarMateriasGrupo($grupoId, $materiaIds);
            $cambios++;
        }

        if ($cambios === 0) response(400, false, 'No hay cambios que aplicar.');
        response(200, true, 'Se han aplicado los cambios al grupo.');
    }

    public function grupo_eliminar(): void
    {
        $grupoId = (int) ($_POST['id_grupo'] ?? 0);
        if ($grupoId <= 0) response(400, false, 'Grupo no válido.');

        $nAlumnos = $this->ce->contarAlumnosDeGrupo($grupoId);
        $this->ce->moverAlumnosASinGrupo($grupoId);
        $af = $this->ce->grupoEliminar($grupoId);

        if ($af === 0) response(404, false, 'No se encontró el grupo.');
        $msg = 'Se ha eliminado el grupo y sus materias.';
        if ($nAlumnos > 0) $msg .= " $nAlumnos alumno(s) quedaron en «Sin grupo» — reasígnalos pronto.";
        response(200, true, $msg);
    }
}
