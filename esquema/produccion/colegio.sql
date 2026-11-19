-- Estructura de la base de producción del colegio (export del 19-11-2026, MariaDB 11.8.9).
-- SOLO estructura y procedures, más los catálogos sin datos de personas: materia, permisos, reporte_columna, status, tipo_descuento, tipo_recibo.
-- Se quitaron TODOS los datos de alumnos, cuentas, pagos y reportes. Nunca subir aquí un export con datos.
-- phpMyAdmin SQL Dump
-- version 5.2.2
-- https://www.phpmyadmin.net/
--
-- Servidor: 127.0.0.1:3306
-- Tiempo de generación: 19-11-2026 a las 16:45:00
-- Versión del servidor: 11.8.9-MariaDB-log
-- Versión de PHP: 7.2.34

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Base de datos: `colegio`
--

DELIMITER $$
--
-- Procedimientos
--
CREATE PROCEDURE `sp_auth_autenticar` (IN `pMatricula` VARCHAR(150))  SQL SECURITY INVOKER BEGIN
    SELECT c.id_cuenta AS IdCuenta, c.usuario_id AS IdUsuario, c.permiso_id AS IdPermiso,
           u.nombre AS Nombre, u.paterno AS Paterno
      FROM cuenta c
      JOIN usuario u ON u.id_usuario = c.usuario_id
     WHERE c.matricula = pMatricula COLLATE utf8mb4_unicode_ci
     LIMIT 1;
END$$

CREATE PROCEDURE `sp_calificacion_grupos` (IN `pIdUsuario` INT, IN `pIdCiclo` INT)  SQL SECURITY INVOKER BEGIN
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
END$$

CREATE PROCEDURE `sp_calificacion_guardar` (IN `pIdCuenta` INT, IN `pIdMateria` INT, IN `pIdCiclo` INT, IN `pIdGrupo` INT, IN `pSoloIngles` TINYINT, IN `pP1` DOUBLE, IN `pP2` DOUBLE, IN `pP3` DOUBLE, IN `pCalifFinal` DOUBLE, IN `pReporte` TEXT)  SQL SECURITY INVOKER BEGIN
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
END$$

CREATE PROCEDURE `sp_calificacion_listar` (IN `pIdMateria` INT, IN `pIdCiclo` INT, IN `pIdGrupo` INT, IN `pSoloIngles` TINYINT)  SQL SECURITY INVOKER BEGIN
    CALL sp_calificacion_validar_materia(pIdMateria, pIdGrupo, pSoloIngles);

    SELECT c.id_cuenta AS IdCuenta, TRIM(CONCAT_WS(' ', u.nombre, u.paterno, u.materno)) AS NombreCompleto,
           cal.p1 AS P1, cal.p2 AS P2, cal.p3 AS P3, cal.calif_final AS CalifFinal, cal.reporte AS Reporte
      FROM cuenta c
      JOIN usuario u ON u.id_usuario = c.usuario_id
      LEFT JOIN calificacion cal ON cal.cuenta_id = c.id_cuenta AND cal.materia_id = pIdMateria AND cal.ciclo_id = pIdCiclo
     WHERE c.grupo_id = pIdGrupo AND c.permiso_id = 3
     ORDER BY u.paterno, u.nombre;
END$$

CREATE PROCEDURE `sp_calificacion_materias` (IN `pIdGrupo` INT, IN `pSoloIngles` TINYINT)  SQL SECURITY INVOKER BEGIN
    SELECT m.id_materia AS IdMateria, m.nombre AS Nombre
      FROM grupo_materia gm
      JOIN materia m ON m.id_materia = gm.id_materia
     WHERE gm.id_grupo = pIdGrupo AND (COALESCE(pSoloIngles, 0) = 0 OR m.nombre LIKE 'ingl%')
     ORDER BY m.nombre;
END$$

CREATE PROCEDURE `sp_calificacion_validar_materia` (IN `pIdMateria` INT, IN `pIdGrupo` INT, IN `pSoloIngles` TINYINT)  SQL SECURITY INVOKER BEGIN
    IF NOT EXISTS (SELECT 1 FROM grupo_materia gm JOIN materia m ON m.id_materia = gm.id_materia
                    WHERE gm.id_materia = pIdMateria AND gm.id_grupo = pIdGrupo
                      AND (COALESCE(pSoloIngles, 0) = 0 OR m.nombre LIKE 'ingl%')) THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'invalido:Materia no válida para tu grupo.';
    END IF;
END$$

CREATE PROCEDURE `sp_ciclo_activar` (IN `pIdCiclo` INT)  SQL SECURITY INVOKER BEGIN
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
END$$

CREATE PROCEDURE `sp_ciclo_activo` ()  SQL SECURITY INVOKER BEGIN
    SELECT c.id_ciclo AS IdCiclo, c.nombre AS Nombre, c.fecha_inicio AS FechaInicio, c.fecha_fin AS FechaFin, 1 AS Activo
      FROM ciclo c
     WHERE c.status_id = (SELECT id_status FROM status WHERE ambito = 'ciclo' AND clave = 'activo')
     LIMIT 1;
END$$

CREATE PROCEDURE `sp_ciclo_crear` (IN `pNombre` VARCHAR(30), IN `pFechaInicio` DATE, IN `pFechaFin` DATE)  SQL SECURITY INVOKER BEGIN
    INSERT INTO ciclo (nombre, fecha_inicio, fecha_fin, status_id)
    VALUES (pNombre, pFechaInicio, pFechaFin,
            (SELECT id_status FROM status WHERE ambito = 'ciclo' AND clave = 'cerrado'));

    SELECT LAST_INSERT_ID() AS Id;
END$$

CREATE PROCEDURE `sp_ciclo_editar` (IN `pIdCiclo` INT, IN `pNombre` VARCHAR(30), IN `pFechaInicio` DATE, IN `pFechaFin` DATE)  SQL SECURITY INVOKER BEGIN
    IF NOT EXISTS (SELECT 1 FROM ciclo WHERE id_ciclo = pIdCiclo) THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'notfound:No se encontró el ciclo.';
    END IF;

    UPDATE ciclo SET nombre = pNombre, fecha_inicio = pFechaInicio, fecha_fin = pFechaFin WHERE id_ciclo = pIdCiclo;

    SELECT 1 AS Afectadas;
END$$

CREATE PROCEDURE `sp_ciclo_eliminar` (IN `pIdCiclo` INT)  SQL SECURITY INVOKER BEGIN
    DELETE FROM ciclo WHERE id_ciclo = pIdCiclo;

    SELECT ROW_COUNT() AS Afectadas;
END$$

CREATE PROCEDURE `sp_ciclo_listar` ()  SQL SECURITY INVOKER BEGIN
    SELECT c.id_ciclo AS IdCiclo, c.nombre AS Nombre, c.fecha_inicio AS FechaInicio, c.fecha_fin AS FechaFin,
           COALESCE(s.clave = 'activo', 0) AS Activo, s.clave AS Estatus, s.nombre AS EstatusNombre
      FROM ciclo c
      LEFT JOIN status s ON s.id_status = c.status_id
     ORDER BY c.fecha_inicio DESC;
END$$

CREATE PROCEDURE `sp_ciclo_obtener` (IN `pIdCiclo` INT)  SQL SECURITY INVOKER BEGIN
    SELECT c.id_ciclo AS IdCiclo, c.nombre AS Nombre, c.fecha_inicio AS FechaInicio, c.fecha_fin AS FechaFin,
           COALESCE(s.clave = 'activo', 0) AS Activo
      FROM ciclo c
      LEFT JOIN status s ON s.id_status = c.status_id
     WHERE c.id_ciclo = pIdCiclo;
END$$

CREATE PROCEDURE `sp_colegiatura_descuento` (IN `pIdPago` INT, IN `pDescuento` DECIMAL(10,2), IN `pIdTipoDescuento` INT, IN `pConceptoDescuento` VARCHAR(60))  SQL SECURITY INVOKER BEGIN
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
END$$

CREATE PROCEDURE `sp_colegiatura_descuentos` ()  SQL SECURITY INVOKER BEGIN
    SELECT td.id_descuento AS IdDescuento, td.nombre AS Nombre, td.porcentaje AS Porcentaje, td.aplica_a AS AplicaA, 1 AS Activo
      FROM tipo_descuento td
     WHERE td.status_id = (SELECT id_status FROM status WHERE ambito = 'descuento' AND clave = 'activo')
     ORDER BY td.aplica_a, td.nombre;
END$$

CREATE PROCEDURE `sp_colegiatura_descuento_obtener` (IN `pIdDescuento` INT)  SQL SECURITY INVOKER BEGIN
    SELECT td.id_descuento AS IdDescuento, td.nombre AS Nombre, td.porcentaje AS Porcentaje, td.aplica_a AS AplicaA, 1 AS Activo
      FROM tipo_descuento td
     WHERE td.id_descuento = pIdDescuento
       AND td.status_id = (SELECT id_status FROM status WHERE ambito = 'descuento' AND clave = 'activo');
