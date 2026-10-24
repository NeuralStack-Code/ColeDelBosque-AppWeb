-- ============================================================
-- Colegio del Bosque · Procedures de los catálogos chicos:
--   Tipos de recibo     (webService/controllers/TipoReciboController.php)
--   Tipos de descuento  (webService/controllers/TipoDescuentoController.php)
--   Permisos / roles    (webService/controllers/PermisoController.php)
-- Idempotente (DROP + CREATE). Antes: sp_sistema.sql
--
-- Las columnas salen con el nombre de la propiedad de su model (TipoRecibo, TipoDescuento, Permiso).
-- ============================================================

DELIMITER $$

-- ─────────────── Tipos de recibo (tabla tipo_recibo) ───────────────

DROP PROCEDURE IF EXISTS sp_tiporecibo_listar $$
CREATE PROCEDURE sp_tiporecibo_listar()
SQL SECURITY INVOKER
BEGIN
    SELECT id_tipo AS IdTipo, nombre AS Nombre, naturaleza AS Naturaleza
      FROM tipo_recibo
     ORDER BY nombre;
END $$

DROP PROCEDURE IF EXISTS sp_tiporecibo_crear $$
CREATE PROCEDURE sp_tiporecibo_crear(IN pNombre VARCHAR(50), IN pNaturaleza VARCHAR(10))
SQL SECURITY INVOKER
BEGIN
    INSERT INTO tipo_recibo (nombre, naturaleza) VALUES (pNombre, pNaturaleza);

    SELECT LAST_INSERT_ID() AS Id;
END $$

DROP PROCEDURE IF EXISTS sp_tiporecibo_editar $$
CREATE PROCEDURE sp_tiporecibo_editar(IN pIdTipo INT, IN pNombre VARCHAR(50), IN pNaturaleza VARCHAR(10))
SQL SECURITY INVOKER
BEGIN
    IF NOT EXISTS (SELECT 1 FROM tipo_recibo WHERE id_tipo = pIdTipo) THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'notfound:No se encontró el tipo de recibo.';
    END IF;

    UPDATE tipo_recibo SET nombre = pNombre, naturaleza = pNaturaleza WHERE id_tipo = pIdTipo;

    SELECT 1 AS Afectadas;
END $$

-- 'en_uso' si ya hay recibos de ese tipo.
DROP PROCEDURE IF EXISTS sp_tiporecibo_eliminar $$
CREATE PROCEDURE sp_tiporecibo_eliminar(IN pIdTipo INT)
SQL SECURITY INVOKER
BEGIN
    IF EXISTS (SELECT 1 FROM recibo WHERE tipo_recibo_id = pIdTipo) THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'en_uso:No se puede eliminar: hay recibos con este tipo.';
    END IF;

    DELETE FROM tipo_recibo WHERE id_tipo = pIdTipo;

    SELECT ROW_COUNT() AS Afectadas;
END $$

-- ─────────────── Tipos de descuento (tabla tipo_descuento) ───────────────
-- Su estado vive en el catálogo status (ámbito 'descuento': activo / inactivo).

DROP PROCEDURE IF EXISTS sp_tipodescuento_listar $$
CREATE PROCEDURE sp_tipodescuento_listar()
SQL SECURITY INVOKER
BEGIN
    SELECT t.id_descuento AS IdDescuento, t.nombre AS Nombre, t.porcentaje AS Porcentaje, t.aplica_a AS AplicaA,
           COALESCE(s.clave = 'activo', 0) AS Activo
      FROM tipo_descuento t
      LEFT JOIN status s ON s.id_status = t.status_id
     ORDER BY t.aplica_a, t.nombre;
END $$

-- Nace activo.
DROP PROCEDURE IF EXISTS sp_tipodescuento_crear $$
CREATE PROCEDURE sp_tipodescuento_crear(IN pNombre VARCHAR(80), IN pPorcentaje DECIMAL(5,2), IN pAplicaA VARCHAR(15))
SQL SECURITY INVOKER
BEGIN
    INSERT INTO tipo_descuento (nombre, porcentaje, aplica_a, status_id)
    VALUES (pNombre, pPorcentaje, pAplicaA,
            (SELECT id_status FROM status WHERE ambito = 'descuento' AND clave = 'activo'));

    SELECT LAST_INSERT_ID() AS Id;
