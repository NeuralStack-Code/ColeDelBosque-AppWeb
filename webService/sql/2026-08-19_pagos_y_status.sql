-- ============================================================================
-- Migración consolidada (2026-08-19): inscripción + descuentos + abonos + status_id
-- Idempotente y re-ejecutable. YA APLICADA en la BD; se conserva como registro
-- y para reproducir en otro entorno (p. ej. producción).
-- Sustituye a los archivos sueltos A/B de esa fecha.
-- ============================================================================

-- ---- 1) Catálogo de tipos de descuento -------------------------------------
CREATE TABLE IF NOT EXISTS tipo_descuento (
    id_descuento INT AUTO_INCREMENT PRIMARY KEY,
    nombre       VARCHAR(80)  NOT NULL,
    porcentaje   DECIMAL(5,2) NOT NULL DEFAULT 0,
    aplica_a     ENUM('colegiatura','inscripcion') NOT NULL DEFAULT 'colegiatura',
    status_id    INT NULL
);

-- ---- 2) Catálogo de estatus (fuente de verdad de estados) ------------------
CREATE TABLE IF NOT EXISTS status (
    id_status INT AUTO_INCREMENT PRIMARY KEY,
    ambito    VARCHAR(30) NOT NULL DEFAULT 'pago',
    clave     VARCHAR(30) NOT NULL,
    nombre    VARCHAR(40) NOT NULL,
    color     VARCHAR(20) NOT NULL DEFAULT '#888',
    orden     INT NOT NULL DEFAULT 0,
    UNIQUE KEY uq_status (ambito, clave)
);

INSERT IGNORE INTO status (ambito, clave, nombre, color, orden) VALUES
    ('pago',       'pendiente',     'Pendiente',     '#b45309', 1),
    ('pago',       'parcial',       'Pago parcial',  '#1d4ed8', 2),
    ('pago',       'pagado',        'Pagado',        '#15803d', 3),
    ('ciclo',      'activo',        'Activo',        '#15803d', 1),
    ('ciclo',      'cerrado',       'Cerrado',       '#6b7280', 2),
    ('descuento',  'activo',        'Activo',        '#15803d', 1),
    ('descuento',  'inactivo',      'Inactivo',      '#6b7280', 2),
    ('alumno',     'activo',        'Activo',        '#15803d', 1),
    ('alumno',     'baja',          'Baja',          '#b91c1c', 2),
    ('alumno',     'egresado',      'Egresado',      '#1d4ed8', 3),
    ('aprobacion', 'aprobado',      'Aprobado',      '#15803d', 1),
    ('aprobacion', 'reprobado',     'Reprobado',     '#b91c1c', 2),
    ('aprobacion', 'recursamiento', 'Recursamiento', '#b45309', 3),
    ('captura',    'pendiente',     'Pendiente',     '#b45309', 1),
    ('captura',    'capturada',     'Capturada',     '#1d4ed8', 2),
    ('captura',    'cerrada',       'Cerrada',       '#15803d', 3);

-- Semilla del descuento 5% (ligado a status descuento/activo)
INSERT INTO tipo_descuento (nombre, porcentaje, aplica_a, status_id)
SELECT 'Descuento estándar 5%', 5.00, 'colegiatura',
       (SELECT id_status FROM status WHERE ambito = 'descuento' AND clave = 'activo')
WHERE NOT EXISTS (SELECT 1 FROM tipo_descuento WHERE nombre = 'Descuento estándar 5%');

-- Tipo de recibo para inscripciones
INSERT INTO tipo_recibo (nombre, naturaleza)
SELECT 'Inscripción', 'ingreso'
WHERE NOT EXISTS (SELECT 1 FROM tipo_recibo WHERE nombre = 'Inscripción');

-- ---- 3) Columnas nuevas -----------------------------------------------------
ALTER TABLE colegiatura
    ADD COLUMN IF NOT EXISTS tipo ENUM('colegiatura','inscripcion') NOT NULL DEFAULT 'colegiatura' AFTER mes,
    ADD COLUMN IF NOT EXISTS ciclo_id INT NULL AFTER cuenta_id,
    ADD COLUMN IF NOT EXISTS tipo_descuento_id INT NULL AFTER descuento,
    ADD COLUMN IF NOT EXISTS status_id INT NULL AFTER tipo;
