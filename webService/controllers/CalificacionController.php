<?php

/**
 * Recurso: calificaciones.  Ruta: /api/calificaciones?action=contexto|listar|guardar
 * Notas por (alumno, materia, ciclo activo). Solo maestros (permiso 2), acotado a su grupo.
 */
class CalificacionController
{
    private CalificacionBusiness $cal;
    private int   $grupoId;
    private int   $cicloId;
    private array $ciclo;

    public function __construct(mysqli $conexion)
    {
        requireAuth();
        if ((int) ($_SESSION['permiso'] ?? 0) !== 2) {
            response(403, false, 'Solo los maestros pueden acceder a calificaciones.');
        }
        $this->cal = new CalificacionBusiness($conexion);

        $this->grupoId = $this->cal->grupoDelMaestro((int) ($_SESSION['id'] ?? 0));
        $ciclo         = $this->cal->cicloActivo();
        if ($this->grupoId <= 0) response(409, false, 'No tienes un grupo asignado. Contacta a la administración.');
        if (!$ciclo)             response(409, false, 'No hay un ciclo escolar activo. Pide al administrador que active uno.');
        $this->ciclo   = $ciclo;
        $this->cicloId = (int) $ciclo['id_ciclo'];
    }

    /** '' → null; si no, float validado 0–10. */
    private function nota($v): ?float
    {
        if ($v === '' || $v === null) return null;
        $n = filter_var($v, FILTER_VALIDATE_FLOAT);
        if ($n === false)      response(400, false, 'Las calificaciones deben ser números.');
        if ($n < 0 || $n > 10) response(400, false, 'Las calificaciones deben estar entre 0 y 10.');
        return (float) $n;
    }

    public function contexto(): void
    {
        response(200, true, 'Contexto obtenido.', [
            'grupo_id' => $this->grupoId,
            'grado'    => $this->cal->gradoDeGrupo($this->grupoId),
            'materias' => $this->cal->materiasDeGrupo($this->grupoId),
            'ciclo'    => $this->ciclo['nombre'],
        ]);
    }

    public function listar(): void
    {
        $materiaId = (int) ($_GET['materia_id'] ?? 0);
        if ($materiaId <= 0 || !$this->cal->materiaEnGrupo($materiaId, $this->grupoId)) {
            response(400, false, 'Materia no válida para tu grupo.');
        }
        response(200, true, 'Calificaciones obtenidas.', [
            'alumnos' => $this->cal->alumnosConNotas($materiaId, $this->cicloId, $this->grupoId),
        ]);
    }

    public function guardar(): void
    {
        $cuentaId  = (int) ($_POST['cuenta_id']  ?? 0);
        $materiaId = (int) ($_POST['materia_id'] ?? 0);
        if ($cuentaId <= 0) response(400, false, 'Alumno no válido.');
        if ($materiaId <= 0 || !$this->cal->materiaEnGrupo($materiaId, $this->grupoId)) {
            response(400, false, 'Materia no válida para tu grupo.');
        }
        if (!$this->cal->alumnoEnGrupo($cuentaId, $this->grupoId)) {
            response(403, false, 'Ese alumno no pertenece a tu grupo.');
        }

        $p1  = $this->nota($_POST['p1'] ?? '');
        $p2  = $this->nota($_POST['p2'] ?? '');
        $p3  = $this->nota($_POST['p3'] ?? '');
        $fin = $this->nota($_POST['final'] ?? '');
        $rep = trim($_POST['reporte'] ?? '');
        $rep = ($rep === '') ? null : $rep;

        if (!$this->cal->guardar($cuentaId, $materiaId, $this->cicloId, $p1, $p2, $p3, $fin, $rep)) {
            response(500, false, 'No se pudo guardar la calificación.');
        }
        response(200, true, 'Calificación guardada.');
    }
}
