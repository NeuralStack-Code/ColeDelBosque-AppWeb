-- ============================================================
-- Colegio del Bosque · Procedures del reporte semanal a papás (webService/controllers/ReporteController.php)
-- Idempotente (DROP + CREATE). Antes: sp_sistema.sql y sp_calificacion.sql
--
-- La maestra captura cada clase (grupo + materia + día, tabla reporte_clase) con su tema y las incidencias de cada
-- alumno (reporte_marca, una por alumno y columna). Al enviar el reporte se registra en reporte_envio.
-- Todo va acotado al grupo de la maestra: el controller ya revisó que el grupo sea suyo.
-- ============================================================

DELIMITER $$

-- Los alumnos del grupo y si tienen algún correo de tutor a dónde mandarles el reporte.
DROP PROCEDURE IF EXISTS sp_reporte_alumnos $$
CREATE PROCEDURE sp_reporte_alumnos(IN pIdGrupo INT)
SQL SECURITY INVOKER
BEGIN
    SELECT c.id_cuenta AS IdCuenta, TRIM(CONCAT_WS(' ', u.nombre, u.paterno, u.materno)) AS NombreCompleto,
           (COALESCE(c.correo_tutor, '') <> '' OR COALESCE(c.correo_tutor2, '') <> '') AS TieneCorreo
      FROM cuenta c
      JOIN usuario u ON u.id_usuario = c.usuario_id
     WHERE c.grupo_id = pIdGrupo AND c.permiso_id = 3
     ORDER BY u.paterno, u.nombre;
END $$

-- Un alumno del grupo con los correos de sus tutores (encriptados). Ninguna fila si no es de ese grupo.
DROP PROCEDURE IF EXISTS sp_reporte_alumno $$
CREATE PROCEDURE sp_reporte_alumno(IN pIdCuenta INT, IN pIdGrupo INT)
SQL SECURITY INVOKER
BEGIN
    SELECT c.id_cuenta AS IdCuenta, TRIM(CONCAT_WS(' ', u.nombre, u.paterno, u.materno)) AS NombreCompleto,
           c.correo_tutor AS CorreoTutor, c.correo_tutor2 AS CorreoTutor2
      FROM cuenta c
      JOIN usuario u ON u.id_usuario = c.usuario_id
     WHERE c.id_cuenta = pIdCuenta AND c.grupo_id = pIdGrupo AND c.permiso_id = 3;
END $$

-- Las clases capturadas en el rango, con cuántos alumnos tuvieron alguna incidencia.
DROP PROCEDURE IF EXISTS sp_reporte_clases $$
CREATE PROCEDURE sp_reporte_clases(IN pIdGrupo INT, IN pDesde DATE, IN pHasta DATE)
SQL SECURITY INVOKER
BEGIN
    SELECT rc.id_clase AS IdClase, rc.fecha AS Fecha, rc.materia_id AS IdMateria, m.nombre AS Materia, rc.tema AS Tema,
           (SELECT COUNT(DISTINCT rm.cuenta_id) FROM reporte_marca rm WHERE rm.clase_id = rc.id_clase) AS Incidencias
      FROM reporte_clase rc
      JOIN materia m ON m.id_materia = rc.materia_id
     WHERE rc.grupo_id = pIdGrupo AND rc.fecha BETWEEN pDesde AND pHasta
     ORDER BY rc.fecha, rc.id_clase;
END $$

-- La clase de un día y materia (ninguna fila si aún no se captura).
DROP PROCEDURE IF EXISTS sp_reporte_clase $$
CREATE PROCEDURE sp_reporte_clase(IN pIdGrupo INT, IN pIdMateria INT, IN pFecha DATE)
SQL SECURITY INVOKER
BEGIN
    SELECT id_clase AS IdClase, materia_id AS IdMateria, tema AS Tema
      FROM reporte_clase
     WHERE grupo_id = pIdGrupo AND materia_id = pIdMateria AND fecha = pFecha
     LIMIT 1;
END $$

-- Las incidencias de una clase.
DROP PROCEDURE IF EXISTS sp_reporte_marcas $$
CREATE PROCEDURE sp_reporte_marcas(IN pIdClase INT)
SQL SECURITY INVOKER
BEGIN
    SELECT cuenta_id AS IdCuenta, columna_id AS IdColumna, marcado AS Marcado, nota AS Nota
      FROM reporte_marca
     WHERE clase_id = pIdClase;
END $$

-- Guarda la clase (o le cambia el tema) y REEMPLAZA sus incidencias, todo o nada.
-- pMarcas: arreglo JSON [{"IdCuenta":1,"IdColumna":2,"Marcado":1,"Nota":""}, …] (Nota vacía = sin nota).
-- Regresa el Id de la clase.
DROP PROCEDURE IF EXISTS sp_reporte_guardar_clase $$
CREATE PROCEDURE sp_reporte_guardar_clase(IN pIdGrupo INT, IN pIdMateria INT, IN pIdCiclo INT, IN pFecha DATE,
                                          IN pTema VARCHAR(255), IN pMarcas LONGTEXT)
