-- ============================================================
-- Colegio del Bosque · Procedures de las cuentas de alumnos y maestros
-- (webService/controllers/AlumnoController.php y DocenteController.php). Idempotente (DROP + CREATE). Antes: sp_sistema.sql
--
-- Una persona es un usuario (nombre y apellidos) con su cuenta (matrícula, grupo, rol). La matrícula y los correos
-- de los tutores van ENCRIPTADOS: aquí ya llegan así. El grupo 0 es el comodín "sin grupo".
-- Las columnas salen con el nombre de la propiedad del model Persona.
-- ============================================================

DELIMITER $$

-- Las cuentas de un rol (3 = alumnos, 2 = maestros) con su grupo.
DROP PROCEDURE IF EXISTS sp_persona_listar $$
CREATE PROCEDURE sp_persona_listar(IN pIdPermiso INT)
SQL SECURITY INVOKER
BEGIN
    SELECT c.id_cuenta AS IdCuenta, c.matricula AS Matricula, c.grupo_id AS IdGrupo, g.grado AS Grado,
           c.correo_tutor AS CorreoTutor, c.correo_tutor2 AS CorreoTutor2, c.niveles_ingles AS NivelesIngles,
           u.id_usuario AS IdUsuario, u.nombre AS Nombre, u.paterno AS Paterno, u.materno AS Materno
      FROM cuenta c
      JOIN usuario u ON u.id_usuario = c.usuario_id
      LEFT JOIN grupo g ON g.id_grupo = c.grupo_id
     WHERE c.permiso_id = pIdPermiso
     ORDER BY u.paterno, u.nombre;
END $$

-- Alta de usuario + cuenta (nace activa), todo o nada. Regresa el Id de la cuenta. 'dup' si la matrícula ya existe.
DROP PROCEDURE IF EXISTS sp_persona_crear $$
CREATE PROCEDURE sp_persona_crear(IN pNombre VARCHAR(25), IN pPaterno VARCHAR(25), IN pMaterno VARCHAR(25), IN pIdGrupo INT,
                                  IN pMatricula VARCHAR(150), IN pIdPermiso INT, IN pNivelesIngles VARCHAR(30),
                                  IN pCorreoTutor VARCHAR(255), IN pCorreoTutor2 VARCHAR(255))
SQL SECURITY INVOKER
BEGIN
    DECLARE vIdUsuario INT;
    DECLARE EXIT HANDLER FOR SQLEXCEPTION
    BEGIN
        ROLLBACK;
        RESIGNAL;
    END;

    START TRANSACTION;

    IF EXISTS (SELECT 1 FROM cuenta WHERE matricula = pMatricula COLLATE utf8mb4_unicode_ci) THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'dup:La matrícula ya existe. Cambia algún dígito e inténtalo de nuevo.';
    END IF;

    INSERT INTO usuario (nombre, paterno, materno) VALUES (pNombre, pPaterno, pMaterno);
    SET vIdUsuario = LAST_INSERT_ID();

    INSERT INTO cuenta (matricula, usuario_id, grupo_id, permiso_id, status_id, niveles_ingles, correo_tutor, correo_tutor2)
    VALUES (pMatricula, vIdUsuario, pIdGrupo, pIdPermiso,
            (SELECT id_status FROM status WHERE ambito = 'alumno' AND clave = 'activo'),
            pNivelesIngles, pCorreoTutor, pCorreoTutor2);

    COMMIT;

    SELECT LAST_INSERT_ID() AS Id;
END $$

-- Cambia nombre, matrícula y, según el rol, el resto, todo o nada:
--   pIdGrupo vacío o 0 = no cambia el grupo · pSinGrupo = 1 deja al maestro sin grupo propio (solo inglés)
--   alumnos (3): correos de los tutores · maestros (2): niveles de inglés.
-- 'notfound' si la cuenta no existe; 'dup' si la matrícula es de otra cuenta.
DROP PROCEDURE IF EXISTS sp_persona_editar $$
CREATE PROCEDURE sp_persona_editar(IN pIdCuenta INT, IN pNombre VARCHAR(25), IN pPaterno VARCHAR(25), IN pMaterno VARCHAR(25),
                                   IN pIdGrupo INT, IN pSinGrupo TINYINT, IN pMatricula VARCHAR(150), IN pIdPermiso INT,
                                   IN pNivelesIngles VARCHAR(30), IN pCorreoTutor VARCHAR(255), IN pCorreoTutor2 VARCHAR(255))
