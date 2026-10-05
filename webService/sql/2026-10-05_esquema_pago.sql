-- Migración: esquemas de pago por CICLO ESCOLAR (ya no por año calendario).
-- Un esquema guarda los montos y qué meses del ciclo se cobran; luego se aplica
-- a todos los alumnos del ciclo, a un grupo o a un alumno.
-- Idempotente. Fecha: 2026-10-05

CREATE TABLE IF NOT EXISTS esquema_pago (
    id_esquema        INT AUTO_INCREMENT PRIMARY KEY,
    ciclo_id          INT NOT NULL,
    nombre            VARCHAR(80) NOT NULL,
    monto_inscripcion DECIMAL(10,2) NOT NULL DEFAULT 0,
    monto_colegiatura DECIMAL(10,2) NOT NULL,
    primer_mes        DATE NOT NULL,              -- día 1 del mes de la primera mensualidad
    num_meses         INT NOT NULL,
    dia_vencimiento   INT NOT NULL DEFAULT 10,
    recargo_pct       DECIMAL(5,2) NOT NULL DEFAULT 0,
    KEY idx_esquema_ciclo (ciclo_id),
    CONSTRAINT fk_esquema_ciclo FOREIGN KEY (ciclo_id) REFERENCES ciclo(id_ciclo) ON DELETE CASCADE
);
