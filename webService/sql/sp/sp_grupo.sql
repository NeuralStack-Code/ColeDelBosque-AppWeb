-- ============================================================
-- Colegio del Bosque · Procedures de los grupos (webService/controllers/GrupoController.php; la lista de grupos
-- también la usan las pantallas de alumnos, maestros y materias). Idempotente (DROP + CREATE). Antes: sp_sistema.sql
--
-- Un grupo es un grado de un ciclo, con su maestra, su nivel y su nivel de inglés, y las materias que lleva
-- (grupo_materia). Al guardar, las materias llegan como ids marcados ('3,5,8') y nombres nuevos (arreglo JSON,
-- ["Robótica"]): los nuevos se dan de alta en el catálogo. El grupo 0 es el comodín "sin grupo": nunca se lista.
-- Las columnas salen con el nombre de la propiedad del model Grupo.
-- ============================================================

DELIMITER $$

-- Los grupos de un ciclo (0 = de todos), con cuántas materias y alumnos tienen.
DROP PROCEDURE IF EXISTS sp_grupo_listar $$
CREATE PROCEDURE sp_grupo_listar(IN pIdCiclo INT)
SQL SECURITY INVOKER
BEGIN
    SELECT g.id_grupo AS IdGrupo, g.grado AS Grado, NULLIF(g.maestra_id, 0) AS IdMaestra, g.ciclo_id AS IdCiclo, g.nivel AS Nivel,
           g.nivel_ingles AS NivelIngles, ci.nombre AS CicloNombre,
           (SELECT COUNT(*) FROM grupo_materia gm WHERE gm.id_grupo = g.id_grupo) AS NumMaterias,
           (SELECT COUNT(*) FROM cuenta cu WHERE cu.grupo_id = g.id_grupo AND cu.permiso_id = 3) AS NumAlumnos
      FROM grupo g
      LEFT JOIN ciclo ci ON ci.id_ciclo = g.ciclo_id
     WHERE g.id_grupo <> 0 AND (COALESCE(pIdCiclo, 0) = 0 OR g.ciclo_id = pIdCiclo)
     ORDER BY g.nivel, g.grado;
END $$

-- Un grupo (ninguna fila si no existe).
DROP PROCEDURE IF EXISTS sp_grupo_obtener $$
CREATE PROCEDURE sp_grupo_obtener(IN pIdGrupo INT)
SQL SECURITY INVOKER
BEGIN
    SELECT g.id_grupo AS IdGrupo, g.grado AS Grado, NULLIF(g.maestra_id, 0) AS IdMaestra, g.ciclo_id AS IdCiclo, g.nivel AS Nivel,
           g.nivel_ingles AS NivelIngles
      FROM grupo g
     WHERE g.id_grupo = pIdGrupo;
END $$

-- Las materias del grupo.
DROP PROCEDURE IF EXISTS sp_grupo_materias $$
CREATE PROCEDURE sp_grupo_materias(IN pIdGrupo INT)
SQL SECURITY INVOKER
BEGIN
    SELECT m.id_materia AS IdMateria, m.nombre AS Nombre
      FROM grupo_materia gm
      JOIN materia m ON m.id_materia = gm.id_materia
     WHERE gm.id_grupo = pIdGrupo
     ORDER BY m.nombre;
END $$

-- Las maestras para asignarle una al grupo.
DROP PROCEDURE IF EXISTS sp_grupo_maestros $$
CREATE PROCEDURE sp_grupo_maestros()
SQL SECURITY INVOKER
BEGIN
    SELECT c.id_cuenta AS IdCuenta, TRIM(CONCAT_WS(' ', u.nombre, u.paterno)) AS Nombre
      FROM cuenta c
      JOIN usuario u ON u.id_usuario = c.usuario_id
     WHERE c.permiso_id = 2
     ORDER BY u.paterno, u.nombre;
END $$

-- Deja al grupo EXACTAMENTE con las materias marcadas (pMaterias: '3,5,8') más las nuevas (pMateriasNuevas: arreglo
-- JSON de nombres; las que no estén en el catálogo se dan de alta). 'invalido' si se queda sin ninguna.
-- Solo se llama desde sp_grupo_crear y sp_grupo_editar, dentro de su transacción.
DROP PROCEDURE IF EXISTS sp_grupo_sincronizar_materias $$
CREATE PROCEDURE sp_grupo_sincronizar_materias(IN pIdGrupo INT, IN pMaterias VARCHAR(2000), IN pMateriasNuevas TEXT)
SQL SECURITY INVOKER
BEGIN
    DECLARE vTotal INT;
    DECLARE i INT DEFAULT 0;
    DECLARE vNombre VARCHAR(255);
    DECLARE vIdMateria INT;

    DROP TEMPORARY TABLE IF EXISTS tmp_grupo_materias;
    CREATE TEMPORARY TABLE tmp_grupo_materias (id_materia INT PRIMARY KEY);
    INSERT IGNORE INTO tmp_grupo_materias SELECT id_materia FROM materia WHERE FIND_IN_SET(id_materia, COALESCE(pMaterias, '')) > 0;

    SET vTotal = COALESCE(JSON_LENGTH(pMateriasNuevas), 0);
    WHILE i < vTotal DO
        SET vNombre = TRIM(JSON_UNQUOTE(JSON_EXTRACT(pMateriasNuevas, CONCAT('$[', i, ']'))));
        IF vNombre <> '' THEN
            SET vIdMateria = NULL;
            SELECT id_materia INTO vIdMateria FROM materia WHERE nombre = vNombre COLLATE utf8mb4_unicode_ci LIMIT 1;
            IF vIdMateria IS NULL THEN
                INSERT INTO materia (nombre) VALUES (vNombre);
                SET vIdMateria = LAST_INSERT_ID();
            END IF;
            INSERT IGNORE INTO tmp_grupo_materias VALUES (vIdMateria);
        END IF;
        SET i = i + 1;
    END WHILE;

    IF NOT EXISTS (SELECT 1 FROM tmp_grupo_materias) THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'invalido:Agrega al menos una materia.';
    END IF;

    DELETE FROM grupo_materia WHERE id_grupo = pIdGrupo AND id_materia NOT IN (SELECT id_materia FROM tmp_grupo_materias);
    INSERT IGNORE INTO grupo_materia (id_grupo, id_materia) SELECT pIdGrupo, id_materia FROM tmp_grupo_materias;
    DROP TEMPORARY TABLE tmp_grupo_materias;