END$$

CREATE PROCEDURE `sp_colegiatura_eliminar` (IN `pIdPago` INT)  SQL SECURITY INVOKER BEGIN
    DELETE FROM colegiatura WHERE id_pago = pIdPago;

    SELECT ROW_COUNT() AS Afectadas;
END$$

CREATE PROCEDURE `sp_colegiatura_estatus` ()  SQL SECURITY INVOKER BEGIN
    SELECT s.clave AS Clave, s.nombre AS Nombre, s.color AS Color
      FROM status s
     WHERE s.ambito = 'pago'
     ORDER BY s.orden;
END$$

CREATE PROCEDURE `sp_colegiatura_generar` (IN `pIdCuenta` INT, IN `pIdCiclo` INT, IN `pTipo` VARCHAR(20), IN `pMes` VARCHAR(20), IN `pAnio` INT, IN `pFechaVencimiento` DATE, IN `pMonto` DECIMAL(10,2), IN `pRecargoMonto` DECIMAL(10,2))  SQL SECURITY INVOKER BEGIN
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
END$$

CREATE PROCEDURE `sp_colegiatura_grupos` (IN `pIdCiclo` INT)  SQL SECURITY INVOKER BEGIN
    SELECT g.id_grupo AS IdGrupo, g.grado AS Grado, g.ciclo_id AS IdCiclo
      FROM grupo g
     WHERE g.id_grupo <> 0 AND (COALESCE(pIdCiclo, 0) = 0 OR g.ciclo_id = pIdCiclo)
     ORDER BY g.nivel, g.grado;
END$$

CREATE PROCEDURE `sp_colegiatura_listar` (IN `pIdCuenta` INT)  SQL SECURITY INVOKER BEGIN
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
END$$

CREATE PROCEDURE `sp_colegiatura_obtener` (IN `pIdPago` INT)  SQL SECURITY INVOKER BEGIN
    SELECT col.id_pago AS IdPago, col.cuenta_id AS IdCuenta, col.ciclo_id AS IdCiclo,
           col.tipo AS Tipo, col.mes AS Mes, col.fecha_vencimiento AS FechaVencimiento,
           col.monto AS Monto, col.descuento AS Descuento, col.recargo_monto AS RecargoMonto, col.recargo AS Recargo,
           col.recibo_id AS IdRecibo, COALESCE(st.clave, 'pendiente') AS Estatus,
           (SELECT COALESCE(SUM(rr.monto), 0) FROM recibo rr WHERE rr.colegiatura_id = col.id_pago) AS Abonado
      FROM colegiatura col
      LEFT JOIN status st ON st.id_status = col.status_id
     WHERE col.id_pago = pIdPago;
END$$

CREATE PROCEDURE `sp_colegiatura_pagar` (IN `pIdPago` INT, IN `pMonto` DECIMAL(10,2), IN `pTipoPago` VARCHAR(30), IN `pComentario` TEXT, IN `pFecha` DATETIME, IN `pCompleta` TINYINT, IN `pRecargo` DECIMAL(10,2))  SQL SECURITY INVOKER BEGIN
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
END$$

CREATE PROCEDURE `sp_colegiatura_recargo` (IN `pIdPago` INT, IN `pRecargoMonto` DECIMAL(10,2))  SQL SECURITY INVOKER BEGIN
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
END$$

CREATE PROCEDURE `sp_dashboard_alumnos_por_grupo` ()  SQL SECURITY INVOKER BEGIN
    SELECT g.grado AS Grado, COUNT(c.id_cuenta) AS Total
      FROM grupo g
      LEFT JOIN cuenta c ON c.grupo_id = g.id_grupo AND c.permiso_id = 3
     GROUP BY g.id_grupo, g.grado
     ORDER BY g.grado;
END$$

CREATE PROCEDURE `sp_dashboard_resumen` ()  SQL SECURITY INVOKER BEGIN
    DECLARE vPagado INT DEFAULT (SELECT id_status FROM status WHERE ambito = 'pago' AND clave = 'pagado');

    SELECT
        (SELECT COUNT(*) FROM cuenta WHERE permiso_id = 3) AS Alumnos,
        (SELECT COUNT(*) FROM cuenta WHERE permiso_id = 2) AS Maestros,
        (SELECT COUNT(*) FROM grupo) AS Grupos,
        (SELECT COALESCE(SUM(CASE WHEN naturaleza = 'ingreso' THEN monto ELSE -monto END), 0) FROM recibo) AS Saldo,
        (SELECT COUNT(*) FROM colegiatura WHERE status_id <> vPagado) AS PagosPendientes,
        (SELECT COUNT(*) FROM colegiatura WHERE status_id =  vPagado) AS PagosPagados;
END$$

CREATE PROCEDURE `sp_esquema_alumnos` ()  SQL SECURITY INVOKER BEGIN
    SELECT c.id_cuenta AS IdCuenta, TRIM(CONCAT_WS(' ', u.nombre, u.paterno, u.materno)) AS Nombre,
           c.matricula AS Matricula, c.grupo_id AS IdGrupo, g.grado AS Grado, g.ciclo_id AS IdCiclo
      FROM cuenta c
      JOIN usuario u ON u.id_usuario = c.usuario_id
      JOIN grupo g   ON g.id_grupo = c.grupo_id
     WHERE c.permiso_id = 3 AND g.ciclo_id IS NOT NULL
     ORDER BY u.paterno, u.nombre;
END$$

CREATE PROCEDURE `sp_esquema_crear` (IN `pIdCiclo` INT, IN `pNombre` VARCHAR(80), IN `pMontoInscripcion` DECIMAL(10,2), IN `pMontoColegiatura` DECIMAL(10,2), IN `pPrimerMes` DATE, IN `pNumMeses` INT, IN `pDiaVencimiento` INT, IN `pRecargoMonto` DECIMAL(10,2))  SQL SECURITY INVOKER BEGIN
    INSERT INTO esquema_pago (ciclo_id, nombre, monto_inscripcion, monto_colegiatura, primer_mes, num_meses, dia_vencimiento, recargo_monto)
    VALUES (pIdCiclo, pNombre, pMontoInscripcion, pMontoColegiatura, pPrimerMes, pNumMeses, pDiaVencimiento, pRecargoMonto);

    SELECT LAST_INSERT_ID() AS Id;
END$$

CREATE PROCEDURE `sp_esquema_destinatarios` (IN `pIdCiclo` INT, IN `pAplicarA` VARCHAR(10), IN `pIdGrupo` INT, IN `pIdCuenta` INT)  SQL SECURITY INVOKER BEGIN
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
END$$

CREATE PROCEDURE `sp_esquema_editar` (IN `pIdEsquema` INT, IN `pIdCiclo` INT, IN `pNombre` VARCHAR(80), IN `pMontoInscripcion` DECIMAL(10,2), IN `pMontoColegiatura` DECIMAL(10,2), IN `pPrimerMes` DATE, IN `pNumMeses` INT, IN `pDiaVencimiento` INT, IN `pRecargoMonto` DECIMAL(10,2))  SQL SECURITY INVOKER BEGIN
    IF NOT EXISTS (SELECT 1 FROM esquema_pago WHERE id_esquema = pIdEsquema) THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'notfound:No se encontró el esquema.';
    END IF;

    UPDATE esquema_pago
       SET ciclo_id = pIdCiclo, nombre = pNombre, monto_inscripcion = pMontoInscripcion,
           monto_colegiatura = pMontoColegiatura, primer_mes = pPrimerMes, num_meses = pNumMeses,
           dia_vencimiento = pDiaVencimiento, recargo_monto = pRecargoMonto
     WHERE id_esquema = pIdEsquema;

    SELECT 1 AS Afectadas;
END$$

CREATE PROCEDURE `sp_esquema_eliminar` (IN `pIdEsquema` INT)  SQL SECURITY INVOKER BEGIN
    DELETE FROM esquema_pago WHERE id_esquema = pIdEsquema;

    SELECT ROW_COUNT() AS Afectadas;
END$$

CREATE PROCEDURE `sp_esquema_grupos` ()  SQL SECURITY INVOKER BEGIN
    SELECT g.id_grupo AS IdGrupo, g.grado AS Grado, g.ciclo_id AS IdCiclo
      FROM grupo g
     WHERE g.id_grupo <> 0 AND g.ciclo_id IS NOT NULL
     ORDER BY g.nivel, g.grado;
END$$

CREATE PROCEDURE `sp_esquema_listar` ()  SQL SECURITY INVOKER BEGIN
    SELECT e.id_esquema AS IdEsquema, e.ciclo_id AS IdCiclo, c.nombre AS CicloNombre, e.nombre AS Nombre,
           e.monto_inscripcion AS MontoInscripcion, e.monto_colegiatura AS MontoColegiatura, e.primer_mes AS PrimerMes,
           e.num_meses AS NumMeses, e.dia_vencimiento AS DiaVencimiento, e.recargo_monto AS RecargoMonto
      FROM esquema_pago e
      JOIN ciclo c ON c.id_ciclo = e.ciclo_id
     ORDER BY c.fecha_inicio DESC, e.nombre;
