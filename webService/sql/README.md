# Stored procedures

Los business no llevan SQL: cada operación arma su comando al procedure
(`$this->db->command('sp_<modulo>_<accion>')` + `addParameter('Campo', valor)` + `execute()`).
Todo el SQL vive aquí, un archivo por módulo, cada uno idempotente (`DROP PROCEDURE IF EXISTS` + `CREATE`).

## Instalación

1. `sp/sp_sistema.sql` **primero**: sin él ningún business puede ejecutar sus procedures.
2. Después, los demás archivos de `sp/`, en cualquier orden.

En phpMyAdmin: selecciona tu base → pestaña *SQL* → pega el archivo y ejecuta (respeta `DELIMITER`).

## Convenciones

- Nombre `sp_<modulo>_<accion>` (`sp_cliente_listar`). Un solo resultset por procedure. `SQL SECURITY INVOKER`.
- Parámetros con `p` + el nombre del campo de la entidad (`pIdCliente` ← `IdCliente`): el business los pasa por
  nombre y `Command` los acomoda en el orden del procedure.
- Las columnas que regresa se llaman igual que la propiedad del model que las carga; si el alias no coincide,
  ese campo llega vacío.
- Altas terminan con `SELECT LAST_INSERT_ID() AS Id;` y bajas con `SELECT ROW_COUNT() AS Afectadas;`
  (el controller los lee con `$result->id()` y `$result->affected()`).
- Errores de negocio: `SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'codigo:mensaje'`. Códigos con estatus HTTP:
  `dup`, `fk`, `en_uso` → 409 · `notfound` → 404 · `invalido` → 422. El mensaje llega tal cual al usuario.
- Al comparar un parámetro de texto contra una columna, fija la intercalación (`COLLATE`) si tu base mezcla varias.

## Este folder no se publica

El `.htaccess` del proyecto niega `webService/sql/` y cualquier `.sql` o `.md`. Si despliegas con git, considera
además dejar esta carpeta fuera del repositorio que se sube al servidor.
