<?php
require_once dirname(__DIR__, 3) . '/apiService/core/crypto.php';

/**
 * Reporte semanal a papás: clases por día (grupo+materia+fecha) e incidencias por
 * alumno. Aquí vive el SQL. Todo acotado al grupo del maestro (el controller valida).
 */
class ReporteBusiness
{
    private mysqli $db;

    public function __construct(mysqli $db)
    {
        $this->db = $db;
    }

    /** Alumnos del grupo; `tiene_correo` indica si hay tutor a quien enviarle. */
    public function alumnosDeGrupo(int $grupoId): array
    {
        $stmt = mysqli_prepare($this->db,
            'SELECT c.id_cuenta, u.nombre, u.paterno, u.materno, c.correo_tutor, c.correo_tutor2
             FROM cuenta c JOIN usuario u ON u.id_usuario = c.usuario_id
             WHERE c.grupo_id = ? AND c.permiso_id = 3
             ORDER BY u.paterno, u.nombre');
        mysqli_stmt_bind_param($stmt, 'i', $grupoId);
        mysqli_stmt_execute($stmt);
        $res = mysqli_stmt_get_result($stmt);
        $alumnos = [];
        while ($r = mysqli_fetch_assoc($res)) {
            $alumnos[] = [
                'id_cuenta'       => (int) $r['id_cuenta'],
                'nombre_completo' => trim("$r[nombre] $r[paterno] $r[materno]"),
                'tiene_correo'    => !empty($r['correo_tutor']) || !empty($r['correo_tutor2']),
            ];
        }
        mysqli_stmt_close($stmt);
        return $alumnos;
    }

    /** Correos de los tutores del alumno, desencriptados. */
    public function correosTutor(int $cuentaId): array
    {
        $stmt = mysqli_prepare($this->db, 'SELECT correo_tutor, correo_tutor2 FROM cuenta WHERE id_cuenta = ? LIMIT 1');
        mysqli_stmt_bind_param($stmt, 'i', $cuentaId);
        mysqli_stmt_execute($stmt);
        $row = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt)) ?: [];
        mysqli_stmt_close($stmt);

