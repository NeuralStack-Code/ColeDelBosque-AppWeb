-- ============================================================
-- Colegio del Bosque · Procedures de las colegiaturas (webService/controllers/ColegiaturaController.php,
-- EsquemaPagoController::aplicar y PadreController::resumen). Idempotente (DROP + CREATE). Antes: sp_sistema.sql
--
-- Un pago es una colegiatura mensual o la inscripción (tabla colegiatura). Los abonos son recibos ligados al pago
-- (recibo.colegiatura_id). Lo que se cobra (recargo vigente, total, saldo) lo calcula la lib Recargo: aquí no.
-- Las columnas salen con el nombre de la propiedad del model Colegiatura.
-- ============================================================

DELIMITER $$

-- Los grupos de un ciclo (0 = de todos) para filtrar el panel.
DROP PROCEDURE IF EXISTS sp_colegiatura_grupos $$
CREATE PROCEDURE sp_colegiatura_grupos(IN pIdCiclo INT)
SQL SECURITY INVOKER
BEGIN
    SELECT g.id_grupo AS IdGrupo, g.grado AS Grado, g.ciclo_id AS IdCiclo
      FROM grupo g
     WHERE g.id_grupo <> 0 AND (COALESCE(pIdCiclo, 0) = 0 OR g.ciclo_id = pIdCiclo)
     ORDER BY g.nivel, g.grado;
END $$

-- Los descuentos activos del catálogo.
DROP PROCEDURE IF EXISTS sp_colegiatura_descuentos $$
CREATE PROCEDURE sp_colegiatura_descuentos()
SQL SECURITY INVOKER
BEGIN
    SELECT td.id_descuento AS IdDescuento, td.nombre AS Nombre, td.porcentaje AS Porcentaje, td.aplica_a AS AplicaA, 1 AS Activo
      FROM tipo_descuento td
     WHERE td.status_id = (SELECT id_status FROM status WHERE ambito = 'descuento' AND clave = 'activo')
     ORDER BY td.aplica_a, td.nombre;
END $$

-- Un descuento activo del catálogo (ninguna fila si no existe o está inactivo).
DROP PROCEDURE IF EXISTS sp_colegiatura_descuento_obtener $$
CREATE PROCEDURE sp_colegiatura_descuento_obtener(IN pIdDescuento INT)
SQL SECURITY INVOKER
BEGIN
    SELECT td.id_descuento AS IdDescuento, td.nombre AS Nombre, td.porcentaje AS Porcentaje, td.aplica_a AS AplicaA, 1 AS Activo
      FROM tipo_descuento td
     WHERE td.id_descuento = pIdDescuento
       AND td.status_id = (SELECT id_status FROM status WHERE ambito = 'descuento' AND clave = 'activo');
END $$

-- Los estados que puede tener un pago.
DROP PROCEDURE IF EXISTS sp_colegiatura_estatus $$
CREATE PROCEDURE sp_colegiatura_estatus()
SQL SECURITY INVOKER
BEGIN
    SELECT s.clave AS Clave, s.nombre AS Nombre, s.color AS Color
      FROM status s
     WHERE s.ambito = 'pago'
     ORDER BY s.orden;
END $$

-- Los pagos de todos los alumnos (pIdCuenta = 0) o de uno, con lo que llevan abonado.
-- Orden: por alumno, la inscripción primero y luego por vencimiento.
DROP PROCEDURE IF EXISTS sp_colegiatura_listar $$
CREATE PROCEDURE sp_colegiatura_listar(IN pIdCuenta INT)
SQL SECURITY INVOKER
BEGIN
    SELECT col.id_pago AS IdPago, col.cuenta_id AS IdCuenta, col.ciclo_id AS IdCiclo, c.grupo_id AS IdGrupo,
           TRIM(CONCAT_WS(' ', u.nombre, u.paterno, u.materno)) AS Alumno, g.grado AS Grado,
           col.tipo AS Tipo, col.mes AS Mes, col.fecha_vencimiento AS FechaVencimiento, col.fecha_pago AS FechaPago,
           col.monto AS Monto, col.descuento AS Descuento, col.tipo_descuento_id AS IdTipoDescuento,
           col.concepto_descuento AS ConceptoDescuento, col.recargo_monto AS RecargoMonto, col.recargo AS Recargo,
           col.recibo_id AS IdRecibo,
           COALESCE(st.clave, 'pendiente') AS Estatus, st.nombre AS EstatusNombre, st.color AS EstatusColor,
           (SELECT COALESCE(SUM(rr.monto), 0) FROM recibo rr WHERE rr.colegiatura_id = col.id_pago) AS Abonado
      FROM colegiatura col
      JOIN cuenta c       ON c.id_cuenta = col.cuenta_id
      JOIN usuario u      ON u.id_usuario = c.usuario_id
      LEFT JOIN grupo g   ON g.id_grupo = c.grupo_id
      LEFT JOIN status st ON st.id_status = col.status_id
     WHERE COALESCE(pIdCuenta, 0) = 0 OR col.cuenta_id = pIdCuenta
     ORDER BY u.paterno, u.nombre, col.tipo DESC, col.fecha_vencimiento ASC;
