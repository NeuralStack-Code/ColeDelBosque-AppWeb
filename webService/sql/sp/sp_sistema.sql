-- ============================================================
-- Procedures del framework (los usa apiService/core/Command.php)
-- INSTALAR PRIMERO: sin este archivo ningún business puede ejecutar sus procedures.
-- Idempotente (DROP + CREATE). En phpMyAdmin: selecciona tu base → pestaña SQL → pega y ejecuta.
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
