<?php
require_once dirname(__DIR__, 3) . '/apiService/core/crypto.php';

/**
 * Autenticación por matrícula. La matrícula se guarda ENCRIPTADA (AES, crypto.php),
 * no hasheada: se encripta el input y se compara.
 */
class AuthBusiness
{
    private mysqli $db;

    public function __construct(mysqli $db)
    {
        $this->db = $db;
    }

    /** Busca la cuenta por matrícula; regresa la fila (permiso/usuario) o null. */
    public function autenticar(string $matricula): ?array
    {
        $enc = encrypt($matricula);
        $stmt = mysqli_prepare($this->db,
            'SELECT c.permiso_id, c.usuario_id, u.nombre, u.paterno
             FROM cuenta c
             JOIN usuario u ON u.id_usuario = c.usuario_id
             WHERE c.matricula = ?
             LIMIT 1');
        mysqli_stmt_bind_param($stmt, 's', $enc);
        mysqli_stmt_execute($stmt);
        $row = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
        mysqli_stmt_close($stmt);
        return $row ?: null;
    }
}
