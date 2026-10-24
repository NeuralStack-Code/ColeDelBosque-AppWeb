-- ============================================================
-- Colegio del Bosque · Procedures del módulo Perfil (webService/controllers/PerfilController.php)
-- Idempotente (DROP + CREATE). Antes: sp_sistema.sql
--
-- Un perfil es una persona (tabla usuario) con su cuenta de acceso (tabla cuenta), de cualquier rol.
-- La matrícula va ENCRIPTADA (apiService/core/crypto.php): aquí llega y sale encriptada.
-- Una cuenta sin grupo guarda grupo_id = 0 (así está en toda la base).
-- Las columnas salen con el nombre de la propiedad de los models Perfil, GrupoOpcion y EstatusOpcion.
--
-- OJO intercalaciones: al comparar un parámetro de texto contra una columna se fija con COLLATE.
-- ============================================================

DELIMITER $$

-- Las cuentas, con su persona, rol, grupo y estatus. pIdPermiso = 0 → todas.
DROP PROCEDURE IF EXISTS sp_perfil_listar $$
CREATE PROCEDURE sp_perfil_listar(IN pIdPermiso INT)
SQL SECURITY INVOKER
BEGIN
    SELECT c.id_cuenta AS IdCuenta, c.matricula AS Matricula, c.permiso_id AS IdPermiso, p.nombre AS PermisoNombre,
           c.grupo_id AS IdGrupo, g.grado AS Grado, st.clave AS Estatus, st.nombre AS EstatusNombre,
           u.id_usuario AS IdUsuario, u.nombre AS Nombre, u.paterno AS Paterno, u.materno AS Materno
      FROM cuenta c
      JOIN usuario u       ON u.id_usuario = c.usuario_id
      LEFT JOIN permisos p ON p.id_permiso = c.permiso_id
      LEFT JOIN grupo g    ON g.id_grupo   = c.grupo_id
      LEFT JOIN status st  ON st.id_status = c.status_id
     WHERE pIdPermiso = 0 OR c.permiso_id = pIdPermiso
     ORDER BY c.permiso_id, u.paterno, u.nombre;
END $$

-- Los grupos que se pueden asignar, con el nombre de su ciclo.
DROP PROCEDURE IF EXISTS sp_perfil_grupos $$
CREATE PROCEDURE sp_perfil_grupos()
SQL SECURITY INVOKER
BEGIN
    SELECT g.id_grupo AS IdGrupo, g.grado AS Grado, ci.nombre AS Ciclo
      FROM grupo g
      LEFT JOIN ciclo ci ON ci.id_ciclo = g.ciclo_id
     WHERE g.id_grupo <> 0
     ORDER BY g.nivel, g.grado;
END $$

-- Los estatus que puede tener una cuenta (catálogo status, ámbito 'alumno').
DROP PROCEDURE IF EXISTS sp_perfil_estatus $$
CREATE PROCEDURE sp_perfil_estatus()
SQL SECURITY INVOKER
BEGIN
    SELECT clave AS Clave, nombre AS Nombre FROM status WHERE ambito = 'alumno' ORDER BY orden;
END $$

-- Alta de persona + cuenta, todo o nada. Regresa el id de la cuenta.
-- 'invalido' si el rol no existe; 'dup' si la matrícula ya existe. Un estatus desconocido se guarda como 'activo'.
DROP PROCEDURE IF EXISTS sp_perfil_crear $$
CREATE PROCEDURE sp_perfil_crear(
    IN pNombre    VARCHAR(25),
    IN pPaterno   VARCHAR(25),
    IN pMaterno   VARCHAR(25),
    IN pMatricula VARCHAR(150),
    IN pIdPermiso INT,
    IN pIdGrupo   INT,
    IN pEstatus   VARCHAR(30)
)
SQL SECURITY INVOKER
BEGIN
    DECLARE EXIT HANDLER FOR SQLEXCEPTION
    BEGIN
        ROLLBACK;
        RESIGNAL;
    END;

    START TRANSACTION;

    IF NOT EXISTS (SELECT 1 FROM permisos WHERE id_permiso = pIdPermiso) THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'invalido:Rol (permiso) no válido.';
    END IF;
    IF EXISTS (SELECT 1 FROM cuenta WHERE matricula = pMatricula COLLATE utf8mb4_unicode_ci) THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'dup:La matrícula ya existe.';
    END IF;

    INSERT INTO usuario (nombre, paterno, materno) VALUES (pNombre, pPaterno, pMaterno);

    INSERT INTO cuenta (matricula, usuario_id, grupo_id, permiso_id, status_id)
    VALUES (pMatricula, LAST_INSERT_ID(), COALESCE(pIdGrupo, 0), pIdPermiso,
            COALESCE((SELECT id_status FROM status WHERE ambito = 'alumno' AND clave = pEstatus COLLATE utf8mb4_unicode_ci),
                     (SELECT id_status FROM status WHERE ambito = 'alumno' AND clave = 'activo')));

    COMMIT;

    SELECT LAST_INSERT_ID() AS Id;
