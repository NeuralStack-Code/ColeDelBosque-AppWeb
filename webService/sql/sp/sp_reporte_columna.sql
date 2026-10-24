-- ============================================================
-- Colegio del Bosque · Procedures de las columnas del reporte semanal
-- (webService/controllers/ReporteColumnaController.php; las activas también las usa ReporteController)
-- Idempotente (DROP + CREATE). Antes: sp_sistema.sql
--
-- Cada columna es una incidencia que la maestra marca (casilla) o escribe (texto) por alumno y clase.
-- Las columnas salen con el nombre de la propiedad del model ReporteColumna.
-- ============================================================

DELIMITER $$

-- Todas las columnas, o solo las activas (pSoloActivas = 1), en su orden.
DROP PROCEDURE IF EXISTS sp_reporte_columna_listar $$
CREATE PROCEDURE sp_reporte_columna_listar(IN pSoloActivas TINYINT)
SQL SECURITY INVOKER
BEGIN
    SELECT id_columna AS IdColumna, nombre AS Nombre, tipo AS Tipo, etiqueta AS Etiqueta, orden AS Orden, activo AS Activo
      FROM reporte_columna
     WHERE COALESCE(pSoloActivas, 0) = 0 OR activo = 1
     ORDER BY orden, id_columna;
END $$

DROP PROCEDURE IF EXISTS sp_reporte_columna_crear $$
CREATE PROCEDURE sp_reporte_columna_crear(IN pNombre VARCHAR(60), IN pTipo VARCHAR(10), IN pEtiqueta VARCHAR(60),
                                          IN pOrden INT, IN pActivo TINYINT)
SQL SECURITY INVOKER
BEGIN
    INSERT INTO reporte_columna (nombre, tipo, etiqueta, orden, activo) VALUES (pNombre, pTipo, pEtiqueta, pOrden, pActivo);

    SELECT LAST_INSERT_ID() AS Id;
END $$

-- 'notfound' si no existe.
DROP PROCEDURE IF EXISTS sp_reporte_columna_editar $$
CREATE PROCEDURE sp_reporte_columna_editar(IN pIdColumna INT, IN pNombre VARCHAR(60), IN pTipo VARCHAR(10),
                                           IN pEtiqueta VARCHAR(60), IN pOrden INT, IN pActivo TINYINT)
SQL SECURITY INVOKER
BEGIN
    IF NOT EXISTS (SELECT 1 FROM reporte_columna WHERE id_columna = pIdColumna) THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'notfound:No se encontró la columna.';
    END IF;

    UPDATE reporte_columna
       SET nombre = pNombre, tipo = pTipo, etiqueta = pEtiqueta, orden = pOrden, activo = pActivo
     WHERE id_columna = pIdColumna;

    SELECT 1 AS Afectadas;
END $$

-- 'en_uso' si ya hay incidencias capturadas con ella (se oculta desactivándola). Regresa las filas afectadas.
DROP PROCEDURE IF EXISTS sp_reporte_columna_eliminar $$
CREATE PROCEDURE sp_reporte_columna_eliminar(IN pIdColumna INT)
SQL SECURITY INVOKER
BEGIN
    IF EXISTS (SELECT 1 FROM reporte_marca WHERE columna_id = pIdColumna) THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'en_uso:No se puede eliminar: ya hay reportes capturados con esta columna. Desactívala para ocultarla.';
    END IF;

    DELETE FROM reporte_columna WHERE id_columna = pIdColumna;

    SELECT ROW_COUNT() AS Afectadas;
END $$

DELIMITER ;
