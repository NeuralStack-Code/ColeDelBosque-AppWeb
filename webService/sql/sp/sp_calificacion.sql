-- ============================================================
-- Colegio del Bosque · Procedures de las calificaciones (webService/controllers/CalificacionController.php;
-- los grupos y materias de la maestra también los usa ReporteController). Idempotente (DROP + CREATE). Antes: sp_sistema.sql
--
-- Una maestra captura en su grupo (todas sus materias) y en los grupos de los niveles de inglés que imparte
-- (ahí solo Inglés). Las materias de inglés son las que empiezan con "Ingl" (Inglés, INGLES, Inglés 1…).
-- Las notas son por alumno, materia y ciclo (tabla calificacion).
-- ============================================================

DELIMITER $$

-- Los grupos donde captura la maestra (pIdUsuario = el usuario en sesión): el suyo primero (SoloIngles = 0) y luego
-- los del ciclo pIdCiclo con alguno de sus niveles de inglés y materia de inglés (SoloIngles = 1).
DROP PROCEDURE IF EXISTS sp_calificacion_grupos $$
CREATE PROCEDURE sp_calificacion_grupos(IN pIdUsuario INT, IN pIdCiclo INT)
SQL SECURITY INVOKER
BEGIN
    SELECT IdGrupo, Grado, SoloIngles
      FROM (SELECT c.grupo_id AS IdGrupo, COALESCE(g.grado, '') AS Grado, 0 AS SoloIngles, 0 AS Bloque, 0 AS Nivel, '' AS Orden
              FROM cuenta c
              LEFT JOIN grupo g ON g.id_grupo = c.grupo_id
             WHERE c.usuario_id = pIdUsuario AND c.permiso_id = 2 AND c.grupo_id > 0
            UNION ALL
            SELECT g.id_grupo, g.grado, 1, 1, g.nivel, g.grado
              FROM cuenta c
              JOIN grupo g ON g.ciclo_id = pIdCiclo AND g.id_grupo <> COALESCE(c.grupo_id, 0)
                          AND FIND_IN_SET(g.nivel_ingles, c.niveles_ingles) > 0
             WHERE c.usuario_id = pIdUsuario AND c.permiso_id = 2
               AND EXISTS (SELECT 1 FROM grupo_materia gm JOIN materia m ON m.id_materia = gm.id_materia
                            WHERE gm.id_grupo = g.id_grupo AND m.nombre LIKE 'ingl%')) grupos
     ORDER BY Bloque, Nivel, Orden;
END $$

-- Las materias que se capturan en el grupo: todas, o solo las de inglés (pSoloIngles = 1).
DROP PROCEDURE IF EXISTS sp_calificacion_materias $$
CREATE PROCEDURE sp_calificacion_materias(IN pIdGrupo INT, IN pSoloIngles TINYINT)
SQL SECURITY INVOKER
BEGIN
    SELECT m.id_materia AS IdMateria, m.nombre AS Nombre
      FROM grupo_materia gm
      JOIN materia m ON m.id_materia = gm.id_materia
     WHERE gm.id_grupo = pIdGrupo AND (COALESCE(pSoloIngles, 0) = 0 OR m.nombre LIKE 'ingl%')
     ORDER BY m.nombre;
END $$

-- 'invalido' si la materia no se captura en ese grupo (o no es de inglés cuando solo se captura inglés).
DROP PROCEDURE IF EXISTS sp_calificacion_validar_materia $$
CREATE PROCEDURE sp_calificacion_validar_materia(IN pIdMateria INT, IN pIdGrupo INT, IN pSoloIngles TINYINT)
SQL SECURITY INVOKER
BEGIN
    IF NOT EXISTS (SELECT 1 FROM grupo_materia gm JOIN materia m ON m.id_materia = gm.id_materia
                    WHERE gm.id_materia = pIdMateria AND gm.id_grupo = pIdGrupo
                      AND (COALESCE(pSoloIngles, 0) = 0 OR m.nombre LIKE 'ingl%')) THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'invalido:Materia no válida para tu grupo.';
    END IF;
END $$

-- Los alumnos del grupo con sus notas de la materia en el ciclo. 'invalido' si la materia no es del grupo.
DROP PROCEDURE IF EXISTS sp_calificacion_listar $$
CREATE PROCEDURE sp_calificacion_listar(IN pIdMateria INT, IN pIdCiclo INT, IN pIdGrupo INT, IN pSoloIngles TINYINT)
SQL SECURITY INVOKER
BEGIN
    CALL sp_calificacion_validar_materia(pIdMateria, pIdGrupo, pSoloIngles);

    SELECT c.id_cuenta AS IdCuenta, TRIM(CONCAT_WS(' ', u.nombre, u.paterno, u.materno)) AS NombreCompleto,
           cal.p1 AS P1, cal.p2 AS P2, cal.p3 AS P3, cal.calif_final AS CalifFinal, cal.reporte AS Reporte
      FROM cuenta c
      JOIN usuario u ON u.id_usuario = c.usuario_id
      LEFT JOIN calificacion cal ON cal.cuenta_id = c.id_cuenta AND cal.materia_id = pIdMateria AND cal.ciclo_id = pIdCiclo
     WHERE c.grupo_id = pIdGrupo AND c.permiso_id = 3
     ORDER BY u.paterno, u.nombre;
END $$

-- Guarda (o actualiza) las notas de un alumno en una materia y ciclo. La captura queda 'capturada'; con
-- calificación final, aprobado (6 o más) o reprobado. 'invalido' si la materia o el alumno no son del grupo.
DROP PROCEDURE IF EXISTS sp_calificacion_guardar $$
CREATE PROCEDURE sp_calificacion_guardar(IN pIdCuenta INT, IN pIdMateria INT, IN pIdCiclo INT, IN pIdGrupo INT,
                                         IN pSoloIngles TINYINT, IN pP1 DOUBLE, IN pP2 DOUBLE, IN pP3 DOUBLE,
                                         IN pCalifFinal DOUBLE, IN pReporte TEXT)
SQL SECURITY INVOKER
BEGIN
    CALL sp_calificacion_validar_materia(pIdMateria, pIdGrupo, pSoloIngles);
    IF NOT EXISTS (SELECT 1 FROM cuenta WHERE id_cuenta = pIdCuenta AND grupo_id = pIdGrupo AND permiso_id = 3) THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'invalido:Ese alumno no pertenece a tu grupo.';
    END IF;

    INSERT INTO calificacion (cuenta_id, materia_id, ciclo_id, p1, p2, p3, calif_final, reporte, status_captura_id, status_aprobacion_id)
    VALUES (pIdCuenta, pIdMateria, pIdCiclo, pP1, pP2, pP3, pCalifFinal, pReporte,
            (SELECT id_status FROM status WHERE ambito = 'captura' AND clave = 'capturada'),
            IF(pCalifFinal IS NULL, NULL,
               (SELECT id_status FROM status WHERE ambito = 'aprobacion' AND clave = IF(pCalifFinal >= 6, 'aprobado', 'reprobado'))))
    ON DUPLICATE KEY UPDATE
        p1 = VALUES(p1), p2 = VALUES(p2), p3 = VALUES(p3), calif_final = VALUES(calif_final), reporte = VALUES(reporte),
        status_captura_id = VALUES(status_captura_id), status_aprobacion_id = VALUES(status_aprobacion_id);

    SELECT 1 AS Afectadas;
END $$

DELIMITER ;
