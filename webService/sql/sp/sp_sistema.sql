-- ============================================================
-- Procedures del framework (los usa apiService/core/Command.php)
-- INSTALAR PRIMERO: sin este archivo ningún business puede ejecutar sus procedures.
-- Idempotente (DROP + CREATE). En phpMyAdmin: selecciona tu base → pestaña SQL → pega y ejecuta.
--
-- Convención de transacciones (1.5.0). ServerApi::transaction() corre varios comandos con UN commit. En MySQL/MariaDB
-- un START TRANSACTION dentro de un procedure CONFIRMA la transacción de afuera, así que un procedure que pueda correr
-- dentro de transaction() solo abre la suya si no hay otra:
--
--     DECLARE vPropia TINYINT DEFAULT 0;
--     DECLARE EXIT HANDLER FOR SQLEXCEPTION BEGIN IF vPropia = 1 THEN ROLLBACK; END IF; RESIGNAL; END;
--     IF @@in_transaction = 0 THEN START TRANSACTION; SET vPropia = 1; END IF;
--     …
--     IF vPropia = 1 THEN COMMIT; END IF;
--
-- Command revisa cada procedure antes de correrlo dentro de transaction() (sp_sistema_firmas) y detiene el que tenga
-- START TRANSACTION sin @@in_transaction. Fuera de transaction() los de la forma anterior siguen funcionando igual.
-- ============================================================

DELIMITER $$

-- Firma de un procedure: sus parámetros en orden. Con esto Command acepta los parámetros POR NOMBRE
-- y los acomoda en la posición correcta (MySQL/MariaDB solo aceptan posición en un CALL).
-- information_schema usa otro juego de caracteres: se convierte y se fija la intercalación para comparar.
DROP PROCEDURE IF EXISTS sp_sistema_parametros $$
CREATE PROCEDURE sp_sistema_parametros(IN pProcedure VARCHAR(64))
SQL SECURITY INVOKER
BEGIN
    SELECT PARAMETER_NAME AS Nombre
      FROM information_schema.PARAMETERS
     WHERE SPECIFIC_SCHEMA = DATABASE()
       AND ROUTINE_TYPE = 'PROCEDURE'
       AND CONVERT(SPECIFIC_NAME USING utf8mb4) COLLATE utf8mb4_unicode_ci = pProcedure COLLATE utf8mb4_unicode_ci
     ORDER BY ORDINAL_POSITION;
END $$

DELIMITER ;

-- ── Desde la 1.5.0 ──────────────────────────────────────────────────────────────────────────────────────────────
DELIMITER $$

-- Versión de los procedures de esta base: cambia con cada CREATE, DROP o ALTER de un procedure. Command la consulta
-- UNA vez por petición y, mientras no cambie, usa las firmas que guardó en su caché (sin una consulta por procedure).
DROP PROCEDURE IF EXISTS sp_sistema_version $$
CREATE PROCEDURE sp_sistema_version()
SQL SECURITY INVOKER
BEGIN
    -- Cuántos hay, cuándo cambió el último y una huella de los parámetros de todos (nombre, posición, procedure): un
    -- procedure que se vuelve a crear con otros parámetros cambia la versión aunque sea en el mismo segundo.
    SELECT DATABASE() AS Base,
           CONCAT((SELECT COUNT(*) FROM information_schema.ROUTINES
                    WHERE ROUTINE_SCHEMA = DATABASE() AND ROUTINE_TYPE = 'PROCEDURE'), '|',
                  (SELECT COALESCE(MAX(LAST_ALTERED), '') FROM information_schema.ROUTINES
                    WHERE ROUTINE_SCHEMA = DATABASE() AND ROUTINE_TYPE = 'PROCEDURE'), '|',
                  (SELECT COALESCE(SUM(CRC32(CONCAT(SPECIFIC_NAME, ':', ORDINAL_POSITION, ':', COALESCE(PARAMETER_NAME, '')))), 0)
                     FROM information_schema.PARAMETERS
                    WHERE SPECIFIC_SCHEMA = DATABASE() AND ROUTINE_TYPE = 'PROCEDURE')) AS Version;
END $$

-- Las firmas de TODOS los procedures de la base (una fila por parámetro; un procedure sin parámetros trae una fila con
-- Nombre NULL) y si el procedure abre su propia transacción sin respetar la de afuera (AbreTransaccion = 1: tiene
-- START TRANSACTION y no revisa @@in_transaction). ServerApi::transaction() no deja usar esos (ver core/ServerApi.php).
DROP PROCEDURE IF EXISTS sp_sistema_firmas $$
CREATE PROCEDURE sp_sistema_firmas()
SQL SECURITY INVOKER
BEGIN
    SELECT CONVERT(r.SPECIFIC_NAME USING utf8mb4) AS Procedimiento,
           CONVERT(p.PARAMETER_NAME USING utf8mb4) AS Nombre,
           IF(r.ROUTINE_DEFINITION LIKE '%START TRANSACTION%' AND r.ROUTINE_DEFINITION NOT LIKE '%@@in_transaction%', 1, 0)
               AS AbreTransaccion
      FROM information_schema.ROUTINES r
      LEFT JOIN information_schema.PARAMETERS p
             ON p.SPECIFIC_SCHEMA = r.ROUTINE_SCHEMA AND p.SPECIFIC_NAME = r.SPECIFIC_NAME AND p.ROUTINE_TYPE = 'PROCEDURE'
            AND p.ORDINAL_POSITION > 0
     WHERE r.ROUTINE_SCHEMA = DATABASE() AND r.ROUTINE_TYPE = 'PROCEDURE'
     ORDER BY r.SPECIFIC_NAME, p.ORDINAL_POSITION;
END $$

DELIMITER ;
