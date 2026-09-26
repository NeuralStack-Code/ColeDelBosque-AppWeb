<?php
require_once dirname(__DIR__, 3) . '/apiService/core/crypto.php';

/**
 * Esquema de pagos por ciclo (inscripción + colegiaturas mensuales), pagos/abonos
 * que generan recibo, descuentos por catálogo. Aquí vive TODO el SQL.
 */
class ColegiaturaBusiness
{
    private const MESES = ['', 'Enero', 'Febrero', 'Marzo', 'Abril', 'Mayo', 'Junio', 'Julio', 'Agosto', 'Septiembre', 'Octubre', 'Noviembre', 'Diciembre'];

    private mysqli $db;

    public function __construct(mysqli $db)
    {
        $this->db = $db;
    }

    /** Recargo vigente (en vivo) para un pago no pagado y vencido. */
    public function recargoVigente(float $monto, float $pct, ?string $venc, string $estatus): float
    {
        if (strtolower($estatus) === 'pagado' || $pct <= 0 || !$venc) return 0.0;
        try { $hoy = new DateTime('today'); $v = new DateTime($venc); }
        catch (Exception $e) { return 0.0; }
        if ($v >= $hoy) return 0.0;
        $meses = ((int) $hoy->format('Y') - (int) $v->format('Y')) * 12 + ((int) $hoy->format('n') - (int) $v->format('n'));
        if ($meses < 1) $meses = 1;
        return round($monto * $pct / 100 * $meses, 2);
    }

    /** id del ciclo activo (0 si no hay). */
    public function cicloActivoId(): int
    {
        $row = mysqli_fetch_assoc(mysqli_query($this->db,
            "SELECT id_ciclo FROM ciclo WHERE status_id = (SELECT id_status FROM status WHERE ambito='ciclo' AND clave='activo') LIMIT 1"));
        return (int) ($row['id_ciclo'] ?? 0);
    }

