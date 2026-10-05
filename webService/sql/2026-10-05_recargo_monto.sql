-- Migración: el recargo por atraso deja de ser un PORCENTAJE y pasa a ser una
-- CANTIDAD EXACTA en pesos por cada mes vencido.
-- Idempotente. Correr DESPUÉS de 2026-10-05_esquema_pago.sql y ANTES de subir el código.
-- Fecha: 2026-10-05

ALTER TABLE colegiatura
    ADD COLUMN IF NOT EXISTS recargo_monto DECIMAL(10,2) NOT NULL DEFAULT 0;

ALTER TABLE esquema_pago
    ADD COLUMN IF NOT EXISTS recargo_monto DECIMAL(10,2) NOT NULL DEFAULT 0;

-- Los pagos que ya tenían porcentaje conservan EXACTAMENTE el mismo recargo:
-- antes era monto × % × meses vencidos; ahora es (monto × %) × meses vencidos.
UPDATE colegiatura
   SET recargo_monto = ROUND(monto * recargo_pct / 100, 2)
 WHERE recargo_pct > 0 AND recargo_monto = 0;

UPDATE esquema_pago
   SET recargo_monto = ROUND(monto_colegiatura * recargo_pct / 100, 2)
 WHERE recargo_pct > 0 AND recargo_monto = 0;

-- recargo_pct se queda en ambas tablas solo como histórico: el sistema ya no lo lee.
