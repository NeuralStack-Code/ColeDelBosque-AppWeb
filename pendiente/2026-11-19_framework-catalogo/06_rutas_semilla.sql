-- ============================================================================================
-- Rutas del sitio (2026-11-19): lo que antes estaba escrito en index.php ($routes y $moved).
-- /maestro y /desarrollador ya no son páginas sueltas: los sirven MaestroController y DesarrolladorController
-- (el catálogo decide quién los abre). Las URLs de Perfil/Permiso cambiaron a Cuenta/PerfilAcceso.
-- Desde la terminal no hay Command: la auditoría va en 0 (el sistema). Se puede correr dos veces.
-- ============================================================================================
SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci;

-- Páginas sueltas
CALL sp_ruta_registrar('',                 'vista', 'webService/views/home.php',                  301, 1, 'Portada', 1, 0, 0);
CALL sp_ruta_registrar('index.php',        'vista', 'webService/views/home.php',                  301, 1, 'Portada', 1, 0, 0);
CALL sp_ruta_registrar('oferta-educativa', 'vista', 'webService/views/site/oferta_educativa.php', 301, 1, 'Oferta educativa', 1, 0, 0);
CALL sp_ruta_registrar('actividades',      'vista', 'webService/views/site/actividades.php',      301, 1, 'Actividades', 1, 0, 0);

-- URLs anteriores → su pantalla de hoy (301, con su ?query)
CALL sp_ruta_registrar('inicio-sesion',                  'redireccion', '/auth/entrar',          301, 1, '', 1, 0, 0);
CALL sp_ruta_registrar('administrador',                  'redireccion', '/dashboard/index',      301, 1, '', 1, 0, 0);
CALL sp_ruta_registrar('administrador/ciclos',           'redireccion', '/ciclo/index',          301, 1, '', 1, 0, 0);
CALL sp_ruta_registrar('administrador/tipos-recibo',     'redireccion', '/tiporecibo/index',     301, 1, '', 1, 0, 0);
CALL sp_ruta_registrar('administrador/tipos-descuento',  'redireccion', '/tipodescuento/index',  301, 1, '', 1, 0, 0);
CALL sp_ruta_registrar('administrador/materias',         'redireccion', '/materia/index',        301, 1, '', 1, 0, 0);
CALL sp_ruta_registrar('administrador/colegiaturas',     'redireccion', '/colegiatura/index',    301, 1, '', 1, 0, 0);
CALL sp_ruta_registrar('administrador/esquemas-pago',    'redireccion', '/esquemapago/index',    301, 1, '', 1, 0, 0);
CALL sp_ruta_registrar('administrador/recibos',          'redireccion', '/recibo/index',         301, 1, '', 1, 0, 0);
CALL sp_ruta_registrar('administrador/reporte-columnas', 'redireccion', '/reportecolumna/index', 301, 1, '', 1, 0, 0);
CALL sp_ruta_registrar('administrador/reportes',         'redireccion', '/reporte/index',        301, 1, '', 1, 0, 0);
CALL sp_ruta_registrar('administrador/alumnos',          'redireccion', '/alumno/index',         301, 1, '', 1, 0, 0);
CALL sp_ruta_registrar('administrador/maestros',         'redireccion', '/docente/index',        301, 1, '', 1, 0, 0);
CALL sp_ruta_registrar('administrador/grupos',           'redireccion', '/grupo/index',          301, 1, '', 1, 0, 0);
CALL sp_ruta_registrar('padre',                          'redireccion', '/padre/index',          301, 1, '', 1, 0, 0);
CALL sp_ruta_registrar('recibo',                         'redireccion', '/recibo/imprimir',      301, 1, 'Enlace de los correos (?id=)', 1, 0, 0);
CALL sp_ruta_registrar('maestro/calificaciones',         'redireccion', '/calificacion/index',   301, 1, '', 1, 0, 0);
CALL sp_ruta_registrar('maestro/reportes',               'redireccion', '/reporte/index',        301, 1, '', 1, 0, 0);
CALL sp_ruta_registrar('desarrollador/tablas',           'redireccion', '/tablas/index',         301, 1, '', 1, 0, 0);
CALL sp_ruta_registrar('desarrollador/perfiles',         'redireccion', '/cuenta/index',         301, 1, '', 1, 0, 0);
CALL sp_ruta_registrar('desarrollador/permisos',         'redireccion', '/perfilacceso/index',   301, 1, '', 1, 0, 0);
CALL sp_ruta_registrar('perfil/index',                   'redireccion', '/cuenta/index',         301, 1, 'Antes: PerfilController', 1, 0, 0);
CALL sp_ruta_registrar('permiso/index',                  'redireccion', '/perfilacceso/index',   301, 1, 'Antes: PermisoController', 1, 0, 0);
