-- ============================================================
-- Agrega "tipo de pago" a los recibos.
-- Corre esto en phpMyAdmin (ya tienes la tabla `recibo` creada).
-- Si tu servidor es MariaDB puedes usar IF NOT EXISTS; si es MySQL
-- y ya existe la columna, ignora el error de "duplicate column".
-- ============================================================

ALTER TABLE recibo
    ADD COLUMN tipo_pago VARCHAR(30) NULL AFTER monto;