        $correos = [];
        foreach (['correo_tutor', 'correo_tutor2'] as $campo) {
            if (empty($row[$campo])) continue;
            $c = (string) decrypt($row[$campo]);
            if ($c !== '') $correos[] = $c;
        }
        return array_values(array_unique($correos));
    }

    /** Clases capturadas en el rango, con cuántos alumnos tuvieron incidencia. */
    public function clasesDeSemana(int $grupoId, string $desde, string $hasta): array
    {
        $stmt = mysqli_prepare($this->db,
            'SELECT rc.id_clase, rc.fecha, rc.materia_id, m.nombre AS materia, rc.tema,
                    (SELECT COUNT(DISTINCT rm.cuenta_id) FROM reporte_marca rm WHERE rm.clase_id = rc.id_clase) AS incidencias
             FROM reporte_clase rc
             JOIN materia m ON m.id_materia = rc.materia_id
             WHERE rc.grupo_id = ? AND rc.fecha BETWEEN ? AND ?
             ORDER BY rc.fecha, rc.id_clase');
        mysqli_stmt_bind_param($stmt, 'iss', $grupoId, $desde, $hasta);
        mysqli_stmt_execute($stmt);
        $res = mysqli_stmt_get_result($stmt);
        $clases = [];
        while ($r = mysqli_fetch_assoc($res)) $clases[] = $r;
        mysqli_stmt_close($stmt);
        return $clases;
    }

    /** Clase de (grupo, materia, día) o null si aún no se captura. */
    public function claseObtener(int $grupoId, int $materiaId, string $fecha): ?array
    {
        $stmt = mysqli_prepare($this->db,
            'SELECT id_clase, tema FROM reporte_clase WHERE grupo_id = ? AND materia_id = ? AND fecha = ? LIMIT 1');
        mysqli_stmt_bind_param($stmt, 'iis', $grupoId, $materiaId, $fecha);
        mysqli_stmt_execute($stmt);
        $row = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
        mysqli_stmt_close($stmt);
        return $row ?: null;
    }

    public function marcasDeClase(int $claseId): array
    {
        $stmt = mysqli_prepare($this->db, 'SELECT cuenta_id, columna_id, marcado, nota FROM reporte_marca WHERE clase_id = ?');
        mysqli_stmt_bind_param($stmt, 'i', $claseId);
        mysqli_stmt_execute($stmt);
        $res = mysqli_stmt_get_result($stmt);
        $marcas = [];
        while ($r = mysqli_fetch_assoc($res)) $marcas[] = $r;
        mysqli_stmt_close($stmt);
        return $marcas;
    }

    /**
     * Inserta o actualiza la clase y REEMPLAZA sus incidencias.
     * @param array $marcas [['cuenta_id'=>int,'columna_id'=>int,'marcado'=>0|1,'nota'=>?string], ...]
     */
    public function guardarClase(int $grupoId, int $materiaId, int $cicloId, string $fecha, string $tema, array $marcas): bool
    {
        try {
            mysqli_begin_transaction($this->db);

            // LAST_INSERT_ID(id_clase) hace que insert_id sirva también cuando ya existía
            $c = mysqli_prepare($this->db,
                'INSERT INTO reporte_clase (grupo_id, materia_id, ciclo_id, fecha, tema) VALUES (?, ?, ?, ?, ?)
                 ON DUPLICATE KEY UPDATE tema = VALUES(tema), id_clase = LAST_INSERT_ID(id_clase)');
            mysqli_stmt_bind_param($c, 'iiiss', $grupoId, $materiaId, $cicloId, $fecha, $tema);
            mysqli_stmt_execute($c);
            $claseId = (int) mysqli_insert_id($this->db);
            mysqli_stmt_close($c);

            $d = mysqli_prepare($this->db, 'DELETE FROM reporte_marca WHERE clase_id = ?');
            mysqli_stmt_bind_param($d, 'i', $claseId);
            mysqli_stmt_execute($d);
            mysqli_stmt_close($d);

            $ins = mysqli_prepare($this->db, 'INSERT INTO reporte_marca (clase_id, cuenta_id, columna_id, marcado, nota) VALUES (?, ?, ?, ?, ?)');
            foreach ($marcas as $m) {
                mysqli_stmt_bind_param($ins, 'iiiis', $claseId, $m['cuenta_id'], $m['columna_id'], $m['marcado'], $m['nota']);
                mysqli_stmt_execute($ins);
            }
            mysqli_stmt_close($ins);

            mysqli_commit($this->db);
            return true;
        } catch (\Throwable $e) {
            mysqli_rollback($this->db);
            error_log('Reporte guardarClase: ' . $e->getMessage());
            return false;
        }
    }

    /** Materia de una clase del grupo (0 si no existe). */
    public function materiaDeClase(int $claseId, int $grupoId): int
    {
        $stmt = mysqli_prepare($this->db, 'SELECT materia_id FROM reporte_clase WHERE id_clase = ? AND grupo_id = ? LIMIT 1');
        mysqli_stmt_bind_param($stmt, 'ii', $claseId, $grupoId);
        mysqli_stmt_execute($stmt);
        $row = mysqli_fetch_row(mysqli_stmt_get_result($stmt));
        mysqli_stmt_close($stmt);
        return (int) ($row[0] ?? 0);
    }

    /** @return int filas afectadas (0 = no existía o no es del grupo). Las marcas caen por FK. */
    public function eliminarClase(int $claseId, int $grupoId): int
    {
        $stmt = mysqli_prepare($this->db, 'DELETE FROM reporte_clase WHERE id_clase = ? AND grupo_id = ?');
        mysqli_stmt_bind_param($stmt, 'ii', $claseId, $grupoId);
        mysqli_stmt_execute($stmt);
        $af = mysqli_stmt_affected_rows($stmt);
        mysqli_stmt_close($stmt);
        return $af;
    }

    public function nombreAlumno(int $cuentaId): string
    {
        $stmt = mysqli_prepare($this->db,
            'SELECT u.nombre, u.paterno, u.materno FROM cuenta c JOIN usuario u ON u.id_usuario = c.usuario_id WHERE c.id_cuenta = ? LIMIT 1');
        mysqli_stmt_bind_param($stmt, 'i', $cuentaId);
        mysqli_stmt_execute($stmt);
        $r = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
        mysqli_stmt_close($stmt);
        return $r ? trim("$r[nombre] $r[paterno] $r[materno]") : '';
    }

    /**
     * Clases de la semana vistas por UN alumno: cada clase con sus incidencias
     * indexadas por columna → ['fecha','materia','tema','marcas'=>[columna_id=>['marcado','nota']]].
     */
    public function reporteAlumno(int $cuentaId, int $grupoId, string $desde, string $hasta): array
    {
        $stmt = mysqli_prepare($this->db,
            'SELECT rc.id_clase, rc.fecha, m.nombre AS materia, rc.tema, rm.columna_id, rm.marcado, rm.nota
             FROM reporte_clase rc
             JOIN materia m ON m.id_materia = rc.materia_id
             LEFT JOIN reporte_marca rm ON rm.clase_id = rc.id_clase AND rm.cuenta_id = ?
             WHERE rc.grupo_id = ? AND rc.fecha BETWEEN ? AND ?
             ORDER BY rc.fecha, rc.id_clase');
        mysqli_stmt_bind_param($stmt, 'iiss', $cuentaId, $grupoId, $desde, $hasta);
        mysqli_stmt_execute($stmt);
        $res = mysqli_stmt_get_result($stmt);
        $clases = [];
        while ($r = mysqli_fetch_assoc($res)) {
            $id = (int) $r['id_clase'];
            if (!isset($clases[$id])) {
                $clases[$id] = ['fecha' => $r['fecha'], 'materia' => $r['materia'], 'tema' => $r['tema'], 'marcas' => []];
            }
            if ($r['columna_id'] !== null) {
                $clases[$id]['marcas'][(int) $r['columna_id']] = ['marcado' => (int) $r['marcado'], 'nota' => $r['nota']];
            }
        }
        mysqli_stmt_close($stmt);
        return array_values($clases);
    }

    /** cuenta_id → fecha/hora del último envío de esa semana. */
    public function enviosDeSemana(int $grupoId, string $lunes): array
    {
        $stmt = mysqli_prepare($this->db,
            'SELECT e.cuenta_id, e.enviado_en FROM reporte_envio e
             JOIN cuenta c ON c.id_cuenta = e.cuenta_id
             WHERE c.grupo_id = ? AND e.semana = ?');
        mysqli_stmt_bind_param($stmt, 'is', $grupoId, $lunes);
        mysqli_stmt_execute($stmt);
        $res = mysqli_stmt_get_result($stmt);
        $envios = [];
        while ($r = mysqli_fetch_assoc($res)) $envios[(int) $r['cuenta_id']] = $r['enviado_en'];
        mysqli_stmt_close($stmt);
        return $envios;
    }

    public function registrarEnvio(int $cuentaId, string $lunes, string $enviadoEn, int $usuarioId, int $destinatarios): void
    {
        $stmt = mysqli_prepare($this->db,
            'INSERT INTO reporte_envio (cuenta_id, semana, enviado_en, enviado_por, destinatarios) VALUES (?, ?, ?, ?, ?)
             ON DUPLICATE KEY UPDATE enviado_en = VALUES(enviado_en), enviado_por = VALUES(enviado_por), destinatarios = VALUES(destinatarios)');
        mysqli_stmt_bind_param($stmt, 'issii', $cuentaId, $lunes, $enviadoEn, $usuarioId, $destinatarios);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);
    }
}
