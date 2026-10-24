-- ============================================================
-- Colegio del Bosque · Procedures del módulo Materia (webService/controllers/MateriaController.php)
-- Idempotente (DROP + CREATE). Antes: sp_sistema.sql
--
-- Una materia puede darse en VARIOS grupos (tabla grupo_materia). Los grupos de una materia viajan como
-- una lista de ids separados por coma ('69,70,71'): así un solo procedure guarda la materia y sus grupos.
-- Las columnas salen con el nombre de la propiedad del model Materia.
-- ============================================================

DELIMITER $$

DROP PROCEDURE IF EXISTS sp_materia_listar $$
CREATE PROCEDURE sp_materia_listar()
SQL SECURITY INVOKER
BEGIN
    SELECT m.id_materia AS IdMateria, m.nombre AS Nombre,
           (SELECT GROUP_CONCAT(g.grado ORDER BY g.grado SEPARATOR ', ')
              FROM grupo_materia gm JOIN grupo g ON g.id_grupo = gm.id_grupo
             WHERE gm.id_materia = m.id_materia) AS GruposTxt,
           (SELECT GROUP_CONCAT(gm.id_grupo ORDER BY gm.id_grupo)
              FROM grupo_materia gm WHERE gm.id_materia = m.id_materia) AS GruposCsv,
           (SELECT COUNT(*) FROM grupo_materia gm WHERE gm.id_materia = m.id_materia) AS NumGrupos
      FROM materia m
     ORDER BY m.nombre;
END $$

-- Alta de la materia con sus grupos, todo o nada. 'dup' si ya hay una con ese nombre.
DROP PROCEDURE IF EXISTS sp_materia_crear $$
CREATE PROCEDURE sp_materia_crear(IN pNombre VARCHAR(25), IN pGrupos VARCHAR(2000))
SQL SECURITY INVOKER
BEGIN
    DECLARE vIdMateria INT;
    DECLARE EXIT HANDLER FOR SQLEXCEPTION
    BEGIN
        ROLLBACK;
        RESIGNAL;
    END;

    START TRANSACTION;

    IF EXISTS (SELECT 1 FROM materia WHERE nombre = pNombre COLLATE utf8mb4_unicode_ci) THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'dup:Ya existe una materia con ese nombre.';
    END IF;

    INSERT INTO materia (nombre) VALUES (pNombre);
    SET vIdMateria = LAST_INSERT_ID();

    INSERT INTO grupo_materia (id_grupo, id_materia)
    SELECT g.id_grupo, vIdMateria FROM grupo g WHERE FIND_IN_SET(g.id_grupo, COALESCE(pGrupos, '')) > 0;

    COMMIT;

    SELECT vIdMateria AS Id;
END $$

-- Cambia el nombre y deja la materia EXACTAMENTE en los grupos de la lista, todo o nada.
-- 'notfound' si no existe; 'dup' si otra materia ya tiene ese nombre.
DROP PROCEDURE IF EXISTS sp_materia_editar $$
CREATE PROCEDURE sp_materia_editar(IN pIdMateria INT, IN pNombre VARCHAR(25), IN pGrupos VARCHAR(2000))
SQL SECURITY INVOKER
BEGIN
    DECLARE EXIT HANDLER FOR SQLEXCEPTION
    BEGIN
        ROLLBACK;
        RESIGNAL;
    END;

    START TRANSACTION;

    IF NOT EXISTS (SELECT 1 FROM materia WHERE id_materia = pIdMateria) THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'notfound:No se encontró la materia.';
    END IF;
    IF EXISTS (SELECT 1 FROM materia WHERE nombre = pNombre COLLATE utf8mb4_unicode_ci AND id_materia <> pIdMateria) THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'dup:Ya existe otra materia con ese nombre.';
    END IF;

    UPDATE materia SET nombre = pNombre WHERE id_materia = pIdMateria;

    DELETE FROM grupo_materia
     WHERE id_materia = pIdMateria AND FIND_IN_SET(id_grupo, COALESCE(pGrupos, '')) = 0;

    INSERT IGNORE INTO grupo_materia (id_grupo, id_materia)
    SELECT g.id_grupo, pIdMateria FROM grupo g WHERE FIND_IN_SET(g.id_grupo, COALESCE(pGrupos, '')) > 0;

    COMMIT;

    SELECT 1 AS Afectadas;
END $$

-- 'en_uso' si ya tiene calificaciones. Sus vínculos con grupos se borran solos (grupo_materia tiene ON DELETE CASCADE).
DROP PROCEDURE IF EXISTS sp_materia_eliminar $$
CREATE PROCEDURE sp_materia_eliminar(IN pIdMateria INT)
SQL SECURITY INVOKER
BEGIN
    IF EXISTS (SELECT 1 FROM calificacion WHERE materia_id = pIdMateria) THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'en_uso:No se puede eliminar: la materia tiene calificaciones registradas.';
    END IF;

    DELETE FROM materia WHERE id_materia = pIdMateria;

    SELECT ROW_COUNT() AS Afectadas;
END $$

DELIMITER ;
