-- ============================================================
-- Colegio del Bosque · Procedures del portal del alumno/padre (webService/controllers/PadreController.php)
-- Idempotente (DROP + CREATE). Antes: sp_sistema.sql
--
-- Todo se busca por el usuario en sesión (cuenta con permiso 3): nadie ve la información de otro alumno.
-- Sus pagos salen de sp_colegiatura_listar (sp_colegiatura.sql), los mismos que ve el administrador.
-- ============================================================

DELIMITER $$

-- La cuenta del alumno en sesión, con su grupo y los correos de sus tutores (encriptados). Ninguna fila si no tiene.
DROP PROCEDURE IF EXISTS sp_padre_cuenta $$
CREATE PROCEDURE sp_padre_cuenta(IN pIdUsuario INT)
SQL SECURITY INVOKER
BEGIN
    SELECT c.id_cuenta AS IdCuenta, c.grupo_id AS IdGrupo, g.grado AS Grado,
           c.correo_tutor AS CorreoTutor, c.correo_tutor2 AS CorreoTutor2
      FROM cuenta c
      LEFT JOIN grupo g ON g.id_grupo = c.grupo_id
     WHERE c.usuario_id = pIdUsuario AND c.permiso_id = 3
     LIMIT 1;
END $$

-- Las calificaciones del alumno en cada materia de su grupo, del ciclo indicado (las que no tiene salen vacías).
DROP PROCEDURE IF EXISTS sp_padre_calificaciones $$
CREATE PROCEDURE sp_padre_calificaciones(IN pIdCuenta INT, IN pIdGrupo INT, IN pIdCiclo INT)
SQL SECURITY INVOKER
BEGIN
    SELECT m.nombre AS Materia, cal.p1 AS P1, cal.p2 AS P2, cal.p3 AS P3, cal.calif_final AS CalifFinal, cal.reporte AS Reporte
      FROM grupo_materia gm
      JOIN materia m ON m.id_materia = gm.id_materia
      LEFT JOIN calificacion cal ON cal.materia_id = m.id_materia AND cal.cuenta_id = pIdCuenta AND cal.ciclo_id = pIdCiclo
     WHERE gm.id_grupo = pIdGrupo
     ORDER BY m.nombre;
END $$

-- Los correos (ya encriptados; NULL = sin correo) a donde llega el reporte semanal del alumno en sesión.
DROP PROCEDURE IF EXISTS sp_padre_guardar_correos $$
CREATE PROCEDURE sp_padre_guardar_correos(IN pIdUsuario INT, IN pCorreoTutor VARCHAR(255), IN pCorreoTutor2 VARCHAR(255))
SQL SECURITY INVOKER
BEGIN
    UPDATE cuenta SET correo_tutor = pCorreoTutor, correo_tutor2 = pCorreoTutor2
     WHERE usuario_id = pIdUsuario AND permiso_id = 3;

    SELECT ROW_COUNT() AS Afectadas;
END $$

DELIMITER ;