END $$

-- Alta del grupo con sus materias, todo o nada. Regresa el Id. 'dup' si ya hay ese grado en el ciclo; 'invalido' sin materias.
DROP PROCEDURE IF EXISTS sp_grupo_crear $$
CREATE PROCEDURE sp_grupo_crear(IN pGrado VARCHAR(50), IN pIdCiclo INT, IN pNivel INT, IN pNivelIngles VARCHAR(10),
                                IN pMaterias VARCHAR(2000), IN pMateriasNuevas TEXT)
SQL SECURITY INVOKER
BEGIN
    DECLARE vIdGrupo INT;
    DECLARE EXIT HANDLER FOR SQLEXCEPTION
    BEGIN
        ROLLBACK;
        RESIGNAL;
    END;

    START TRANSACTION;

    -- El mismo grado puede repetirse en otro ciclo (<=> compara también "sin ciclo")
    IF EXISTS (SELECT 1 FROM grupo WHERE grado = pGrado COLLATE utf8mb4_unicode_ci AND ciclo_id <=> pIdCiclo) THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'dup:Ya existe ese grupo en el ciclo seleccionado.';
    END IF;

    INSERT INTO grupo (grado, ciclo_id, nivel, nivel_ingles) VALUES (pGrado, pIdCiclo, pNivel, pNivelIngles);
    SET vIdGrupo = LAST_INSERT_ID();
    CALL sp_grupo_sincronizar_materias(vIdGrupo, pMaterias, pMateriasNuevas);

    COMMIT;

    SELECT vIdGrupo AS Id;
END $$

-- Cambia lo que se mande, todo o nada. Un valor NULL en grado, maestra, nivel o ciclo = no cambia;
-- pCambiarNivelIngles / pCambiarMaterias = 1 aplican esos campos. 'notfound' · 'invalido' (sin materias).
DROP PROCEDURE IF EXISTS sp_grupo_editar $$
CREATE PROCEDURE sp_grupo_editar(IN pIdGrupo INT, IN pGrado VARCHAR(50), IN pIdMaestra INT, IN pNivel INT, IN pIdCiclo INT,
                                 IN pCambiarNivelIngles TINYINT, IN pNivelIngles VARCHAR(10),
                                 IN pCambiarMaterias TINYINT, IN pMaterias VARCHAR(2000), IN pMateriasNuevas TEXT)
SQL SECURITY INVOKER
BEGIN
    DECLARE EXIT HANDLER FOR SQLEXCEPTION
    BEGIN
        ROLLBACK;
        RESIGNAL;
    END;

    START TRANSACTION;

    IF NOT EXISTS (SELECT 1 FROM grupo WHERE id_grupo = pIdGrupo) THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'notfound:No se encontró el grupo.';
    END IF;

    UPDATE grupo
       SET grado        = COALESCE(pGrado, grado),
           maestra_id   = COALESCE(pIdMaestra, maestra_id),
           nivel        = COALESCE(pNivel, nivel),
           ciclo_id     = COALESCE(pIdCiclo, ciclo_id),
           nivel_ingles = IF(pCambiarNivelIngles = 1, pNivelIngles, nivel_ingles)
     WHERE id_grupo = pIdGrupo;

    IF pCambiarMaterias = 1 THEN
        CALL sp_grupo_sincronizar_materias(pIdGrupo, pMaterias, pMateriasNuevas);
    END IF;

    COMMIT;

    SELECT 1 AS Afectadas;
END $$

-- Baja del grupo, todo o nada: sus alumnos y maestras quedan en "sin grupo" (0) y sus materias se desligan (FK).
-- Regresa Afectadas (0 = no existía) y cuántos alumnos quedaron sin grupo.
DROP PROCEDURE IF EXISTS sp_grupo_eliminar $$
CREATE PROCEDURE sp_grupo_eliminar(IN pIdGrupo INT)
SQL SECURITY INVOKER
BEGIN
    DECLARE vAlumnos INT;
    DECLARE vAfectadas INT;
    DECLARE EXIT HANDLER FOR SQLEXCEPTION
    BEGIN
        ROLLBACK;
        RESIGNAL;
    END;

    START TRANSACTION;

    SELECT COUNT(*) INTO vAlumnos FROM cuenta WHERE grupo_id = pIdGrupo AND permiso_id = 3;
    UPDATE cuenta SET grupo_id = 0 WHERE grupo_id = pIdGrupo;
    DELETE FROM grupo WHERE id_grupo = pIdGrupo;
    SET vAfectadas = ROW_COUNT();

    COMMIT;

    SELECT vAfectadas AS Afectadas, vAlumnos AS Alumnos;
END $$

DELIMITER ;