END$$

CREATE PROCEDURE `sp_esquema_obtener` (IN `pIdEsquema` INT)  SQL SECURITY INVOKER BEGIN
    SELECT e.id_esquema AS IdEsquema, e.ciclo_id AS IdCiclo, e.nombre AS Nombre,
           e.monto_inscripcion AS MontoInscripcion, e.monto_colegiatura AS MontoColegiatura, e.primer_mes AS PrimerMes,
           e.num_meses AS NumMeses, e.dia_vencimiento AS DiaVencimiento, e.recargo_monto AS RecargoMonto
      FROM esquema_pago e
     WHERE e.id_esquema = pIdEsquema;
END$$

CREATE PROCEDURE `sp_grupo_crear` (IN `pGrado` VARCHAR(50), IN `pIdCiclo` INT, IN `pNivel` INT, IN `pNivelIngles` VARCHAR(10), IN `pMaterias` VARCHAR(2000), IN `pMateriasNuevas` TEXT)  SQL SECURITY INVOKER BEGIN
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
END$$

CREATE PROCEDURE `sp_grupo_editar` (IN `pIdGrupo` INT, IN `pGrado` VARCHAR(50), IN `pIdMaestra` INT, IN `pNivel` INT, IN `pIdCiclo` INT, IN `pCambiarNivelIngles` TINYINT, IN `pNivelIngles` VARCHAR(10), IN `pCambiarMaterias` TINYINT, IN `pMaterias` VARCHAR(2000), IN `pMateriasNuevas` TEXT)  SQL SECURITY INVOKER BEGIN
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
END$$

CREATE PROCEDURE `sp_grupo_eliminar` (IN `pIdGrupo` INT)  SQL SECURITY INVOKER BEGIN
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
END$$

CREATE PROCEDURE `sp_grupo_listar` (IN `pIdCiclo` INT)  SQL SECURITY INVOKER BEGIN
    SELECT g.id_grupo AS IdGrupo, g.grado AS Grado, NULLIF(g.maestra_id, 0) AS IdMaestra, g.ciclo_id AS IdCiclo, g.nivel AS Nivel,
           g.nivel_ingles AS NivelIngles, ci.nombre AS CicloNombre,
           (SELECT COUNT(*) FROM grupo_materia gm WHERE gm.id_grupo = g.id_grupo) AS NumMaterias,
           (SELECT COUNT(*) FROM cuenta cu WHERE cu.grupo_id = g.id_grupo AND cu.permiso_id = 3) AS NumAlumnos
      FROM grupo g
      LEFT JOIN ciclo ci ON ci.id_ciclo = g.ciclo_id
     WHERE g.id_grupo <> 0 AND (COALESCE(pIdCiclo, 0) = 0 OR g.ciclo_id = pIdCiclo)
     ORDER BY g.nivel, g.grado;
END$$

CREATE PROCEDURE `sp_grupo_maestros` ()  SQL SECURITY INVOKER BEGIN
    SELECT c.id_cuenta AS IdCuenta, TRIM(CONCAT_WS(' ', u.nombre, u.paterno)) AS Nombre
      FROM cuenta c
      JOIN usuario u ON u.id_usuario = c.usuario_id
     WHERE c.permiso_id = 2
     ORDER BY u.paterno, u.nombre;
END$$

CREATE PROCEDURE `sp_grupo_materias` (IN `pIdGrupo` INT)  SQL SECURITY INVOKER BEGIN
    SELECT m.id_materia AS IdMateria, m.nombre AS Nombre
      FROM grupo_materia gm
      JOIN materia m ON m.id_materia = gm.id_materia
     WHERE gm.id_grupo = pIdGrupo
     ORDER BY m.nombre;
END$$

CREATE PROCEDURE `sp_grupo_obtener` (IN `pIdGrupo` INT)  SQL SECURITY INVOKER BEGIN
    SELECT g.id_grupo AS IdGrupo, g.grado AS Grado, NULLIF(g.maestra_id, 0) AS IdMaestra, g.ciclo_id AS IdCiclo, g.nivel AS Nivel,
           g.nivel_ingles AS NivelIngles
      FROM grupo g
     WHERE g.id_grupo = pIdGrupo;
END$$

CREATE PROCEDURE `sp_grupo_sincronizar_materias` (IN `pIdGrupo` INT, IN `pMaterias` VARCHAR(2000), IN `pMateriasNuevas` TEXT)  SQL SECURITY INVOKER BEGIN
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
END$$

CREATE PROCEDURE `sp_materia_crear` (IN `pNombre` VARCHAR(25), IN `pGrupos` VARCHAR(2000))  SQL SECURITY INVOKER BEGIN
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
END$$

CREATE PROCEDURE `sp_materia_editar` (IN `pIdMateria` INT, IN `pNombre` VARCHAR(25), IN `pGrupos` VARCHAR(2000))  SQL SECURITY INVOKER BEGIN
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
END$$

CREATE PROCEDURE `sp_materia_eliminar` (IN `pIdMateria` INT)  SQL SECURITY INVOKER BEGIN
    IF EXISTS (SELECT 1 FROM calificacion WHERE materia_id = pIdMateria) THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'en_uso:No se puede eliminar: la materia tiene calificaciones registradas.';
    END IF;

    DELETE FROM materia WHERE id_materia = pIdMateria;

    SELECT ROW_COUNT() AS Afectadas;
END$$

CREATE PROCEDURE `sp_materia_listar` ()  SQL SECURITY INVOKER BEGIN
    SELECT m.id_materia AS IdMateria, m.nombre AS Nombre,
           (SELECT GROUP_CONCAT(g.grado ORDER BY g.grado SEPARATOR ', ')
              FROM grupo_materia gm JOIN grupo g ON g.id_grupo = gm.id_grupo
             WHERE gm.id_materia = m.id_materia) AS GruposTxt,
           (SELECT GROUP_CONCAT(gm.id_grupo ORDER BY gm.id_grupo)
              FROM grupo_materia gm WHERE gm.id_materia = m.id_materia) AS GruposCsv,
           (SELECT COUNT(*) FROM grupo_materia gm WHERE gm.id_materia = m.id_materia) AS NumGrupos
      FROM materia m
     ORDER BY m.nombre;
END$$

CREATE PROCEDURE `sp_padre_calificaciones` (IN `pIdCuenta` INT, IN `pIdGrupo` INT, IN `pIdCiclo` INT)  SQL SECURITY INVOKER BEGIN
    SELECT m.nombre AS Materia, cal.p1 AS P1, cal.p2 AS P2, cal.p3 AS P3, cal.calif_final AS CalifFinal, cal.reporte AS Reporte
      FROM grupo_materia gm
      JOIN materia m ON m.id_materia = gm.id_materia
      LEFT JOIN calificacion cal ON cal.materia_id = m.id_materia AND cal.cuenta_id = pIdCuenta AND cal.ciclo_id = pIdCiclo
     WHERE gm.id_grupo = pIdGrupo
     ORDER BY m.nombre;
END$$

CREATE PROCEDURE `sp_padre_cuenta` (IN `pIdUsuario` INT)  SQL SECURITY INVOKER BEGIN
    SELECT c.id_cuenta AS IdCuenta, c.grupo_id AS IdGrupo, g.grado AS Grado,
           c.correo_tutor AS CorreoTutor, c.correo_tutor2 AS CorreoTutor2
      FROM cuenta c
      LEFT JOIN grupo g ON g.id_grupo = c.grupo_id
     WHERE c.usuario_id = pIdUsuario AND c.permiso_id = 3
     LIMIT 1;
END$$

CREATE PROCEDURE `sp_padre_guardar_correos` (IN `pIdUsuario` INT, IN `pCorreoTutor` VARCHAR(255), IN `pCorreoTutor2` VARCHAR(255))  SQL SECURITY INVOKER BEGIN
    UPDATE cuenta SET correo_tutor = pCorreoTutor, correo_tutor2 = pCorreoTutor2
     WHERE usuario_id = pIdUsuario AND permiso_id = 3;

    SELECT ROW_COUNT() AS Afectadas;
END$$

CREATE PROCEDURE `sp_perfil_crear` (IN `pNombre` VARCHAR(25), IN `pPaterno` VARCHAR(25), IN `pMaterno` VARCHAR(25), IN `pMatricula` VARCHAR(150), IN `pIdPermiso` INT, IN `pIdGrupo` INT, IN `pEstatus` VARCHAR(30))  SQL SECURITY INVOKER BEGIN
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
END$$

CREATE PROCEDURE `sp_perfil_editar` (IN `pIdCuenta` INT, IN `pNombre` VARCHAR(25), IN `pPaterno` VARCHAR(25), IN `pMaterno` VARCHAR(25), IN `pMatricula` VARCHAR(150), IN `pIdPermiso` INT, IN `pIdGrupo` INT, IN `pEstatus` VARCHAR(30))  SQL SECURITY INVOKER BEGIN
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
END$$