END $$

-- Un pago, con lo abonado (ninguna fila si no existe). Para cobrarlo o aplicarle un descuento.
DROP PROCEDURE IF EXISTS sp_colegiatura_obtener $$
CREATE PROCEDURE sp_colegiatura_obtener(IN pIdPago INT)
SQL SECURITY INVOKER
BEGIN
    SELECT col.id_pago AS IdPago, col.cuenta_id AS IdCuenta, col.ciclo_id AS IdCiclo,
           col.tipo AS Tipo, col.mes AS Mes, col.fecha_vencimiento AS FechaVencimiento,
           col.monto AS Monto, col.descuento AS Descuento, col.recargo_monto AS RecargoMonto, col.recargo AS Recargo,
           col.recibo_id AS IdRecibo, COALESCE(st.clave, 'pendiente') AS Estatus,
           (SELECT COALESCE(SUM(rr.monto), 0) FROM recibo rr WHERE rr.colegiatura_id = col.id_pago) AS Abonado
      FROM colegiatura col
      LEFT JOIN status st ON st.id_status = col.status_id
     WHERE col.id_pago = pIdPago;
END $$

-- Cobra un pago, todo o nada: genera el recibo del abono (tipo Inscripción o Colegiatura) y deja el pago
-- 'pagado' (con el recargo congelado y la fecha de hoy) si pCompleta = 1, o 'parcial'. Regresa el Id del recibo.
-- 'notfound' si el pago no existe; 'invalido' si ya está pagado.
DROP PROCEDURE IF EXISTS sp_colegiatura_pagar $$
CREATE PROCEDURE sp_colegiatura_pagar(IN pIdPago INT, IN pMonto DECIMAL(10,2), IN pTipoPago VARCHAR(30),
                                      IN pComentario TEXT, IN pFecha DATETIME, IN pCompleta TINYINT,
                                      IN pRecargo DECIMAL(10,2))
SQL SECURITY INVOKER
BEGIN
    DECLARE vIdCuenta INT;
    DECLARE vTipo VARCHAR(20);
    DECLARE vEstatus VARCHAR(30);
    DECLARE vIdRecibo INT;
    DECLARE EXIT HANDLER FOR SQLEXCEPTION
    BEGIN
        ROLLBACK;
        RESIGNAL;
    END;

    START TRANSACTION;

    SELECT col.cuenta_id, col.tipo, COALESCE(st.clave, 'pendiente') INTO vIdCuenta, vTipo, vEstatus
      FROM colegiatura col
      LEFT JOIN status st ON st.id_status = col.status_id
     WHERE col.id_pago = pIdPago
       FOR UPDATE;

    IF vIdCuenta IS NULL THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'notfound:No se encontró la colegiatura.';
    END IF;
    IF vEstatus = 'pagado' THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'invalido:Este pago ya está saldado.';
    END IF;

    INSERT INTO recibo (tipo_recibo_id, naturaleza, destinatario_tipo, cuenta_id, colegiatura_id, monto, tipo_pago, comentario, fecha)
    VALUES ((SELECT id_tipo FROM tipo_recibo
              WHERE nombre = IF(vTipo = 'inscripcion', 'Inscripción', 'Colegiatura') COLLATE utf8mb4_unicode_ci LIMIT 1),
            'ingreso', 'alumno', vIdCuenta, pIdPago, pMonto, pTipoPago, pComentario, pFecha);
    SET vIdRecibo = LAST_INSERT_ID();

    IF pCompleta = 1 THEN
        UPDATE colegiatura
           SET status_id = (SELECT id_status FROM status WHERE ambito = 'pago' AND clave = 'pagado'),
               fecha_pago = CURDATE(), recargo = pRecargo, recibo_id = vIdRecibo
         WHERE id_pago = pIdPago;
    ELSE
        UPDATE colegiatura
           SET status_id = (SELECT id_status FROM status WHERE ambito = 'pago' AND clave = 'parcial'),
               recibo_id = vIdRecibo
         WHERE id_pago = pIdPago;
    END IF;

    COMMIT;

    SELECT vIdRecibo AS Id;
END $$

-- Aplica un descuento al pago (pIdTipoDescuento = NULL lo quita). 'notfound' · 'invalido' si ya está pagado.
DROP PROCEDURE IF EXISTS sp_colegiatura_descuento $$
CREATE PROCEDURE sp_colegiatura_descuento(IN pIdPago INT, IN pDescuento DECIMAL(10,2), IN pIdTipoDescuento INT,
                                          IN pConceptoDescuento VARCHAR(60))