SQL SECURITY INVOKER
BEGIN
    DECLARE vIdClase INT;
    DECLARE vTotal INT;
    DECLARE i INT DEFAULT 0;
    DECLARE EXIT HANDLER FOR SQLEXCEPTION
    BEGIN
        ROLLBACK;
        RESIGNAL;
    END;

    START TRANSACTION;

    -- LAST_INSERT_ID(id_clase) hace que sirva también cuando la clase ya existía
    INSERT INTO reporte_clase (grupo_id, materia_id, ciclo_id, fecha, tema) VALUES (pIdGrupo, pIdMateria, pIdCiclo, pFecha, pTema)
    ON DUPLICATE KEY UPDATE tema = VALUES(tema), id_clase = LAST_INSERT_ID(id_clase);
    SET vIdClase = LAST_INSERT_ID();

    DELETE FROM reporte_marca WHERE clase_id = vIdClase;

    SET vTotal = COALESCE(JSON_LENGTH(pMarcas), 0);
    WHILE i < vTotal DO
        INSERT INTO reporte_marca (clase_id, cuenta_id, columna_id, marcado, nota)
        VALUES (vIdClase,
                JSON_EXTRACT(pMarcas, CONCAT('$[', i, '].IdCuenta')),
                JSON_EXTRACT(pMarcas, CONCAT('$[', i, '].IdColumna')),
                JSON_EXTRACT(pMarcas, CONCAT('$[', i, '].Marcado')),
                NULLIF(JSON_UNQUOTE(JSON_EXTRACT(pMarcas, CONCAT('$[', i, '].Nota'))), ''));
        SET i = i + 1;
    END WHILE;

    COMMIT;

    SELECT vIdClase AS Id;
END $$

-- Regresa las filas afectadas (0 = no existe o no es del grupo). Sus incidencias caen por la llave foránea.
DROP PROCEDURE IF EXISTS sp_reporte_eliminar_clase $$
CREATE PROCEDURE sp_reporte_eliminar_clase(IN pIdClase INT, IN pIdGrupo INT)
SQL SECURITY INVOKER
BEGIN
    DELETE FROM reporte_clase WHERE id_clase = pIdClase AND grupo_id = pIdGrupo;

    SELECT ROW_COUNT() AS Afectadas;
END $$

-- Una clase del grupo (ninguna fila si no existe o es de otro grupo): para saber su materia antes de borrarla.
DROP PROCEDURE IF EXISTS sp_reporte_clase_obtener $$
CREATE PROCEDURE sp_reporte_clase_obtener(IN pIdClase INT, IN pIdGrupo INT)
SQL SECURITY INVOKER
BEGIN
    SELECT id_clase AS IdClase, materia_id AS IdMateria, tema AS Tema
      FROM reporte_clase
     WHERE id_clase = pIdClase AND grupo_id = pIdGrupo;
END $$

-- Las clases de la semana vistas por UN alumno: una fila por clase y columna con incidencia (IdColumna vacío si
-- no tuvo ninguna en esa clase). El controller las agrupa por clase para el correo.
DROP PROCEDURE IF EXISTS sp_reporte_semana_alumno $$
CREATE PROCEDURE sp_reporte_semana_alumno(IN pIdCuenta INT, IN pIdGrupo INT, IN pDesde DATE, IN pHasta DATE)
SQL SECURITY INVOKER
BEGIN
    SELECT rc.id_clase AS IdClase, rc.fecha AS Fecha, m.nombre AS Materia, rc.tema AS Tema,
           rm.columna_id AS IdColumna, rm.marcado AS Marcado, rm.nota AS Nota
      FROM reporte_clase rc
      JOIN materia m ON m.id_materia = rc.materia_id
      LEFT JOIN reporte_marca rm ON rm.clase_id = rc.id_clase AND rm.cuenta_id = pIdCuenta
     WHERE rc.grupo_id = pIdGrupo AND rc.fecha BETWEEN pDesde AND pHasta
     ORDER BY rc.fecha, rc.id_clase;
END $$

-- Cuándo se le mandó a cada alumno del grupo el reporte de la semana que empieza en pSemana (lunes).
DROP PROCEDURE IF EXISTS sp_reporte_envios $$
CREATE PROCEDURE sp_reporte_envios(IN pIdGrupo INT, IN pSemana DATE)
SQL SECURITY INVOKER
BEGIN
    SELECT e.cuenta_id AS IdCuenta, e.enviado_en AS EnviadoEn
      FROM reporte_envio e
      JOIN cuenta c ON c.id_cuenta = e.cuenta_id
     WHERE c.grupo_id = pIdGrupo AND e.semana = pSemana;
END $$

-- Registra (o actualiza) el envío del reporte de una semana a un alumno.
DROP PROCEDURE IF EXISTS sp_reporte_registrar_envio $$
CREATE PROCEDURE sp_reporte_registrar_envio(IN pIdCuenta INT, IN pSemana DATE, IN pEnviadoEn DATETIME,
                                            IN pEnviadoPor INT, IN pDestinatarios INT)
SQL SECURITY INVOKER
BEGIN
    INSERT INTO reporte_envio (cuenta_id, semana, enviado_en, enviado_por, destinatarios)
    VALUES (pIdCuenta, pSemana, pEnviadoEn, pEnviadoPor, pDestinatarios)
    ON DUPLICATE KEY UPDATE enviado_en = VALUES(enviado_en), enviado_por = VALUES(enviado_por), destinatarios = VALUES(destinatarios);

    SELECT 1 AS Afectadas;
END $$

DELIMITER ;