CREATE PROCEDURE `sp_perfil_eliminar` (IN `pIdCuenta` INT, IN `pIdUsuarioSesion` INT)  SQL SECURITY INVOKER BEGIN
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
END$$

CREATE PROCEDURE `sp_perfil_estatus` ()  SQL SECURITY INVOKER BEGIN
    SELECT clave AS Clave, nombre AS Nombre FROM status WHERE ambito = 'alumno' ORDER BY orden;
END$$

CREATE PROCEDURE `sp_perfil_grupos` ()  SQL SECURITY INVOKER BEGIN
    SELECT g.id_grupo AS IdGrupo, g.grado AS Grado, ci.nombre AS Ciclo
      FROM grupo g
      LEFT JOIN ciclo ci ON ci.id_ciclo = g.ciclo_id
     WHERE g.id_grupo <> 0
     ORDER BY g.nivel, g.grado;
END$$

CREATE PROCEDURE `sp_perfil_listar` (IN `pIdPermiso` INT)  SQL SECURITY INVOKER BEGIN
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
END$$

CREATE PROCEDURE `sp_permiso_crear` (IN `pNombre` VARCHAR(25))  SQL SECURITY INVOKER BEGIN
    INSERT INTO permisos (nombre) VALUES (pNombre);

    SELECT LAST_INSERT_ID() AS Id;
END$$

CREATE PROCEDURE `sp_permiso_editar` (IN `pIdPermiso` INT, IN `pNombre` VARCHAR(25))  SQL SECURITY INVOKER BEGIN
    IF NOT EXISTS (SELECT 1 FROM permisos WHERE id_permiso = pIdPermiso) THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'notfound:No se encontró el rol.';
    END IF;

    UPDATE permisos SET nombre = pNombre WHERE id_permiso = pIdPermiso;

    SELECT 1 AS Afectadas;
END$$

CREATE PROCEDURE `sp_permiso_eliminar` (IN `pIdPermiso` INT)  SQL SECURITY INVOKER BEGIN
    IF EXISTS (SELECT 1 FROM cuenta WHERE permiso_id = pIdPermiso) THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'en_uso:No se puede eliminar: hay cuentas con este rol.';
    END IF;

    DELETE FROM permisos WHERE id_permiso = pIdPermiso;

    SELECT ROW_COUNT() AS Afectadas;
END$$

CREATE PROCEDURE `sp_permiso_listar` ()  SQL SECURITY INVOKER BEGIN
    SELECT p.id_permiso AS IdPermiso, p.nombre AS Nombre,
           (SELECT COUNT(*) FROM cuenta c WHERE c.permiso_id = p.id_permiso) AS NumCuentas
      FROM permisos p
     ORDER BY p.id_permiso;
END$$

CREATE PROCEDURE `sp_persona_correo` (IN `pIdCuenta` INT)  SQL SECURITY INVOKER BEGIN
    SELECT c.id_cuenta AS IdCuenta, TRIM(CONCAT_WS(' ', u.nombre, u.paterno, u.materno)) AS NombreCompleto,
           c.matricula AS Matricula, c.correo_tutor AS CorreoTutor, c.correo_tutor2 AS CorreoTutor2
      FROM cuenta c
      JOIN usuario u ON u.id_usuario = c.usuario_id
     WHERE c.id_cuenta = pIdCuenta AND c.permiso_id = 3;
END$$

CREATE PROCEDURE `sp_persona_crear` (IN `pNombre` VARCHAR(25), IN `pPaterno` VARCHAR(25), IN `pMaterno` VARCHAR(25), IN `pIdGrupo` INT, IN `pMatricula` VARCHAR(150), IN `pIdPermiso` INT, IN `pNivelesIngles` VARCHAR(30), IN `pCorreoTutor` VARCHAR(255), IN `pCorreoTutor2` VARCHAR(255))  SQL SECURITY INVOKER BEGIN
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
END$$

CREATE PROCEDURE `sp_persona_editar` (IN `pIdCuenta` INT, IN `pNombre` VARCHAR(25), IN `pPaterno` VARCHAR(25), IN `pMaterno` VARCHAR(25), IN `pIdGrupo` INT, IN `pSinGrupo` TINYINT, IN `pMatricula` VARCHAR(150), IN `pIdPermiso` INT, IN `pNivelesIngles` VARCHAR(30), IN `pCorreoTutor` VARCHAR(255), IN `pCorreoTutor2` VARCHAR(255))  SQL SECURITY INVOKER BEGIN
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
END$$

CREATE PROCEDURE `sp_persona_eliminar` (IN `pIdCuenta` INT)  SQL SECURITY INVOKER BEGIN
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
END$$

CREATE PROCEDURE `sp_persona_listar` (IN `pIdPermiso` INT)  SQL SECURITY INVOKER BEGIN
    SELECT c.id_cuenta AS IdCuenta, c.matricula AS Matricula, c.grupo_id AS IdGrupo, g.grado AS Grado,
           c.correo_tutor AS CorreoTutor, c.correo_tutor2 AS CorreoTutor2, c.niveles_ingles AS NivelesIngles,
           u.id_usuario AS IdUsuario, u.nombre AS Nombre, u.paterno AS Paterno, u.materno AS Materno
      FROM cuenta c
      JOIN usuario u ON u.id_usuario = c.usuario_id
      LEFT JOIN grupo g ON g.id_grupo = c.grupo_id
     WHERE c.permiso_id = pIdPermiso
     ORDER BY u.paterno, u.nombre;
END$$

CREATE PROCEDURE `sp_recibo_crear` (IN `pIdTipoRecibo` INT, IN `pNaturaleza` VARCHAR(10), IN `pDestinatarioTipo` VARCHAR(10), IN `pIdCuenta` INT, IN `pMonto` DECIMAL(10,2), IN `pTipoPago` VARCHAR(30), IN `pComentario` TEXT, IN `pFecha` DATETIME)  SQL SECURITY INVOKER BEGIN
    CALL sp_recibo_validar_destinatario(pDestinatarioTipo, pIdCuenta);

    INSERT INTO recibo (tipo_recibo_id, naturaleza, destinatario_tipo, cuenta_id, monto, tipo_pago, comentario, fecha)
    VALUES (pIdTipoRecibo, pNaturaleza, pDestinatarioTipo, pIdCuenta, pMonto, pTipoPago, pComentario, pFecha);

    SELECT LAST_INSERT_ID() AS Id;
END$$

CREATE PROCEDURE `sp_recibo_editar` (IN `pIdRecibo` INT, IN `pIdTipoRecibo` INT, IN `pNaturaleza` VARCHAR(10), IN `pDestinatarioTipo` VARCHAR(10), IN `pIdCuenta` INT, IN `pMonto` DECIMAL(10,2), IN `pTipoPago` VARCHAR(30), IN `pComentario` TEXT, IN `pFecha` DATETIME)  SQL SECURITY INVOKER BEGIN
    IF NOT EXISTS (SELECT 1 FROM recibo WHERE id_recibo = pIdRecibo) THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'notfound:No se encontró el recibo.';
    END IF;
    CALL sp_recibo_validar_destinatario(pDestinatarioTipo, pIdCuenta);

    UPDATE recibo
       SET tipo_recibo_id = pIdTipoRecibo, naturaleza = pNaturaleza, destinatario_tipo = pDestinatarioTipo,
           cuenta_id = pIdCuenta, monto = pMonto, tipo_pago = pTipoPago, comentario = pComentario, fecha = pFecha
     WHERE id_recibo = pIdRecibo;

    SELECT 1 AS Afectadas;
END$$

CREATE PROCEDURE `sp_recibo_eliminar` (IN `pIdRecibo` INT)  SQL SECURITY INVOKER BEGIN
    DELETE FROM recibo WHERE id_recibo = pIdRecibo;

    SELECT ROW_COUNT() AS Afectadas;
END$$

CREATE PROCEDURE `sp_recibo_listar` ()  SQL SECURITY INVOKER BEGIN
    SELECT r.id_recibo AS IdRecibo, r.tipo_recibo_id AS IdTipoRecibo, r.naturaleza AS Naturaleza,
           r.destinatario_tipo AS DestinatarioTipo, r.cuenta_id AS IdCuenta, r.monto AS Monto, r.tipo_pago AS TipoPago,
           r.comentario AS Comentario, r.fecha AS Fecha, t.nombre AS TipoNombre,
           IF(r.destinatario_tipo = 'escuela', 'Escuela', TRIM(CONCAT_WS(' ', u.nombre, u.paterno, u.materno))) AS Destinatario
      FROM recibo r
      LEFT JOIN tipo_recibo t ON t.id_tipo = r.tipo_recibo_id
      LEFT JOIN cuenta c      ON c.id_cuenta = r.cuenta_id
      LEFT JOIN usuario u     ON u.id_usuario = c.usuario_id
     ORDER BY r.fecha DESC, r.id_recibo DESC;
