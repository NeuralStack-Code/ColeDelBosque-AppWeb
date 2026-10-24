-- ============================================================
-- Colegio del Bosque · Procedures del panel del administrador (webService/controllers/DashboardController.php)
-- y del visor de tablas del desarrollador (webService/controllers/TablasController.php).
-- Idempotente (DROP + CREATE). Antes: sp_sistema.sql
-- ============================================================

DELIMITER $$

-- Los números del panel, en una fila (model DashboardResumen).
DROP PROCEDURE IF EXISTS sp_dashboard_resumen $$
CREATE PROCEDURE sp_dashboard_resumen()
SQL SECURITY INVOKER
BEGIN
    DECLARE vPagado INT DEFAULT (SELECT id_status FROM status WHERE ambito = 'pago' AND clave = 'pagado');

    SELECT
        (SELECT COUNT(*) FROM cuenta WHERE permiso_id = 3) AS Alumnos,
        (SELECT COUNT(*) FROM cuenta WHERE permiso_id = 2) AS Maestros,
        (SELECT COUNT(*) FROM grupo) AS Grupos,
        (SELECT COALESCE(SUM(CASE WHEN naturaleza = 'ingreso' THEN monto ELSE -monto END), 0) FROM recibo) AS Saldo,
        (SELECT COUNT(*) FROM colegiatura WHERE status_id <> vPagado) AS PagosPendientes,
        (SELECT COUNT(*) FROM colegiatura WHERE status_id =  vPagado) AS PagosPagados;
END $$

-- Cuántos alumnos tiene cada grupo (model GrupoConteo).
DROP PROCEDURE IF EXISTS sp_dashboard_alumnos_por_grupo $$
CREATE PROCEDURE sp_dashboard_alumnos_por_grupo()
SQL SECURITY INVOKER
BEGIN
    SELECT g.grado AS Grado, COUNT(c.id_cuenta) AS Total
      FROM grupo g
      LEFT JOIN cuenta c ON c.grupo_id = g.id_grupo AND c.permiso_id = 3
     GROUP BY g.id_grupo, g.grado
     ORDER BY g.grado;
END $$

-- ─────────────── Visor de tablas (solo lectura, solo el desarrollador) ───────────────
-- El nombre de la tabla nunca se arma con lo que llega de fuera: siempre se toma de information_schema.

-- Cada tabla de la base con su número exacto de filas (model TablaResumen).
DROP PROCEDURE IF EXISTS sp_tablas_listar $$
CREATE PROCEDURE sp_tablas_listar()
SQL SECURITY INVOKER
BEGIN
    DECLARE vTabla VARCHAR(64);
    DECLARE vFin   INT DEFAULT 0;
    DECLARE cur CURSOR FOR
        SELECT TABLE_NAME FROM information_schema.TABLES
         WHERE TABLE_SCHEMA = DATABASE() AND TABLE_TYPE = 'BASE TABLE'
         ORDER BY TABLE_NAME;
    DECLARE CONTINUE HANDLER FOR NOT FOUND SET vFin = 1;

    DROP TEMPORARY TABLE IF EXISTS tmp_tablas;
    CREATE TEMPORARY TABLE tmp_tablas (Tabla VARCHAR(64), Filas INT);

    OPEN cur;
    recorrer: LOOP
        FETCH cur INTO vTabla;
        IF vFin = 1 THEN LEAVE recorrer; END IF;
        SET @sql = CONCAT('INSERT INTO tmp_tablas SELECT ''', vTabla, ''', COUNT(*) FROM `', vTabla, '`');
        PREPARE stmt FROM @sql;
        EXECUTE stmt;
        DEALLOCATE PREPARE stmt;
    END LOOP;
    CLOSE cur;

    SELECT Tabla, Filas FROM tmp_tablas ORDER BY Tabla;
    DROP TEMPORARY TABLE tmp_tablas;
END $$

-- Los nombres de las columnas de una tabla, en su orden. Sin filas = la tabla no existe.
DROP PROCEDURE IF EXISTS sp_tablas_columnas $$
CREATE PROCEDURE sp_tablas_columnas(IN pTabla VARCHAR(64))
SQL SECURITY INVOKER
BEGIN
    SELECT COLUMN_NAME AS Columna
      FROM information_schema.COLUMNS
     WHERE TABLE_SCHEMA = DATABASE()
       AND CONVERT(TABLE_NAME USING utf8mb4) COLLATE utf8mb4_unicode_ci = pTabla COLLATE utf8mb4_unicode_ci
     ORDER BY ORDINAL_POSITION;
END $$

-- Las primeras pLimite filas de una tabla. 'notfound' si la tabla no existe.
DROP PROCEDURE IF EXISTS sp_tablas_ver $$
CREATE PROCEDURE sp_tablas_ver(IN pTabla VARCHAR(64), IN pLimite INT)
SQL SECURITY INVOKER
BEGIN
    DECLARE vTabla VARCHAR(64) DEFAULT NULL;

    SELECT TABLE_NAME INTO vTabla
      FROM information_schema.TABLES
     WHERE TABLE_SCHEMA = DATABASE() AND TABLE_TYPE = 'BASE TABLE'
       AND CONVERT(TABLE_NAME USING utf8mb4) COLLATE utf8mb4_unicode_ci = pTabla COLLATE utf8mb4_unicode_ci
     LIMIT 1;

    IF vTabla IS NULL THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'notfound:Tabla no válida.';
    END IF;

    SET @sql = CONCAT('SELECT * FROM `', vTabla, '` LIMIT ', GREATEST(1, LEAST(500, pLimite)));
    PREPARE stmt FROM @sql;
    EXECUTE stmt;
    DEALLOCATE PREPARE stmt;
END $$

DELIMITER ;