SQL SECURITY INVOKER
BEGIN
    DECLARE vIdUsuario INT;
    DECLARE EXIT HANDLER FOR SQLEXCEPTION
    BEGIN
        ROLLBACK;
        RESIGNAL;
    END;

    START TRANSACTION;

    SELECT usuario_id INTO vIdUsuario FROM cuenta WHERE id_cuenta = pIdCuenta;
    IF vIdUsuario IS NULL THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'notfound:No se encontró la cuenta.';
    END IF;
    IF EXISTS (SELECT 1 FROM cuenta WHERE matricula = pMatricula COLLATE utf8mb4_unicode_ci AND id_cuenta <> pIdCuenta) THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'dup:Esa matrícula ya es de otra cuenta.';
    END IF;

    UPDATE usuario SET nombre = pNombre, paterno = pPaterno, materno = pMaterno WHERE id_usuario = vIdUsuario;
    UPDATE cuenta
       SET matricula = pMatricula,
           grupo_id = IF(COALESCE(pIdGrupo, 0) > 0, pIdGrupo, grupo_id)
     WHERE id_cuenta = pIdCuenta;

    IF pIdPermiso = 3 THEN
        UPDATE cuenta SET correo_tutor = pCorreoTutor, correo_tutor2 = pCorreoTutor2 WHERE id_cuenta = pIdCuenta;
    END IF;
    IF pIdPermiso = 2 THEN
        UPDATE cuenta SET niveles_ingles = pNivelesIngles WHERE id_cuenta = pIdCuenta AND permiso_id = 2;
        IF pSinGrupo = 1 THEN
            UPDATE cuenta SET grupo_id = 0 WHERE id_cuenta = pIdCuenta AND permiso_id = 2;
        END IF;
    END IF;

    COMMIT;

    SELECT 1 AS Afectadas;
END $$

-- Baja de la cuenta con todo lo suyo (recibos, colegiaturas, calificaciones; si era maestra, sus grupos quedan
-- sin asignar) y su usuario, todo o nada. 'notfound' si no existe.
DROP PROCEDURE IF EXISTS sp_persona_eliminar $$
CREATE PROCEDURE sp_persona_eliminar(IN pIdCuenta INT)
SQL SECURITY INVOKER
BEGIN
    DECLARE vIdUsuario INT;
    DECLARE EXIT HANDLER FOR SQLEXCEPTION
    BEGIN
        ROLLBACK;
        RESIGNAL;
    END;

    START TRANSACTION;

    SELECT usuario_id INTO vIdUsuario FROM cuenta WHERE id_cuenta = pIdCuenta;
    IF vIdUsuario IS NULL THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'notfound:No se encontró el registro.';
    END IF;

    DELETE FROM recibo       WHERE cuenta_id = pIdCuenta;
    DELETE FROM colegiatura  WHERE cuenta_id = pIdCuenta;
    DELETE FROM calificacion WHERE cuenta_id = pIdCuenta;
    UPDATE grupo SET maestra_id = 0 WHERE maestra_id = pIdCuenta;         -- 0 = sin asignar (la columna no admite NULL)
    DELETE FROM cuenta       WHERE id_cuenta = pIdCuenta;
    DELETE FROM usuario      WHERE id_usuario = vIdUsuario;

    COMMIT;

    SELECT 1 AS Afectadas;
END $$

-- Lo necesario para mandarle su matrícula a los tutores de un alumno (encriptado). Ninguna fila si no es un alumno.
DROP PROCEDURE IF EXISTS sp_persona_correo $$
CREATE PROCEDURE sp_persona_correo(IN pIdCuenta INT)
SQL SECURITY INVOKER
BEGIN
    SELECT c.id_cuenta AS IdCuenta, TRIM(CONCAT_WS(' ', u.nombre, u.paterno, u.materno)) AS NombreCompleto,
           c.matricula AS Matricula, c.correo_tutor AS CorreoTutor, c.correo_tutor2 AS CorreoTutor2
      FROM cuenta c
      JOIN usuario u ON u.id_usuario = c.usuario_id
     WHERE c.id_cuenta = pIdCuenta AND c.permiso_id = 3;
END $$

DELIMITER ;
