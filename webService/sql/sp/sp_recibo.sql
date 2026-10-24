-- ============================================================
-- Colegio del Bosque · Procedures de los recibos (webService/controllers/ReciboController.php)
-- Idempotente (DROP + CREATE). Antes: sp_sistema.sql
--
-- Un recibo es un ingreso o un egreso de la escuela (tabla recibo). Su destinatario es la escuela, un alumno o un
-- docente. Los abonos a colegiaturas también son recibos: los genera sp_colegiatura_pagar (sp_colegiatura.sql).
-- Las columnas salen con el nombre de la propiedad del model Recibo.
-- ============================================================

DELIMITER $$

-- Las cuentas de un rol (3 = alumno, 2 = docente) para elegir el destinatario.
DROP PROCEDURE IF EXISTS sp_recibo_personas $$
CREATE PROCEDURE sp_recibo_personas(IN pIdPermiso INT)
SQL SECURITY INVOKER
BEGIN
    SELECT c.id_cuenta AS IdCuenta, TRIM(CONCAT_WS(' ', u.nombre, u.paterno, u.materno)) AS Nombre
      FROM cuenta c
      JOIN usuario u ON u.id_usuario = c.usuario_id
     WHERE c.permiso_id = pIdPermiso
     ORDER BY u.paterno, u.nombre;
END $$

DROP PROCEDURE IF EXISTS sp_recibo_listar $$
CREATE PROCEDURE sp_recibo_listar()
SQL SECURITY INVOKER
BEGIN
    SELECT r.id_recibo AS IdRecibo, r.tipo_recibo_id AS IdTipoRecibo, r.naturaleza AS Naturaleza,
           r.destinatario_tipo AS DestinatarioTipo, r.cuenta_id AS IdCuenta, r.monto AS Monto, r.tipo_pago AS TipoPago,
           r.comentario AS Comentario, r.fecha AS Fecha, t.nombre AS TipoNombre,
           IF(r.destinatario_tipo = 'escuela', 'Escuela', TRIM(CONCAT_WS(' ', u.nombre, u.paterno, u.materno))) AS Destinatario
      FROM recibo r
      LEFT JOIN tipo_recibo t ON t.id_tipo = r.tipo_recibo_id
      LEFT JOIN cuenta c      ON c.id_cuenta = r.cuenta_id
      LEFT JOIN usuario u     ON u.id_usuario = c.usuario_id
     ORDER BY r.fecha DESC, r.id_recibo DESC;
END $$

-- Un recibo (ninguna fila si no existe), con el usuario y el rol de la cuenta destinataria: así el controller
-- sabe si un alumno puede verlo (solo los suyos).
DROP PROCEDURE IF EXISTS sp_recibo_obtener $$
CREATE PROCEDURE sp_recibo_obtener(IN pIdRecibo INT)
SQL SECURITY INVOKER
BEGIN
    SELECT r.id_recibo AS IdRecibo, r.tipo_recibo_id AS IdTipoRecibo, r.naturaleza AS Naturaleza,
           r.destinatario_tipo AS DestinatarioTipo, r.cuenta_id AS IdCuenta, r.monto AS Monto, r.tipo_pago AS TipoPago,
           r.comentario AS Comentario, r.fecha AS Fecha, t.nombre AS TipoNombre,
           IF(r.destinatario_tipo = 'escuela', 'Escuela', TRIM(CONCAT_WS(' ', u.nombre, u.paterno, u.materno))) AS Destinatario,
           c.usuario_id AS IdUsuarioCuenta, c.permiso_id AS IdPermisoCuenta
      FROM recibo r
      LEFT JOIN tipo_recibo t ON t.id_tipo = r.tipo_recibo_id
      LEFT JOIN cuenta c      ON c.id_cuenta = r.cuenta_id
      LEFT JOIN usuario u     ON u.id_usuario = c.usuario_id
     WHERE r.id_recibo = pIdRecibo;
END $$

-- El destinatario (alumno o docente) tiene que ser una cuenta de ese rol. 'invalido' si no.
DROP PROCEDURE IF EXISTS sp_recibo_validar_destinatario $$
CREATE PROCEDURE sp_recibo_validar_destinatario(IN pDestinatarioTipo VARCHAR(10), IN pIdCuenta INT)
SQL SECURITY INVOKER
BEGIN
    DECLARE vMensaje VARCHAR(120);
    IF pDestinatarioTipo <> 'escuela' COLLATE utf8mb4_unicode_ci
       AND NOT EXISTS (SELECT 1 FROM cuenta WHERE id_cuenta = pIdCuenta
                          AND permiso_id = IF(pDestinatarioTipo = 'alumno' COLLATE utf8mb4_unicode_ci, 3, 2)) THEN
        SET vMensaje = CONCAT('invalido:El destinatario no es un ', pDestinatarioTipo, ' válido.');
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = vMensaje;
    END IF;
END $$

DROP PROCEDURE IF EXISTS sp_recibo_crear $$
CREATE PROCEDURE sp_recibo_crear(IN pIdTipoRecibo INT, IN pNaturaleza VARCHAR(10), IN pDestinatarioTipo VARCHAR(10),
                                 IN pIdCuenta INT, IN pMonto DECIMAL(10,2), IN pTipoPago VARCHAR(30),
                                 IN pComentario TEXT, IN pFecha DATETIME)
SQL SECURITY INVOKER
BEGIN
    CALL sp_recibo_validar_destinatario(pDestinatarioTipo, pIdCuenta);

    INSERT INTO recibo (tipo_recibo_id, naturaleza, destinatario_tipo, cuenta_id, monto, tipo_pago, comentario, fecha)
    VALUES (pIdTipoRecibo, pNaturaleza, pDestinatarioTipo, pIdCuenta, pMonto, pTipoPago, pComentario, pFecha);

    SELECT LAST_INSERT_ID() AS Id;
END $$

-- 'notfound' si no existe; 'invalido' si el destinatario no es de ese rol.
DROP PROCEDURE IF EXISTS sp_recibo_editar $$
CREATE PROCEDURE sp_recibo_editar(IN pIdRecibo INT, IN pIdTipoRecibo INT, IN pNaturaleza VARCHAR(10),
                                  IN pDestinatarioTipo VARCHAR(10), IN pIdCuenta INT, IN pMonto DECIMAL(10,2),
                                  IN pTipoPago VARCHAR(30), IN pComentario TEXT, IN pFecha DATETIME)
SQL SECURITY INVOKER
BEGIN
    IF NOT EXISTS (SELECT 1 FROM recibo WHERE id_recibo = pIdRecibo) THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'notfound:No se encontró el recibo.';
    END IF;
    CALL sp_recibo_validar_destinatario(pDestinatarioTipo, pIdCuenta);

    UPDATE recibo
       SET tipo_recibo_id = pIdTipoRecibo, naturaleza = pNaturaleza, destinatario_tipo = pDestinatarioTipo,
           cuenta_id = pIdCuenta, monto = pMonto, tipo_pago = pTipoPago, comentario = pComentario, fecha = pFecha
     WHERE id_recibo = pIdRecibo;

    SELECT 1 AS Afectadas;
END $$

-- Regresa las filas afectadas (0 = no existía).
DROP PROCEDURE IF EXISTS sp_recibo_eliminar $$
CREATE PROCEDURE sp_recibo_eliminar(IN pIdRecibo INT)
SQL SECURITY INVOKER
BEGIN
    DELETE FROM recibo WHERE id_recibo = pIdRecibo;

    SELECT ROW_COUNT() AS Afectadas;
END $$

DELIMITER ;
