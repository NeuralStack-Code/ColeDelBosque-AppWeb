<?php
/**
 * Catálogo de estatus (tabla `status`). Resuelve clave <-> id.
 * Uso: statusId($conexion, 'pago', 'pagado')  →  int id_status
 */
if (!function_exists('statusId')) {
    function statusId(mysqli $conexion, string $ambito, string $clave): ?int {
        static $cache = [];
        $key = $ambito . '|' . $clave;
        if (array_key_exists($key, $cache)) return $cache[$key];
        $stmt = mysqli_prepare($conexion, 'SELECT id_status FROM status WHERE ambito = ? AND clave = ? LIMIT 1');
        mysqli_stmt_bind_param($stmt, 'ss', $ambito, $clave);
        mysqli_stmt_execute($stmt);
        $row = mysqli_fetch_row(mysqli_stmt_get_result($stmt));
        mysqli_stmt_close($stmt);
        return $cache[$key] = $row ? (int) $row[0] : null;
    }
}