END$$

CREATE PROCEDURE `sp_recibo_obtener` (IN `pIdRecibo` INT)  SQL SECURITY INVOKER BEGIN
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
END$$

CREATE PROCEDURE `sp_recibo_personas` (IN `pIdPermiso` INT)  SQL SECURITY INVOKER BEGIN
    SELECT c.id_cuenta AS IdCuenta, TRIM(CONCAT_WS(' ', u.nombre, u.paterno, u.materno)) AS Nombre
      FROM cuenta c
      JOIN usuario u ON u.id_usuario = c.usuario_id
     WHERE c.permiso_id = pIdPermiso
     ORDER BY u.paterno, u.nombre;
END$$

CREATE PROCEDURE `sp_recibo_validar_destinatario` (IN `pDestinatarioTipo` VARCHAR(10), IN `pIdCuenta` INT)  SQL SECURITY INVOKER BEGIN
    DECLARE vMensaje VARCHAR(120);
    IF pDestinatarioTipo <> 'escuela' COLLATE utf8mb4_unicode_ci
       AND NOT EXISTS (SELECT 1 FROM cuenta WHERE id_cuenta = pIdCuenta
                          AND permiso_id = IF(pDestinatarioTipo = 'alumno' COLLATE utf8mb4_unicode_ci, 3, 2)) THEN
        SET vMensaje = CONCAT('invalido:El destinatario no es un ', pDestinatarioTipo, ' válido.');
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = vMensaje;
    END IF;
END$$

CREATE PROCEDURE `sp_reporte_alumno` (IN `pIdCuenta` INT, IN `pIdGrupo` INT)  SQL SECURITY INVOKER BEGIN
    SELECT c.id_cuenta AS IdCuenta, TRIM(CONCAT_WS(' ', u.nombre, u.paterno, u.materno)) AS NombreCompleto,
           c.correo_tutor AS CorreoTutor, c.correo_tutor2 AS CorreoTutor2
      FROM cuenta c
      JOIN usuario u ON u.id_usuario = c.usuario_id
     WHERE c.id_cuenta = pIdCuenta AND c.grupo_id = pIdGrupo AND c.permiso_id = 3;
END$$

CREATE PROCEDURE `sp_reporte_alumnos` (IN `pIdGrupo` INT)  SQL SECURITY INVOKER BEGIN
    SELECT c.id_cuenta AS IdCuenta, TRIM(CONCAT_WS(' ', u.nombre, u.paterno, u.materno)) AS NombreCompleto,
           (COALESCE(c.correo_tutor, '') <> '' OR COALESCE(c.correo_tutor2, '') <> '') AS TieneCorreo
      FROM cuenta c
      JOIN usuario u ON u.id_usuario = c.usuario_id
     WHERE c.grupo_id = pIdGrupo AND c.permiso_id = 3
     ORDER BY u.paterno, u.nombre;
END$$

CREATE PROCEDURE `sp_reporte_clase` (IN `pIdGrupo` INT, IN `pIdMateria` INT, IN `pFecha` DATE)  SQL SECURITY INVOKER BEGIN
    SELECT id_clase AS IdClase, materia_id AS IdMateria, tema AS Tema
      FROM reporte_clase
     WHERE grupo_id = pIdGrupo AND materia_id = pIdMateria AND fecha = pFecha
     LIMIT 1;
END$$

CREATE PROCEDURE `sp_reporte_clases` (IN `pIdGrupo` INT, IN `pDesde` DATE, IN `pHasta` DATE)  SQL SECURITY INVOKER BEGIN
    SELECT rc.id_clase AS IdClase, rc.fecha AS Fecha, rc.materia_id AS IdMateria, m.nombre AS Materia, rc.tema AS Tema,
           (SELECT COUNT(DISTINCT rm.cuenta_id) FROM reporte_marca rm WHERE rm.clase_id = rc.id_clase) AS Incidencias
      FROM reporte_clase rc
      JOIN materia m ON m.id_materia = rc.materia_id
     WHERE rc.grupo_id = pIdGrupo AND rc.fecha BETWEEN pDesde AND pHasta
     ORDER BY rc.fecha, rc.id_clase;
END$$

CREATE PROCEDURE `sp_reporte_clase_obtener` (IN `pIdClase` INT, IN `pIdGrupo` INT)  SQL SECURITY INVOKER BEGIN
    SELECT id_clase AS IdClase, materia_id AS IdMateria, tema AS Tema
      FROM reporte_clase
     WHERE id_clase = pIdClase AND grupo_id = pIdGrupo;
END$$

CREATE PROCEDURE `sp_reporte_columna_crear` (IN `pNombre` VARCHAR(60), IN `pTipo` VARCHAR(10), IN `pEtiqueta` VARCHAR(60), IN `pOrden` INT, IN `pActivo` TINYINT)  SQL SECURITY INVOKER BEGIN
    INSERT INTO reporte_columna (nombre, tipo, etiqueta, orden, activo) VALUES (pNombre, pTipo, pEtiqueta, pOrden, pActivo);

    SELECT LAST_INSERT_ID() AS Id;
END$$

CREATE PROCEDURE `sp_reporte_columna_editar` (IN `pIdColumna` INT, IN `pNombre` VARCHAR(60), IN `pTipo` VARCHAR(10), IN `pEtiqueta` VARCHAR(60), IN `pOrden` INT, IN `pActivo` TINYINT)  SQL SECURITY INVOKER BEGIN
    IF NOT EXISTS (SELECT 1 FROM reporte_columna WHERE id_columna = pIdColumna) THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'notfound:No se encontró la columna.';
    END IF;

    UPDATE reporte_columna
       SET nombre = pNombre, tipo = pTipo, etiqueta = pEtiqueta, orden = pOrden, activo = pActivo
     WHERE id_columna = pIdColumna;

    SELECT 1 AS Afectadas;
END$$

CREATE PROCEDURE `sp_reporte_columna_eliminar` (IN `pIdColumna` INT)  SQL SECURITY INVOKER BEGIN
    IF EXISTS (SELECT 1 FROM reporte_marca WHERE columna_id = pIdColumna) THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'en_uso:No se puede eliminar: ya hay reportes capturados con esta columna. Desactívala para ocultarla.';
    END IF;

    DELETE FROM reporte_columna WHERE id_columna = pIdColumna;

    SELECT ROW_COUNT() AS Afectadas;
END$$

CREATE PROCEDURE `sp_reporte_columna_listar` (IN `pSoloActivas` TINYINT)  SQL SECURITY INVOKER BEGIN
    SELECT id_columna AS IdColumna, nombre AS Nombre, tipo AS Tipo, etiqueta AS Etiqueta, orden AS Orden, activo AS Activo
      FROM reporte_columna
     WHERE COALESCE(pSoloActivas, 0) = 0 OR activo = 1
     ORDER BY orden, id_columna;
END$$

CREATE PROCEDURE `sp_reporte_eliminar_clase` (IN `pIdClase` INT, IN `pIdGrupo` INT)  SQL SECURITY INVOKER BEGIN
    DELETE FROM reporte_clase WHERE id_clase = pIdClase AND grupo_id = pIdGrupo;

    SELECT ROW_COUNT() AS Afectadas;
END$$

CREATE PROCEDURE `sp_reporte_envios` (IN `pIdGrupo` INT, IN `pSemana` DATE)  SQL SECURITY INVOKER BEGIN
    SELECT e.cuenta_id AS IdCuenta, e.enviado_en AS EnviadoEn
      FROM reporte_envio e
      JOIN cuenta c ON c.id_cuenta = e.cuenta_id
     WHERE c.grupo_id = pIdGrupo AND e.semana = pSemana;
END$$

CREATE PROCEDURE `sp_reporte_guardar_clase` (IN `pIdGrupo` INT, IN `pIdMateria` INT, IN `pIdCiclo` INT, IN `pFecha` DATE, IN `pTema` VARCHAR(255), IN `pMarcas` LONGTEXT)  SQL SECURITY INVOKER BEGIN
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
END$$

CREATE PROCEDURE `sp_reporte_marcas` (IN `pIdClase` INT)  SQL SECURITY INVOKER BEGIN
    SELECT cuenta_id AS IdCuenta, columna_id AS IdColumna, marcado AS Marcado, nota AS Nota
      FROM reporte_marca
     WHERE clase_id = pIdClase;
END$$

CREATE PROCEDURE `sp_reporte_registrar_envio` (IN `pIdCuenta` INT, IN `pSemana` DATE, IN `pEnviadoEn` DATETIME, IN `pEnviadoPor` INT, IN `pDestinatarios` INT)  SQL SECURITY INVOKER BEGIN
    INSERT INTO reporte_envio (cuenta_id, semana, enviado_en, enviado_por, destinatarios)
    VALUES (pIdCuenta, pSemana, pEnviadoEn, pEnviadoPor, pDestinatarios)
    ON DUPLICATE KEY UPDATE enviado_en = VALUES(enviado_en), enviado_por = VALUES(enviado_por), destinatarios = VALUES(destinatarios);

    SELECT 1 AS Afectadas;
