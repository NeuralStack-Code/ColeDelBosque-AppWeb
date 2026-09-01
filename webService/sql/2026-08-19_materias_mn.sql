-- Migración: materias muchos-a-muchos con grupos (tabla puente grupo_materia).
-- Idempotente. materia deja de tener grupo_id; se vuelve catálogo.
-- Fecha: 2026-08-19

-- Tabla puente (con FK en cascada: borrar grupo o materia limpia sus vínculos)
CREATE TABLE IF NOT EXISTS grupo_materia (
    id_grupo   INT NOT NULL,
    id_materia INT NOT NULL,
    PRIMARY KEY (id_grupo, id_materia),
    CONSTRAINT fk_gm_grupo   FOREIGN KEY (id_grupo)   REFERENCES grupo(id_grupo)     ON DELETE CASCADE,
    CONSTRAINT fk_gm_materia FOREIGN KEY (id_materia) REFERENCES materia(id_materia) ON DELETE CASCADE
);

-- Migrar las asignaciones actuales (materia.grupo_id -> puente)
SET @tieneCol := (SELECT COUNT(*) FROM information_schema.COLUMNS
                  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'materia' AND COLUMN_NAME = 'grupo_id');
SET @s := IF(@tieneCol > 0,
    "INSERT IGNORE INTO grupo_materia (id_grupo, id_materia) SELECT grupo_id, id_materia FROM materia WHERE grupo_id IS NOT NULL AND grupo_id > 0",
    "DO 0");
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;

-- Quitar la FK vieja y la columna grupo_id de materia
SET @fk := (SELECT CONSTRAINT_NAME FROM information_schema.KEY_COLUMN_USAGE
            WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'materia'
              AND COLUMN_NAME = 'grupo_id' AND REFERENCED_TABLE_NAME = 'grupo' LIMIT 1);
SET @s := IF(@fk IS NOT NULL, CONCAT('ALTER TABLE materia DROP FOREIGN KEY ', @fk), "DO 0");
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;

ALTER TABLE materia DROP COLUMN IF EXISTS grupo_id;
