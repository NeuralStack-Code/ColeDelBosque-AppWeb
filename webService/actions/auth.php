<?php
/**
 * Action: auth  →  /api/auth?action=login|logout
 * Autenticación por matrícula contra la tabla `cuenta` (+ `usuario`).
 *
 * La matrícula se guarda ENCRIPTADA (AES-256-CBC, ver core/crypto.php),
 * NO hasheada. Se encripta el input y se compara.
 *
 * Niveles (cuenta.permiso_id):  1 = admin · 2 = maestro · 3 = padre
 *
 * Depende de: $conexion (conexionBDD), response() y BASE_URL (router).
 */
require_once __DIR__ . '/../../apiService/core/conexionBDD.php';
require_once __DIR__ . '/../../apiService/core/crypto.php';

$action = $_GET['action'] ?? '';

switch ($action) {

    case 'login':
        $matricula = trim($_POST['matricula'] ?? '');
        if ($matricula === '') {
            response(400, false, 'Ingresa tu matrícula.');
        }

        // La matrícula se almacena encriptada (compatibilidad con la BDD actual)
        $matriculaEnc = encrypt($matricula);

        $sql = 'SELECT c.permiso_id, c.usuario_id, u.nombre, u.paterno
                FROM cuenta c
                JOIN usuario u ON u.id_usuario = c.usuario_id
                WHERE c.matricula = ?
                LIMIT 1';
        $stmt = mysqli_prepare($conexion, $sql);
        mysqli_stmt_bind_param($stmt, 's', $matriculaEnc);
        mysqli_stmt_execute($stmt);
        $row = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
        mysqli_stmt_close($stmt);

        if (!$row) {
            response(401, false, 'ID incorrecto. Verifica tu matrícula.');
        }

        $permiso = (int) $row['permiso_id'];
        $_SESSION['usuario'] = trim($row['nombre'] . ' ' . $row['paterno']);
        $_SESSION['permiso'] = $permiso;
        $_SESSION['id']      = (int) $row['usuario_id'];

        // Destino según nivel
        $destinos = [
            1 => BASE_URL . '/administrador',
            2 => BASE_URL . '/maestro',
            3 => BASE_URL . '/padre',
            4 => BASE_URL . '/desarrollador',
        ];
        $redirect = $destinos[$permiso] ?? BASE_URL . '/';

        response(200, true, 'Bienvenido, ' . $_SESSION['usuario'] . '.', ['redirect' => $redirect]);
        break;

    case 'logout':
        $_SESSION = [];
        session_destroy();
        response(200, true, 'Sesión cerrada.', ['redirect' => BASE_URL . '/inicio-sesion']);
        break;

    default:
        response(400, false, "Acción de auth no válida: '$action'.");
}
