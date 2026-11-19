-- ============================================================================================
-- Todos los procedures del colegio (uno por módulo), juntos. Generado desde webService/sql/sp/*.sql
-- (copia en SqlNSC/ColegioDelBosque/sql/sp). Cada bloque es idempotente: DROP PROCEDURE IF EXISTS + CREATE.
-- Requiere 01_sp_sistema.sql. Incluye sp_catalogo (permisos) y sp_ruta (rutas del sitio).
-- ============================================================================================

-- ──────────────────────────── sp_auth.sql ────────────────────────────
-- ============================================================
-- Colegio del Bosque · Procedures del módulo Auth (webService/controllers/AuthController.php)
-- Idempotente (DROP + CREATE). Antes: sp_sistema.sql
--
-- Se entra con la matrícula. En la base va ENCRIPTADA (apiService/core/crypto.php): aquí llega ya encriptada.
--
-- OJO intercalaciones: las tablas son utf8mb4_unicode_ci y la conexión puede llegar con otra. Al comparar un
-- parámetro de texto contra una columna se fija con COLLATE o falla con "Illegal mix of collations".
-- ============================================================

DELIMITER $$

-- La cuenta de una matrícula, con el nombre de la persona. Sin filas = matrícula incorrecta.
DROP PROCEDURE IF EXISTS sp_auth_autenticar $$
CREATE PROCEDURE sp_auth_autenticar(IN pMatricula VARCHAR(150))
SQL SECURITY INVOKER
BEGIN
    SELECT c.id_cuenta AS IdCuenta, c.usuario_id AS IdUsuario, c.permiso_id AS IdPermiso,
           u.nombre AS Nombre, u.paterno AS Paterno
      FROM cuenta c
      JOIN usuario u ON u.id_usuario = c.usuario_id
     WHERE c.matricula = pMatricula COLLATE utf8mb4_unicode_ci
     LIMIT 1;
END $$

DELIMITER ;

-- ──────────────────────────── sp_calificacion.sql ────────────────────────────
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

-- ──────────────────────────── sp_catalogo.sql ────────────────────────────
-- ============================================================================================
-- Catálogo de permisos del colegio: componentes, objetos (grupo · pantalla · acción), perfiles y su asignación a
-- CUENTAS (CuentaPerfiles, N:M). Es el mismo catálogo de NeuralStack Code; aquí los perfiles son de la cuenta
-- (la que entra con su matrícula) y no de un usuario.
--
-- El actor: las altas y cambios declaran pIdUsuarioRegistra / pIdUsuarioCambia y Command los llena solo con
-- la CUENTA que actúa (Controller::__construct → Command::actor($this->accountId())). Con eso se aplica
-- "nadie da lo que no tiene": quien no tiene un perfil EsAdmin solo concede lo que él mismo tiene y nunca lo Sensible,
-- y no se puede tocar un perfil que tiene asignado. -1 = el sistema (semillas desde la terminal pasan 0).
--
-- Requiere: 01_tablas_catalogo.sql. Idempotente (DROP + CREATE). SQL SECURITY INVOKER.
-- ============================================================================================
SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci;

DELIMITER $$

DROP PROCEDURE IF EXISTS sp_catalogo_actor_admin $$
CREATE PROCEDURE `sp_catalogo_actor_admin`(IN pIdCuenta INT, OUT pEsAdmin TINYINT)
    SQL SECURITY INVOKER
BEGIN
    SET pIdCuenta = COALESCE(pIdCuenta, 0);
    SET pEsAdmin = pIdCuenta = -1
                OR (pIdCuenta > 0 AND EXISTS (SELECT 1 FROM CuentaPerfiles up
                                                 JOIN Perfiles p ON p.IdPerfil = up.IdPerfil AND p.Activo = 1 AND p.EsAdmin = 1
                                                WHERE up.IdCuenta = pIdCuenta));
END
$$

DROP PROCEDURE IF EXISTS sp_catalogo_hay_admin $$
CREATE PROCEDURE `sp_catalogo_hay_admin`(IN pSinPerfil INT, IN pSinCuenta INT, OUT pHay TINYINT)
    SQL SECURITY INVOKER
BEGIN
    SET pHay = EXISTS (SELECT 1 FROM CuentaPerfiles up
                         JOIN Perfiles p ON p.IdPerfil = up.IdPerfil AND p.Activo = 1 AND p.EsAdmin = 1
                        WHERE up.IdPerfil <> COALESCE(pSinPerfil, 0) AND up.IdCuenta <> COALESCE(pSinCuenta, 0));
END
$$

DROP PROCEDURE IF EXISTS sp_catalogo_llenar_concedidos $$
CREATE PROCEDURE `sp_catalogo_llenar_concedidos`(IN pIdCuenta INT)
    SQL SECURITY INVOKER
BEGIN
    DECLARE vEsAdmin TINYINT DEFAULT 0;
    SET pIdCuenta = COALESCE(pIdCuenta, 0);

    DROP TEMPORARY TABLE IF EXISTS tmp_concedidos;
    CREATE TEMPORARY TABLE tmp_concedidos (IdObjeto INT PRIMARY KEY);

    SELECT COUNT(*) > 0 INTO vEsAdmin
      FROM CuentaPerfiles up JOIN Perfiles p ON p.IdPerfil = up.IdPerfil AND p.Activo = 1 AND p.EsAdmin = 1
     WHERE up.IdCuenta = pIdCuenta;

    INSERT IGNORE INTO tmp_concedidos
    SELECT o.IdObjeto FROM Objetos o
     WHERE o.Activo = 1
       AND (o.Acceso = 'publico' OR (pIdCuenta > 0 AND (o.Acceso = 'sesion' OR vEsAdmin = 1)));

    INSERT IGNORE INTO tmp_concedidos
    SELECT po.IdObjeto
      FROM CuentaPerfiles up
      JOIN Perfiles p     ON p.IdPerfil = up.IdPerfil AND p.Activo = 1
      JOIN PerfilObjeto po ON po.IdPerfil = p.IdPerfil
      JOIN Objetos o      ON o.IdObjeto = po.IdObjeto AND o.Activo = 1
     WHERE up.IdCuenta = pIdCuenta;

    INSERT IGNORE INTO tmp_concedidos
    SELECT ou.IdObjetoMetodo
      FROM CuentaPerfiles up
      JOIN Perfiles p     ON p.IdPerfil = up.IdPerfil AND p.Activo = 1
      JOIN PerfilObjeto po ON po.IdPerfil = p.IdPerfil
      JOIN Objetos v      ON v.IdObjeto = po.IdObjeto AND v.Activo = 1
      JOIN ObjetoUsos ou  ON ou.IdObjeto = v.IdObjeto
      JOIN Objetos o      ON o.IdObjeto = ou.IdObjetoMetodo AND o.Activo = 1
     WHERE up.IdCuenta = pIdCuenta;
END
$$

DROP PROCEDURE IF EXISTS sp_catalogo_perfil_actualizar $$
CREATE PROCEDURE `sp_catalogo_perfil_actualizar`(IN pIdPerfil INT, IN pNombre VARCHAR(80), IN pDescripcion VARCHAR(255),
                                               IN pEsAdmin TINYINT, IN pActivo TINYINT, IN pInicio VARCHAR(80),
                                               IN pIdUsuarioCambia INT)
    SQL SECURITY INVOKER
BEGIN
    DECLARE vActorAdmin TINYINT;
    DECLARE vEraAdmin TINYINT;
    DECLARE vHayAdmin TINYINT;
    DECLARE vAfectadas INT;
    DECLARE EXIT HANDLER FOR SQLEXCEPTION BEGIN ROLLBACK; RESIGNAL; END;

    SET pNombre = TRIM(COALESCE(pNombre, '')), pEsAdmin = COALESCE(pEsAdmin, 0), pActivo = COALESCE(pActivo, 1), pInicio = TRIM(COALESCE(pInicio, ''));
    SELECT EsAdmin INTO vEraAdmin FROM Perfiles WHERE IdPerfil = pIdPerfil;
    IF vEraAdmin IS NULL THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'notfound:Perfil no encontrado.'; END IF;
    IF pNombre = '' THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'invalido:El nombre es obligatorio.'; END IF;

    CALL sp_catalogo_actor_admin(pIdUsuarioCambia, vActorAdmin);
    IF vActorAdmin = 0 THEN
        IF vEraAdmin = 1 OR pEsAdmin = 1 THEN
            SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'permiso:Solo un administrador puede tocar un perfil administrador.';
        END IF;
        IF EXISTS (SELECT 1 FROM CuentaPerfiles WHERE IdCuenta = pIdUsuarioCambia AND IdPerfil = pIdPerfil) THEN
            SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'permiso:No puedes modificar un perfil que tienes asignado.';
        END IF;
    END IF;
    IF vEraAdmin = 1 AND (pEsAdmin = 0 OR pActivo = 0) THEN
        CALL sp_catalogo_hay_admin(pIdPerfil, 0, vHayAdmin);
        IF vHayAdmin = 0 THEN
            SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'invalido:El sistema se quedaría sin ninguna cuenta con perfil administrador.';
        END IF;
    END IF;
    IF EXISTS (SELECT 1 FROM Perfiles WHERE Nombre = pNombre COLLATE utf8mb4_unicode_ci AND IdPerfil <> pIdPerfil) THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'dup:Ya existe un perfil con ese nombre.';
    END IF;

    START TRANSACTION;
    UPDATE Perfiles
       SET Nombre = pNombre, Descripcion = COALESCE(pDescripcion, ''), EsAdmin = pEsAdmin, Activo = pActivo, Inicio = pInicio,
           NumeroCambios = NumeroCambios + 1, FechaUltimoCambio = NOW(), IdUsuarioCambia = pIdUsuarioCambia
     WHERE IdPerfil = pIdPerfil
       AND NOT (Nombre COLLATE utf8mb4_bin = pNombre COLLATE utf8mb4_bin
                AND Descripcion COLLATE utf8mb4_bin = COALESCE(pDescripcion, '') COLLATE utf8mb4_bin
                AND EsAdmin = pEsAdmin AND Activo = pActivo
                AND Inicio COLLATE utf8mb4_bin = pInicio COLLATE utf8mb4_bin);
    SET vAfectadas = ROW_COUNT();
    IF vAfectadas > 0 THEN
        INSERT INTO BitacoraPermisos (Accion, Detalle, IdUsuarioRegistra)
        VALUES ('perfil.actualizar', JSON_OBJECT('perfil', pIdPerfil, 'nombre', pNombre, 'esAdmin', pEsAdmin, 'activo', pActivo, 'inicio', pInicio),
                COALESCE(pIdUsuarioCambia, 0));
    END IF;
    COMMIT;
    SELECT 1 AS Afectadas;
END
$$

DROP PROCEDURE IF EXISTS sp_catalogo_perfil_crear $$
CREATE PROCEDURE `sp_catalogo_perfil_crear`(IN pNombre VARCHAR(80), IN pDescripcion VARCHAR(255), IN pEsAdmin TINYINT,
                                          IN pActivo TINYINT, IN pInicio VARCHAR(80), IN pIdUsuarioRegistra INT)
    SQL SECURITY INVOKER
BEGIN
    DECLARE vActorAdmin TINYINT;
    DECLARE vId INT;
    DECLARE EXIT HANDLER FOR SQLEXCEPTION BEGIN ROLLBACK; RESIGNAL; END;

    SET pNombre = TRIM(COALESCE(pNombre, '')), pEsAdmin = COALESCE(pEsAdmin, 0), pActivo = COALESCE(pActivo, 1), pInicio = TRIM(COALESCE(pInicio, ''));
    IF pNombre = '' THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'invalido:El nombre es obligatorio.'; END IF;
    CALL sp_catalogo_actor_admin(pIdUsuarioRegistra, vActorAdmin);
    IF pEsAdmin = 1 AND vActorAdmin = 0 THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'permiso:Solo un administrador puede crear un perfil administrador.';
    END IF;
    IF EXISTS (SELECT 1 FROM Perfiles WHERE Nombre = pNombre COLLATE utf8mb4_unicode_ci) THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'dup:Ya existe un perfil con ese nombre.';
    END IF;

    START TRANSACTION;
    INSERT INTO Perfiles (Nombre, Descripcion, EsAdmin, Activo, Inicio, IdUsuarioRegistra)
    VALUES (pNombre, COALESCE(pDescripcion, ''), pEsAdmin, pActivo, pInicio, COALESCE(pIdUsuarioRegistra, 0));
    SET vId = LAST_INSERT_ID();
    INSERT INTO BitacoraPermisos (Accion, Detalle, IdUsuarioRegistra)
    VALUES ('perfil.crear', JSON_OBJECT('perfil', vId, 'nombre', pNombre, 'esAdmin', pEsAdmin), COALESCE(pIdUsuarioRegistra, 0));
    COMMIT;
    SELECT vId AS Id;
END
$$

DROP PROCEDURE IF EXISTS sp_catalogo_perfil_eliminar $$
CREATE PROCEDURE `sp_catalogo_perfil_eliminar`(IN pIdPerfil INT, IN pIdUsuarioCambia INT)
    SQL SECURITY INVOKER
BEGIN
    DECLARE vEsAdmin TINYINT;
    DECLARE vNombre VARCHAR(80);
    DECLARE vCuentas INT;
    DECLARE vActorAdmin TINYINT;
    DECLARE EXIT HANDLER FOR SQLEXCEPTION BEGIN ROLLBACK; RESIGNAL; END;

    SELECT EsAdmin, Nombre INTO vEsAdmin, vNombre FROM Perfiles WHERE IdPerfil = pIdPerfil;
    IF vEsAdmin IS NULL THEN
        SELECT 0 AS Afectadas;
    ELSE
        CALL sp_catalogo_actor_admin(pIdUsuarioCambia, vActorAdmin);
        IF vActorAdmin = 0 AND vEsAdmin = 1 THEN
            SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'permiso:Solo un administrador puede eliminar un perfil administrador.';
        END IF;
        IF vActorAdmin = 0 AND EXISTS (SELECT 1 FROM CuentaPerfiles WHERE IdCuenta = pIdUsuarioCambia AND IdPerfil = pIdPerfil) THEN
            SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'permiso:No puedes eliminar un perfil que tienes asignado.';
        END IF;
        SELECT COUNT(*) INTO vCuentas FROM CuentaPerfiles WHERE IdPerfil = pIdPerfil;
        IF vCuentas > 0 THEN
            SET @msg = CONCAT('en_uso:El perfil lo tienen ', vCuentas, ' cuenta(s): quítaselo antes de eliminarlo.');
            SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = @msg;
        END IF;

        START TRANSACTION;
        DELETE FROM Perfiles WHERE IdPerfil = pIdPerfil;
        INSERT INTO BitacoraPermisos (Accion, Detalle, IdUsuarioRegistra)
        VALUES ('perfil.eliminar', JSON_OBJECT('perfil', pIdPerfil, 'nombre', vNombre), COALESCE(pIdUsuarioCambia, 0));
        COMMIT;
        SELECT 1 AS Afectadas;
    END IF;
END
$$

DROP PROCEDURE IF EXISTS sp_catalogo_perfil_listar $$
CREATE PROCEDURE `sp_catalogo_perfil_listar`()
    SQL SECURITY INVOKER
BEGIN
    SELECT p.IdPerfil, p.Nombre, p.Descripcion, p.EsAdmin, p.Activo, p.Inicio, p.TipoCuenta,
           (SELECT COUNT(*) FROM CuentaPerfiles up WHERE up.IdPerfil = p.IdPerfil) AS Cuentas,
           (SELECT COUNT(*) FROM PerfilObjeto po JOIN Objetos o ON o.IdObjeto = po.IdObjeto AND o.Activo = 1
             WHERE po.IdPerfil = p.IdPerfil) AS Objetos
      FROM Perfiles p
     ORDER BY p.EsAdmin DESC, p.Nombre;
END
$$

DROP PROCEDURE IF EXISTS sp_catalogo_perfil_objetos $$
CREATE PROCEDURE `sp_catalogo_perfil_objetos`(IN pIdPerfil INT)
    SQL SECURITY INVOKER
BEGIN
    SELECT po.IdPerfil, po.IdObjeto
      FROM PerfilObjeto po
     WHERE COALESCE(pIdPerfil, 0) = 0 OR po.IdPerfil = pIdPerfil;
END
$$

DROP PROCEDURE IF EXISTS sp_catalogo_perfil_objetos_guardar $$
CREATE PROCEDURE `sp_catalogo_perfil_objetos_guardar`(IN pIdPerfil INT, IN pObjetos MEDIUMTEXT, IN pIdUsuarioCambia INT)
    SQL SECURITY INVOKER
BEGIN
    DECLARE vEsAdmin TINYINT;
    DECLARE vActorAdmin TINYINT;
    DECLARE vQuitados INT;
    DECLARE vAgregados INT;
    DECLARE vObjeto VARCHAR(140);
    DECLARE EXIT HANDLER FOR SQLEXCEPTION BEGIN ROLLBACK; RESIGNAL; END;

    SELECT EsAdmin INTO vEsAdmin FROM Perfiles WHERE IdPerfil = pIdPerfil;
    IF vEsAdmin IS NULL THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'notfound:Perfil no encontrado.'; END IF;

    DROP TEMPORARY TABLE IF EXISTS tmp_pedidos;
    CREATE TEMPORARY TABLE tmp_pedidos (IdObjeto INT PRIMARY KEY);
    INSERT IGNORE INTO tmp_pedidos
    SELECT o.IdObjeto
      FROM JSON_TABLE(COALESCE(NULLIF(pObjetos, ''), '[]'), '$[*]' COLUMNS (IdObjeto INT PATH '$')) AS j
      JOIN Objetos o ON o.IdObjeto = j.IdObjeto AND o.Activo = 1 AND o.IdTipoObjeto IN (2, 3);

    CALL sp_catalogo_actor_admin(pIdUsuarioCambia, vActorAdmin);
    IF vActorAdmin = 0 THEN
        IF vEsAdmin = 1 THEN
            SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'permiso:Solo un administrador puede tocar un perfil administrador.';
        END IF;
        IF EXISTS (SELECT 1 FROM CuentaPerfiles WHERE IdCuenta = pIdUsuarioCambia AND IdPerfil = pIdPerfil) THEN
            SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'permiso:No puedes modificar un perfil que tienes asignado.';
        END IF;
        CALL sp_catalogo_llenar_concedidos(pIdUsuarioCambia);
        SELECT CONCAT(c.Clave, '/', o.Clave) INTO vObjeto
          FROM tmp_pedidos t
          JOIN Objetos o ON o.IdObjeto = t.IdObjeto
          JOIN Componentes c ON c.IdComponente = o.IdComponente
          LEFT JOIN tmp_concedidos k ON k.IdObjeto = t.IdObjeto
         WHERE NOT EXISTS (SELECT 1 FROM PerfilObjeto po WHERE po.IdPerfil = pIdPerfil AND po.IdObjeto = t.IdObjeto)
           AND (o.Sensible = 1 OR k.IdObjeto IS NULL)
         LIMIT 1;
        DROP TEMPORARY TABLE tmp_concedidos;
        IF vObjeto IS NOT NULL THEN
            SET @msg = CONCAT('permiso:No puedes conceder ', vObjeto, ': no lo tienes o solo lo concede un administrador.');
            SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = @msg;
        END IF;
    END IF;

    START TRANSACTION;
    DELETE FROM PerfilObjeto WHERE IdPerfil = pIdPerfil AND IdObjeto NOT IN (SELECT IdObjeto FROM tmp_pedidos);
    SET vQuitados = ROW_COUNT();
    INSERT IGNORE INTO PerfilObjeto (IdPerfil, IdObjeto, IdUsuarioRegistra)
    SELECT pIdPerfil, IdObjeto, COALESCE(pIdUsuarioCambia, 0) FROM tmp_pedidos;
    SET vAgregados = ROW_COUNT();
    IF vQuitados + vAgregados > 0 THEN
        INSERT INTO BitacoraPermisos (Accion, Detalle, IdUsuarioRegistra)
        VALUES ('perfil.objetos', JSON_OBJECT('perfil', pIdPerfil, 'agregados', vAgregados, 'quitados', vQuitados),
                COALESCE(pIdUsuarioCambia, 0));
    END IF;
    COMMIT;
    DROP TEMPORARY TABLE tmp_pedidos;
    SELECT vQuitados + vAgregados AS Afectadas;
END
$$

DROP PROCEDURE IF EXISTS sp_catalogo_revision $$
CREATE PROCEDURE `sp_catalogo_revision`()
    SQL SECURITY INVOKER
BEGIN
    SELECT COALESCE(MAX(IdBitacora), 0) AS Revision FROM BitacoraPermisos;
END
$$

DROP PROCEDURE IF EXISTS sp_catalogo_cuenta_es_admin $$
CREATE PROCEDURE `sp_catalogo_cuenta_es_admin`(IN pIdCuenta INT, OUT pEsAdmin TINYINT)
    SQL SECURITY INVOKER
BEGIN
    SET pEsAdmin = EXISTS (SELECT 1 FROM CuentaPerfiles up
                             JOIN Perfiles p ON p.IdPerfil = up.IdPerfil AND p.Activo = 1 AND p.EsAdmin = 1
                            WHERE up.IdCuenta = pIdCuenta);
END
$$

DROP PROCEDURE IF EXISTS sp_catalogo_cuenta_perfiles $$
CREATE PROCEDURE `sp_catalogo_cuenta_perfiles`(IN pIdCuenta INT)
    SQL SECURITY INVOKER
BEGIN
    SELECT up.IdCuenta, p.IdPerfil, p.Nombre, p.EsAdmin, p.Activo, p.Inicio
      FROM CuentaPerfiles up
      JOIN Perfiles p ON p.IdPerfil = up.IdPerfil
     WHERE COALESCE(pIdCuenta, 0) = 0 OR up.IdCuenta = pIdCuenta
     ORDER BY up.IdCuenta, p.EsAdmin DESC, p.Nombre;
END
$$

DROP PROCEDURE IF EXISTS sp_catalogo_cuenta_perfiles_asignar $$
CREATE PROCEDURE `sp_catalogo_cuenta_perfiles_asignar`(IN pIdCuenta INT, IN pPerfiles MEDIUMTEXT, IN pIdUsuarioCambia INT)
    SQL SECURITY INVOKER
BEGIN
    DECLARE vActorAdmin TINYINT;
    DECLARE vHayAdmin TINYINT;
    DECLARE vQuitados INT;
    DECLARE vAgregados INT;
    DECLARE vPerfil VARCHAR(80);
    DECLARE EXIT HANDLER FOR SQLEXCEPTION BEGIN ROLLBACK; RESIGNAL; END;

    IF NOT EXISTS (SELECT 1 FROM cuenta WHERE id_cuenta = pIdCuenta) THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'notfound:Cuenta no encontrada.';
    END IF;

    DROP TEMPORARY TABLE IF EXISTS tmp_perfiles;
    CREATE TEMPORARY TABLE tmp_perfiles (IdPerfil INT PRIMARY KEY, EsAdmin TINYINT, Activo TINYINT);
    INSERT IGNORE INTO tmp_perfiles
    SELECT p.IdPerfil, p.EsAdmin, p.Activo
      FROM JSON_TABLE(COALESCE(NULLIF(pPerfiles, ''), '[]'), '$[*]' COLUMNS (IdPerfil INT PATH '$')) AS j
      JOIN Perfiles p ON p.IdPerfil = j.IdPerfil;

    CALL sp_catalogo_actor_admin(pIdUsuarioCambia, vActorAdmin);
    IF vActorAdmin = 0 THEN
        
        IF EXISTS (SELECT 1 FROM tmp_perfiles t
                    WHERE t.EsAdmin = 1
                      AND NOT EXISTS (SELECT 1 FROM CuentaPerfiles up WHERE up.IdCuenta = pIdCuenta AND up.IdPerfil = t.IdPerfil)) THEN
            SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'permiso:Solo un administrador puede asignar o quitar un perfil administrador.';
        END IF;
        IF EXISTS (SELECT 1 FROM CuentaPerfiles up JOIN Perfiles p ON p.IdPerfil = up.IdPerfil AND p.EsAdmin = 1
                    WHERE up.IdCuenta = pIdCuenta AND up.IdPerfil NOT IN (SELECT IdPerfil FROM tmp_perfiles)) THEN
            SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'permiso:Solo un administrador puede asignar o quitar un perfil administrador.';
        END IF;
        
        CALL sp_catalogo_llenar_concedidos(pIdUsuarioCambia);
        SELECT p.Nombre INTO vPerfil
          FROM tmp_perfiles t
          JOIN Perfiles p      ON p.IdPerfil = t.IdPerfil
          JOIN PerfilObjeto po ON po.IdPerfil = t.IdPerfil
          JOIN Objetos o       ON o.IdObjeto = po.IdObjeto AND o.Activo = 1
          LEFT JOIN tmp_concedidos k ON k.IdObjeto = po.IdObjeto
         WHERE NOT EXISTS (SELECT 1 FROM CuentaPerfiles up WHERE up.IdCuenta = pIdCuenta AND up.IdPerfil = t.IdPerfil)
           AND (o.Sensible = 1 OR k.IdObjeto IS NULL)
         LIMIT 1;
        DROP TEMPORARY TABLE tmp_concedidos;
        IF vPerfil IS NOT NULL THEN
            SET @msg = CONCAT('permiso:No puedes asignar el perfil ', vPerfil, ': concede cosas que no tienes o que solo da un administrador.');
            SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = @msg;
        END IF;
    END IF;

    
    CALL sp_catalogo_hay_admin(0, pIdCuenta, vHayAdmin);
    IF vHayAdmin = 0 AND NOT EXISTS (SELECT 1 FROM tmp_perfiles WHERE EsAdmin = 1 AND Activo = 1)
       AND EXISTS (SELECT 1 FROM CuentaPerfiles up JOIN Perfiles p ON p.IdPerfil = up.IdPerfil AND p.EsAdmin = 1 AND p.Activo = 1
                    WHERE up.IdCuenta = pIdCuenta) THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'invalido:El sistema se quedaría sin ninguna cuenta con perfil administrador.';
    END IF;

    START TRANSACTION;
    DELETE FROM CuentaPerfiles WHERE IdCuenta = pIdCuenta AND IdPerfil NOT IN (SELECT IdPerfil FROM tmp_perfiles);
    SET vQuitados = ROW_COUNT();
    INSERT IGNORE INTO CuentaPerfiles (IdCuenta, IdPerfil, IdUsuarioRegistra)
    SELECT pIdCuenta, IdPerfil, COALESCE(pIdUsuarioCambia, 0) FROM tmp_perfiles;
    SET vAgregados = ROW_COUNT();
    IF vQuitados + vAgregados > 0 THEN
        INSERT INTO BitacoraPermisos (Accion, Detalle, IdUsuarioRegistra)
        VALUES ('cuenta.perfiles', JSON_OBJECT('cuenta', pIdCuenta, 'perfiles', JSON_EXTRACT(COALESCE(NULLIF(pPerfiles, ''), '[]'), '$'),
                                                'agregados', vAgregados, 'quitados', vQuitados),
                COALESCE(pIdUsuarioCambia, 0));
    END IF;
    COMMIT;
    DROP TEMPORARY TABLE tmp_perfiles;
    SELECT vQuitados + vAgregados AS Afectadas;
END
$$

DROP PROCEDURE IF EXISTS sp_catalogo_version_registrar $$
CREATE PROCEDURE `sp_catalogo_version_registrar`(IN pHuella CHAR(64), IN pObjetos INT, IN pOrigen VARCHAR(20), IN pIdUsuarioRegistra INT)
    SQL SECURITY INVOKER
BEGIN
    DECLARE vId INT;
    DECLARE EXIT HANDLER FOR SQLEXCEPTION BEGIN ROLLBACK; RESIGNAL; END;
    START TRANSACTION;
    INSERT INTO CatalogoVersion (Huella, Objetos, Origen, IdUsuarioRegistra)
    VALUES (pHuella, COALESCE(pObjetos, 0), COALESCE(NULLIF(pOrigen, ''), 'pantalla'), COALESCE(pIdUsuarioRegistra, 0));
    SET vId = LAST_INSERT_ID();
    INSERT INTO BitacoraPermisos (Accion, Detalle, IdUsuarioRegistra)
    VALUES ('catalogo.sincronizar', JSON_OBJECT('version', vId, 'origen', COALESCE(NULLIF(pOrigen, ''), 'pantalla'),
                                                'objetos', COALESCE(pObjetos, 0), 'huella', pHuella),
            COALESCE(pIdUsuarioRegistra, 0));
    COMMIT;
    SELECT vId AS Id;
END
$$

DROP PROCEDURE IF EXISTS sp_catalogo_version_ultima $$
CREATE PROCEDURE `sp_catalogo_version_ultima`()
    SQL SECURITY INVOKER
BEGIN
    SELECT IdCatalogoVersion, Huella, Objetos, Origen, FechaRegistro, IdUsuarioRegistra
      FROM CatalogoVersion ORDER BY IdCatalogoVersion DESC LIMIT 1;
END
$$

DROP PROCEDURE IF EXISTS sp_componente_listar $$
CREATE PROCEDURE `sp_componente_listar`()
    SQL SECURITY INVOKER
BEGIN
    SELECT IdComponente, Clave, Clase, Archivo, Modulo, Conexion, Sistema
      FROM Componentes
     WHERE Clave <> ''
     ORDER BY Sistema DESC, Clave;
END
$$

DROP PROCEDURE IF EXISTS sp_componente_registrar $$
CREATE PROCEDURE `sp_componente_registrar`(IN pClave VARCHAR(50), IN pClase VARCHAR(80), IN pArchivo VARCHAR(160),
                                         IN pModulo VARCHAR(40), IN pConexion VARCHAR(20), IN pSistema VARCHAR(10),
                                         IN pIdUsuarioRegistra INT, IN pIdUsuarioCambia INT)
    SQL SECURITY INVOKER
BEGIN
    DECLARE vId INT;
    DECLARE vSistema VARCHAR(10);
    SET pClave = TRIM(COALESCE(pClave, '')), pClase = TRIM(COALESCE(pClase, '')), pArchivo = TRIM(COALESCE(pArchivo, '')),
        pModulo = TRIM(COALESCE(pModulo, '')), pConexion = TRIM(COALESCE(pConexion, '')),
        pSistema = TRIM(COALESCE(NULLIF(pSistema, ''), 'portal'));

    IF pClave = '' OR pClase = '' THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'invalido:Falta la clave o la clase del componente.'; END IF;
    IF pSistema <> 'portal' THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'invalido:En el colegio todos los componentes son del sistema portal.'; END IF;
    IF pArchivo NOT LIKE 'webService/controllers/%.php' OR pArchivo LIKE '%..%' THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'invalido:El archivo del componente no está en la carpeta de controllers de su sistema.';
    END IF;
    IF pConexion NOT IN ('principal', 'ninguna') THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'invalido:Conexión desconocida (principal o ninguna).';
    END IF;

    SELECT IdComponente, Sistema INTO vId, vSistema FROM Componentes WHERE Clave = pClave COLLATE utf8mb4_unicode_ci;
    IF vId IS NOT NULL AND vSistema <> pSistema COLLATE utf8mb4_unicode_ci AND (SELECT Archivo FROM Componentes WHERE IdComponente = vId) <> '' THEN
        SET @msg = CONCAT('dup:El componente ', pClave, ' ya existe en el sistema ', vSistema, '.');
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = @msg;
    END IF;

    IF vId IS NULL THEN
        INSERT INTO Componentes (Clave, Clase, Archivo, Modulo, Conexion, Sistema, IdUsuarioRegistra)
        VALUES (pClave, pClase, pArchivo, pModulo, pConexion, pSistema, COALESCE(pIdUsuarioRegistra, 0));
        SET vId = LAST_INSERT_ID();
    ELSE
        UPDATE Componentes
           SET Clase = pClase, Archivo = pArchivo, Modulo = pModulo, Conexion = pConexion, Sistema = pSistema,
               NumeroCambios = NumeroCambios + 1, FechaUltimoCambio = NOW(), IdUsuarioCambia = pIdUsuarioCambia
         WHERE IdComponente = vId
           AND NOT (Clase COLLATE utf8mb4_bin = pClase COLLATE utf8mb4_bin
                    AND Archivo COLLATE utf8mb4_bin = pArchivo COLLATE utf8mb4_bin
                    AND Modulo = pModulo COLLATE utf8mb4_unicode_ci AND Conexion = pConexion COLLATE utf8mb4_unicode_ci
                    AND Sistema = pSistema COLLATE utf8mb4_unicode_ci);
    END IF;
    SELECT vId AS Id;
END
$$

DROP PROCEDURE IF EXISTS sp_objeto_autorizar $$
CREATE PROCEDURE `sp_objeto_autorizar`(IN pIdCuenta INT, IN pComponente VARCHAR(50), IN pMetodo VARCHAR(80))
    SQL SECURITY INVOKER
BEGIN
    SET pIdCuenta = COALESCE(pIdCuenta, 0);
    SELECT IF(o.IdObjeto IS NULL, 0, 1) AS Existe, o.IdObjeto, o.IdTipoObjeto AS Tipo, o.SoloPost, o.Acceso,
           CASE
               WHEN o.IdObjeto IS NULL     THEN 0
               WHEN o.Acceso = 'publico'   THEN 1
               WHEN pIdCuenta = 0         THEN 0
               WHEN o.Acceso = 'sesion'    THEN 1
               WHEN EXISTS (SELECT 1
                              FROM CuentaPerfiles up
                              JOIN Perfiles p ON p.IdPerfil = up.IdPerfil AND p.Activo = 1
                             WHERE up.IdCuenta = pIdCuenta
                               AND (p.EsAdmin = 1
                                    OR EXISTS (SELECT 1 FROM PerfilObjeto po WHERE po.IdPerfil = p.IdPerfil AND po.IdObjeto = o.IdObjeto)
                                    OR EXISTS (SELECT 1 FROM ObjetoUsos ou
                                                 JOIN Objetos v ON v.IdObjeto = ou.IdObjeto AND v.Activo = 1
                                                 JOIN PerfilObjeto po ON po.IdObjeto = v.IdObjeto AND po.IdPerfil = p.IdPerfil
                                                WHERE ou.IdObjetoMetodo = o.IdObjeto))) THEN 1
               ELSE 0
           END AS Permitido,
           c.Clase, c.Archivo, c.Modulo, c.Conexion
      FROM (SELECT 1 AS Uno) d
      LEFT JOIN (Objetos o JOIN Componentes c ON c.IdComponente = o.IdComponente)
             ON c.Clave = pComponente COLLATE utf8mb4_unicode_ci
            AND o.Clave = pMetodo COLLATE utf8mb4_unicode_ci
            AND o.Activo = 1 AND o.IdTipoObjeto IN (2, 3) AND c.Sistema = 'portal';
END
$$

DROP PROCEDURE IF EXISTS sp_objeto_concedidos $$
CREATE PROCEDURE `sp_objeto_concedidos`(IN pIdCuenta INT, IN pComponente VARCHAR(50))
    SQL SECURITY INVOKER
BEGIN
    CALL sp_catalogo_llenar_concedidos(pIdCuenta);
    SELECT o.Clave AS Metodo
      FROM tmp_concedidos t
      JOIN Objetos o     ON o.IdObjeto = t.IdObjeto AND o.IdTipoObjeto IN (2, 3)
      JOIN Componentes c ON c.IdComponente = o.IdComponente
     WHERE c.Clave = pComponente COLLATE utf8mb4_unicode_ci
     ORDER BY o.Orden, o.Clave;
    DROP TEMPORARY TABLE tmp_concedidos;
END
$$

DROP PROCEDURE IF EXISTS sp_objeto_listar $$
CREATE PROCEDURE `sp_objeto_listar`()
    SQL SECURITY INVOKER
BEGIN
    SELECT o.IdObjeto, o.IdPadre, o.IdTipoObjeto AS Tipo, c.Clave AS Componente, c.Clase, o.Clave, o.Nombre, o.Icono,
           o.Orden, o.SoloPost, o.Acceso, o.Sensible, o.PaginaAnterior, o.Activo, c.Sistema
      FROM Objetos o
      JOIN Componentes c ON c.IdComponente = o.IdComponente
     ORDER BY o.IdTipoObjeto, c.Clave, o.Orden, o.IdObjeto;
END
$$

DROP PROCEDURE IF EXISTS sp_objeto_menu $$
CREATE PROCEDURE `sp_objeto_menu`(IN pIdCuenta INT)
    SQL SECURITY INVOKER
BEGIN
    CALL sp_catalogo_llenar_concedidos(pIdCuenta);
    SELECT o.IdTipoObjeto AS Tipo, o.IdObjeto, o.IdPadre, o.Nombre, o.Icono, CONCAT('/', c.Clave, '/', o.Clave) AS Ruta, o.Orden
      FROM tmp_concedidos t
      JOIN Objetos o     ON o.IdObjeto = t.IdObjeto AND o.IdTipoObjeto = 2 AND o.Nombre <> ''
      JOIN Componentes c ON c.IdComponente = o.IdComponente
      LEFT JOIN Objetos pa ON pa.IdObjeto = o.IdPadre
     WHERE pa.IdObjeto IS NULL OR pa.IdTipoObjeto = 1
    UNION ALL
    SELECT g.IdTipoObjeto, g.IdObjeto, g.IdPadre, g.Nombre, g.Icono, '', g.Orden
      FROM Objetos g
     WHERE g.IdTipoObjeto = 1 AND g.Activo = 1
     ORDER BY Orden, IdObjeto;
    DROP TEMPORARY TABLE tmp_concedidos;
END
$$

DROP PROCEDURE IF EXISTS sp_objeto_registrar $$
CREATE PROCEDURE `sp_objeto_registrar`(IN pComponente VARCHAR(50), IN pClase VARCHAR(80), IN pTipo INT, IN pPadre VARCHAR(80),
                                     IN pClave VARCHAR(80), IN pNombre VARCHAR(120), IN pIcono VARCHAR(60), IN pOrden INT,
                                     IN pSoloPost TINYINT, IN pAcceso VARCHAR(10), IN pSensible TINYINT,
                                     IN pPaginaAnterior VARCHAR(50), IN pIdUsuarioRegistra INT, IN pIdUsuarioCambia INT)
    SQL SECURITY INVOKER
BEGIN
    DECLARE vIdComponente INT;
    DECLARE vIdPadre INT;
    DECLARE vIdObjeto INT;
    DECLARE EXIT HANDLER FOR SQLEXCEPTION BEGIN ROLLBACK; RESIGNAL; END;

    SET pComponente = COALESCE(pComponente, ''), pClase = COALESCE(pClase, ''), pPadre = COALESCE(pPadre, ''),
        pClave = TRIM(COALESCE(pClave, '')), pNombre = COALESCE(pNombre, ''), pIcono = COALESCE(pIcono, ''),
        pOrden = COALESCE(pOrden, 0), pSoloPost = COALESCE(pSoloPost, 1), pSensible = COALESCE(pSensible, 0);

    IF pTipo NOT IN (1, 2, 3) THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'invalido:Tipo de objeto inválido.'; END IF;
    IF pClave = '' THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'invalido:Falta la clave del objeto.'; END IF;
    IF pAcceso NOT IN ('publico', 'sesion', 'perfil') THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'invalido:Acceso inválido.'; END IF;
    IF (pTipo = 1) <> (pComponente = '') THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'invalido:Solo los grupos van sin componente.';
    END IF;

    START TRANSACTION;

    SELECT IdComponente INTO vIdComponente FROM Componentes WHERE Clave = pComponente COLLATE utf8mb4_unicode_ci;
    IF vIdComponente IS NULL THEN
        INSERT INTO Componentes (Clave, Clase, IdUsuarioRegistra) VALUES (pComponente, pClase, COALESCE(pIdUsuarioRegistra, 0));
        SET vIdComponente = LAST_INSERT_ID();
    ELSEIF pClase <> '' THEN
        UPDATE Componentes
           SET Clase = pClase, NumeroCambios = NumeroCambios + 1, FechaUltimoCambio = NOW(), IdUsuarioCambia = pIdUsuarioCambia
         WHERE IdComponente = vIdComponente AND Clase COLLATE utf8mb4_bin <> pClase COLLATE utf8mb4_bin;
    END IF;

    IF pPadre <> '' THEN
        IF pTipo = 3 THEN
            SELECT IdObjeto INTO vIdPadre FROM Objetos
             WHERE IdComponente = vIdComponente AND Clave = pPadre COLLATE utf8mb4_unicode_ci AND IdTipoObjeto = 2;
        ELSE
            SELECT o.IdObjeto INTO vIdPadre FROM Objetos o JOIN Componentes c ON c.IdComponente = o.IdComponente
             WHERE c.Clave = '' AND o.Clave = pPadre COLLATE utf8mb4_unicode_ci AND o.IdTipoObjeto = 1;
            
            IF vIdPadre IS NULL AND pTipo = 2 THEN
                SELECT IdObjeto INTO vIdPadre FROM Objetos
                 WHERE IdComponente = vIdComponente AND Clave = pPadre COLLATE utf8mb4_unicode_ci AND IdTipoObjeto = 2
                   AND Clave <> pClave COLLATE utf8mb4_unicode_ci;
            END IF;
        END IF;
        IF vIdPadre IS NULL THEN
            SET @msg = CONCAT('invalido:El padre ', pPadre, ' de ', pComponente, '/', pClave, ' no está registrado.');
            SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = @msg;
        END IF;
    END IF;

    SELECT IdObjeto INTO vIdObjeto FROM Objetos WHERE IdComponente = vIdComponente AND Clave = pClave COLLATE utf8mb4_unicode_ci;
    IF vIdObjeto IS NULL THEN
        INSERT INTO Objetos (IdTipoObjeto, IdComponente, IdPadre, Clave, Nombre, Icono, Orden, SoloPost, Acceso, Sensible,
                             PaginaAnterior, Activo, IdUsuarioRegistra)
        VALUES (pTipo, vIdComponente, vIdPadre, pClave, pNombre, pIcono, pOrden, pSoloPost, pAcceso, pSensible,
                pPaginaAnterior, 1, COALESCE(pIdUsuarioRegistra, 0));
        SET vIdObjeto = LAST_INSERT_ID();
    ELSE
        UPDATE Objetos
           SET IdTipoObjeto = pTipo, IdPadre = vIdPadre, Clave = pClave, Nombre = pNombre, Icono = pIcono, Orden = pOrden,
               SoloPost = pSoloPost, Acceso = pAcceso, Sensible = pSensible, PaginaAnterior = pPaginaAnterior, Activo = 1,
               NumeroCambios = NumeroCambios + 1, FechaUltimoCambio = NOW(), IdUsuarioCambia = pIdUsuarioCambia
         WHERE IdObjeto = vIdObjeto
           AND NOT (IdTipoObjeto = pTipo AND IdPadre <=> vIdPadre
                    AND Clave COLLATE utf8mb4_bin = pClave COLLATE utf8mb4_bin
                    AND Nombre COLLATE utf8mb4_bin = pNombre COLLATE utf8mb4_bin
                    AND Icono COLLATE utf8mb4_bin = pIcono COLLATE utf8mb4_bin
                    AND Orden = pOrden AND SoloPost = pSoloPost AND Acceso = pAcceso COLLATE utf8mb4_unicode_ci
                    AND Sensible = pSensible AND PaginaAnterior <=> pPaginaAnterior COLLATE utf8mb4_unicode_ci AND Activo = 1);
    END IF;

    COMMIT;
    SELECT vIdObjeto AS Id;
END
$$

DROP PROCEDURE IF EXISTS sp_objeto_retirar $$
CREATE PROCEDURE `sp_objeto_retirar`(IN pVigentes MEDIUMTEXT, IN pSistema VARCHAR(10), IN pIdUsuarioCambia INT)
    SQL SECURITY INVOKER
BEGIN
    DECLARE vAfectadas INT;
    SET pSistema = COALESCE(NULLIF(pSistema, ''), 'portal');
    IF COALESCE(JSON_LENGTH(pVigentes), 0) = 0 THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'invalido:La lista de objetos vigentes está vacía.';
    END IF;

    DROP TEMPORARY TABLE IF EXISTS tmp_vigentes;
    CREATE TEMPORARY TABLE tmp_vigentes (Llave VARCHAR(140) COLLATE utf8mb4_unicode_ci PRIMARY KEY);
    INSERT IGNORE INTO tmp_vigentes
    SELECT j.Llave FROM JSON_TABLE(pVigentes, '$[*]' COLUMNS (Llave VARCHAR(140) PATH '$')) AS j;

    UPDATE Objetos o JOIN Componentes c ON c.IdComponente = o.IdComponente
       SET o.Activo = 0, o.NumeroCambios = o.NumeroCambios + 1, o.FechaUltimoCambio = NOW(), o.IdUsuarioCambia = pIdUsuarioCambia
     WHERE o.Activo = 1 AND c.Sistema = pSistema COLLATE utf8mb4_unicode_ci
       AND CONCAT(c.Clave, ':', o.Clave) NOT IN (SELECT Llave FROM tmp_vigentes);
    SET vAfectadas = ROW_COUNT();

    DROP TEMPORARY TABLE tmp_vigentes;
    SELECT vAfectadas AS Afectadas;
END
$$

DROP PROCEDURE IF EXISTS sp_objeto_uso_registrar $$
CREATE PROCEDURE `sp_objeto_uso_registrar`(IN pComponente VARCHAR(50), IN pClave VARCHAR(80), IN pUsaComponente VARCHAR(50),
                                         IN pUsaClave VARCHAR(80), IN pIdUsuarioRegistra INT)
    SQL SECURITY INVOKER
BEGIN
    DECLARE vVista INT;
    DECLARE vMetodo INT;
    SELECT o.IdObjeto INTO vVista FROM Objetos o JOIN Componentes c ON c.IdComponente = o.IdComponente
     WHERE c.Clave = pComponente COLLATE utf8mb4_unicode_ci AND o.Clave = pClave COLLATE utf8mb4_unicode_ci AND o.IdTipoObjeto = 2;
    SELECT o.IdObjeto INTO vMetodo FROM Objetos o JOIN Componentes c ON c.IdComponente = o.IdComponente
     WHERE c.Clave = pUsaComponente COLLATE utf8mb4_unicode_ci AND o.Clave = pUsaClave COLLATE utf8mb4_unicode_ci AND o.IdTipoObjeto IN (2, 3);
    IF vVista IS NULL OR vMetodo IS NULL THEN
        SET @msg = CONCAT('notfound:No están registrados ', pComponente, '/', pClave, ' o ', pUsaComponente, '/', pUsaClave, '.');
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = @msg;
    END IF;
    INSERT IGNORE INTO ObjetoUsos (IdObjeto, IdObjetoMetodo, IdUsuarioRegistra) VALUES (vVista, vMetodo, COALESCE(pIdUsuarioRegistra, 0));
    SELECT ROW_COUNT() AS Afectadas;
END
$$

DELIMITER ;

-- ──────────────────────────── sp_catalogos.sql ────────────────────────────
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

-- ──────────────────────────── sp_ciclo.sql ────────────────────────────
-- ============================================================
-- Colegio del Bosque · Procedures del módulo Ciclo (webService/controllers/CicloController.php)
-- Idempotente (DROP + CREATE). Antes: sp_sistema.sql
--
-- El estado de un ciclo vive en el catálogo status (ámbito 'ciclo': activo / cerrado). Solo uno está activo.
-- Las columnas salen con el nombre de la propiedad del model Ciclo.
-- ============================================================

DELIMITER $$

DROP PROCEDURE IF EXISTS sp_ciclo_listar $$
CREATE PROCEDURE sp_ciclo_listar()
SQL SECURITY INVOKER
BEGIN
    SELECT c.id_ciclo AS IdCiclo, c.nombre AS Nombre, c.fecha_inicio AS FechaInicio, c.fecha_fin AS FechaFin,
           COALESCE(s.clave = 'activo', 0) AS Activo, s.clave AS Estatus, s.nombre AS EstatusNombre
      FROM ciclo c
      LEFT JOIN status s ON s.id_status = c.status_id
     ORDER BY c.fecha_inicio DESC;
END $$

-- Un ciclo nace cerrado: se activa aparte (sp_ciclo_activar).
DROP PROCEDURE IF EXISTS sp_ciclo_crear $$
CREATE PROCEDURE sp_ciclo_crear(IN pNombre VARCHAR(30), IN pFechaInicio DATE, IN pFechaFin DATE)
SQL SECURITY INVOKER
BEGIN
    INSERT INTO ciclo (nombre, fecha_inicio, fecha_fin, status_id)
    VALUES (pNombre, pFechaInicio, pFechaFin,
            (SELECT id_status FROM status WHERE ambito = 'ciclo' AND clave = 'cerrado'));

    SELECT LAST_INSERT_ID() AS Id;
END $$

DROP PROCEDURE IF EXISTS sp_ciclo_editar $$
CREATE PROCEDURE sp_ciclo_editar(IN pIdCiclo INT, IN pNombre VARCHAR(30), IN pFechaInicio DATE, IN pFechaFin DATE)
SQL SECURITY INVOKER
BEGIN
    IF NOT EXISTS (SELECT 1 FROM ciclo WHERE id_ciclo = pIdCiclo) THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'notfound:No se encontró el ciclo.';
    END IF;

    UPDATE ciclo SET nombre = pNombre, fecha_inicio = pFechaInicio, fecha_fin = pFechaFin WHERE id_ciclo = pIdCiclo;

    SELECT 1 AS Afectadas;
END $$

-- Activa un ciclo y cierra los demás, todo o nada.
DROP PROCEDURE IF EXISTS sp_ciclo_activar $$
CREATE PROCEDURE sp_ciclo_activar(IN pIdCiclo INT)
SQL SECURITY INVOKER
BEGIN
    DECLARE EXIT HANDLER FOR SQLEXCEPTION
    BEGIN
        ROLLBACK;
        RESIGNAL;
    END;

    START TRANSACTION;

    IF NOT EXISTS (SELECT 1 FROM ciclo WHERE id_ciclo = pIdCiclo) THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'notfound:No se encontró el ciclo.';
    END IF;

    UPDATE ciclo
       SET status_id = (SELECT id_status FROM status WHERE ambito = 'ciclo' AND clave = 'cerrado')
     WHERE id_ciclo <> pIdCiclo;
    UPDATE ciclo
       SET status_id = (SELECT id_status FROM status WHERE ambito = 'ciclo' AND clave = 'activo')
     WHERE id_ciclo = pIdCiclo;

    COMMIT;

    SELECT 1 AS Afectadas;
END $$

-- OJO: los esquemas de pago del ciclo se borran con él (esquema_pago tiene ON DELETE CASCADE).
DROP PROCEDURE IF EXISTS sp_ciclo_eliminar $$
CREATE PROCEDURE sp_ciclo_eliminar(IN pIdCiclo INT)
SQL SECURITY INVOKER
BEGIN
    DELETE FROM ciclo WHERE id_ciclo = pIdCiclo;

    SELECT ROW_COUNT() AS Afectadas;
END $$

-- El ciclo activo (ninguna fila si no hay). Lo usan colegiaturas, calificaciones, reportes y el portal del padre.
DROP PROCEDURE IF EXISTS sp_ciclo_activo $$
CREATE PROCEDURE sp_ciclo_activo()
SQL SECURITY INVOKER
BEGIN
    SELECT c.id_ciclo AS IdCiclo, c.nombre AS Nombre, c.fecha_inicio AS FechaInicio, c.fecha_fin AS FechaFin, 1 AS Activo
      FROM ciclo c
     WHERE c.status_id = (SELECT id_status FROM status WHERE ambito = 'ciclo' AND clave = 'activo')
     LIMIT 1;
END $$

-- Un ciclo con sus fechas (ninguna fila si no existe). Lo usa el esquema de pago para validar sus meses.
DROP PROCEDURE IF EXISTS sp_ciclo_obtener $$
CREATE PROCEDURE sp_ciclo_obtener(IN pIdCiclo INT)
SQL SECURITY INVOKER
BEGIN
    SELECT c.id_ciclo AS IdCiclo, c.nombre AS Nombre, c.fecha_inicio AS FechaInicio, c.fecha_fin AS FechaFin,
           COALESCE(s.clave = 'activo', 0) AS Activo
      FROM ciclo c
      LEFT JOIN status s ON s.id_status = c.status_id
     WHERE c.id_ciclo = pIdCiclo;
END $$

DELIMITER ;

-- ──────────────────────────── sp_colegiatura.sql ────────────────────────────
-- ============================================================
-- Colegio del Bosque · Procedures de las colegiaturas (webService/controllers/ColegiaturaController.php,
-- EsquemaPagoController::aplicar y PadreController::resumen). Idempotente (DROP + CREATE). Antes: sp_sistema.sql
--
-- Un pago es una colegiatura mensual o la inscripción (tabla colegiatura). Los abonos son recibos ligados al pago
-- (recibo.colegiatura_id). Lo que se cobra (recargo vigente, total, saldo) lo calcula la lib Recargo: aquí no.
-- Las columnas salen con el nombre de la propiedad del model Colegiatura.
-- ============================================================

DELIMITER $$

-- Los grupos de un ciclo (0 = de todos) para filtrar el panel.
DROP PROCEDURE IF EXISTS sp_colegiatura_grupos $$
CREATE PROCEDURE sp_colegiatura_grupos(IN pIdCiclo INT)
SQL SECURITY INVOKER
BEGIN
    SELECT g.id_grupo AS IdGrupo, g.grado AS Grado, g.ciclo_id AS IdCiclo
      FROM grupo g
     WHERE g.id_grupo <> 0 AND (COALESCE(pIdCiclo, 0) = 0 OR g.ciclo_id = pIdCiclo)
     ORDER BY g.nivel, g.grado;
END $$

-- Los descuentos activos del catálogo.
DROP PROCEDURE IF EXISTS sp_colegiatura_descuentos $$
CREATE PROCEDURE sp_colegiatura_descuentos()
SQL SECURITY INVOKER
BEGIN
    SELECT td.id_descuento AS IdDescuento, td.nombre AS Nombre, td.porcentaje AS Porcentaje, td.aplica_a AS AplicaA, 1 AS Activo
      FROM tipo_descuento td
     WHERE td.status_id = (SELECT id_status FROM status WHERE ambito = 'descuento' AND clave = 'activo')
     ORDER BY td.aplica_a, td.nombre;
END $$

-- Un descuento activo del catálogo (ninguna fila si no existe o está inactivo).
DROP PROCEDURE IF EXISTS sp_colegiatura_descuento_obtener $$
CREATE PROCEDURE sp_colegiatura_descuento_obtener(IN pIdDescuento INT)
SQL SECURITY INVOKER
BEGIN
    SELECT td.id_descuento AS IdDescuento, td.nombre AS Nombre, td.porcentaje AS Porcentaje, td.aplica_a AS AplicaA, 1 AS Activo
      FROM tipo_descuento td
     WHERE td.id_descuento = pIdDescuento
       AND td.status_id = (SELECT id_status FROM status WHERE ambito = 'descuento' AND clave = 'activo');
END $$

-- Los estados que puede tener un pago.
DROP PROCEDURE IF EXISTS sp_colegiatura_estatus $$
CREATE PROCEDURE sp_colegiatura_estatus()
SQL SECURITY INVOKER
BEGIN
    SELECT s.clave AS Clave, s.nombre AS Nombre, s.color AS Color
      FROM status s
     WHERE s.ambito = 'pago'
     ORDER BY s.orden;
END $$

-- Los pagos de todos los alumnos (pIdCuenta = 0) o de uno, con lo que llevan abonado.
-- Orden: por alumno, la inscripción primero y luego por vencimiento.
DROP PROCEDURE IF EXISTS sp_colegiatura_listar $$
CREATE PROCEDURE sp_colegiatura_listar(IN pIdCuenta INT)
SQL SECURITY INVOKER
BEGIN
    SELECT col.id_pago AS IdPago, col.cuenta_id AS IdCuenta, col.ciclo_id AS IdCiclo, c.grupo_id AS IdGrupo,
           TRIM(CONCAT_WS(' ', u.nombre, u.paterno, u.materno)) AS Alumno, g.grado AS Grado,
           col.tipo AS Tipo, col.mes AS Mes, col.fecha_vencimiento AS FechaVencimiento, col.fecha_pago AS FechaPago,
           col.monto AS Monto, col.descuento AS Descuento, col.tipo_descuento_id AS IdTipoDescuento,
           col.concepto_descuento AS ConceptoDescuento, col.recargo_monto AS RecargoMonto, col.recargo AS Recargo,
           col.recibo_id AS IdRecibo,
           COALESCE(st.clave, 'pendiente') AS Estatus, st.nombre AS EstatusNombre, st.color AS EstatusColor,
           (SELECT COALESCE(SUM(rr.monto), 0) FROM recibo rr WHERE rr.colegiatura_id = col.id_pago) AS Abonado
      FROM colegiatura col
      JOIN cuenta c       ON c.id_cuenta = col.cuenta_id
      JOIN usuario u      ON u.id_usuario = c.usuario_id
      LEFT JOIN grupo g   ON g.id_grupo = c.grupo_id
      LEFT JOIN status st ON st.id_status = col.status_id
     WHERE COALESCE(pIdCuenta, 0) = 0 OR col.cuenta_id = pIdCuenta
     ORDER BY u.paterno, u.nombre, col.tipo DESC, col.fecha_vencimiento ASC;
END $$

-- Un pago, con lo abonado (ninguna fila si no existe). Para cobrarlo o aplicarle un descuento.
DROP PROCEDURE IF EXISTS sp_colegiatura_obtener $$
CREATE PROCEDURE sp_colegiatura_obtener(IN pIdPago INT)
SQL SECURITY INVOKER
BEGIN
    SELECT col.id_pago AS IdPago, col.cuenta_id AS IdCuenta, col.ciclo_id AS IdCiclo,
           col.tipo AS Tipo, col.mes AS Mes, col.fecha_vencimiento AS FechaVencimiento,
           col.monto AS Monto, col.descuento AS Descuento, col.recargo_monto AS RecargoMonto, col.recargo AS Recargo,
           col.recibo_id AS IdRecibo, COALESCE(st.clave, 'pendiente') AS Estatus,
           (SELECT COALESCE(SUM(rr.monto), 0) FROM recibo rr WHERE rr.colegiatura_id = col.id_pago) AS Abonado
      FROM colegiatura col
      LEFT JOIN status st ON st.id_status = col.status_id
     WHERE col.id_pago = pIdPago;
END $$

-- Cobra un pago, todo o nada: genera el recibo del abono (tipo Inscripción o Colegiatura) y deja el pago
-- 'pagado' (con el recargo congelado y la fecha de hoy) si pCompleta = 1, o 'parcial'. Regresa el Id del recibo.
-- 'notfound' si el pago no existe; 'invalido' si ya está pagado.
DROP PROCEDURE IF EXISTS sp_colegiatura_pagar $$
CREATE PROCEDURE sp_colegiatura_pagar(IN pIdPago INT, IN pMonto DECIMAL(10,2), IN pTipoPago VARCHAR(30),
                                      IN pComentario TEXT, IN pFecha DATETIME, IN pCompleta TINYINT,
                                      IN pRecargo DECIMAL(10,2))
SQL SECURITY INVOKER
BEGIN
    DECLARE vIdCuenta INT;
    DECLARE vTipo VARCHAR(20);
    DECLARE vEstatus VARCHAR(30);
    DECLARE vIdRecibo INT;
    DECLARE EXIT HANDLER FOR SQLEXCEPTION
    BEGIN
        ROLLBACK;
        RESIGNAL;
    END;

    START TRANSACTION;

    SELECT col.cuenta_id, col.tipo, COALESCE(st.clave, 'pendiente') INTO vIdCuenta, vTipo, vEstatus
      FROM colegiatura col
      LEFT JOIN status st ON st.id_status = col.status_id
     WHERE col.id_pago = pIdPago
       FOR UPDATE;

    IF vIdCuenta IS NULL THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'notfound:No se encontró la colegiatura.';
    END IF;
    IF vEstatus = 'pagado' THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'invalido:Este pago ya está saldado.';
    END IF;

    INSERT INTO recibo (tipo_recibo_id, naturaleza, destinatario_tipo, cuenta_id, colegiatura_id, monto, tipo_pago, comentario, fecha)
    VALUES ((SELECT id_tipo FROM tipo_recibo
              WHERE nombre = IF(vTipo = 'inscripcion', 'Inscripción', 'Colegiatura') COLLATE utf8mb4_unicode_ci LIMIT 1),
            'ingreso', 'alumno', vIdCuenta, pIdPago, pMonto, pTipoPago, pComentario, pFecha);
    SET vIdRecibo = LAST_INSERT_ID();

    IF pCompleta = 1 THEN
        UPDATE colegiatura
           SET status_id = (SELECT id_status FROM status WHERE ambito = 'pago' AND clave = 'pagado'),
               fecha_pago = CURDATE(), recargo = pRecargo, recibo_id = vIdRecibo
         WHERE id_pago = pIdPago;
    ELSE
        UPDATE colegiatura
           SET status_id = (SELECT id_status FROM status WHERE ambito = 'pago' AND clave = 'parcial'),
               recibo_id = vIdRecibo
         WHERE id_pago = pIdPago;
    END IF;

    COMMIT;

    SELECT vIdRecibo AS Id;
END $$

-- Aplica un descuento al pago (pIdTipoDescuento = NULL lo quita). 'notfound' · 'invalido' si ya está pagado.
DROP PROCEDURE IF EXISTS sp_colegiatura_descuento $$
CREATE PROCEDURE sp_colegiatura_descuento(IN pIdPago INT, IN pDescuento DECIMAL(10,2), IN pIdTipoDescuento INT,
                                          IN pConceptoDescuento VARCHAR(60))
SQL SECURITY INVOKER
BEGIN
    DECLARE vEstatus VARCHAR(30);

    SELECT COALESCE(st.clave, 'pendiente') INTO vEstatus
      FROM colegiatura col
      LEFT JOIN status st ON st.id_status = col.status_id
     WHERE col.id_pago = pIdPago;

    IF vEstatus IS NULL THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'notfound:No se encontró el registro.';
    END IF;
    IF vEstatus = 'pagado' THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'invalido:No se puede: el pago ya está registrado.';
    END IF;

    IF pIdTipoDescuento IS NULL THEN
        UPDATE colegiatura SET descuento = 0, tipo_descuento_id = NULL, concepto_descuento = NULL WHERE id_pago = pIdPago;
    ELSE
        UPDATE colegiatura
           SET descuento = pDescuento, tipo_descuento_id = pIdTipoDescuento, concepto_descuento = pConceptoDescuento
         WHERE id_pago = pIdPago;
    END IF;

    SELECT 1 AS Afectadas;
END $$

-- Fija el recargo (pesos por mes vencido) de un pago que aún no está saldado. 'invalido' si no existe o ya se pagó.
DROP PROCEDURE IF EXISTS sp_colegiatura_recargo $$
CREATE PROCEDURE sp_colegiatura_recargo(IN pIdPago INT, IN pRecargoMonto DECIMAL(10,2))
SQL SECURITY INVOKER
BEGIN
    UPDATE colegiatura
       SET recargo_monto = pRecargoMonto
     WHERE id_pago = pIdPago
       AND (status_id IS NULL OR status_id <> (SELECT id_status FROM status WHERE ambito = 'pago' AND clave = 'pagado'));

    IF ROW_COUNT() = 0 AND NOT EXISTS (
        SELECT 1 FROM colegiatura
         WHERE id_pago = pIdPago
           AND (status_id IS NULL OR status_id <> (SELECT id_status FROM status WHERE ambito = 'pago' AND clave = 'pagado'))) THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'invalido:No se puede: el pago no existe o ya está saldado.';
    END IF;

    SELECT 1 AS Afectadas;
END $$

-- Genera (o actualiza) UN pago de un esquema para un alumno. Lo llama EsquemaPagoController::aplicar por cada
-- alumno y cada pago del calendario. La inscripción es una por ciclo; la colegiatura se identifica por mes + año.
-- Un pago que no existe se crea pendiente; uno pendiente o parcial se actualiza si cambió el monto o el recargo;
-- uno pagado no se toca. Regresa Resultado: creado · actualizado · sin_cambios.
DROP PROCEDURE IF EXISTS sp_colegiatura_generar $$
CREATE PROCEDURE sp_colegiatura_generar(IN pIdCuenta INT, IN pIdCiclo INT, IN pTipo VARCHAR(20), IN pMes VARCHAR(20),
                                        IN pAnio INT, IN pFechaVencimiento DATE, IN pMonto DECIMAL(10,2),
                                        IN pRecargoMonto DECIMAL(10,2))
SQL SECURITY INVOKER
BEGIN
    DECLARE vIdPago INT;
    DECLARE vEstatus VARCHAR(30);
    DECLARE vMonto DECIMAL(10,2);
    DECLARE vRecargoMonto DECIMAL(10,2);

    SELECT col.id_pago, COALESCE(st.clave, ''), ROUND(col.monto, 2), col.recargo_monto
      INTO vIdPago, vEstatus, vMonto, vRecargoMonto
      FROM colegiatura col
      LEFT JOIN status st ON st.id_status = col.status_id
     WHERE col.cuenta_id = pIdCuenta AND col.ciclo_id = pIdCiclo AND col.tipo = pTipo COLLATE utf8mb4_unicode_ci
       AND (col.tipo = 'inscripcion'
            OR (col.mes = pMes COLLATE utf8mb4_unicode_ci AND YEAR(col.fecha_vencimiento) = pAnio))
     LIMIT 1;

    IF vIdPago IS NULL THEN
        INSERT INTO colegiatura (cuenta_id, ciclo_id, mes, tipo, monto, status_id, fecha_vencimiento, recargo_pct, recargo_monto)
        VALUES (pIdCuenta, pIdCiclo, pMes, pTipo, pMonto,
                (SELECT id_status FROM status WHERE ambito = 'pago' AND clave = 'pendiente'),
                pFechaVencimiento, 0, pRecargoMonto);
        SELECT 'creado' AS Resultado;
    ELSEIF vEstatus <> 'pagado' AND (vMonto <> pMonto OR vRecargoMonto <> pRecargoMonto) THEN
        UPDATE colegiatura SET monto = pMonto, recargo_monto = pRecargoMonto WHERE id_pago = vIdPago;
        SELECT 'actualizado' AS Resultado;
    ELSE
        SELECT 'sin_cambios' AS Resultado;
    END IF;
END $$

-- Regresa las filas afectadas (0 = no existía). Sus recibos se conservan.
DROP PROCEDURE IF EXISTS sp_colegiatura_eliminar $$
CREATE PROCEDURE sp_colegiatura_eliminar(IN pIdPago INT)
SQL SECURITY INVOKER
BEGIN
    DELETE FROM colegiatura WHERE id_pago = pIdPago;

    SELECT ROW_COUNT() AS Afectadas;
END $$

DELIMITER ;

-- ──────────────────────────── sp_cuenta.sql ────────────────────────────
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
DROP PROCEDURE IF EXISTS sp_cuenta_listar $$
CREATE PROCEDURE sp_cuenta_listar(IN pIdPermiso INT)
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
DROP PROCEDURE IF EXISTS sp_cuenta_grupos $$
CREATE PROCEDURE sp_cuenta_grupos()
SQL SECURITY INVOKER
BEGIN
    SELECT g.id_grupo AS IdGrupo, g.grado AS Grado, ci.nombre AS Ciclo
      FROM grupo g
      LEFT JOIN ciclo ci ON ci.id_ciclo = g.ciclo_id
     WHERE g.id_grupo <> 0
     ORDER BY g.nivel, g.grado;
END $$

-- Los estatus que puede tener una cuenta (catálogo status, ámbito 'alumno').
DROP PROCEDURE IF EXISTS sp_cuenta_estatus $$
CREATE PROCEDURE sp_cuenta_estatus()
SQL SECURITY INVOKER
BEGIN
    SELECT clave AS Clave, nombre AS Nombre FROM status WHERE ambito = 'alumno' ORDER BY orden;
END $$

-- Alta de persona + cuenta, todo o nada. Regresa el id de la cuenta.
-- 'invalido' si el rol no existe; 'dup' si la matrícula ya existe. Un estatus desconocido se guarda como 'activo'.
DROP PROCEDURE IF EXISTS sp_cuenta_crear $$
CREATE PROCEDURE sp_cuenta_crear(
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
    DECLARE vIdCuenta INT;
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
    SET vIdCuenta = LAST_INSERT_ID();

    -- El perfil de su tipo (Perfiles.TipoCuenta), si hay uno. Un perfil administrador nunca se da solo.
    INSERT IGNORE INTO CuentaPerfiles (IdCuenta, IdPerfil)
    SELECT vIdCuenta, IdPerfil FROM Perfiles WHERE TipoCuenta = pIdPermiso AND Activo = 1 AND EsAdmin = 0;

    COMMIT;

    SELECT vIdCuenta AS Id;
END $$

-- Cambia la persona y su cuenta, todo o nada.
-- 'notfound' si la cuenta no existe; 'invalido' si el rol no existe; 'dup' si otra cuenta ya tiene esa matrícula.
DROP PROCEDURE IF EXISTS sp_cuenta_editar $$
CREATE PROCEDURE sp_cuenta_editar(
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
DROP PROCEDURE IF EXISTS sp_cuenta_eliminar $$
CREATE PROCEDURE sp_cuenta_eliminar(IN pIdCuenta INT, IN pIdUsuarioSesion INT)
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

-- ──────────────────────────── sp_dashboard.sql ────────────────────────────
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

-- ──────────────────────────── sp_esquema_pago.sql ────────────────────────────
-- ============================================================
-- Colegio del Bosque · Procedures de los esquemas de pago (webService/controllers/EsquemaPagoController.php)
-- Idempotente (DROP + CREATE). Antes: sp_sistema.sql
--
-- Un esquema dice cuánto y cuántos meses se cobra en un ciclo (tabla esquema_pago). Aplicarlo genera los pagos de
-- cada alumno: eso lo hace sp_colegiatura_generar (sp_colegiatura.sql), uno por alumno y por mes.
-- Las columnas salen con el nombre de la propiedad del model EsquemaPago.
-- ============================================================

DELIMITER $$

DROP PROCEDURE IF EXISTS sp_esquema_listar $$
CREATE PROCEDURE sp_esquema_listar()
SQL SECURITY INVOKER
BEGIN
    SELECT e.id_esquema AS IdEsquema, e.ciclo_id AS IdCiclo, c.nombre AS CicloNombre, e.nombre AS Nombre,
           e.monto_inscripcion AS MontoInscripcion, e.monto_colegiatura AS MontoColegiatura, e.primer_mes AS PrimerMes,
           e.num_meses AS NumMeses, e.dia_vencimiento AS DiaVencimiento, e.recargo_monto AS RecargoMonto
      FROM esquema_pago e
      JOIN ciclo c ON c.id_ciclo = e.ciclo_id
     ORDER BY c.fecha_inicio DESC, e.nombre;
END $$

-- Un esquema (ninguna fila si no existe).
DROP PROCEDURE IF EXISTS sp_esquema_obtener $$
CREATE PROCEDURE sp_esquema_obtener(IN pIdEsquema INT)
SQL SECURITY INVOKER
BEGIN
    SELECT e.id_esquema AS IdEsquema, e.ciclo_id AS IdCiclo, e.nombre AS Nombre,
           e.monto_inscripcion AS MontoInscripcion, e.monto_colegiatura AS MontoColegiatura, e.primer_mes AS PrimerMes,
           e.num_meses AS NumMeses, e.dia_vencimiento AS DiaVencimiento, e.recargo_monto AS RecargoMonto
      FROM esquema_pago e
     WHERE e.id_esquema = pIdEsquema;
END $$

DROP PROCEDURE IF EXISTS sp_esquema_crear $$
CREATE PROCEDURE sp_esquema_crear(IN pIdCiclo INT, IN pNombre VARCHAR(80), IN pMontoInscripcion DECIMAL(10,2),
                                  IN pMontoColegiatura DECIMAL(10,2), IN pPrimerMes DATE, IN pNumMeses INT,
                                  IN pDiaVencimiento INT, IN pRecargoMonto DECIMAL(10,2))
SQL SECURITY INVOKER
BEGIN
    INSERT INTO esquema_pago (ciclo_id, nombre, monto_inscripcion, monto_colegiatura, primer_mes, num_meses, dia_vencimiento, recargo_monto)
    VALUES (pIdCiclo, pNombre, pMontoInscripcion, pMontoColegiatura, pPrimerMes, pNumMeses, pDiaVencimiento, pRecargoMonto);

    SELECT LAST_INSERT_ID() AS Id;
END $$

-- 'notfound' si no existe. No toca los pagos ya generados: hay que volver a aplicarlo.
DROP PROCEDURE IF EXISTS sp_esquema_editar $$
CREATE PROCEDURE sp_esquema_editar(IN pIdEsquema INT, IN pIdCiclo INT, IN pNombre VARCHAR(80),
                                   IN pMontoInscripcion DECIMAL(10,2), IN pMontoColegiatura DECIMAL(10,2),
                                   IN pPrimerMes DATE, IN pNumMeses INT, IN pDiaVencimiento INT,
                                   IN pRecargoMonto DECIMAL(10,2))
SQL SECURITY INVOKER
BEGIN
    IF NOT EXISTS (SELECT 1 FROM esquema_pago WHERE id_esquema = pIdEsquema) THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'notfound:No se encontró el esquema.';
    END IF;

    UPDATE esquema_pago
       SET ciclo_id = pIdCiclo, nombre = pNombre, monto_inscripcion = pMontoInscripcion,
           monto_colegiatura = pMontoColegiatura, primer_mes = pPrimerMes, num_meses = pNumMeses,
           dia_vencimiento = pDiaVencimiento, recargo_monto = pRecargoMonto
     WHERE id_esquema = pIdEsquema;

    SELECT 1 AS Afectadas;
END $$

-- Regresa las filas afectadas (0 = no existía). Los pagos ya generados se conservan.
DROP PROCEDURE IF EXISTS sp_esquema_eliminar $$
CREATE PROCEDURE sp_esquema_eliminar(IN pIdEsquema INT)
SQL SECURITY INVOKER
BEGIN
    DELETE FROM esquema_pago WHERE id_esquema = pIdEsquema;

    SELECT ROW_COUNT() AS Afectadas;
END $$

-- Los grupos que tienen ciclo (para elegir a quién aplicar).
DROP PROCEDURE IF EXISTS sp_esquema_grupos $$
CREATE PROCEDURE sp_esquema_grupos()
SQL SECURITY INVOKER
BEGIN
    SELECT g.id_grupo AS IdGrupo, g.grado AS Grado, g.ciclo_id AS IdCiclo
      FROM grupo g
     WHERE g.id_grupo <> 0 AND g.ciclo_id IS NOT NULL
     ORDER BY g.nivel, g.grado;
END $$

-- Los alumnos que están en un grupo con ciclo (para elegir a quién aplicar). La matrícula va encriptada.
DROP PROCEDURE IF EXISTS sp_esquema_alumnos $$
CREATE PROCEDURE sp_esquema_alumnos()
SQL SECURITY INVOKER
BEGIN
    SELECT c.id_cuenta AS IdCuenta, TRIM(CONCAT_WS(' ', u.nombre, u.paterno, u.materno)) AS Nombre,
           c.matricula AS Matricula, c.grupo_id AS IdGrupo, g.grado AS Grado, g.ciclo_id AS IdCiclo
      FROM cuenta c
      JOIN usuario u ON u.id_usuario = c.usuario_id
      JOIN grupo g   ON g.id_grupo = c.grupo_id
     WHERE c.permiso_id = 3 AND g.ciclo_id IS NOT NULL
     ORDER BY u.paterno, u.nombre;
END $$

-- A quiénes se aplica un esquema del ciclo pIdCiclo: a todos sus alumnos, a los de un grupo o a uno solo.
-- 'invalido' si el grupo no es de ese ciclo; 'notfound' si el alumno no está en un grupo de ese ciclo.
DROP PROCEDURE IF EXISTS sp_esquema_destinatarios $$
CREATE PROCEDURE sp_esquema_destinatarios(IN pIdCiclo INT, IN pAplicarA VARCHAR(10), IN pIdGrupo INT, IN pIdCuenta INT)
SQL SECURITY INVOKER
BEGIN
    IF pAplicarA = 'grupo' COLLATE utf8mb4_unicode_ci THEN
        IF NOT EXISTS (SELECT 1 FROM grupo WHERE id_grupo = pIdGrupo AND ciclo_id = pIdCiclo) THEN
            SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'invalido:Selecciona un grupo del ciclo del esquema.';
        END IF;
        SELECT id_cuenta AS IdCuenta FROM cuenta WHERE grupo_id = pIdGrupo AND permiso_id = 3;
    ELSEIF pAplicarA = 'alumno' COLLATE utf8mb4_unicode_ci THEN
        IF NOT EXISTS (SELECT 1 FROM cuenta c JOIN grupo g ON g.id_grupo = c.grupo_id
                        WHERE c.id_cuenta = pIdCuenta AND c.permiso_id = 3 AND g.ciclo_id = pIdCiclo) THEN
            SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'notfound:El alumno no está en un grupo del ciclo del esquema.';
        END IF;
        SELECT pIdCuenta AS IdCuenta;
    ELSE
        SELECT c.id_cuenta AS IdCuenta
          FROM cuenta c JOIN grupo g ON g.id_grupo = c.grupo_id
         WHERE c.permiso_id = 3 AND g.ciclo_id = pIdCiclo;
    END IF;
END $$

DELIMITER ;

-- ──────────────────────────── sp_grupo.sql ────────────────────────────
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

-- ──────────────────────────── sp_materia.sql ────────────────────────────
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

-- ──────────────────────────── sp_padre.sql ────────────────────────────
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

-- ──────────────────────────── sp_persona.sql ────────────────────────────
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
    DECLARE vIdCuenta INT;
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
    SET vIdCuenta = LAST_INSERT_ID();

    -- El perfil de su tipo (Perfiles.TipoCuenta), si hay uno. Un perfil administrador nunca se da solo.
    INSERT IGNORE INTO CuentaPerfiles (IdCuenta, IdPerfil)
    SELECT vIdCuenta, IdPerfil FROM Perfiles WHERE TipoCuenta = pIdPermiso AND Activo = 1 AND EsAdmin = 0;

    COMMIT;

    SELECT vIdCuenta AS Id;
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

-- ──────────────────────────── sp_recibo.sql ────────────────────────────
-- ============================================================
-- Colegio del Bosque · Procedures de los recibos (webService/controllers/ReciboController.php)
-- Idempotente (DROP + CREATE). Antes: sp_sistema.sql
--
-- Un recibo es un ingreso o un egreso de la escuela (tabla recibo). Su destinatario es la escuela, un alumno o un
-- docente. Los abonos a colegiaturas también son recibos: los genera sp_colegiatura_pagar (sp_colegiatura.sql).
-- Las columnas salen con el nombre de la propiedad del model Recibo.
-- ============================================================

DELIMITER $$

-- Las cuentas de un rol (3 = alumno, 2 = docente) para elegir el destinatario.
DROP PROCEDURE IF EXISTS sp_recibo_personas $$
CREATE PROCEDURE sp_recibo_personas(IN pIdPermiso INT)
SQL SECURITY INVOKER
BEGIN
    SELECT c.id_cuenta AS IdCuenta, TRIM(CONCAT_WS(' ', u.nombre, u.paterno, u.materno)) AS Nombre
      FROM cuenta c
      JOIN usuario u ON u.id_usuario = c.usuario_id
     WHERE c.permiso_id = pIdPermiso
     ORDER BY u.paterno, u.nombre;
END $$

DROP PROCEDURE IF EXISTS sp_recibo_listar $$
CREATE PROCEDURE sp_recibo_listar()
SQL SECURITY INVOKER
BEGIN
    SELECT r.id_recibo AS IdRecibo, r.tipo_recibo_id AS IdTipoRecibo, r.naturaleza AS Naturaleza,
           r.destinatario_tipo AS DestinatarioTipo, r.cuenta_id AS IdCuenta, r.monto AS Monto, r.tipo_pago AS TipoPago,
           r.comentario AS Comentario, r.fecha AS Fecha, t.nombre AS TipoNombre,
           IF(r.destinatario_tipo = 'escuela', 'Escuela', TRIM(CONCAT_WS(' ', u.nombre, u.paterno, u.materno))) AS Destinatario
      FROM recibo r
      LEFT JOIN tipo_recibo t ON t.id_tipo = r.tipo_recibo_id
      LEFT JOIN cuenta c      ON c.id_cuenta = r.cuenta_id
      LEFT JOIN usuario u     ON u.id_usuario = c.usuario_id
     ORDER BY r.fecha DESC, r.id_recibo DESC;
END $$

-- Un recibo (ninguna fila si no existe), con el usuario y el rol de la cuenta destinataria: así el controller
-- sabe si un alumno puede verlo (solo los suyos).
DROP PROCEDURE IF EXISTS sp_recibo_obtener $$
CREATE PROCEDURE sp_recibo_obtener(IN pIdRecibo INT)
SQL SECURITY INVOKER
BEGIN
    SELECT r.id_recibo AS IdRecibo, r.tipo_recibo_id AS IdTipoRecibo, r.naturaleza AS Naturaleza,
           r.destinatario_tipo AS DestinatarioTipo, r.cuenta_id AS IdCuenta, r.monto AS Monto, r.tipo_pago AS TipoPago,
           r.comentario AS Comentario, r.fecha AS Fecha, t.nombre AS TipoNombre,
           IF(r.destinatario_tipo = 'escuela', 'Escuela', TRIM(CONCAT_WS(' ', u.nombre, u.paterno, u.materno))) AS Destinatario,
           c.usuario_id AS IdUsuarioCuenta, c.permiso_id AS IdPermisoCuenta
      FROM recibo r
      LEFT JOIN tipo_recibo t ON t.id_tipo = r.tipo_recibo_id
      LEFT JOIN cuenta c      ON c.id_cuenta = r.cuenta_id
      LEFT JOIN usuario u     ON u.id_usuario = c.usuario_id
     WHERE r.id_recibo = pIdRecibo;
END $$

-- El destinatario (alumno o docente) tiene que ser una cuenta de ese rol. 'invalido' si no.
DROP PROCEDURE IF EXISTS sp_recibo_validar_destinatario $$
CREATE PROCEDURE sp_recibo_validar_destinatario(IN pDestinatarioTipo VARCHAR(10), IN pIdCuenta INT)
SQL SECURITY INVOKER
BEGIN
    DECLARE vMensaje VARCHAR(120);
    IF pDestinatarioTipo <> 'escuela' COLLATE utf8mb4_unicode_ci
       AND NOT EXISTS (SELECT 1 FROM cuenta WHERE id_cuenta = pIdCuenta
                          AND permiso_id = IF(pDestinatarioTipo = 'alumno' COLLATE utf8mb4_unicode_ci, 3, 2)) THEN
        SET vMensaje = CONCAT('invalido:El destinatario no es un ', pDestinatarioTipo, ' válido.');
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = vMensaje;
    END IF;
END $$

DROP PROCEDURE IF EXISTS sp_recibo_crear $$
CREATE PROCEDURE sp_recibo_crear(IN pIdTipoRecibo INT, IN pNaturaleza VARCHAR(10), IN pDestinatarioTipo VARCHAR(10),
                                 IN pIdCuenta INT, IN pMonto DECIMAL(10,2), IN pTipoPago VARCHAR(30),
                                 IN pComentario TEXT, IN pFecha DATETIME)
SQL SECURITY INVOKER
BEGIN
    CALL sp_recibo_validar_destinatario(pDestinatarioTipo, pIdCuenta);

    INSERT INTO recibo (tipo_recibo_id, naturaleza, destinatario_tipo, cuenta_id, monto, tipo_pago, comentario, fecha)
    VALUES (pIdTipoRecibo, pNaturaleza, pDestinatarioTipo, pIdCuenta, pMonto, pTipoPago, pComentario, pFecha);

    SELECT LAST_INSERT_ID() AS Id;
END $$

-- 'notfound' si no existe; 'invalido' si el destinatario no es de ese rol.
DROP PROCEDURE IF EXISTS sp_recibo_editar $$
CREATE PROCEDURE sp_recibo_editar(IN pIdRecibo INT, IN pIdTipoRecibo INT, IN pNaturaleza VARCHAR(10),
                                  IN pDestinatarioTipo VARCHAR(10), IN pIdCuenta INT, IN pMonto DECIMAL(10,2),
                                  IN pTipoPago VARCHAR(30), IN pComentario TEXT, IN pFecha DATETIME)
SQL SECURITY INVOKER
BEGIN
    IF NOT EXISTS (SELECT 1 FROM recibo WHERE id_recibo = pIdRecibo) THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'notfound:No se encontró el recibo.';
    END IF;
    CALL sp_recibo_validar_destinatario(pDestinatarioTipo, pIdCuenta);

    UPDATE recibo
       SET tipo_recibo_id = pIdTipoRecibo, naturaleza = pNaturaleza, destinatario_tipo = pDestinatarioTipo,
           cuenta_id = pIdCuenta, monto = pMonto, tipo_pago = pTipoPago, comentario = pComentario, fecha = pFecha
     WHERE id_recibo = pIdRecibo;

    SELECT 1 AS Afectadas;
END $$

-- Regresa las filas afectadas (0 = no existía).
DROP PROCEDURE IF EXISTS sp_recibo_eliminar $$
CREATE PROCEDURE sp_recibo_eliminar(IN pIdRecibo INT)
SQL SECURITY INVOKER
BEGIN
    DELETE FROM recibo WHERE id_recibo = pIdRecibo;

    SELECT ROW_COUNT() AS Afectadas;
END $$

DELIMITER ;

-- ──────────────────────────── sp_reporte.sql ────────────────────────────
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

-- ──────────────────────────── sp_reporte_columna.sql ────────────────────────────
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

-- ──────────────────────────── sp_ruta.sql ────────────────────────────
-- ============================================================================================
-- Rutas del sitio (tabla Rutas, core/Rutas.php): las lee index.php y las guarda en caché 5 minutos.
-- Mismo par de procedures que en NeuralStack Code. Requiere la tabla Rutas (catalogo/01_tablas_catalogo.sql).
-- ============================================================================================
SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci;

DELIMITER $$

-- Las rutas activas (el sitio las guarda en caché 5 minutos: un cambio aquí se ve a lo más 5 minutos después).
DROP PROCEDURE IF EXISTS sp_ruta_listar $$
CREATE PROCEDURE sp_ruta_listar()
SQL SECURITY INVOKER
BEGIN
    SELECT Ruta, Tipo, Destino, CodigoHttp, ConservarQuery
      FROM Rutas
     WHERE Activo = 1
     ORDER BY Ruta;
END $$

-- Alta o cambio de una ruta (por Ruta). 'invalido' si el tipo, el destino o el código no son válidos. Regresa Id.
DROP PROCEDURE IF EXISTS sp_ruta_registrar $$
CREATE PROCEDURE sp_ruta_registrar(
    IN pRuta              VARCHAR(150),
    IN pTipo              VARCHAR(12),
    IN pDestino           VARCHAR(255),
    IN pCodigoHttp        SMALLINT,
    IN pConservarQuery    TINYINT,
    IN pDescripcion       VARCHAR(150),
    IN pActivo            TINYINT,
    IN pIdUsuarioRegistra INT,
    IN pIdUsuarioCambia   INT
)
SQL SECURITY INVOKER
BEGIN
    SET pRuta = TRIM(BOTH '/' FROM TRIM(COALESCE(pRuta, '')));
    IF pTipo COLLATE utf8mb4_unicode_ci NOT IN ('vista', 'redireccion') THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'invalido:El tipo debe ser vista o redireccion.';
    END IF;
    IF pTipo = 'vista' AND (pDestino NOT LIKE 'webService/views/%.php' OR pDestino LIKE '%..%') THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'invalido:Una vista debe ser un .php dentro de webService/views/.';
    END IF;
    IF pTipo = 'redireccion' AND (pDestino NOT LIKE '/%' OR pDestino LIKE '//%') THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'invalido:Una redirección va a una ruta interna que empieza con /.';
    END IF;
    IF COALESCE(pCodigoHttp, 301) NOT IN (301, 302) THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'invalido:El código debe ser 301 o 302.';
    END IF;

    INSERT INTO Rutas (Ruta, Tipo, Destino, CodigoHttp, ConservarQuery, Descripcion, Activo, IdUsuarioRegistra)
    VALUES (pRuta, pTipo, pDestino, COALESCE(pCodigoHttp, 301), COALESCE(pConservarQuery, 1), COALESCE(pDescripcion, ''),
            COALESCE(pActivo, 1), COALESCE(pIdUsuarioRegistra, 0))
    ON DUPLICATE KEY UPDATE
        Tipo = VALUES(Tipo), Destino = VALUES(Destino), CodigoHttp = VALUES(CodigoHttp),
        ConservarQuery = VALUES(ConservarQuery), Descripcion = VALUES(Descripcion), Activo = VALUES(Activo),
        NumeroCambios = NumeroCambios + 1, IdUsuarioCambia = pIdUsuarioCambia;
    SELECT IdRuta AS Id FROM Rutas WHERE Ruta = pRuta COLLATE utf8mb4_unicode_ci;
END $$

DELIMITER ;