END$$

CREATE PROCEDURE `sp_reporte_semana_alumno` (IN `pIdCuenta` INT, IN `pIdGrupo` INT, IN `pDesde` DATE, IN `pHasta` DATE)  SQL SECURITY INVOKER BEGIN
    SELECT rc.id_clase AS IdClase, rc.fecha AS Fecha, m.nombre AS Materia, rc.tema AS Tema,
           rm.columna_id AS IdColumna, rm.marcado AS Marcado, rm.nota AS Nota
      FROM reporte_clase rc
      JOIN materia m ON m.id_materia = rc.materia_id
      LEFT JOIN reporte_marca rm ON rm.clase_id = rc.id_clase AND rm.cuenta_id = pIdCuenta
     WHERE rc.grupo_id = pIdGrupo AND rc.fecha BETWEEN pDesde AND pHasta
     ORDER BY rc.fecha, rc.id_clase;
END$$

CREATE PROCEDURE `sp_sistema_parametros` (IN `pProcedure` VARCHAR(64))  SQL SECURITY INVOKER BEGIN
    SELECT PARAMETER_NAME AS Nombre
      FROM information_schema.PARAMETERS
     WHERE SPECIFIC_SCHEMA = DATABASE()
       AND ROUTINE_TYPE = 'PROCEDURE'
       AND CONVERT(SPECIFIC_NAME USING utf8mb4) COLLATE utf8mb4_unicode_ci = pProcedure COLLATE utf8mb4_unicode_ci
     ORDER BY ORDINAL_POSITION;
END$$

CREATE PROCEDURE `sp_tablas_columnas` (IN `pTabla` VARCHAR(64))  SQL SECURITY INVOKER BEGIN
    SELECT COLUMN_NAME AS Columna
      FROM information_schema.COLUMNS
     WHERE TABLE_SCHEMA = DATABASE()
       AND CONVERT(TABLE_NAME USING utf8mb4) COLLATE utf8mb4_unicode_ci = pTabla COLLATE utf8mb4_unicode_ci
     ORDER BY ORDINAL_POSITION;
END$$

CREATE PROCEDURE `sp_tablas_listar` ()  SQL SECURITY INVOKER BEGIN
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
END$$

CREATE PROCEDURE `sp_tablas_ver` (IN `pTabla` VARCHAR(64), IN `pLimite` INT)  SQL SECURITY INVOKER BEGIN
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
END$$

CREATE PROCEDURE `sp_tipodescuento_crear` (IN `pNombre` VARCHAR(80), IN `pPorcentaje` DECIMAL(5,2), IN `pAplicaA` VARCHAR(15))  SQL SECURITY INVOKER BEGIN
    INSERT INTO tipo_descuento (nombre, porcentaje, aplica_a, status_id)
    VALUES (pNombre, pPorcentaje, pAplicaA,
            (SELECT id_status FROM status WHERE ambito = 'descuento' AND clave = 'activo'));

    SELECT LAST_INSERT_ID() AS Id;
END$$

CREATE PROCEDURE `sp_tipodescuento_editar` (IN `pIdDescuento` INT, IN `pNombre` VARCHAR(80), IN `pPorcentaje` DECIMAL(5,2), IN `pAplicaA` VARCHAR(15), IN `pActivo` TINYINT)  SQL SECURITY INVOKER BEGIN
    IF NOT EXISTS (SELECT 1 FROM tipo_descuento WHERE id_descuento = pIdDescuento) THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'notfound:No se encontró el descuento.';
    END IF;

    UPDATE tipo_descuento
       SET nombre = pNombre, porcentaje = pPorcentaje, aplica_a = pAplicaA,
           status_id = (SELECT id_status FROM status WHERE ambito = 'descuento' AND clave = IF(pActivo = 1, 'activo', 'inactivo'))
     WHERE id_descuento = pIdDescuento;

    SELECT 1 AS Afectadas;
END$$

CREATE PROCEDURE `sp_tipodescuento_eliminar` (IN `pIdDescuento` INT)  SQL SECURITY INVOKER BEGIN
    IF EXISTS (SELECT 1 FROM colegiatura WHERE tipo_descuento_id = pIdDescuento) THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'en_uso:No se puede eliminar: hay pagos con este descuento aplicado. Puedes desactivarlo.';
    END IF;

    DELETE FROM tipo_descuento WHERE id_descuento = pIdDescuento;

    SELECT ROW_COUNT() AS Afectadas;
END$$

CREATE PROCEDURE `sp_tipodescuento_listar` ()  SQL SECURITY INVOKER BEGIN
    SELECT t.id_descuento AS IdDescuento, t.nombre AS Nombre, t.porcentaje AS Porcentaje, t.aplica_a AS AplicaA,
           COALESCE(s.clave = 'activo', 0) AS Activo
      FROM tipo_descuento t
      LEFT JOIN status s ON s.id_status = t.status_id
     ORDER BY t.aplica_a, t.nombre;
END$$

CREATE PROCEDURE `sp_tiporecibo_crear` (IN `pNombre` VARCHAR(50), IN `pNaturaleza` VARCHAR(10))  SQL SECURITY INVOKER BEGIN
    INSERT INTO tipo_recibo (nombre, naturaleza) VALUES (pNombre, pNaturaleza);

    SELECT LAST_INSERT_ID() AS Id;
END$$

CREATE PROCEDURE `sp_tiporecibo_editar` (IN `pIdTipo` INT, IN `pNombre` VARCHAR(50), IN `pNaturaleza` VARCHAR(10))  SQL SECURITY INVOKER BEGIN
    IF NOT EXISTS (SELECT 1 FROM tipo_recibo WHERE id_tipo = pIdTipo) THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'notfound:No se encontró el tipo de recibo.';
    END IF;

    UPDATE tipo_recibo SET nombre = pNombre, naturaleza = pNaturaleza WHERE id_tipo = pIdTipo;

    SELECT 1 AS Afectadas;
END$$

CREATE PROCEDURE `sp_tiporecibo_eliminar` (IN `pIdTipo` INT)  SQL SECURITY INVOKER BEGIN
    IF EXISTS (SELECT 1 FROM recibo WHERE tipo_recibo_id = pIdTipo) THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'en_uso:No se puede eliminar: hay recibos con este tipo.';
    END IF;

    DELETE FROM tipo_recibo WHERE id_tipo = pIdTipo;

    SELECT ROW_COUNT() AS Afectadas;
END$$

CREATE PROCEDURE `sp_tiporecibo_listar` ()  SQL SECURITY INVOKER BEGIN
    SELECT id_tipo AS IdTipo, nombre AS Nombre, naturaleza AS Naturaleza
      FROM tipo_recibo
     ORDER BY nombre;
END$$

DELIMITER ;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `calificacion`
--

