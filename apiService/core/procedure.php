<?php
/**
 * Llamado a stored procedures. Es el ÚNICO punto donde el business toca la BD:
 * el SQL vive en los procedures (webService/sql/sp/*.sql), el business solo hace sp().
 * Funciones, sin clase base (estándar MVC). Se carga desde core/autoload.php.
 *
 * Convención de los procedures:
 *   - Nombre sp_<modulo>_<accion> (sp_cliente_listar). Un solo resultset por procedure.
 *   - Errores de negocio: SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = '<codigo>:<mensaje>'
 *     (ej. 'fk:Tiene proyectos asociados'). spCodigo() extrae '<codigo>' para el business.
 *   - Al comparar un parámetro de TEXTO contra una columna, fijar la intercalación:
 *     WHERE Correo = pCorreo COLLATE utf8mb4_unicode_ci. La base mezcla unicode_ci y
 *     uca1400_ai_ci; sin COLLATE el motor responde "Illegal mix of collations".
 *   - Las transacciones viven DENTRO del procedure (START TRANSACTION … COMMIT, con
 *     EXIT HANDLER FOR SQLEXCEPTION que hace ROLLBACK + RESIGNAL). El business no abre
 *     transacciones propias: sp() hace rollback de la conexión cuando un procedure falla.
 */

/** Ejecuta CALL nombre(?, ?, ...) con parámetros preparados y regresa las filas (o [] si no hay resultset). */
function sp(mysqli $db, string $nombre, array $params = []): array
{
    if (!preg_match('/^sp_[a-z0-9_]+$/', $nombre)) {
        throw new InvalidArgumentException("Procedure inválido: $nombre");
    }
    $stmt = mysqli_prepare($db, 'CALL ' . $nombre . '(' . implode(', ', array_fill(0, count($params), '?')) . ')');
    try {
        if ($params) {
            $tipos = '';
            foreach ($params as $v) $tipos .= is_int($v) || is_bool($v) ? 'i' : (is_float($v) ? 'd' : 's');
            $valores = array_map(fn($v) => is_bool($v) ? (int) $v : $v, array_values($params));
            mysqli_stmt_bind_param($stmt, $tipos, ...$valores);
        }
        mysqli_stmt_execute($stmt);
        $res   = mysqli_stmt_get_result($stmt);
        $filas = $res ? mysqli_fetch_all($res, MYSQLI_ASSOC) : [];
        // Un CALL siempre deja un resultado de estado extra: hay que vaciarlo o la conexión queda "out of sync".
        while (mysqli_stmt_more_results($stmt)) mysqli_stmt_next_result($stmt);
        return $filas;
    } catch (mysqli_sql_exception $e) {
        $error = $e;
    } finally {
        mysqli_stmt_close($stmt);
    }

    // Solo se llega aquí si el procedure falló. Si abrió una transacción, puede haber abortado SIN pasar por
    // su handler: un "Data truncated" en modo estricto llega con SQLSTATE 01000 (clase advertencia) y
    // "EXIT HANDLER FOR SQLEXCEPTION" no lo atrapa. Sin este rollback la transacción quedaría abierta y la
    // siguiente operación de la conexión la confirmaría a medias. Sin transacción abierta no hace nada.
    try { mysqli_rollback($db); } catch (Throwable $x) { /* la conexión ya no sirve: el error original manda */ }
    throw $error;
}

/** Primera fila del procedure, o null si no regresó nada. */
function spFila(mysqli $db, string $nombre, array $params = []): ?array
{
    return sp($db, $nombre, $params)[0] ?? null;
}

/**
 * Si la excepción viene de un SIGNAL de negocio ('45000' con 'codigo:mensaje'), regresa el código.
 * Cualquier otro error de BD regresa null: el business lo vuelve a lanzar y el router responde 500.
 */
function spCodigo(Throwable $e): ?string
{
    if (!$e instanceof mysqli_sql_exception || $e->getSqlState() !== '45000') return null;
    return preg_match('/^([a-z_]+):/', $e->getMessage(), $m) ? $m[1] : null;
}

/** Texto para el usuario de un SIGNAL de negocio ('codigo:mensaje' → 'mensaje'), o null si no lo es. */
function spMensaje(Throwable $e): ?string
{
    return spCodigo($e) === null ? null : trim(substr($e->getMessage(), strpos($e->getMessage(), ':') + 1));
}

// Clases del core que no se cargan siempre: se cargan la primera vez que se usan. Van aquí porque el autoload.php es
// del proyecto (update.php no lo toca) y este archivo sí llega con cada actualización: así un proyecto ya instalado
// tiene Crypto al actualizar, sin editar su autoload.
spl_autoload_register(static function (string $clase): void {
    if (in_array($clase, ['Crypto'], true)) require __DIR__ . '/' . $clase . '.php';
});