SQL SECURITY INVOKER
BEGIN
    DECLARE vEstatus VARCHAR(30);

    SELECT COALESCE(st.clave, 'pendiente') INTO vEstatus
      FROM colegiatura col
      LEFT JOIN status st ON st.id_status = col.status_id
     WHERE col.id_pago = pIdPago;

    IF vEstatus IS NULL THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'notfound:No se encontró el registro.';
    END IF;
    IF vEstatus = 'pagado' THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'invalido:No se puede: el pago ya está registrado.';
    END IF;

    IF pIdTipoDescuento IS NULL THEN
        UPDATE colegiatura SET descuento = 0, tipo_descuento_id = NULL, concepto_descuento = NULL WHERE id_pago = pIdPago;
    ELSE
        UPDATE colegiatura
           SET descuento = pDescuento, tipo_descuento_id = pIdTipoDescuento, concepto_descuento = pConceptoDescuento
         WHERE id_pago = pIdPago;
    END IF;

    SELECT 1 AS Afectadas;
END $$

-- Fija el recargo (pesos por mes vencido) de un pago que aún no está saldado. 'invalido' si no existe o ya se pagó.
DROP PROCEDURE IF EXISTS sp_colegiatura_recargo $$
CREATE PROCEDURE sp_colegiatura_recargo(IN pIdPago INT, IN pRecargoMonto DECIMAL(10,2))
SQL SECURITY INVOKER
BEGIN
    UPDATE colegiatura
       SET recargo_monto = pRecargoMonto
     WHERE id_pago = pIdPago
       AND (status_id IS NULL OR status_id <> (SELECT id_status FROM status WHERE ambito = 'pago' AND clave = 'pagado'));

    IF ROW_COUNT() = 0 AND NOT EXISTS (
        SELECT 1 FROM colegiatura
         WHERE id_pago = pIdPago
           AND (status_id IS NULL OR status_id <> (SELECT id_status FROM status WHERE ambito = 'pago' AND clave = 'pagado'))) THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'invalido:No se puede: el pago no existe o ya está saldado.';
    END IF;

    SELECT 1 AS Afectadas;
END $$

-- Genera (o actualiza) UN pago de un esquema para un alumno. Lo llama EsquemaPagoController::aplicar por cada
-- alumno y cada pago del calendario. La inscripción es una por ciclo; la colegiatura se identifica por mes + año.
-- Un pago que no existe se crea pendiente; uno pendiente o parcial se actualiza si cambió el monto o el recargo;
-- uno pagado no se toca. Regresa Resultado: creado · actualizado · sin_cambios.
DROP PROCEDURE IF EXISTS sp_colegiatura_generar $$
CREATE PROCEDURE sp_colegiatura_generar(IN pIdCuenta INT, IN pIdCiclo INT, IN pTipo VARCHAR(20), IN pMes VARCHAR(20),
                                        IN pAnio INT, IN pFechaVencimiento DATE, IN pMonto DECIMAL(10,2),
                                        IN pRecargoMonto DECIMAL(10,2))
SQL SECURITY INVOKER
BEGIN
    DECLARE vIdPago INT;
    DECLARE vEstatus VARCHAR(30);
    DECLARE vMonto DECIMAL(10,2);
    DECLARE vRecargoMonto DECIMAL(10,2);

    SELECT col.id_pago, COALESCE(st.clave, ''), ROUND(col.monto, 2), col.recargo_monto
      INTO vIdPago, vEstatus, vMonto, vRecargoMonto
      FROM colegiatura col
      LEFT JOIN status st ON st.id_status = col.status_id
     WHERE col.cuenta_id = pIdCuenta AND col.ciclo_id = pIdCiclo AND col.tipo = pTipo COLLATE utf8mb4_unicode_ci
       AND (col.tipo = 'inscripcion'
            OR (col.mes = pMes COLLATE utf8mb4_unicode_ci AND YEAR(col.fecha_vencimiento) = pAnio))
     LIMIT 1;

    IF vIdPago IS NULL THEN
        INSERT INTO colegiatura (cuenta_id, ciclo_id, mes, tipo, monto, status_id, fecha_vencimiento, recargo_pct, recargo_monto)
        VALUES (pIdCuenta, pIdCiclo, pMes, pTipo, pMonto,
                (SELECT id_status FROM status WHERE ambito = 'pago' AND clave = 'pendiente'),
                pFechaVencimiento, 0, pRecargoMonto);
        SELECT 'creado' AS Resultado;
    ELSEIF vEstatus <> 'pagado' AND (vMonto <> pMonto OR vRecargoMonto <> pRecargoMonto) THEN
        UPDATE colegiatura SET monto = pMonto, recargo_monto = pRecargoMonto WHERE id_pago = vIdPago;
        SELECT 'actualizado' AS Resultado;
    ELSE
        SELECT 'sin_cambios' AS Resultado;
    END IF;
END $$

-- Regresa las filas afectadas (0 = no existía). Sus recibos se conservan.
DROP PROCEDURE IF EXISTS sp_colegiatura_eliminar $$
CREATE PROCEDURE sp_colegiatura_eliminar(IN pIdPago INT)
SQL SECURITY INVOKER
BEGIN
    DELETE FROM colegiatura WHERE id_pago = pIdPago;

    SELECT ROW_COUNT() AS Afectadas;
END $$

DELIMITER ;
