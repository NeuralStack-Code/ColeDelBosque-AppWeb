-- ============================================================================================
-- Perfiles del colegio y lo que concede cada uno (2026-11-19). Va DESPUÉS de la semilla del catálogo (02_catalogo.sql).
--
-- Reemplaza los roles fijos (Controller::ADMIN/TEACHER/PARENT/DEVELOPER) por cuatro perfiles que conceden lo mismo
-- que concedía cada rol, y le da a cada cuenta el perfil de su tipo (cuenta.permiso_id). El tipo de cuenta se queda
-- como dato (qué es la persona: alumno, maestro…); los permisos ya solo los dan los perfiles.
--   Administrador  control escolar y pagos, y el reporte semanal de TODOS los grupos (reporte/grupos)
--   Maestro        su panel, calificaciones y el reporte semanal de sus grupos
--   Padre          el panel de la familia (entra con la matrícula del alumno)
--   Desarrollador  EsAdmin: concede todo (cuentas, perfiles y permisos, tablas)
--
-- Se puede correr dos veces: no pisa un perfil que ya existe ni quita lo que alguien haya concedido a mano.
-- ============================================================================================
SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci;

INSERT IGNORE INTO Perfiles (Nombre, Descripcion, EsAdmin, Activo, Inicio, TipoCuenta) VALUES
    ('Administrador', 'Control escolar, pagos y reportes semanales de todos los grupos.', 0, 1, '/dashboard/index', 1),
    ('Maestro',       'Calificaciones y reporte semanal de sus grupos.',                  0, 1, '/maestro',         2),
    ('Padre',         'Panel de la familia: calificaciones, pagos y correos del tutor.',   0, 1, '/padre/index',     3),
    ('Desarrollador', 'Concede todo: cuentas, perfiles y permisos, tablas.',              1, 1, '/desarrollador',   NULL);

-- Lo que concede cada perfil: la pantalla y lo que cuelga de ella (lo mismo que marcar la pantalla en el árbol).
-- Lo de "Aparte" (métodos sin pantalla, IdPadre NULL) no entra solo: se nombra (reporte/grupos).
DROP TEMPORARY TABLE IF EXISTS tmp_concede;
CREATE TEMPORARY TABLE tmp_concede (Perfil VARCHAR(80), Componente VARCHAR(50), Clave VARCHAR(80)) DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
INSERT INTO tmp_concede (Perfil, Componente, Clave) VALUES
    ('Administrador', 'dashboard', '*'), ('Administrador', 'alumno', '*'), ('Administrador', 'docente', '*'),
    ('Administrador', 'grupo', '*'), ('Administrador', 'materia', '*'), ('Administrador', 'ciclo', '*'),
    ('Administrador', 'reportecolumna', '*'), ('Administrador', 'reporte', '*'), ('Administrador', 'reporte', 'grupos'),
    ('Administrador', 'recibo', '*'), ('Administrador', 'colegiatura', '*'), ('Administrador', 'esquemapago', '*'),
    ('Administrador', 'tiporecibo', '*'), ('Administrador', 'tipodescuento', '*'),
    ('Maestro', 'maestro', '*'), ('Maestro', 'calificacion', '*'), ('Maestro', 'reporte', '*'),
    ('Padre', 'padre', '*');

INSERT IGNORE INTO PerfilObjeto (IdPerfil, IdObjeto)
SELECT p.IdPerfil, o.IdObjeto
  FROM tmp_concede t
  JOIN Perfiles p    ON p.Nombre = t.Perfil
  JOIN Componentes c ON c.Clave = t.Componente
  JOIN Objetos o     ON o.IdComponente = c.IdComponente AND o.Activo = 1 AND o.IdTipoObjeto IN (2, 3)
                    AND o.Acceso = 'perfil'
                    AND (o.Clave = t.Clave OR (t.Clave = '*' AND (o.IdTipoObjeto = 2 OR o.IdPadre IS NOT NULL)));
DROP TEMPORARY TABLE tmp_concede;

-- Cada cuenta, el perfil de su tipo.
INSERT IGNORE INTO CuentaPerfiles (IdCuenta, IdPerfil)
SELECT c.id_cuenta, p.IdPerfil
  FROM cuenta c
  JOIN Perfiles p ON p.Nombre = CASE c.permiso_id WHEN 1 THEN 'Administrador' WHEN 2 THEN 'Maestro'
                                                  WHEN 3 THEN 'Padre'         WHEN 4 THEN 'Desarrollador' END;

-- Un renglón en la bitácora: cambia la revisión y cada sesión vuelve a pedir su menú.
INSERT INTO BitacoraPermisos (Accion, Detalle)
VALUES ('semilla.perfiles', JSON_OBJECT('perfiles', (SELECT COUNT(*) FROM Perfiles), 'cuentas', (SELECT COUNT(*) FROM CuentaPerfiles)));
