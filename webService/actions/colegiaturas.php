<?php
/**
 * Action: colegiaturas  →  /api/colegiaturas?action=...
 * Esquema de pagos por ciclo: inscripción + colegiaturas mensuales.
 * Generar (a todos o a un alumno), listar (con recargo/descuento/total),
 * registrar pago (genera recibo), aplicar descuento por catálogo, eliminar. (solo admin)
 *
 * colegiatura(id_pago, cuenta_id, ciclo_id, fecha_pago, mes, tipo, monto, status_id,
 *             fecha_vencimiento, recargo_pct, recargo, descuento, tipo_descuento_id,
 *             concepto_descuento, recibo_id)   status_id → status(ambito 'pago')
 */
require_once __DIR__ . '/../../apiService/core/conexionBDD.php';
require_once __DIR__ . '/../../apiService/core/crypto.php';
require_once __DIR__ . '/../../apiService/middleware/auth.php';

requireAdmin();

$action = $_GET['action'] ?? '';
$MESES  = ['', 'Enero', 'Febrero', 'Marzo', 'Abril', 'Mayo', 'Junio', 'Julio', 'Agosto', 'Septiembre', 'Octubre', 'Noviembre', 'Diciembre'];

/** Recargo vigente (en vivo) para un pago no pagado y vencido. */
function recargoVigente(float $monto, float $pct, ?string $venc, string $estatus): float {
    if (strtolower($estatus) === 'pagado' || $pct <= 0 || !$venc) return 0.0;
    try { $hoy = new DateTime('today'); $v = new DateTime($venc); }
    catch (Exception $e) { return 0.0; }
    if ($v >= $hoy) return 0.0;
    $meses = ((int)$hoy->format('Y') - (int)$v->format('Y')) * 12 + ((int)$hoy->format('n') - (int)$v->format('n'));
    if ($meses < 1) $meses = 1;
    return round($monto * $pct / 100 * $meses, 2);
}

/** id del ciclo activo (0 si no hay). */
function cicloActivoId(mysqli $conexion): int {
    $row = mysqli_fetch_assoc(mysqli_query($conexion,
        "SELECT id_ciclo FROM ciclo WHERE status_id = (SELECT id_status FROM status WHERE ambito='ciclo' AND clave='activo') LIMIT 1"));
    return (int) ($row['id_ciclo'] ?? 0);
}

