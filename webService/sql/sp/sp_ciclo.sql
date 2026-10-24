-- ============================================================
-- Colegio del Bosque · Procedures del módulo Ciclo (webService/controllers/CicloController.php)
-- Idempotente (DROP + CREATE). Antes: sp_sistema.sql
--
-- El estado de un ciclo vive en el catálogo status (ámbito 'ciclo': activo / cerrado). Solo uno está activo.
-- Las columnas salen con el nombre de la propiedad del model Ciclo.
-- ============================================================

DELIMITER $$

DROP PROCEDURE IF EXISTS sp_ciclo_listar $$
CREATE PROCEDURE sp_ciclo_listar()
SQL SECURITY INVOKER
BEGIN
    SELECT c.id_ciclo AS IdCiclo, c.nombre AS Nombre, c.fecha_inicio AS FechaInicio, c.fecha_fin AS FechaFin,
           COALESCE(s.clave = 'activo', 0) AS Activo, s.clave AS Estatus, s.nombre AS EstatusNombre
      FROM ciclo c
      LEFT JOIN status s ON s.id_status = c.status_id
     ORDER BY c.fecha_inicio DESC;
END $$

-- Un ciclo nace cerrado: se activa aparte (sp_ciclo_activar).
DROP PROCEDURE IF EXISTS sp_ciclo_crear $$
CREATE PROCEDURE sp_ciclo_crear(IN pNombre VARCHAR(30), IN pFechaInicio DATE, IN pFechaFin DATE)
SQL SECURITY INVOKER
BEGIN
    INSERT INTO ciclo (nombre, fecha_inicio, fecha_fin, status_id)
    VALUES (pNombre, pFechaInicio, pFechaFin,
            (SELECT id_status FROM status WHERE ambito = 'ciclo' AND clave = 'cerrado'));

    SELECT LAST_INSERT_ID() AS Id;
END $$

DROP PROCEDURE IF EXISTS sp_ciclo_editar $$
CREATE PROCEDURE sp_ciclo_editar(IN pIdCiclo INT, IN pNombre VARCHAR(30), IN pFechaInicio DATE, IN pFechaFin DATE)
SQL SECURITY INVOKER
BEGIN
    IF NOT EXISTS (SELECT 1 FROM ciclo WHERE id_ciclo = pIdCiclo) THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'notfound:No se encontró el ciclo.';
    END IF;

    UPDATE ciclo SET nombre = pNombre, fecha_inicio = pFechaInicio, fecha_fin = pFechaFin WHERE id_ciclo = pIdCiclo;

    SELECT 1 AS Afectadas;
END $$

-- Activa un ciclo y cierra los demás, todo o nada.
DROP PROCEDURE IF EXISTS sp_ciclo_activar $$
CREATE PROCEDURE sp_ciclo_activar(IN pIdCiclo INT)
SQL SECURITY INVOKER
BEGIN
    DECLARE EXIT HANDLER FOR SQLEXCEPTION
    BEGIN
        ROLLBACK;
        RESIGNAL;
    END;

    START TRANSACTION;

    IF NOT EXISTS (SELECT 1 FROM ciclo WHERE id_ciclo = pIdCiclo) THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'notfound:No se encontró el ciclo.';
    END IF;

    UPDATE ciclo
       SET status_id = (SELECT id_status FROM status WHERE ambito = 'ciclo' AND clave = 'cerrado')
     WHERE id_ciclo <> pIdCiclo;
    UPDATE ciclo
       SET status_id = (SELECT id_status FROM status WHERE ambito = 'ciclo' AND clave = 'activo')
     WHERE id_ciclo = pIdCiclo;

    COMMIT;

    SELECT 1 AS Afectadas;
END $$

-- OJO: los esquemas de pago del ciclo se borran con él (esquema_pago tiene ON DELETE CASCADE).
DROP PROCEDURE IF EXISTS sp_ciclo_eliminar $$
CREATE PROCEDURE sp_ciclo_eliminar(IN pIdCiclo INT)
SQL SECURITY INVOKER
BEGIN
    DELETE FROM ciclo WHERE id_ciclo = pIdCiclo;

    SELECT ROW_COUNT() AS Afectadas;
END $$

-- El ciclo activo (ninguna fila si no hay). Lo usan colegiaturas, calificaciones, reportes y el portal del padre.
DROP PROCEDURE IF EXISTS sp_ciclo_activo $$
CREATE PROCEDURE sp_ciclo_activo()
SQL SECURITY INVOKER
BEGIN
    SELECT c.id_ciclo AS IdCiclo, c.nombre AS Nombre, c.fecha_inicio AS FechaInicio, c.fecha_fin AS FechaFin, 1 AS Activo
      FROM ciclo c
     WHERE c.status_id = (SELECT id_status FROM status WHERE ambito = 'ciclo' AND clave = 'activo')
     LIMIT 1;
END $$

-- Un ciclo con sus fechas (ninguna fila si no existe). Lo usa el esquema de pago para validar sus meses.
DROP PROCEDURE IF EXISTS sp_ciclo_obtener $$
CREATE PROCEDURE sp_ciclo_obtener(IN pIdCiclo INT)
SQL SECURITY INVOKER
BEGIN
    SELECT c.id_ciclo AS IdCiclo, c.nombre AS Nombre, c.fecha_inicio AS FechaInicio, c.fecha_fin AS FechaFin,
           COALESCE(s.clave = 'activo', 0) AS Activo
      FROM ciclo c
      LEFT JOIN status s ON s.id_status = c.status_id
     WHERE c.id_ciclo = pIdCiclo;
END $$

DELIMITER ;
