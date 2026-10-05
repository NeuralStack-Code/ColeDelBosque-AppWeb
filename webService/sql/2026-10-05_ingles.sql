-- Migración: maestros de inglés por nivel.
--   · Cada grupo puede pertenecer a un nivel de inglés (básico, medio, avanzado).
--   · A cada maestro se le marcan los niveles de inglés que imparte; con eso captura
--     calificaciones y reporte semanal de Inglés en todos los grupos de esos niveles.
--   · "Inglés" es cualquier materia cuyo nombre empiece con "Ingl" (Inglés, INGLES, Inglés 1…).
-- Idempotente. Correr ANTES de subir el código. Fecha: 2026-10-05

ALTER TABLE grupo
    ADD COLUMN IF NOT EXISTS nivel_ingles VARCHAR(10) NULL;      -- basico | medio | avanzado

ALTER TABLE cuenta
    ADD COLUMN IF NOT EXISTS niveles_ingles VARCHAR(30) NULL;    -- lista separada por comas; NULL = no da inglés