END $$

-- Cambia la persona y su cuenta, todo o nada.
-- 'notfound' si la cuenta no existe; 'invalido' si el rol no existe; 'dup' si otra cuenta ya tiene esa matrícula.
DROP PROCEDURE IF EXISTS sp_perfil_editar $$
CREATE PROCEDURE sp_perfil_editar(
    IN pIdCuenta  INT,
    IN pNombre    VARCHAR(25),
    IN pPaterno   VARCHAR(25),
    IN pMaterno   VARCHAR(25),
    IN pMatricula VARCHAR(150),
    IN pIdPermiso INT,
    IN pIdGrupo   INT,
    IN pEstatus   VARCHAR(30)
)
SQL SECURITY INVOKER
BEGIN
    DECLARE vIdUsuario INT DEFAULT NULL;
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
    IF NOT EXISTS (SELECT 1 FROM permisos WHERE id_permiso = pIdPermiso) THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'invalido:Rol (permiso) no válido.';
    END IF;
    IF EXISTS (SELECT 1 FROM cuenta WHERE matricula = pMatricula COLLATE utf8mb4_unicode_ci AND id_cuenta <> pIdCuenta) THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'dup:La matrícula ya existe en otra cuenta.';
    END IF;

    UPDATE usuario SET nombre = pNombre, paterno = pPaterno, materno = pMaterno WHERE id_usuario = vIdUsuario;

    UPDATE cuenta
       SET matricula = pMatricula, grupo_id = COALESCE(pIdGrupo, 0), permiso_id = pIdPermiso,
           status_id = COALESCE((SELECT id_status FROM status WHERE ambito = 'alumno' AND clave = pEstatus COLLATE utf8mb4_unicode_ci),
                                (SELECT id_status FROM status WHERE ambito = 'alumno' AND clave = 'activo'))
     WHERE id_cuenta = pIdCuenta;

    COMMIT;

    SELECT 1 AS Afectadas;
END $$

-- Borra la cuenta, su persona y TODO lo suyo (recibos, colegiaturas, calificaciones), todo o nada; el grupo
-- que tuviera como maestra se queda sin maestra. pIdUsuarioSesion = quien lo pide: nadie borra su propia cuenta.
-- 'notfound' si la cuenta no existe; 'en_uso' si es la de quien está en sesión.
DROP PROCEDURE IF EXISTS sp_perfil_eliminar $$
CREATE PROCEDURE sp_perfil_eliminar(IN pIdCuenta INT, IN pIdUsuarioSesion INT)
SQL SECURITY INVOKER
BEGIN
    DECLARE vIdUsuario INT DEFAULT NULL;
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
    IF vIdUsuario = pIdUsuarioSesion THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'en_uso:No puedes eliminar tu propia cuenta.';
    END IF;

    DELETE FROM recibo       WHERE cuenta_id = pIdCuenta;
    DELETE FROM colegiatura  WHERE cuenta_id = pIdCuenta;
    DELETE FROM calificacion WHERE cuenta_id = pIdCuenta;
    UPDATE grupo SET maestra_id = 0 WHERE maestra_id = pIdCuenta;
    DELETE FROM cuenta       WHERE id_cuenta  = pIdCuenta;   -- sus reportes semanales se borran en cascada
    DELETE FROM usuario      WHERE id_usuario = vIdUsuario;

    COMMIT;

    SELECT 1 AS Afectadas;
END $$

DELIMITER ;