switch ($action) {

    // ---- Catálogos: ciclo activo, grupos y alumnos del ciclo, tipos de descuento ----
    case 'catalogos':
        $ciclo   = mysqli_fetch_assoc(mysqli_query($conexion,
            "SELECT id_ciclo, nombre FROM ciclo WHERE status_id = (SELECT id_status FROM status WHERE ambito='ciclo' AND clave='activo') LIMIT 1"));
        $cicloId = (int) ($ciclo['id_ciclo'] ?? 0);

        // Grupos del ciclo activo (para buscar un alumno específico y para el filtro)
        $gr = [];
        $sqlG = 'SELECT id_grupo, grado FROM grupo WHERE id_grupo <> 0'
              . ($cicloId ? ' AND ciclo_id = ' . $cicloId : '') . ' ORDER BY nivel, grado';
        $res = mysqli_query($conexion, $sqlG);
        while ($r = mysqli_fetch_assoc($res)) $gr[] = $r;

        // Alumnos del ciclo activo (permiso 3, con grupo válido) — matrícula en claro para buscar
        $al = [];
        $sqlA = 'SELECT c.id_cuenta, c.matricula, c.grupo_id, g.grado, u.nombre, u.paterno, u.materno
                 FROM cuenta c
                 JOIN usuario u ON u.id_usuario = c.usuario_id
                 JOIN grupo g   ON g.id_grupo = c.grupo_id
                 WHERE c.permiso_id = 3' . ($cicloId ? ' AND g.ciclo_id = ' . $cicloId : '') . '
                 ORDER BY u.paterno, u.nombre';
        $res = mysqli_query($conexion, $sqlA);
        while ($r = mysqli_fetch_assoc($res)) {
            $al[] = [
                'id_cuenta' => $r['id_cuenta'],
                'nombre'    => trim("$r[nombre] $r[paterno] $r[materno]"),
                'matricula' => decrypt($r['matricula']),
                'grupo_id'  => $r['grupo_id'],
                'grado'     => $r['grado'],
            ];
        }

        // Tipos de descuento activos
        $desc = [];
        $res = mysqli_query($conexion,
            "SELECT id_descuento, nombre, porcentaje, aplica_a FROM tipo_descuento
             WHERE status_id = (SELECT id_status FROM status WHERE ambito='descuento' AND clave='activo') ORDER BY aplica_a, nombre");
        while ($r = mysqli_fetch_assoc($res)) $desc[] = $r;

        // Catálogo de estatus de pago (fuente de verdad de nombres/colores)
        $est = [];
        $res = mysqli_query($conexion,
            "SELECT clave, nombre, color FROM status WHERE ambito = 'pago' ORDER BY orden");
        while ($r = mysqli_fetch_assoc($res)) $est[] = $r;

        response(200, true, 'Catálogos.', [
            'ciclo'      => $ciclo ?: null,
            'grupos'     => $gr,
            'alumnos'    => $al,
            'descuentos' => $desc,
            'estatus'    => $est,
        ]);
        break;

    // ---- Listar con cálculo de recargo/total (inscripción primero, luego meses) ----
    case 'listar':
        $sql = 'SELECT col.*, c.grupo_id, u.nombre, u.paterno, u.materno, g.grado,
                       st.clave AS estatus, st.nombre AS estatus_nombre, st.color AS estatus_color,
                       (SELECT COALESCE(SUM(rr.monto),0) FROM recibo rr WHERE rr.colegiatura_id = col.id_pago) AS abonado
                FROM colegiatura col
                JOIN cuenta c  ON c.id_cuenta = col.cuenta_id
                JOIN usuario u ON u.id_usuario = c.usuario_id
                LEFT JOIN grupo g  ON g.id_grupo = c.grupo_id
                LEFT JOIN status st ON st.id_status = col.status_id
                ORDER BY u.paterno, u.nombre, col.tipo DESC, col.fecha_vencimiento ASC';
        $res = mysqli_query($conexion, $sql);
        $items = [];
        while ($r = mysqli_fetch_assoc($res)) {
            $monto = (float) $r['monto'];
            $desc  = (float) $r['descuento'];
            $recargo = strtolower($r['estatus']) === 'pagado'
                ? (float) $r['recargo']
                : recargoVigente($monto, (float) $r['recargo_pct'], $r['fecha_vencimiento'], $r['estatus']);
            $abonado = (float) $r['abonado'];
            $total   = max(0, $monto + $recargo - $desc);
            $r['alumno']        = trim("$r[nombre] $r[paterno] $r[materno]");
            $r['recargo_calc']  = $recargo;
            $r['abonado']       = $abonado;
            $r['total']         = $total;
            $r['saldo']         = max(0, round($total - $abonado, 2));
            $items[] = $r;
        }
        response(200, true, 'Colegiaturas obtenidas.', ['items' => $items]);
        break;

    // ---- Generar esquema: inscripción + colegiaturas, a todos o a un alumno ----
    case 'generar':
        $montoCol  = filter_var($_POST['monto_colegiatura'] ?? '', FILTER_VALIDATE_FLOAT);
        $montoIns  = filter_var($_POST['monto_inscripcion'] ?? '0', FILTER_VALIDATE_FLOAT);
        $anio      = (int) ($_POST['anio'] ?? 0);
        $mesInicio = (int) ($_POST['mes_inicio'] ?? 0);
        $numMeses  = (int) ($_POST['num_meses'] ?? 0);
        $diaVenc   = (int) ($_POST['dia_venc'] ?? 1);
        $recPct    = (float) ($_POST['recargo_pct'] ?? 0);
        $aplicarA  = ($_POST['aplicar_a'] ?? 'todos') === 'alumno' ? 'alumno' : 'todos';
        $cuentaSel = (int) ($_POST['cuenta_id'] ?? 0);

        if ($montoCol === false || $montoCol <= 0) response(400, false, 'Monto de colegiatura no válido.');
        if ($montoIns === false || $montoIns < 0)  response(400, false, 'Monto de inscripción no válido.');
        if ($anio < 2020 || $anio > 2100)          response(400, false, 'Año no válido.');
        if ($mesInicio < 1 || $mesInicio > 12)     response(400, false, 'Mes de inicio no válido.');
        if ($numMeses < 1 || $numMeses > 24)       response(400, false, 'Número de mensualidades no válido.');
        if ($diaVenc < 1 || $diaVenc > 28)         response(400, false, 'Día de vencimiento entre 1 y 28.');

        $cicloId = cicloActivoId($conexion);
        if ($cicloId <= 0) response(409, false, 'No hay un ciclo escolar activo. Actívalo antes de generar.');

        // Alumnos destino (siempre dentro del ciclo activo)
        $alumnos = [];
        if ($aplicarA === 'alumno') {
            if ($cuentaSel <= 0) response(400, false, 'Selecciona un alumno.');
            $chk = mysqli_prepare($conexion,
                'SELECT c.id_cuenta FROM cuenta c JOIN grupo g ON g.id_grupo = c.grupo_id
                 WHERE c.id_cuenta = ? AND c.permiso_id = 3 AND g.ciclo_id = ? LIMIT 1');
            mysqli_stmt_bind_param($chk, 'ii', $cuentaSel, $cicloId);
            mysqli_stmt_execute($chk);
            if (!mysqli_fetch_row(mysqli_stmt_get_result($chk))) response(404, false, 'El alumno no está en un grupo del ciclo activo.');
            mysqli_stmt_close($chk);
            $alumnos[] = $cuentaSel;
        } else {
            $res = mysqli_query($conexion,
                'SELECT c.id_cuenta FROM cuenta c JOIN grupo g ON g.id_grupo = c.grupo_id
                 WHERE c.permiso_id = 3 AND g.ciclo_id = ' . $cicloId);
            while ($r = mysqli_fetch_row($res)) $alumnos[] = (int) $r[0];
        }
        if (!$alumnos) response(409, false, 'No hay alumnos en el ciclo activo para generar el esquema.');

        global $MESES;
        $creados = 0; $actualizados = 0; $saltados = 0;
        $stPendiente = statusId($conexion, 'pago', 'pendiente');

        // Inscripción (una por ciclo)
        $chkIns = mysqli_prepare($conexion,
            'SELECT col.id_pago, s.clave AS estatus, col.monto FROM colegiatura col
             LEFT JOIN status s ON s.id_status = col.status_id
             WHERE col.cuenta_id = ? AND col.ciclo_id = ? AND col.tipo = "inscripcion" LIMIT 1');
        $insIns = mysqli_prepare($conexion,
            'INSERT INTO colegiatura (cuenta_id, ciclo_id, mes, tipo, monto, status_id, fecha_vencimiento, recargo_pct)
             VALUES (?, ?, "Inscripción", "inscripcion", ?, ?, ?, 0)');
        $updIns = mysqli_prepare($conexion, 'UPDATE colegiatura SET monto = ? WHERE id_pago = ?');

        // Colegiatura mensual
        $chkCol = mysqli_prepare($conexion,
            'SELECT col.id_pago, s.clave AS estatus, col.monto FROM colegiatura col
             LEFT JOIN status s ON s.id_status = col.status_id
             WHERE col.cuenta_id = ? AND col.ciclo_id = ? AND col.tipo = "colegiatura" AND col.mes = ? AND YEAR(col.fecha_vencimiento) = ? LIMIT 1');
        $insCol = mysqli_prepare($conexion,
            'INSERT INTO colegiatura (cuenta_id, ciclo_id, mes, tipo, monto, status_id, fecha_vencimiento, recargo_pct)
             VALUES (?, ?, ?, "colegiatura", ?, ?, ?, ?)');
        $updCol = mysqli_prepare($conexion, 'UPDATE colegiatura SET monto = ?, recargo_pct = ? WHERE id_pago = ?');

        foreach ($alumnos as $cid) {
            // --- Inscripción ---
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

            // --- Colegiaturas mensuales ---
            for ($k = 0; $k < $numMeses; $k++) {
                $mn = ($mesInicio - 1 + $k) % 12 + 1;
                $an = $anio + intdiv($mesInicio - 1 + $k, 12);
                $mesNombre = $MESES[$mn];
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

        $msg = "Esquema generado: $creados nuevo(s)";
        if ($actualizados) $msg .= ", $actualizados actualizado(s)";
        if ($saltados)     $msg .= ", $saltados sin cambios";
        $msg .= '.';
        response(201, true, $msg);
        break;

    // ---- Registrar pago o abono → genera recibo; marca parcial/pagado ----
    case 'registrar_pago':
        $id   = (int) ($_POST['id_pago'] ?? 0);
        $pago = trim($_POST['tipo_pago'] ?? 'Efectivo');
        // monto del abono; si viene vacío se toma el saldo completo
        $abonoIn = (isset($_POST['monto']) && $_POST['monto'] !== '')
            ? filter_var($_POST['monto'], FILTER_VALIDATE_FLOAT) : null;
        if ($id <= 0) response(400, false, 'Registro no válido.');
        if ($abonoIn !== null && ($abonoIn === false || $abonoIn <= 0)) response(400, false, 'El monto del abono no es válido.');

        $q = mysqli_prepare($conexion,
            'SELECT col.cuenta_id, col.mes, col.tipo, col.monto, s.clave AS estatus, col.recargo_pct, col.descuento, col.fecha_vencimiento
             FROM colegiatura col LEFT JOIN status s ON s.id_status = col.status_id WHERE col.id_pago = ? LIMIT 1');
        mysqli_stmt_bind_param($q, 'i', $id);
        mysqli_stmt_execute($q);
        $col = mysqli_fetch_assoc(mysqli_stmt_get_result($q));
        mysqli_stmt_close($q);
        if (!$col) response(404, false, 'No se encontró la colegiatura.');
        if (strtolower($col['estatus']) === 'pagado') response(409, false, 'Este pago ya está saldado.');

        $monto   = (float) $col['monto'];
        $desc    = (float) $col['descuento'];
        // Recargo en vivo (sigue creciendo mientras no se salda)
        $recargo = recargoVigente($monto, (float) $col['recargo_pct'], $col['fecha_vencimiento'], $col['estatus']);
        $total   = max(0, $monto + $recargo - $desc);
        $cuentaId = (int) $col['cuenta_id'];
        $esInscripcion = $col['tipo'] === 'inscripcion';

        // Ya abonado (suma de recibos ligados a esta colegiatura)
        $qa = mysqli_prepare($conexion, 'SELECT COALESCE(SUM(monto),0) FROM recibo WHERE colegiatura_id = ?');
        mysqli_stmt_bind_param($qa, 'i', $id);
        mysqli_stmt_execute($qa);
        $abonado = (float) (mysqli_fetch_row(mysqli_stmt_get_result($qa))[0] ?? 0);
        mysqli_stmt_close($qa);

        $saldo = round($total - $abonado, 2);
        if ($saldo <= 0.005) response(409, false, 'Este pago ya está cubierto.');

        $abono = $abonoIn ?? $saldo;
        if ($abono > $saldo + 0.005) {
            response(409, false, 'El abono ($' . number_format($abono, 2) . ') supera el saldo ($' . number_format($saldo, 2) . ').');
        }
        $completa = ($abonado + $abono) >= ($total - 0.005);

        // Tipo de recibo ("Inscripción" o "Colegiatura")
        $tipoNombre = $esInscripcion ? 'Inscripción' : 'Colegiatura';
        $tipoId = null;
        $t = mysqli_prepare($conexion, 'SELECT id_tipo FROM tipo_recibo WHERE nombre = ? LIMIT 1');
        mysqli_stmt_bind_param($t, 's', $tipoNombre);
        mysqli_stmt_execute($t);
        if ($row = mysqli_fetch_assoc(mysqli_stmt_get_result($t))) $tipoId = (int) $row['id_tipo'];
        mysqli_stmt_close($t);

        // Comentario: "Colegiatura Agosto" si es pago único completo; "Abono — ..." si es parcial o uno de varios
        $base = $esInscripcion ? 'Inscripción' : ('Colegiatura ' . $col['mes']);
        $com  = ($completa && $abonado <= 0.005) ? $base : ('Abono — ' . $base);

        $nat = 'ingreso'; $dtipo = 'alumno'; $fecha = date('Y-m-d H:i:s');
        $ins = mysqli_prepare($conexion,
            'INSERT INTO recibo (tipo_recibo_id, naturaleza, destinatario_tipo, cuenta_id, colegiatura_id, monto, tipo_pago, comentario, fecha)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)');
        mysqli_stmt_bind_param($ins, 'issiidsss', $tipoId, $nat, $dtipo, $cuentaId, $id, $abono, $pago, $com, $fecha);
        if (!mysqli_stmt_execute($ins)) response(500, false, 'No se pudo generar el recibo.');
        $reciboId = mysqli_insert_id($conexion);
        mysqli_stmt_close($ins);

        if ($completa) {
            // Saldado: congela recargo + liga recibo final
            $stPagado = statusId($conexion, 'pago', 'pagado');
            $up = mysqli_prepare($conexion,
                'UPDATE colegiatura SET status_id = ?, fecha_pago = CURDATE(), recargo = ?, recibo_id = ? WHERE id_pago = ?');
            mysqli_stmt_bind_param($up, 'idii', $stPagado, $recargo, $reciboId, $id);
            mysqli_stmt_execute($up);
            mysqli_stmt_close($up);
            response(200, true, 'Pago saldado y recibo generado.', ['recibo_id' => $reciboId, 'completa' => true, 'saldo' => 0]);
        } else {
            // Abono parcial: estatus parcial, liga el último recibo (recargo sigue vivo)
            $stParcial = statusId($conexion, 'pago', 'parcial');
            $up = mysqli_prepare($conexion,
                'UPDATE colegiatura SET status_id = ?, recibo_id = ? WHERE id_pago = ?');
            mysqli_stmt_bind_param($up, 'iii', $stParcial, $reciboId, $id);
            mysqli_stmt_execute($up);
            mysqli_stmt_close($up);
            $saldoRest = round($saldo - $abono, 2);
            response(200, true, 'Abono registrado. Saldo restante: $' . number_format($saldoRest, 2) . '.',
                ['recibo_id' => $reciboId, 'completa' => false, 'saldo' => $saldoRest]);
        }
        break;

    // ---- Aplicar descuento (desde el catálogo tipo_descuento) ----
    case 'descuento':
        $id   = (int) ($_POST['id_pago'] ?? 0);
        $tdId = (int) ($_POST['tipo_descuento_id'] ?? 0); // 0 = quitar descuento
        if ($id <= 0) response(400, false, 'Registro no válido.');

        $q = mysqli_prepare($conexion,
            'SELECT col.tipo, col.monto, s.clave AS estatus FROM colegiatura col
             LEFT JOIN status s ON s.id_status = col.status_id WHERE col.id_pago = ? LIMIT 1');
        mysqli_stmt_bind_param($q, 'i', $id);
        mysqli_stmt_execute($q);
        $col = mysqli_fetch_assoc(mysqli_stmt_get_result($q));
        mysqli_stmt_close($q);
        if (!$col) response(404, false, 'No se encontró el registro.');
        if (strtolower($col['estatus']) === 'pagado') response(409, false, 'No se puede: el pago ya está registrado.');

        // Quitar descuento
        if ($tdId <= 0) {
            $up = mysqli_prepare($conexion,
                'UPDATE colegiatura SET descuento = 0, tipo_descuento_id = NULL, concepto_descuento = NULL WHERE id_pago = ?');
            mysqli_stmt_bind_param($up, 'i', $id);
            mysqli_stmt_execute($up);
            mysqli_stmt_close($up);
            response(200, true, 'Descuento quitado.');
        }

        // Buscar el tipo de descuento y validar que aplique a este pago
        $td = mysqli_prepare($conexion,
            "SELECT nombre, porcentaje, aplica_a FROM tipo_descuento
             WHERE id_descuento = ? AND status_id = (SELECT id_status FROM status WHERE ambito='descuento' AND clave='activo') LIMIT 1");
        mysqli_stmt_bind_param($td, 'i', $tdId);
        mysqli_stmt_execute($td);
        $desc = mysqli_fetch_assoc(mysqli_stmt_get_result($td));
        mysqli_stmt_close($td);
        if (!$desc) response(404, false, 'Descuento no encontrado o inactivo.');

        $tipoCol = $col['tipo'] === 'inscripcion' ? 'inscripcion' : 'colegiatura';
        if ($desc['aplica_a'] !== $tipoCol) response(409, false, 'Ese descuento no aplica a este tipo de pago.');

        $descMonto = round((float) $col['monto'] * (float) $desc['porcentaje'] / 100, 2);
        $concepto  = function_exists('mb_substr') ? mb_substr($desc['nombre'], 0, 60) : substr($desc['nombre'], 0, 60);

        $up = mysqli_prepare($conexion,
            'UPDATE colegiatura SET descuento = ?, tipo_descuento_id = ?, concepto_descuento = ? WHERE id_pago = ?');
        mysqli_stmt_bind_param($up, 'disi', $descMonto, $tdId, $concepto, $id);
        mysqli_stmt_execute($up);
        mysqli_stmt_close($up);
        response(200, true, "Descuento aplicado: {$desc['nombre']} (−$" . number_format($descMonto, 2) . ').');
        break;

    // ---- Eliminar ----
    case 'eliminar':
        $id = (int) ($_POST['id_pago'] ?? 0);
        if ($id <= 0) response(400, false, 'Registro no válido.');
        $stmt = mysqli_prepare($conexion, 'DELETE FROM colegiatura WHERE id_pago = ?');
        mysqli_stmt_bind_param($stmt, 'i', $id);
        mysqli_stmt_execute($stmt);
        $af = mysqli_stmt_affected_rows($stmt);
        mysqli_stmt_close($stmt);
        if ($af === 0) response(404, false, 'No se encontró el registro.');
        response(200, true, 'Registro eliminado.');
        break;

    default:
        response(400, false, "Acción de colegiaturas no válida: '$action'.");
}
