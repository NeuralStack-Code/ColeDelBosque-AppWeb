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