END $$

DROP PROCEDURE IF EXISTS sp_tipodescuento_editar $$
CREATE PROCEDURE sp_tipodescuento_editar(
    IN pIdDescuento INT, IN pNombre VARCHAR(80), IN pPorcentaje DECIMAL(5,2), IN pAplicaA VARCHAR(15), IN pActivo TINYINT
)
SQL SECURITY INVOKER
BEGIN
    IF NOT EXISTS (SELECT 1 FROM tipo_descuento WHERE id_descuento = pIdDescuento) THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'notfound:No se encontró el descuento.';
    END IF;

    UPDATE tipo_descuento
       SET nombre = pNombre, porcentaje = pPorcentaje, aplica_a = pAplicaA,
           status_id = (SELECT id_status FROM status WHERE ambito = 'descuento' AND clave = IF(pActivo = 1, 'activo', 'inactivo'))
     WHERE id_descuento = pIdDescuento;

    SELECT 1 AS Afectadas;
END $$

-- 'en_uso' si ya hay pagos con ese descuento aplicado (en ese caso se desactiva, no se borra).
DROP PROCEDURE IF EXISTS sp_tipodescuento_eliminar $$
CREATE PROCEDURE sp_tipodescuento_eliminar(IN pIdDescuento INT)
SQL SECURITY INVOKER
BEGIN
    IF EXISTS (SELECT 1 FROM colegiatura WHERE tipo_descuento_id = pIdDescuento) THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'en_uso:No se puede eliminar: hay pagos con este descuento aplicado. Puedes desactivarlo.';
    END IF;

    DELETE FROM tipo_descuento WHERE id_descuento = pIdDescuento;

    SELECT ROW_COUNT() AS Afectadas;
END $$

-- ─────────────── Permisos / roles (tabla permisos) ───────────────

DROP PROCEDURE IF EXISTS sp_permiso_listar $$
CREATE PROCEDURE sp_permiso_listar()
SQL SECURITY INVOKER
BEGIN
    SELECT p.id_permiso AS IdPermiso, p.nombre AS Nombre,
           (SELECT COUNT(*) FROM cuenta c WHERE c.permiso_id = p.id_permiso) AS NumCuentas
      FROM permisos p
     ORDER BY p.id_permiso;
END $$

DROP PROCEDURE IF EXISTS sp_permiso_crear $$
CREATE PROCEDURE sp_permiso_crear(IN pNombre VARCHAR(25))
SQL SECURITY INVOKER
BEGIN
    INSERT INTO permisos (nombre) VALUES (pNombre);

    SELECT LAST_INSERT_ID() AS Id;
END $$

DROP PROCEDURE IF EXISTS sp_permiso_editar $$
CREATE PROCEDURE sp_permiso_editar(IN pIdPermiso INT, IN pNombre VARCHAR(25))
SQL SECURITY INVOKER
BEGIN
    IF NOT EXISTS (SELECT 1 FROM permisos WHERE id_permiso = pIdPermiso) THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'notfound:No se encontró el rol.';
    END IF;

    UPDATE permisos SET nombre = pNombre WHERE id_permiso = pIdPermiso;

    SELECT 1 AS Afectadas;
END $$

-- 'en_uso' si hay cuentas con ese rol. (Los roles base 1-4 los protege el controller.)
DROP PROCEDURE IF EXISTS sp_permiso_eliminar $$
CREATE PROCEDURE sp_permiso_eliminar(IN pIdPermiso INT)
SQL SECURITY INVOKER
BEGIN
    IF EXISTS (SELECT 1 FROM cuenta WHERE permiso_id = pIdPermiso) THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'en_uso:No se puede eliminar: hay cuentas con este rol.';
    END IF;

    DELETE FROM permisos WHERE id_permiso = pIdPermiso;

    SELECT ROW_COUNT() AS Afectadas;
END $$

DELIMITER ;
