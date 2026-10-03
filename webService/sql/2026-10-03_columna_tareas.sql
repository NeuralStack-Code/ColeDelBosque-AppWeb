-- Migración: la columna "Retardos" del reporte semanal pasa a ser "Tareas",
-- capturada como observación (texto) en vez de casilla.
-- Idempotente: solo toca la columna si todavía se llama Retardos.
-- Fecha: 2026-10-03

UPDATE reporte_columna
   SET nombre = 'Tareas', tipo = 'texto', etiqueta = NULL
 WHERE nombre = 'Retardos';