CREATE TABLE `calificacion` (
  `id_calificacion` int(11) NOT NULL,
  `cuenta_id` int(11) NOT NULL,
  `materia_id` int(11) NOT NULL,
  `ciclo_id` int(11) DEFAULT NULL,
  `reporte` text DEFAULT NULL,
  `p1` float DEFAULT NULL,
  `p2` float DEFAULT NULL,
  `p3` float DEFAULT NULL,
  `calif_final` float DEFAULT NULL,
  `status_aprobacion_id` int(11) DEFAULT NULL,
  `status_captura_id` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `ciclo`
--

CREATE TABLE `ciclo` (
  `id_ciclo` int(11) NOT NULL,
  `nombre` varchar(30) NOT NULL,
  `fecha_inicio` date NOT NULL,
  `fecha_fin` date NOT NULL,
  `status_id` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `colegiatura`
--

CREATE TABLE `colegiatura` (
  `id_pago` int(11) NOT NULL,
  `cuenta_id` int(11) NOT NULL,
  `ciclo_id` int(11) DEFAULT NULL,
  `fecha_pago` date DEFAULT NULL,
  `mes` varchar(20) DEFAULT NULL,
  `tipo` enum('colegiatura','inscripcion') NOT NULL DEFAULT 'colegiatura',
  `monto` float DEFAULT NULL,
  `status_id` int(11) DEFAULT NULL,
  `fecha_vencimiento` date DEFAULT NULL,
  `recargo_pct` decimal(5,2) NOT NULL DEFAULT 0.00,
  `recargo` decimal(10,2) NOT NULL DEFAULT 0.00,
  `descuento` decimal(10,2) NOT NULL DEFAULT 0.00,
  `tipo_descuento_id` int(11) DEFAULT NULL,
  `concepto_descuento` varchar(60) DEFAULT NULL,
  `recibo_id` int(11) DEFAULT NULL,
  `recargo_monto` decimal(10,2) NOT NULL DEFAULT 0.00
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `cuenta`
--

CREATE TABLE `cuenta` (
  `id_cuenta` int(11) NOT NULL,
  `matricula` varchar(150) DEFAULT NULL,
  `usuario_id` int(11) NOT NULL,
  `grupo_id` int(11) DEFAULT NULL,
  `permiso_id` int(11) NOT NULL,
  `status_id` int(11) DEFAULT NULL,
  `correo_tutor` varchar(255) DEFAULT NULL,
  `correo_tutor2` varchar(255) DEFAULT NULL,
  `niveles_ingles` varchar(30) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `esquema_pago`
--

CREATE TABLE `esquema_pago` (
  `id_esquema` int(11) NOT NULL,
  `ciclo_id` int(11) NOT NULL,
  `nombre` varchar(80) NOT NULL,
  `monto_inscripcion` decimal(10,2) NOT NULL DEFAULT 0.00,
  `monto_colegiatura` decimal(10,2) NOT NULL,
  `primer_mes` date NOT NULL,
  `num_meses` int(11) NOT NULL,
  `dia_vencimiento` int(11) NOT NULL DEFAULT 10,
  `recargo_pct` decimal(5,2) NOT NULL DEFAULT 0.00,
  `recargo_monto` decimal(10,2) NOT NULL DEFAULT 0.00
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `evento`
--

CREATE TABLE `evento` (
  `id_evento` int(11) NOT NULL,
  `ciclo_id` int(11) DEFAULT NULL,
  `tipo` enum('feriado','vacaciones','evento') NOT NULL DEFAULT 'evento',
  `titulo` varchar(100) NOT NULL,
  `descripcion` text DEFAULT NULL,
  `fecha_inicio` date NOT NULL,
  `fecha_fin` date NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `grupo`
--

CREATE TABLE `grupo` (
  `id_grupo` int(11) NOT NULL,
  `grado` varchar(50) DEFAULT NULL,
  `maestra_id` int(11) NOT NULL DEFAULT 0,
  `ciclo_id` int(11) DEFAULT NULL,
  `nivel` int(11) DEFAULT NULL,
  `nivel_ingles` varchar(10) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `grupo_materia`
--

CREATE TABLE `grupo_materia` (
  `id_grupo` int(11) NOT NULL,
  `id_materia` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `materia`
--

CREATE TABLE `materia` (
  `id_materia` int(11) NOT NULL,
  `nombre` varchar(25) DEFAULT NULL,
  `calificaciones` float DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Volcado de datos para la tabla `materia`
--

INSERT INTO `materia` (`id_materia`, `nombre`, `calificaciones`) VALUES
(17, 'LENGUAJES', NULL),
(18, 'PENSAMIENTO MATEMATICO', NULL),
(19, 'ESPAÑOL', NULL),
(20, 'MATEMATICAS', NULL),
(21, 'INGLES', NULL),
(22, 'PROYECTOS', NULL);

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `permisos`
--

CREATE TABLE `permisos` (
  `id_permiso` int(11) NOT NULL,
  `nombre` varchar(25) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Volcado de datos para la tabla `permisos`
--

INSERT INTO `permisos` (`id_permiso`, `nombre`) VALUES
(1, 'administrador'),
(2, 'maestro'),
(3, 'alumno'),
(4, 'Desarrollador');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `recibo`
--

CREATE TABLE `recibo` (
  `id_recibo` int(11) NOT NULL,
  `tipo_recibo_id` int(11) DEFAULT NULL,
  `naturaleza` enum('ingreso','gasto') NOT NULL,
  `destinatario_tipo` enum('alumno','docente','escuela') NOT NULL DEFAULT 'escuela',
  `cuenta_id` int(11) DEFAULT NULL,
  `colegiatura_id` int(11) DEFAULT NULL,
  `monto` decimal(10,2) NOT NULL,
  `tipo_pago` varchar(30) DEFAULT NULL,
  `comentario` text DEFAULT NULL,
  `fecha` datetime NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `reporte_clase`
--

CREATE TABLE `reporte_clase` (
  `id_clase` int(11) NOT NULL,
  `grupo_id` int(11) NOT NULL,
  `materia_id` int(11) NOT NULL,
  `ciclo_id` int(11) NOT NULL,
  `fecha` date NOT NULL,
  `tema` varchar(255) NOT NULL DEFAULT ''
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `reporte_columna`
--

CREATE TABLE `reporte_columna` (
  `id_columna` int(11) NOT NULL,
  `nombre` varchar(60) NOT NULL,
  `tipo` enum('casilla','texto') NOT NULL DEFAULT 'casilla',
  `etiqueta` varchar(60) DEFAULT NULL,
  `orden` int(11) NOT NULL DEFAULT 0,
  `activo` tinyint(1) NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Volcado de datos para la tabla `reporte_columna`
--

INSERT INTO `reporte_columna` (`id_columna`, `nombre`, `tipo`, `etiqueta`, `orden`, `activo`) VALUES
(1, 'Material / Entregas', 'casilla', 'No cumplió', 1, 1),
(2, 'Ausencias', 'casilla', 'Falta', 2, 1),
(3, 'Tareas', 'texto', NULL, 3, 1),
(4, 'Uniformes', 'casilla', 'Incompleto', 4, 1),
(5, 'Conducta', 'texto', NULL, 5, 1),
(6, 'Desempeño', 'texto', NULL, 6, 1);

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `reporte_envio`
--

CREATE TABLE `reporte_envio` (
  `id_envio` int(11) NOT NULL,
  `cuenta_id` int(11) NOT NULL,
  `semana` date NOT NULL,
  `enviado_en` datetime NOT NULL,
  `enviado_por` int(11) NOT NULL,
  `destinatarios` int(11) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `reporte_marca`
--

CREATE TABLE `reporte_marca` (
  `id_marca` int(11) NOT NULL,
  `clase_id` int(11) NOT NULL,
  `cuenta_id` int(11) NOT NULL,
  `columna_id` int(11) NOT NULL,
  `marcado` tinyint(1) NOT NULL DEFAULT 0,
  `nota` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `status`
--

CREATE TABLE `status` (
  `id_status` int(11) NOT NULL,
  `ambito` varchar(30) NOT NULL DEFAULT 'pago',
  `clave` varchar(30) NOT NULL,
  `nombre` varchar(40) NOT NULL,
  `color` varchar(20) NOT NULL DEFAULT '#888',
  `orden` int(11) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Volcado de datos para la tabla `status`
--

INSERT INTO `status` (`id_status`, `ambito`, `clave`, `nombre`, `color`, `orden`) VALUES
(1, 'pago', 'pendiente', 'Pendiente', '#b45309', 1),
(2, 'pago', 'parcial', 'Pago parcial', '#1d4ed8', 2),
(3, 'pago', 'pagado', 'Pagado', '#15803d', 3),
(7, 'ciclo', 'activo', 'Activo', '#15803d', 1),
(8, 'ciclo', 'cerrado', 'Cerrado', '#6b7280', 2),
(9, 'descuento', 'activo', 'Activo', '#15803d', 1),
(10, 'descuento', 'inactivo', 'Inactivo', '#6b7280', 2),
(11, 'alumno', 'activo', 'Activo', '#15803d', 1),
(12, 'alumno', 'baja', 'Baja', '#b91c1c', 2),
(13, 'alumno', 'egresado', 'Egresado', '#1d4ed8', 3),
(14, 'aprobacion', 'aprobado', 'Aprobado', '#15803d', 1),
(15, 'aprobacion', 'reprobado', 'Reprobado', '#b91c1c', 2),
(16, 'aprobacion', 'recursamiento', 'Recursamiento', '#b45309', 3),
(17, 'captura', 'pendiente', 'Pendiente', '#b45309', 1),
(18, 'captura', 'capturada', 'Capturada', '#1d4ed8', 2),
(19, 'captura', 'cerrada', 'Cerrada', '#15803d', 3);

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `tipo_descuento`
--

CREATE TABLE `tipo_descuento` (
  `id_descuento` int(11) NOT NULL,
  `nombre` varchar(80) NOT NULL,
  `porcentaje` decimal(5,2) NOT NULL DEFAULT 0.00,
  `aplica_a` enum('colegiatura','inscripcion') NOT NULL DEFAULT 'colegiatura',
  `status_id` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Volcado de datos para la tabla `tipo_descuento`
--

INSERT INTO `tipo_descuento` (`id_descuento`, `nombre`, `porcentaje`, `aplica_a`, `status_id`) VALUES
(1, 'Descuento estándar 5%', 5.00, 'colegiatura', 9),
(2, 'descuento hermanos', 5.00, 'colegiatura', 9);

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `tipo_recibo`
--

CREATE TABLE `tipo_recibo` (
  `id_tipo` int(11) NOT NULL,
  `nombre` varchar(50) NOT NULL,
  `naturaleza` enum('ingreso','gasto') NOT NULL DEFAULT 'ingreso'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Volcado de datos para la tabla `tipo_recibo`
--

INSERT INTO `tipo_recibo` (`id_tipo`, `nombre`, `naturaleza`) VALUES
(1, 'Regular', 'ingreso'),
(2, 'Colegiatura', 'ingreso'),
(3, 'Nómina', 'gasto'),
(4, 'Inscripción', 'ingreso');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `usuario`
--

CREATE TABLE `usuario` (
  `id_usuario` int(11) NOT NULL,
  `nombre` varchar(25) DEFAULT NULL,
  `paterno` varchar(25) DEFAULT NULL,
  `materno` varchar(25) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--

--
-- Índices para tablas volcadas
--

--
-- Indices de la tabla `calificacion`
--
ALTER TABLE `calificacion`
  ADD PRIMARY KEY (`id_calificacion`),
  ADD UNIQUE KEY `uq_alumno_materia_ciclo` (`cuenta_id`,`materia_id`,`ciclo_id`);

--
-- Indices de la tabla `ciclo`
--
ALTER TABLE `ciclo`
  ADD PRIMARY KEY (`id_ciclo`);

--
-- Indices de la tabla `colegiatura`
--
ALTER TABLE `colegiatura`
  ADD PRIMARY KEY (`id_pago`),
  ADD KEY `fk_cuenta_colegiatura` (`cuenta_id`);

--
-- Indices de la tabla `cuenta`
--
ALTER TABLE `cuenta`
  ADD PRIMARY KEY (`id_cuenta`),
  ADD KEY `fk_usuario_cuenta` (`usuario_id`),
  ADD KEY `fk_permisos_cuenta` (`permiso_id`);

--
-- Indices de la tabla `esquema_pago`
--
ALTER TABLE `esquema_pago`
  ADD PRIMARY KEY (`id_esquema`),
  ADD KEY `idx_esquema_ciclo` (`ciclo_id`);

--
-- Indices de la tabla `evento`
--
ALTER TABLE `evento`
  ADD PRIMARY KEY (`id_evento`);

--
-- Indices de la tabla `grupo`
--
ALTER TABLE `grupo`
  ADD PRIMARY KEY (`id_grupo`);

--
-- Indices de la tabla `grupo_materia`
--
ALTER TABLE `grupo_materia`
  ADD PRIMARY KEY (`id_grupo`,`id_materia`),
  ADD KEY `fk_gm_materia` (`id_materia`);

--
-- Indices de la tabla `materia`
--
ALTER TABLE `materia`
  ADD PRIMARY KEY (`id_materia`);

--
-- Indices de la tabla `permisos`
--
ALTER TABLE `permisos`
  ADD PRIMARY KEY (`id_permiso`);

--
-- Indices de la tabla `recibo`
--
ALTER TABLE `recibo`
  ADD PRIMARY KEY (`id_recibo`);

--
-- Indices de la tabla `reporte_clase`
--
ALTER TABLE `reporte_clase`
  ADD PRIMARY KEY (`id_clase`),
  ADD UNIQUE KEY `uq_clase` (`grupo_id`,`materia_id`,`fecha`),
  ADD KEY `idx_clase_fecha` (`grupo_id`,`fecha`),
  ADD KEY `fk_rc_materia` (`materia_id`);

--
-- Indices de la tabla `reporte_columna`
--
ALTER TABLE `reporte_columna`
  ADD PRIMARY KEY (`id_columna`);

--
-- Indices de la tabla `reporte_envio`
--
ALTER TABLE `reporte_envio`
  ADD PRIMARY KEY (`id_envio`),
  ADD UNIQUE KEY `uq_envio` (`cuenta_id`,`semana`);

--
-- Indices de la tabla `reporte_marca`
--
ALTER TABLE `reporte_marca`
  ADD PRIMARY KEY (`id_marca`),
  ADD UNIQUE KEY `uq_marca` (`clase_id`,`cuenta_id`,`columna_id`),
  ADD KEY `fk_rm_cuenta` (`cuenta_id`),
  ADD KEY `fk_rm_columna` (`columna_id`);

--
-- Indices de la tabla `status`
--
ALTER TABLE `status`
  ADD PRIMARY KEY (`id_status`),
  ADD UNIQUE KEY `uq_status` (`ambito`,`clave`);

--
-- Indices de la tabla `tipo_descuento`
--
ALTER TABLE `tipo_descuento`
  ADD PRIMARY KEY (`id_descuento`);

--
-- Indices de la tabla `tipo_recibo`
--
ALTER TABLE `tipo_recibo`
  ADD PRIMARY KEY (`id_tipo`);

--
-- Indices de la tabla `usuario`
--
ALTER TABLE `usuario`
  ADD PRIMARY KEY (`id_usuario`);

--
-- AUTO_INCREMENT de las tablas volcadas
--

--
-- AUTO_INCREMENT de la tabla `calificacion`
--
ALTER TABLE `calificacion`
  MODIFY `id_calificacion` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `ciclo`
--
ALTER TABLE `ciclo`
  MODIFY `id_ciclo` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `colegiatura`
--
ALTER TABLE `colegiatura`
  MODIFY `id_pago` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `cuenta`
--
ALTER TABLE `cuenta`
  MODIFY `id_cuenta` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `esquema_pago`
--
ALTER TABLE `esquema_pago`
  MODIFY `id_esquema` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `evento`
--
ALTER TABLE `evento`
  MODIFY `id_evento` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `grupo`
--
ALTER TABLE `grupo`
  MODIFY `id_grupo` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `materia`
--
ALTER TABLE `materia`
  MODIFY `id_materia` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `permisos`
--
ALTER TABLE `permisos`
  MODIFY `id_permiso` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `recibo`
--
ALTER TABLE `recibo`
  MODIFY `id_recibo` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `reporte_clase`
--
ALTER TABLE `reporte_clase`
  MODIFY `id_clase` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `reporte_columna`
--
ALTER TABLE `reporte_columna`
  MODIFY `id_columna` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `reporte_envio`
--
ALTER TABLE `reporte_envio`
  MODIFY `id_envio` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `reporte_marca`
--
ALTER TABLE `reporte_marca`
  MODIFY `id_marca` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `status`
--
ALTER TABLE `status`
  MODIFY `id_status` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `tipo_descuento`
--
ALTER TABLE `tipo_descuento`
  MODIFY `id_descuento` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `tipo_recibo`
--
ALTER TABLE `tipo_recibo`
  MODIFY `id_tipo` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `usuario`
--
ALTER TABLE `usuario`
  MODIFY `id_usuario` int(11) NOT NULL AUTO_INCREMENT;

--
-- Restricciones para tablas volcadas
--

--
-- Filtros para la tabla `colegiatura`
--
ALTER TABLE `colegiatura`
  ADD CONSTRAINT `fk_cuenta_colegiatura` FOREIGN KEY (`cuenta_id`) REFERENCES `cuenta` (`id_cuenta`);

--
-- Filtros para la tabla `cuenta`
--
ALTER TABLE `cuenta`
  ADD CONSTRAINT `fk_permisos_cuenta` FOREIGN KEY (`permiso_id`) REFERENCES `permisos` (`id_permiso`),
  ADD CONSTRAINT `fk_usuario_cuenta` FOREIGN KEY (`usuario_id`) REFERENCES `usuario` (`id_usuario`);

--
-- Filtros para la tabla `esquema_pago`
--
ALTER TABLE `esquema_pago`
  ADD CONSTRAINT `fk_esquema_ciclo` FOREIGN KEY (`ciclo_id`) REFERENCES `ciclo` (`id_ciclo`) ON DELETE CASCADE;

--
-- Filtros para la tabla `grupo_materia`
--
ALTER TABLE `grupo_materia`
  ADD CONSTRAINT `fk_gm_grupo` FOREIGN KEY (`id_grupo`) REFERENCES `grupo` (`id_grupo`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_gm_materia` FOREIGN KEY (`id_materia`) REFERENCES `materia` (`id_materia`) ON DELETE CASCADE;

--
-- Filtros para la tabla `reporte_clase`
--
ALTER TABLE `reporte_clase`
  ADD CONSTRAINT `fk_rc_grupo` FOREIGN KEY (`grupo_id`) REFERENCES `grupo` (`id_grupo`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_rc_materia` FOREIGN KEY (`materia_id`) REFERENCES `materia` (`id_materia`) ON DELETE CASCADE;

--
-- Filtros para la tabla `reporte_envio`
--
ALTER TABLE `reporte_envio`
  ADD CONSTRAINT `fk_re_cuenta` FOREIGN KEY (`cuenta_id`) REFERENCES `cuenta` (`id_cuenta`) ON DELETE CASCADE;

--
-- Filtros para la tabla `reporte_marca`
--
ALTER TABLE `reporte_marca`
  ADD CONSTRAINT `fk_rm_clase` FOREIGN KEY (`clase_id`) REFERENCES `reporte_clase` (`id_clase`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_rm_columna` FOREIGN KEY (`columna_id`) REFERENCES `reporte_columna` (`id_columna`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_rm_cuenta` FOREIGN KEY (`cuenta_id`) REFERENCES `cuenta` (`id_cuenta`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
