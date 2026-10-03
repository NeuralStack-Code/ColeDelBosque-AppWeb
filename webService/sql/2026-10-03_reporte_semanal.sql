-- Migración: reporte semanal a papás (clase por día + incidencias por alumno).
-- Idempotente. Correr ANTES de subir el código (alumnos/maestros leen cuenta.correo_tutor).
-- Fecha: 2026-10-03

-- Correos de los tutores (se guardan ENCRIPTADOS con crypto.php, como la matrícula)
ALTER TABLE cuenta
    ADD COLUMN IF NOT EXISTS correo_tutor  VARCHAR(255) NULL,
    ADD COLUMN IF NOT EXISTS correo_tutor2 VARCHAR(255) NULL;

-- Columnas del reporte, configurables por el administrador.
--   casilla → la maestra solo marca; en el correo sale `etiqueta` (ej. "Falta")
--   texto   → la maestra escribe una nota corta
CREATE TABLE IF NOT EXISTS reporte_columna (
    id_columna INT AUTO_INCREMENT PRIMARY KEY,
    nombre     VARCHAR(60) NOT NULL,
    tipo       ENUM('casilla','texto') NOT NULL DEFAULT 'casilla',
    etiqueta   VARCHAR(60) NULL,
    orden      INT NOT NULL DEFAULT 0,
    activo     TINYINT(1) NOT NULL DEFAULT 1
);

-- Columnas iniciales (solo si el catálogo está vacío)
INSERT INTO reporte_columna (nombre, tipo, etiqueta, orden)
SELECT t.nombre, t.tipo, t.etiqueta, t.orden FROM (
    SELECT 'Material / Entregas' AS nombre, 'casilla' AS tipo, 'No cumplió' AS etiqueta, 1 AS orden
    UNION ALL SELECT 'Ausencias',  'casilla', 'Falta',      2
    UNION ALL SELECT 'Tareas',     'texto',   NULL,         3
    UNION ALL SELECT 'Uniformes',  'casilla', 'Incompleto', 4
    UNION ALL SELECT 'Conducta',   'texto',   NULL,         5
    UNION ALL SELECT 'Desempeño',  'texto',   NULL,         6
) t
WHERE NOT EXISTS (SELECT 1 FROM reporte_columna);

-- Una clase = (grupo, materia, día). El tema se captura una vez para todo el grupo.
CREATE TABLE IF NOT EXISTS reporte_clase (
    id_clase   INT AUTO_INCREMENT PRIMARY KEY,
    grupo_id   INT NOT NULL,
    materia_id INT NOT NULL,
    ciclo_id   INT NOT NULL,
    fecha      DATE NOT NULL,
    tema       VARCHAR(255) NOT NULL DEFAULT '',
    UNIQUE KEY uq_clase (grupo_id, materia_id, fecha),
    KEY idx_clase_fecha (grupo_id, fecha),
    CONSTRAINT fk_rc_grupo   FOREIGN KEY (grupo_id)   REFERENCES grupo(id_grupo)     ON DELETE CASCADE,
    CONSTRAINT fk_rc_materia FOREIGN KEY (materia_id) REFERENCES materia(id_materia) ON DELETE CASCADE
);

-- Incidencias por alumno: solo se guarda a quien tuvo algo (casilla marcada o nota).
CREATE TABLE IF NOT EXISTS reporte_marca (
    id_marca   INT AUTO_INCREMENT PRIMARY KEY,
    clase_id   INT NOT NULL,
    cuenta_id  INT NOT NULL,
    columna_id INT NOT NULL,
    marcado    TINYINT(1) NOT NULL DEFAULT 0,
    nota       VARCHAR(255) NULL,
    UNIQUE KEY uq_marca (clase_id, cuenta_id, columna_id),
    CONSTRAINT fk_rm_clase   FOREIGN KEY (clase_id)   REFERENCES reporte_clase(id_clase)     ON DELETE CASCADE,
    CONSTRAINT fk_rm_cuenta  FOREIGN KEY (cuenta_id)  REFERENCES cuenta(id_cuenta)           ON DELETE CASCADE,
    CONSTRAINT fk_rm_columna FOREIGN KEY (columna_id) REFERENCES reporte_columna(id_columna) ON DELETE CASCADE
);

-- Bitácora de envíos: un renglón por (alumno, semana); semana = lunes.
CREATE TABLE IF NOT EXISTS reporte_envio (
    id_envio      INT AUTO_INCREMENT PRIMARY KEY,
    cuenta_id     INT NOT NULL,
    semana        DATE NOT NULL,
    enviado_en    DATETIME NOT NULL,
    enviado_por   INT NOT NULL,
    destinatarios INT NOT NULL DEFAULT 0,
    UNIQUE KEY uq_envio (cuenta_id, semana),
    CONSTRAINT fk_re_cuenta FOREIGN KEY (cuenta_id) REFERENCES cuenta(id_cuenta) ON DELETE CASCADE
);
