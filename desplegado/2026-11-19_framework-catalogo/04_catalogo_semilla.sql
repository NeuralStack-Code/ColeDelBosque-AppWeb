-- ============================================================================================
-- Semilla del catálogo de permisos. GENERADA por apiService/tools/catalogo/generar_semilla.php
-- 2026-11-19 16:01 · NO editar a mano: cambiar el código o apiService/tools/catalogo/ajustes.php y regenerar.
-- Requiere: las tablas y procedures del catálogo (01_tablas_catalogo.sql y sp_catalogo.sql)
-- Huella: e314a8660eb8c901d757d7904a478e1f590fc4f2bc0e19901b6107dc7142d1cf
-- Sin information_schema (phpMyAdmin #1109). Se puede correr más de una vez.
-- ============================================================================================
SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci;

-- CALL sp_objeto_registrar(Componente, Clase, Tipo, Padre, Clave, Nombre, Icono, Orden, SoloPost, Acceso, Sensible, PaginaAnterior, IdUsuarioRegistra, IdUsuarioCambia)
-- Desde la terminal no hay Command que llene la auditoría: se pasa 0 (sistema) explícito.

-- Componentes: qué archivo, módulo y base atiende cada uno (el router los carga desde aquí)
CALL sp_componente_registrar('alumno', 'AlumnoController', 'webService/controllers/AlumnoController.php', 'escolar', 'principal', 'portal', 0, 0);
CALL sp_componente_registrar('auth', 'AuthController', 'webService/controllers/AuthController.php', 'sitio', 'principal', 'portal', 0, 0);
CALL sp_componente_registrar('calificacion', 'CalificacionController', 'webService/controllers/CalificacionController.php', 'escolar', 'principal', 'portal', 0, 0);
CALL sp_componente_registrar('ciclo', 'CicloController', 'webService/controllers/CicloController.php', 'escolar', 'principal', 'portal', 0, 0);
CALL sp_componente_registrar('colegiatura', 'ColegiaturaController', 'webService/controllers/ColegiaturaController.php', 'pagos', 'principal', 'portal', 0, 0);
CALL sp_componente_registrar('contacto', 'ContactoController', 'webService/controllers/ContactoController.php', 'sitio', 'ninguna', 'portal', 0, 0);
CALL sp_componente_registrar('cuenta', 'CuentaController', 'webService/controllers/CuentaController.php', 'sistema', 'principal', 'portal', 0, 0);
CALL sp_componente_registrar('dashboard', 'DashboardController', 'webService/controllers/DashboardController.php', 'escolar', 'principal', 'portal', 0, 0);
CALL sp_componente_registrar('desarrollador', 'DesarrolladorController', 'webService/controllers/DesarrolladorController.php', 'sistema', 'principal', 'portal', 0, 0);
CALL sp_componente_registrar('docente', 'DocenteController', 'webService/controllers/DocenteController.php', 'escolar', 'principal', 'portal', 0, 0);
CALL sp_componente_registrar('esquemapago', 'EsquemaPagoController', 'webService/controllers/EsquemaPagoController.php', 'pagos', 'principal', 'portal', 0, 0);
CALL sp_componente_registrar('grupo', 'GrupoController', 'webService/controllers/GrupoController.php', 'escolar', 'principal', 'portal', 0, 0);
CALL sp_componente_registrar('maestro', 'MaestroController', 'webService/controllers/MaestroController.php', 'escolar', 'principal', 'portal', 0, 0);
CALL sp_componente_registrar('materia', 'MateriaController', 'webService/controllers/MateriaController.php', 'escolar', 'principal', 'portal', 0, 0);
CALL sp_componente_registrar('padre', 'PadreController', 'webService/controllers/PadreController.php', 'padre', 'principal', 'portal', 0, 0);
CALL sp_componente_registrar('perfilacceso', 'PerfilAccesoController', 'webService/controllers/PerfilAccesoController.php', 'sistema', 'principal', 'portal', 0, 0);
CALL sp_componente_registrar('recibo', 'ReciboController', 'webService/controllers/ReciboController.php', 'pagos', 'principal', 'portal', 0, 0);
CALL sp_componente_registrar('reportecolumna', 'ReporteColumnaController', 'webService/controllers/ReporteColumnaController.php', 'escolar', 'principal', 'portal', 0, 0);
CALL sp_componente_registrar('reporte', 'ReporteController', 'webService/controllers/ReporteController.php', 'escolar', 'principal', 'portal', 0, 0);
CALL sp_componente_registrar('tablas', 'TablasController', 'webService/controllers/TablasController.php', 'sistema', 'principal', 'portal', 0, 0);
CALL sp_componente_registrar('tipodescuento', 'TipoDescuentoController', 'webService/controllers/TipoDescuentoController.php', 'pagos', 'principal', 'portal', 0, 0);
CALL sp_componente_registrar('tiporecibo', 'TipoReciboController', 'webService/controllers/TipoReciboController.php', 'pagos', 'principal', 'portal', 0, 0);

-- Grupos
CALL sp_objeto_registrar('', '', 1, '', 'escolar', 'Control escolar', 'school', 10, 0, 'sesion', 0, NULL, 0, 0);
CALL sp_objeto_registrar('', '', 1, '', 'pagos', 'Pagos', 'payments', 20, 0, 'sesion', 0, NULL, 0, 0);
CALL sp_objeto_registrar('', '', 1, '', 'padre', 'Familias', 'family_restroom', 30, 0, 'sesion', 0, NULL, 0, 0);
CALL sp_objeto_registrar('', '', 1, '', 'sistema', 'Sistema', 'settings', 40, 0, 'sesion', 0, NULL, 0, 0);

-- Vistas
CALL sp_objeto_registrar('alumno', 'AlumnoController', 2, 'escolar', 'index', 'Alumnos', '', 20, 0, 'perfil', 0, NULL, 0, 0);
CALL sp_objeto_registrar('auth', 'AuthController', 2, '', 'entrar', '', '', 10, 0, 'publico', 0, NULL, 0, 0);
CALL sp_objeto_registrar('calificacion', 'CalificacionController', 2, 'escolar', 'index', 'Calificaciones', '', 70, 0, 'perfil', 0, NULL, 0, 0);
CALL sp_objeto_registrar('ciclo', 'CicloController', 2, 'escolar', 'index', 'Ciclo escolar', '', 60, 0, 'perfil', 0, NULL, 0, 0);
CALL sp_objeto_registrar('colegiatura', 'ColegiaturaController', 2, 'pagos', 'index', 'Colegiaturas', '', 20, 0, 'perfil', 0, NULL, 0, 0);
CALL sp_objeto_registrar('contacto', 'ContactoController', 2, '', 'index', '', '', 10, 0, 'publico', 0, NULL, 0, 0);
CALL sp_objeto_registrar('cuenta', 'CuentaController', 2, 'sistema', 'index', 'Cuentas', '', 20, 0, 'perfil', 0, NULL, 0, 0);
CALL sp_objeto_registrar('dashboard', 'DashboardController', 2, 'escolar', 'index', 'Panel del administrador', '', 10, 0, 'perfil', 0, NULL, 0, 0);
CALL sp_objeto_registrar('desarrollador', 'DesarrolladorController', 2, 'sistema', 'index', 'Panel del desarrollador', '', 10, 0, 'perfil', 0, NULL, 0, 0);
CALL sp_objeto_registrar('docente', 'DocenteController', 2, 'escolar', 'index', 'Maestros', '', 30, 0, 'perfil', 0, NULL, 0, 0);
CALL sp_objeto_registrar('esquemapago', 'EsquemaPagoController', 2, 'pagos', 'index', 'Esquemas de pago', '', 30, 0, 'perfil', 0, NULL, 0, 0);
CALL sp_objeto_registrar('grupo', 'GrupoController', 2, 'escolar', 'index', 'Grupos', '', 40, 0, 'perfil', 0, NULL, 0, 0);
CALL sp_objeto_registrar('maestro', 'MaestroController', 2, 'escolar', 'index', 'Panel del maestro', '', 12, 0, 'perfil', 0, NULL, 0, 0);
CALL sp_objeto_registrar('materia', 'MateriaController', 2, 'escolar', 'index', 'Materias', '', 50, 0, 'perfil', 0, NULL, 0, 0);
CALL sp_objeto_registrar('padre', 'PadreController', 2, 'padre', 'index', 'Panel de la familia', '', 10, 0, 'perfil', 0, NULL, 0, 0);
CALL sp_objeto_registrar('perfilacceso', 'PerfilAccesoController', 2, 'sistema', 'index', 'Perfiles y permisos', '', 30, 0, 'perfil', 1, NULL, 0, 0);
CALL sp_objeto_registrar('recibo', 'ReciboController', 2, 'pagos', 'index', 'Recibos', '', 10, 0, 'perfil', 0, NULL, 0, 0);
CALL sp_objeto_registrar('recibo', 'ReciboController', 2, 'index', 'imprimir', 'Imprimir', '', 50, 0, 'sesion', 0, NULL, 0, 0);
CALL sp_objeto_registrar('reportecolumna', 'ReporteColumnaController', 2, 'escolar', 'index', 'Columnas del reporte', '', 90, 0, 'perfil', 0, NULL, 0, 0);
CALL sp_objeto_registrar('reporte', 'ReporteController', 2, 'escolar', 'index', 'Reporte semanal', '', 80, 0, 'perfil', 0, NULL, 0, 0);
CALL sp_objeto_registrar('tablas', 'TablasController', 2, 'sistema', 'index', 'Tablas', '', 40, 0, 'perfil', 1, NULL, 0, 0);
CALL sp_objeto_registrar('tipodescuento', 'TipoDescuentoController', 2, 'pagos', 'index', 'Tipos de descuento', '', 50, 0, 'perfil', 0, NULL, 0, 0);
CALL sp_objeto_registrar('tiporecibo', 'TipoReciboController', 2, 'pagos', 'index', 'Tipos de recibo', '', 40, 0, 'perfil', 0, NULL, 0, 0);

-- Métodos
CALL sp_objeto_registrar('alumno', 'AlumnoController', 3, 'index', 'listar', 'Listar', '', 10, 0, 'perfil', 0, NULL, 0, 0);
CALL sp_objeto_registrar('alumno', 'AlumnoController', 3, 'index', 'guardar', 'Guardar', '', 20, 1, 'perfil', 0, NULL, 0, 0);
CALL sp_objeto_registrar('alumno', 'AlumnoController', 3, 'index', 'eliminar', 'Eliminar', '', 30, 1, 'perfil', 0, NULL, 0, 0);
CALL sp_objeto_registrar('alumno', 'AlumnoController', 3, 'index', 'enviarMatricula', 'Enviar matricula', '', 40, 1, 'perfil', 0, NULL, 0, 0);
CALL sp_objeto_registrar('auth', 'AuthController', 3, '', 'salir', 'Salir', '', 20, 0, 'publico', 0, NULL, 0, 0);
CALL sp_objeto_registrar('calificacion', 'CalificacionController', 3, 'index', 'contexto', 'Contexto', '', 10, 0, 'perfil', 0, NULL, 0, 0);
CALL sp_objeto_registrar('calificacion', 'CalificacionController', 3, 'index', 'listar', 'Listar', '', 20, 0, 'perfil', 0, NULL, 0, 0);
CALL sp_objeto_registrar('calificacion', 'CalificacionController', 3, 'index', 'guardar', 'Guardar', '', 30, 1, 'perfil', 0, NULL, 0, 0);
CALL sp_objeto_registrar('ciclo', 'CicloController', 3, 'index', 'listar', 'Listar', '', 10, 0, 'perfil', 0, NULL, 0, 0);
CALL sp_objeto_registrar('ciclo', 'CicloController', 3, 'index', 'guardar', 'Guardar', '', 20, 1, 'perfil', 0, NULL, 0, 0);
CALL sp_objeto_registrar('ciclo', 'CicloController', 3, 'index', 'activar', 'Activar', '', 30, 1, 'perfil', 0, NULL, 0, 0);
CALL sp_objeto_registrar('ciclo', 'CicloController', 3, 'index', 'eliminar', 'Eliminar', '', 40, 1, 'perfil', 0, NULL, 0, 0);
CALL sp_objeto_registrar('colegiatura', 'ColegiaturaController', 3, 'index', 'catalogos', 'Catalogos', '', 10, 0, 'perfil', 0, NULL, 0, 0);
CALL sp_objeto_registrar('colegiatura', 'ColegiaturaController', 3, 'index', 'listar', 'Listar', '', 20, 0, 'perfil', 0, NULL, 0, 0);
CALL sp_objeto_registrar('colegiatura', 'ColegiaturaController', 3, 'index', 'pagar', 'Pagar', '', 30, 1, 'perfil', 0, NULL, 0, 0);
CALL sp_objeto_registrar('colegiatura', 'ColegiaturaController', 3, 'index', 'descuento', 'Descuento', '', 40, 1, 'perfil', 0, NULL, 0, 0);
CALL sp_objeto_registrar('colegiatura', 'ColegiaturaController', 3, 'index', 'recargo', 'Recargo', '', 50, 1, 'perfil', 0, NULL, 0, 0);
CALL sp_objeto_registrar('colegiatura', 'ColegiaturaController', 3, 'index', 'eliminar', 'Eliminar', '', 60, 1, 'perfil', 0, NULL, 0, 0);
CALL sp_objeto_registrar('contacto', 'ContactoController', 3, 'index', 'enviar', 'Enviar', '', 20, 1, 'publico', 0, NULL, 0, 0);
CALL sp_objeto_registrar('cuenta', 'CuentaController', 3, 'index', 'catalogos', 'Catalogos', '', 10, 0, 'perfil', 0, NULL, 0, 0);
CALL sp_objeto_registrar('cuenta', 'CuentaController', 3, 'index', 'listar', 'Listar', '', 20, 0, 'perfil', 0, NULL, 0, 0);
CALL sp_objeto_registrar('cuenta', 'CuentaController', 3, 'index', 'guardar', 'Guardar', '', 30, 1, 'perfil', 1, NULL, 0, 0);
CALL sp_objeto_registrar('cuenta', 'CuentaController', 3, 'index', 'perfiles', 'Perfiles', '', 40, 1, 'perfil', 1, NULL, 0, 0);
CALL sp_objeto_registrar('cuenta', 'CuentaController', 3, 'index', 'eliminar', 'Eliminar', '', 50, 1, 'perfil', 1, NULL, 0, 0);
CALL sp_objeto_registrar('dashboard', 'DashboardController', 3, 'index', 'resumen', 'Resumen', '', 10, 0, 'perfil', 0, NULL, 0, 0);
CALL sp_objeto_registrar('docente', 'DocenteController', 3, 'index', 'listar', 'Listar', '', 10, 0, 'perfil', 0, NULL, 0, 0);
CALL sp_objeto_registrar('docente', 'DocenteController', 3, 'index', 'guardar', 'Guardar', '', 20, 1, 'perfil', 0, NULL, 0, 0);
CALL sp_objeto_registrar('docente', 'DocenteController', 3, 'index', 'eliminar', 'Eliminar', '', 30, 1, 'perfil', 0, NULL, 0, 0);
CALL sp_objeto_registrar('esquemapago', 'EsquemaPagoController', 3, 'index', 'catalogos', 'Catalogos', '', 10, 0, 'perfil', 0, NULL, 0, 0);
CALL sp_objeto_registrar('esquemapago', 'EsquemaPagoController', 3, 'index', 'listar', 'Listar', '', 20, 0, 'perfil', 0, NULL, 0, 0);
CALL sp_objeto_registrar('esquemapago', 'EsquemaPagoController', 3, 'index', 'desglose', 'Desglose', '', 30, 0, 'perfil', 0, NULL, 0, 0);
CALL sp_objeto_registrar('esquemapago', 'EsquemaPagoController', 3, 'index', 'guardar', 'Guardar', '', 40, 1, 'perfil', 0, NULL, 0, 0);
CALL sp_objeto_registrar('esquemapago', 'EsquemaPagoController', 3, 'index', 'eliminar', 'Eliminar', '', 50, 1, 'perfil', 0, NULL, 0, 0);
CALL sp_objeto_registrar('esquemapago', 'EsquemaPagoController', 3, 'index', 'aplicar', 'Aplicar', '', 60, 1, 'perfil', 0, NULL, 0, 0);
CALL sp_objeto_registrar('grupo', 'GrupoController', 3, 'index', 'listar', 'Listar', '', 10, 0, 'perfil', 0, NULL, 0, 0);
CALL sp_objeto_registrar('grupo', 'GrupoController', 3, 'index', 'detalle', 'Detalle', '', 20, 0, 'perfil', 0, NULL, 0, 0);
CALL sp_objeto_registrar('grupo', 'GrupoController', 3, 'index', 'guardar', 'Guardar', '', 30, 1, 'perfil', 0, NULL, 0, 0);
CALL sp_objeto_registrar('grupo', 'GrupoController', 3, 'index', 'eliminar', 'Eliminar', '', 40, 1, 'perfil', 0, NULL, 0, 0);
CALL sp_objeto_registrar('materia', 'MateriaController', 3, 'index', 'listar', 'Listar', '', 10, 0, 'perfil', 0, NULL, 0, 0);
CALL sp_objeto_registrar('materia', 'MateriaController', 3, 'index', 'guardar', 'Guardar', '', 20, 1, 'perfil', 0, NULL, 0, 0);
CALL sp_objeto_registrar('materia', 'MateriaController', 3, 'index', 'eliminar', 'Eliminar', '', 30, 1, 'perfil', 0, NULL, 0, 0);
CALL sp_objeto_registrar('padre', 'PadreController', 3, 'index', 'resumen', 'Resumen', '', 10, 0, 'perfil', 0, NULL, 0, 0);
CALL sp_objeto_registrar('padre', 'PadreController', 3, 'index', 'guardarCorreos', 'Guardar correos', '', 20, 1, 'perfil', 0, NULL, 0, 0);
CALL sp_objeto_registrar('perfilacceso', 'PerfilAccesoController', 3, 'index', 'listar', 'Listar', '', 10, 0, 'perfil', 0, NULL, 0, 0);
CALL sp_objeto_registrar('perfilacceso', 'PerfilAccesoController', 3, 'index', 'guardar', 'Guardar', '', 20, 1, 'perfil', 1, NULL, 0, 0);
CALL sp_objeto_registrar('perfilacceso', 'PerfilAccesoController', 3, 'index', 'eliminar', 'Eliminar', '', 30, 1, 'perfil', 1, NULL, 0, 0);
CALL sp_objeto_registrar('perfilacceso', 'PerfilAccesoController', 3, 'index', 'objetos', 'Objetos', '', 40, 1, 'perfil', 1, NULL, 0, 0);
CALL sp_objeto_registrar('recibo', 'ReciboController', 3, 'index', 'catalogos', 'Catalogos', '', 10, 0, 'perfil', 0, NULL, 0, 0);
CALL sp_objeto_registrar('recibo', 'ReciboController', 3, 'index', 'listar', 'Listar', '', 20, 0, 'perfil', 0, NULL, 0, 0);
CALL sp_objeto_registrar('recibo', 'ReciboController', 3, 'index', 'guardar', 'Guardar', '', 30, 1, 'perfil', 0, NULL, 0, 0);
CALL sp_objeto_registrar('recibo', 'ReciboController', 3, 'index', 'eliminar', 'Eliminar', '', 40, 1, 'perfil', 0, NULL, 0, 0);
CALL sp_objeto_registrar('reportecolumna', 'ReporteColumnaController', 3, 'index', 'listar', 'Listar', '', 10, 0, 'perfil', 0, NULL, 0, 0);
CALL sp_objeto_registrar('reportecolumna', 'ReporteColumnaController', 3, 'index', 'guardar', 'Guardar', '', 20, 1, 'perfil', 0, NULL, 0, 0);
CALL sp_objeto_registrar('reportecolumna', 'ReporteColumnaController', 3, 'index', 'eliminar', 'Eliminar', '', 30, 1, 'perfil', 0, NULL, 0, 0);
CALL sp_objeto_registrar('reporte', 'ReporteController', 3, '', 'grupos', 'Grupos', '', 10, 0, 'perfil', 0, NULL, 0, 0);
CALL sp_objeto_registrar('reporte', 'ReporteController', 3, 'index', 'contexto', 'Contexto', '', 20, 0, 'perfil', 0, NULL, 0, 0);
CALL sp_objeto_registrar('reporte', 'ReporteController', 3, 'index', 'semana', 'Semana', '', 30, 0, 'perfil', 0, NULL, 0, 0);
CALL sp_objeto_registrar('reporte', 'ReporteController', 3, 'index', 'clase', 'Clase', '', 40, 1, 'perfil', 0, NULL, 0, 0);
CALL sp_objeto_registrar('reporte', 'ReporteController', 3, 'index', 'guardarClase', 'Guardar clase', '', 50, 1, 'perfil', 0, NULL, 0, 0);
CALL sp_objeto_registrar('reporte', 'ReporteController', 3, 'index', 'eliminarClase', 'Eliminar clase', '', 60, 1, 'perfil', 0, NULL, 0, 0);
CALL sp_objeto_registrar('reporte', 'ReporteController', 3, 'index', 'vistaPrevia', 'Vista previa', '', 70, 0, 'perfil', 0, NULL, 0, 0);
CALL sp_objeto_registrar('reporte', 'ReporteController', 3, 'index', 'enviar', 'Enviar', '', 80, 1, 'perfil', 0, NULL, 0, 0);
CALL sp_objeto_registrar('tablas', 'TablasController', 3, 'index', 'listar', 'Listar', '', 10, 0, 'perfil', 1, NULL, 0, 0);
CALL sp_objeto_registrar('tablas', 'TablasController', 3, 'index', 'ver', 'Ver', '', 20, 0, 'perfil', 1, NULL, 0, 0);
CALL sp_objeto_registrar('tipodescuento', 'TipoDescuentoController', 3, 'index', 'listar', 'Listar', '', 10, 0, 'perfil', 0, NULL, 0, 0);
CALL sp_objeto_registrar('tipodescuento', 'TipoDescuentoController', 3, 'index', 'guardar', 'Guardar', '', 20, 1, 'perfil', 0, NULL, 0, 0);
CALL sp_objeto_registrar('tipodescuento', 'TipoDescuentoController', 3, 'index', 'eliminar', 'Eliminar', '', 30, 1, 'perfil', 0, NULL, 0, 0);
CALL sp_objeto_registrar('tiporecibo', 'TipoReciboController', 3, 'index', 'listar', 'Listar', '', 10, 0, 'perfil', 0, NULL, 0, 0);
CALL sp_objeto_registrar('tiporecibo', 'TipoReciboController', 3, 'index', 'guardar', 'Guardar', '', 20, 1, 'perfil', 0, NULL, 0, 0);
CALL sp_objeto_registrar('tiporecibo', 'TipoReciboController', 3, 'index', 'eliminar', 'Eliminar', '', 30, 1, 'perfil', 0, NULL, 0, 0);

-- Usos (una vista que llama métodos de otro lugar)
CALL sp_objeto_uso_registrar('alumno', 'index', 'grupo', 'listar', 0);
CALL sp_objeto_uso_registrar('docente', 'index', 'grupo', 'listar', 0);
CALL sp_objeto_uso_registrar('grupo', 'index', 'ciclo', 'listar', 0);
CALL sp_objeto_uso_registrar('grupo', 'index', 'materia', 'listar', 0);

-- Lo que ya no está en el código queda inactivo (no se borra: conserva sus asignaciones)
CALL sp_objeto_retirar('[":escolar",":pagos",":padre",":sistema","alumno:index","auth:entrar","calificacion:index","ciclo:index","colegiatura:index","contacto:index","cuenta:index","dashboard:index","desarrollador:index","docente:index","esquemapago:index","grupo:index","maestro:index","materia:index","padre:index","perfilacceso:index","recibo:index","recibo:imprimir","reportecolumna:index","reporte:index","tablas:index","tipodescuento:index","tiporecibo:index","alumno:listar","alumno:guardar","alumno:eliminar","alumno:enviarMatricula","auth:salir","calificacion:contexto","calificacion:listar","calificacion:guardar","ciclo:listar","ciclo:guardar","ciclo:activar","ciclo:eliminar","colegiatura:catalogos","colegiatura:listar","colegiatura:pagar","colegiatura:descuento","colegiatura:recargo","colegiatura:eliminar","contacto:enviar","cuenta:catalogos","cuenta:listar","cuenta:guardar","cuenta:perfiles","cuenta:eliminar","dashboard:resumen","docente:listar","docente:guardar","docente:eliminar","esquemapago:catalogos","esquemapago:listar","esquemapago:desglose","esquemapago:guardar","esquemapago:eliminar","esquemapago:aplicar","grupo:listar","grupo:detalle","grupo:guardar","grupo:eliminar","materia:listar","materia:guardar","materia:eliminar","padre:resumen","padre:guardarCorreos","perfilacceso:listar","perfilacceso:guardar","perfilacceso:eliminar","perfilacceso:objetos","recibo:catalogos","recibo:listar","recibo:guardar","recibo:eliminar","reportecolumna:listar","reportecolumna:guardar","reportecolumna:eliminar","reporte:grupos","reporte:contexto","reporte:semana","reporte:clase","reporte:guardarClase","reporte:eliminarClase","reporte:vistaPrevia","reporte:enviar","tablas:listar","tablas:ver","tipodescuento:listar","tipodescuento:guardar","tipodescuento:eliminar","tiporecibo:listar","tiporecibo:guardar","tiporecibo:eliminar"]', 'portal', 0);

-- Versión cargada (+ su renglón en BitacoraPermisos)
CALL sp_catalogo_version_registrar('e314a8660eb8c901d757d7904a478e1f590fc4f2bc0e19901b6107dc7142d1cf', 97, 'semilla', 0);