    public function catalogos(): array
    {
        $ciclo   = mysqli_fetch_assoc(mysqli_query($this->db,
            "SELECT id_ciclo, nombre FROM ciclo WHERE status_id = (SELECT id_status FROM status WHERE ambito='ciclo' AND clave='activo') LIMIT 1"));
        $cicloId = (int) ($ciclo['id_ciclo'] ?? 0);

        $gr = [];
        $sqlG = 'SELECT id_grupo, grado FROM grupo WHERE id_grupo <> 0'
              . ($cicloId ? ' AND ciclo_id = ' . $cicloId : '') . ' ORDER BY nivel, grado';
        $res = mysqli_query($this->db, $sqlG);
        while ($r = mysqli_fetch_assoc($res)) $gr[] = $r;

        $al = [];
        $sqlA = 'SELECT c.id_cuenta, c.matricula, c.grupo_id, g.grado, u.nombre, u.paterno, u.materno
                 FROM cuenta c
                 JOIN usuario u ON u.id_usuario = c.usuario_id
                 JOIN grupo g   ON g.id_grupo = c.grupo_id
                 WHERE c.permiso_id = 3' . ($cicloId ? ' AND g.ciclo_id = ' . $cicloId : '') . '
                 ORDER BY u.paterno, u.nombre';
        $res = mysqli_query($this->db, $sqlA);
        while ($r = mysqli_fetch_assoc($res)) {
            $al[] = [
                'id_cuenta' => $r['id_cuenta'],
                'nombre'    => trim("$r[nombre] $r[paterno] $r[materno]"),
                'matricula' => decrypt($r['matricula']),
                'grupo_id'  => $r['grupo_id'],
                'grado'     => $r['grado'],
            ];
        }

        $desc = [];
        $res = mysqli_query($this->db,
            "SELECT id_descuento, nombre, porcentaje, aplica_a FROM tipo_descuento
             WHERE status_id = (SELECT id_status FROM status WHERE ambito='descuento' AND clave='activo') ORDER BY aplica_a, nombre");
        while ($r = mysqli_fetch_assoc($res)) $desc[] = $r;

        $est = [];
        $res = mysqli_query($this->db,
            "SELECT clave, nombre, color FROM status WHERE ambito = 'pago' ORDER BY orden");
        while ($r = mysqli_fetch_assoc($res)) $est[] = $r;

        return [
            'ciclo'      => $ciclo ?: null,
            'grupos'     => $gr,
            'alumnos'    => $al,
            'descuentos' => $desc,
            'estatus'    => $est,
        ];
    }

    public function listar(): array
    {
        $sql = 'SELECT col.*, c.grupo_id, u.nombre, u.paterno, u.materno, g.grado,
                       st.clave AS estatus, st.nombre AS estatus_nombre, st.color AS estatus_color,
                       (SELECT COALESCE(SUM(rr.monto),0) FROM recibo rr WHERE rr.colegiatura_id = col.id_pago) AS abonado
                FROM colegiatura col
                JOIN cuenta c  ON c.id_cuenta = col.cuenta_id
                JOIN usuario u ON u.id_usuario = c.usuario_id
                LEFT JOIN grupo g  ON g.id_grupo = c.grupo_id
                LEFT JOIN status st ON st.id_status = col.status_id
                ORDER BY u.paterno, u.nombre, col.tipo DESC, col.fecha_vencimiento ASC';
        $res = mysqli_query($this->db, $sql);
        $items = [];
        while ($r = mysqli_fetch_assoc($res)) {
            $monto = (float) $r['monto'];
            $desc  = (float) $r['descuento'];
            $recargo = strtolower($r['estatus']) === 'pagado'
                ? (float) $r['recargo']
                : $this->recargoVigente($monto, (float) $r['recargo_pct'], $r['fecha_vencimiento'], $r['estatus']);
            $abonado = (float) $r['abonado'];
            $total   = max(0, $monto + $recargo - $desc);
            $r['alumno']       = trim("$r[nombre] $r[paterno] $r[materno]");
            $r['recargo_calc'] = $recargo;
            $r['abonado']      = $abonado;
            $r['total']        = $total;
            $r['saldo']        = max(0, round($total - $abonado, 2));
            $items[] = $r;
        }
        return $items;
    }

    /** ¿El alumno está en un grupo del ciclo activo? */
    public function alumnoEnCicloActivo(int $cuentaId, int $cicloId): bool
    {
        $chk = mysqli_prepare($this->db,
            'SELECT c.id_cuenta FROM cuenta c JOIN grupo g ON g.id_grupo = c.grupo_id
             WHERE c.id_cuenta = ? AND c.permiso_id = 3 AND g.ciclo_id = ? LIMIT 1');
        mysqli_stmt_bind_param($chk, 'ii', $cuentaId, $cicloId);
        mysqli_stmt_execute($chk);
        $ok = (bool) mysqli_fetch_row(mysqli_stmt_get_result($chk));
        mysqli_stmt_close($chk);
        return $ok;
    }

    /** @return int[] id_cuenta de todos los alumnos del ciclo activo. */
    public function alumnosDelCicloActivo(int $cicloId): array
    {
        $alumnos = [];
        $res = mysqli_query($this->db,
            'SELECT c.id_cuenta FROM cuenta c JOIN grupo g ON g.id_grupo = c.grupo_id
             WHERE c.permiso_id = 3 AND g.ciclo_id = ' . $cicloId);
        while ($r = mysqli_fetch_row($res)) $alumnos[] = (int) $r[0];
        return $alumnos;
    }

    /** Genera inscripción + colegiaturas mensuales. @return array{creados:int,actualizados:int,saltados:int} */
    public function generarEsquema(array $alumnos, int $cicloId, float $montoCol, float $montoIns, int $anio, int $mesInicio, int $numMeses, int $diaVenc, float $recPct): array
    {
        $creados = 0; $actualizados = 0; $saltados = 0;
        $stPendiente = statusId($this->db, 'pago', 'pendiente');

        $chkIns = mysqli_prepare($this->db,
            'SELECT col.id_pago, s.clave AS estatus, col.monto FROM colegiatura col
             LEFT JOIN status s ON s.id_status = col.status_id
             WHERE col.cuenta_id = ? AND col.ciclo_id = ? AND col.tipo = "inscripcion" LIMIT 1');
        $insIns = mysqli_prepare($this->db,
            'INSERT INTO colegiatura (cuenta_id, ciclo_id, mes, tipo, monto, status_id, fecha_vencimiento, recargo_pct)
             VALUES (?, ?, "Inscripción", "inscripcion", ?, ?, ?, 0)');
        $updIns = mysqli_prepare($this->db, 'UPDATE colegiatura SET monto = ? WHERE id_pago = ?');

        $chkCol = mysqli_prepare($this->db,
            'SELECT col.id_pago, s.clave AS estatus, col.monto FROM colegiatura col
             LEFT JOIN status s ON s.id_status = col.status_id
             WHERE col.cuenta_id = ? AND col.ciclo_id = ? AND col.tipo = "colegiatura" AND col.mes = ? AND YEAR(col.fecha_vencimiento) = ? LIMIT 1');
        $insCol = mysqli_prepare($this->db,
            'INSERT INTO colegiatura (cuenta_id, ciclo_id, mes, tipo, monto, status_id, fecha_vencimiento, recargo_pct)
             VALUES (?, ?, ?, "colegiatura", ?, ?, ?, ?)');
        $updCol = mysqli_prepare($this->db, 'UPDATE colegiatura SET monto = ?, recargo_pct = ? WHERE id_pago = ?');

        foreach ($alumnos as $cid) {
            if ($montoIns > 0) {
                mysqli_stmt_bind_param($chkIns, 'ii', $cid, $cicloId);
                mysqli_stmt_execute($chkIns);
                $ex = mysqli_fetch_assoc(mysqli_stmt_get_result($chkIns));
                if (!$ex) {
                    $venc = sprintf('%04d-%02d-%02d', $anio, $mesInicio, $diaVenc);
                    mysqli_stmt_bind_param($insIns, 'iidis', $cid, $cicloId, $montoIns, $stPendiente, $venc);
                    if (mysqli_stmt_execute($insIns)) $creados++;
                } elseif (strtolower($ex['estatus']) !== 'pagado' && (float) $ex['monto'] != $montoIns) {
                    $eid = (int) $ex['id_pago'];
                    mysqli_stmt_bind_param($updIns, 'di', $montoIns, $eid);
                    mysqli_stmt_execute($updIns); $actualizados++;
                } else { $saltados++; }
            }

            for ($k = 0; $k < $numMeses; $k++) {
                $mn = ($mesInicio - 1 + $k) % 12 + 1;
                $an = $anio + intdiv($mesInicio - 1 + $k, 12);
                $mesNombre = self::MESES[$mn];
                $venc = sprintf('%04d-%02d-%02d', $an, $mn, $diaVenc);

                mysqli_stmt_bind_param($chkCol, 'iisi', $cid, $cicloId, $mesNombre, $an);
                mysqli_stmt_execute($chkCol);
                $ex = mysqli_fetch_assoc(mysqli_stmt_get_result($chkCol));
                if (!$ex) {
                    mysqli_stmt_bind_param($insCol, 'iisdisd', $cid, $cicloId, $mesNombre, $montoCol, $stPendiente, $venc, $recPct);
                    if (mysqli_stmt_execute($insCol)) $creados++;
                } elseif (strtolower($ex['estatus']) !== 'pagado' && (float) $ex['monto'] != $montoCol) {
                    $eid = (int) $ex['id_pago'];
                    mysqli_stmt_bind_param($updCol, 'ddi', $montoCol, $recPct, $eid);
                    mysqli_stmt_execute($updCol); $actualizados++;
                } else { $saltados++; }
            }
        }
        mysqli_stmt_close($chkIns); mysqli_stmt_close($insIns); mysqli_stmt_close($updIns);
        mysqli_stmt_close($chkCol); mysqli_stmt_close($insCol); mysqli_stmt_close($updCol);

        return ['creados' => $creados, 'actualizados' => $actualizados, 'saltados' => $saltados];
    }

    /** Datos de una colegiatura para procesar un pago (o null). */
    public function colegiaturaParaPago(int $id): ?array
    {
        $q = mysqli_prepare($this->db,
            'SELECT col.cuenta_id, col.mes, col.tipo, col.monto, s.clave AS estatus, col.recargo_pct, col.descuento, col.fecha_vencimiento
             FROM colegiatura col LEFT JOIN status s ON s.id_status = col.status_id WHERE col.id_pago = ? LIMIT 1');
        mysqli_stmt_bind_param($q, 'i', $id);
        mysqli_stmt_execute($q);
        $col = mysqli_fetch_assoc(mysqli_stmt_get_result($q));
        mysqli_stmt_close($q);
        return $col ?: null;
    }

    /** Total abonado a una colegiatura. */
    public function abonadoDe(int $id): float
    {
        $qa = mysqli_prepare($this->db, 'SELECT COALESCE(SUM(monto),0) FROM recibo WHERE colegiatura_id = ?');
        mysqli_stmt_bind_param($qa, 'i', $id);
        mysqli_stmt_execute($qa);
        $abonado = (float) (mysqli_fetch_row(mysqli_stmt_get_result($qa))[0] ?? 0);
        mysqli_stmt_close($qa);
        return $abonado;
    }

    public function tipoReciboId(string $nombre): ?int
    {
        $t = mysqli_prepare($this->db, 'SELECT id_tipo FROM tipo_recibo WHERE nombre = ? LIMIT 1');
        mysqli_stmt_bind_param($t, 's', $nombre);
        mysqli_stmt_execute($t);
        $row = mysqli_fetch_assoc(mysqli_stmt_get_result($t));
        mysqli_stmt_close($t);
        return $row ? (int) $row['id_tipo'] : null;
    }

    /** Inserta el recibo del abono; regresa su id (0 = falló). */
    public function crearReciboAbono(?int $tipoId, int $cuentaId, int $colegiaturaId, float $abono, string $pago, string $com): int
    {
        $nat = 'ingreso'; $dtipo = 'alumno'; $fecha = date('Y-m-d H:i:s');
        $ins = mysqli_prepare($this->db,
            'INSERT INTO recibo (tipo_recibo_id, naturaleza, destinatario_tipo, cuenta_id, colegiatura_id, monto, tipo_pago, comentario, fecha)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)');
        mysqli_stmt_bind_param($ins, 'issiidsss', $tipoId, $nat, $dtipo, $cuentaId, $colegiaturaId, $abono, $pago, $com, $fecha);
        $ok = mysqli_stmt_execute($ins);
        $id = $ok ? mysqli_insert_id($this->db) : 0;
        mysqli_stmt_close($ins);
        return $id;
    }

    /** Marca la colegiatura como saldada (congela recargo, liga recibo final). */
    public function marcarPagado(int $id, float $recargo, int $reciboId): void
    {
        $stPagado = statusId($this->db, 'pago', 'pagado');
        $up = mysqli_prepare($this->db,
            'UPDATE colegiatura SET status_id = ?, fecha_pago = CURDATE(), recargo = ?, recibo_id = ? WHERE id_pago = ?');
        mysqli_stmt_bind_param($up, 'idii', $stPagado, $recargo, $reciboId, $id);
        mysqli_stmt_execute($up);
        mysqli_stmt_close($up);
    }

    /** Marca la colegiatura como parcial (liga el último recibo). */
    public function marcarParcial(int $id, int $reciboId): void
    {
        $stParcial = statusId($this->db, 'pago', 'parcial');
        $up = mysqli_prepare($this->db, 'UPDATE colegiatura SET status_id = ?, recibo_id = ? WHERE id_pago = ?');
        mysqli_stmt_bind_param($up, 'iii', $stParcial, $reciboId, $id);
        mysqli_stmt_execute($up);
        mysqli_stmt_close($up);
    }

    /** Datos de una colegiatura para aplicar/quitar descuento (o null). */
    public function colegiaturaParaDescuento(int $id): ?array
    {
        $q = mysqli_prepare($this->db,
            'SELECT col.tipo, col.monto, s.clave AS estatus FROM colegiatura col
             LEFT JOIN status s ON s.id_status = col.status_id WHERE col.id_pago = ? LIMIT 1');
        mysqli_stmt_bind_param($q, 'i', $id);
        mysqli_stmt_execute($q);
        $col = mysqli_fetch_assoc(mysqli_stmt_get_result($q));
        mysqli_stmt_close($q);
        return $col ?: null;
    }

    public function quitarDescuento(int $id): void
    {
        $up = mysqli_prepare($this->db,
            'UPDATE colegiatura SET descuento = 0, tipo_descuento_id = NULL, concepto_descuento = NULL WHERE id_pago = ?');
        mysqli_stmt_bind_param($up, 'i', $id);
        mysqli_stmt_execute($up);
        mysqli_stmt_close($up);
    }

    /** Tipo de descuento activo (o null). */
    public function tipoDescuentoActivo(int $tdId): ?array
    {
        $td = mysqli_prepare($this->db,
            "SELECT nombre, porcentaje, aplica_a FROM tipo_descuento
             WHERE id_descuento = ? AND status_id = (SELECT id_status FROM status WHERE ambito='descuento' AND clave='activo') LIMIT 1");
        mysqli_stmt_bind_param($td, 'i', $tdId);
        mysqli_stmt_execute($td);
        $desc = mysqli_fetch_assoc(mysqli_stmt_get_result($td));
        mysqli_stmt_close($td);
        return $desc ?: null;
    }

    public function aplicarDescuento(int $id, float $descMonto, int $tdId, string $concepto): void
    {
        $up = mysqli_prepare($this->db,
            'UPDATE colegiatura SET descuento = ?, tipo_descuento_id = ?, concepto_descuento = ? WHERE id_pago = ?');
        mysqli_stmt_bind_param($up, 'disi', $descMonto, $tdId, $concepto, $id);
        mysqli_stmt_execute($up);
        mysqli_stmt_close($up);
    }

    /** @return int filas afectadas (0 = no existía). */
    public function eliminar(int $id): int
    {
        $stmt = mysqli_prepare($this->db, 'DELETE FROM colegiatura WHERE id_pago = ?');
        mysqli_stmt_bind_param($stmt, 'i', $id);
        mysqli_stmt_execute($stmt);
        $af = mysqli_stmt_affected_rows($stmt);
        mysqli_stmt_close($stmt);
        return $af;
    }
}
