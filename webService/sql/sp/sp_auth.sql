-- ============================================================
-- Colegio del Bosque · Procedures del módulo Auth (webService/controllers/AuthController.php)
-- Idempotente (DROP + CREATE). Antes: sp_sistema.sql
--
-- Se entra con la matrícula. En la base va ENCRIPTADA (apiService/core/crypto.php): aquí llega ya encriptada.
--
-- OJO intercalaciones: las tablas son utf8mb4_unicode_ci y la conexión puede llegar con otra. Al comparar un
-- parámetro de texto contra una columna se fija con COLLATE o falla con "Illegal mix of collations".
-- ============================================================

DELIMITER $$

-- La cuenta de una matrícula, con el nombre de la persona. Sin filas = matrícula incorrecta.
DROP PROCEDURE IF EXISTS sp_auth_autenticar $$
CREATE PROCEDURE sp_auth_autenticar(IN pMatricula VARCHAR(150))
SQL SECURITY INVOKER
BEGIN
    SELECT c.id_cuenta AS IdCuenta, c.usuario_id AS IdUsuario, c.permiso_id AS IdPermiso,
           u.nombre AS Nombre, u.paterno AS Paterno
      FROM cuenta c
      JOIN usuario u ON u.id_usuario = c.usuario_id
     WHERE c.matricula = pMatricula COLLATE utf8mb4_unicode_ci
     LIMIT 1;
END $$

DELIMITER ;