ALTER TABLE recibo         ADD COLUMN IF NOT EXISTS colegiatura_id INT NULL AFTER cuenta_id;
ALTER TABLE ciclo          ADD COLUMN IF NOT EXISTS status_id INT NULL;
ALTER TABLE cuenta         ADD COLUMN IF NOT EXISTS status_id INT NULL;
ALTER TABLE calificacion   ADD COLUMN IF NOT EXISTS status_aprobacion_id INT NULL;
ALTER TABLE calificacion   ADD COLUMN IF NOT EXISTS status_captura_id INT NULL;

-- ---- 4) Backfills -----------------------------------------------------------
-- colegiatura.ciclo_id desde el grupo del alumno (o cualquier ciclo si no tiene)
UPDATE colegiatura col
    JOIN cuenta c     ON c.id_cuenta = col.cuenta_id
    LEFT JOIN grupo g ON g.id_grupo = c.grupo_id
    SET col.ciclo_id = COALESCE(g.ciclo_id, (SELECT id_ciclo FROM ciclo LIMIT 1))
    WHERE col.ciclo_id IS NULL;

-- recibo.colegiatura_id de pagos ya registrados
UPDATE recibo r JOIN colegiatura col ON col.recibo_id = r.id_recibo
    SET r.colegiatura_id = col.id_pago WHERE r.colegiatura_id IS NULL;

-- Estatus por defecto de cuentas y de calificaciones existentes
UPDATE cuenta c JOIN status s ON s.ambito = 'alumno' AND s.clave = 'activo'
    SET c.status_id = s.id_status WHERE c.status_id IS NULL;
UPDATE calificacion ca JOIN status s ON s.ambito = 'captura' AND s.clave =
        CASE WHEN ca.calif_final IS NOT NULL THEN 'cerrada'
             WHEN (ca.p1 IS NOT NULL OR ca.p2 IS NOT NULL OR ca.p3 IS NOT NULL) THEN 'capturada'
             ELSE 'pendiente' END
    SET ca.status_captura_id = s.id_status WHERE ca.status_captura_id IS NULL;
UPDATE calificacion ca JOIN status s ON s.ambito = 'aprobacion' AND s.clave = IF(ca.calif_final >= 6, 'aprobado', 'reprobado')
    SET ca.status_aprobacion_id = s.id_status WHERE ca.status_aprobacion_id IS NULL AND ca.calif_final IS NOT NULL;

-- Backfills desde columnas VIEJAS: solo si aún existen (re-ejecutable tras el DROP)
SET @c := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'colegiatura' AND COLUMN_NAME = 'estatus');
SET @s := IF(@c > 0, "UPDATE colegiatura col JOIN status s ON s.ambito='pago' AND s.clave=COALESCE(NULLIF(col.estatus,''),'pendiente') SET col.status_id=s.id_status WHERE col.status_id IS NULL", "DO 0");
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;

SET @c := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'ciclo' AND COLUMN_NAME = 'activo');
SET @s := IF(@c > 0, "UPDATE ciclo c JOIN status s ON s.ambito='ciclo' AND s.clave=IF(c.activo=1,'activo','cerrado') SET c.status_id=s.id_status WHERE c.status_id IS NULL", "DO 0");
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;

SET @c := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'tipo_descuento' AND COLUMN_NAME = 'activo');
SET @s := IF(@c > 0, "UPDATE tipo_descuento t JOIN status s ON s.ambito='descuento' AND s.clave=IF(t.activo=1,'activo','inactivo') SET t.status_id=s.id_status WHERE t.status_id IS NULL", "DO 0");
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;

-- Cualquier colegiatura sin status queda como pendiente
UPDATE colegiatura SET status_id = (SELECT id_status FROM status WHERE ambito = 'pago' AND clave = 'pendiente')
    WHERE status_id IS NULL;

-- ---- 5) Eliminar columnas viejas (ya migradas a status_id) -----------------
ALTER TABLE colegiatura    DROP COLUMN IF EXISTS estatus;
ALTER TABLE ciclo          DROP COLUMN IF EXISTS activo;
ALTER TABLE tipo_descuento DROP COLUMN IF EXISTS activo;
