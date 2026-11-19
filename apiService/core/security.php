<?php
/**
 * Seguridad común del proyecto: la cookie de sesión, las cabeceras, la defensa CSRF y las contraseñas.
 * Funciones, sin clase (como procedure.php). Es del proyecto: adáptala (por ejemplo, agrega tu Content-Security-Policy).
 *
 *   startSecureSession();               // en lugar de session_start()
 *   sendSecurityHeaders();              // en cada respuesta (el index.php raíz y el router)
 *   requireSameOrigin();                // lo llama el router antes de cualquier controller
 *   $hash = passwordMake($password);    // para guardar · passwordCheck($password, $hash) para revisar
 */

/** Sesión con la cookie endurecida: no la lee JavaScript, solo viaja por HTTPS (si lo hay) y no sale a otros sitios. */
function startSecureSession(): void
{
    if (session_status() !== PHP_SESSION_NONE) return;
    ini_set('session.use_strict_mode', '1');
    ini_set('session.use_only_cookies', '1');
    session_set_cookie_params(['lifetime' => 0, 'path' => '/', 'secure' => isHttps(), 'httponly' => true, 'samesite' => 'Lax']);
    session_start();
}

/** Cabeceras de seguridad (por si el servidor no tiene mod_headers o es el de desarrollo). HSTS solo por HTTPS. */
function sendSecurityHeaders(): void
{
    if (headers_sent()) return;
    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: SAMEORIGIN');
    header('Referrer-Policy: strict-origin-when-cross-origin');
    header('Permissions-Policy: camera=(), microphone=(), geolocation=()');
    if (isHttps()) header('Strict-Transport-Security: max-age=31536000; includeSubDomains');
}

function isHttps(): bool
{
    return (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || strtolower($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https';
}

/**
 * Defensa CSRF: lo que escribe (POST, PUT, PATCH, DELETE) solo se acepta si viene de este mismo sitio. El navegador
 * manda Origin (o al menos Referer); si apunta a otro host, 403. Junto con SameSite=Lax en la cookie cubre formularios
 * y fetch sin poner un token en cada vista. Los clientes sin navegador (móvil, escritorio) no mandan ninguno y pasan.
 */
function requireSameOrigin(): void
{
    if (in_array($_SERVER['REQUEST_METHOD'] ?? 'GET', ['GET', 'HEAD', 'OPTIONS'], true)) return;
    $host   = strtolower((string) ($_SERVER['HTTP_HOST'] ?? ''));
    $source = $_SERVER['HTTP_ORIGIN'] ?? $_SERVER['HTTP_REFERER'] ?? '';
    if ($source === '') return;
    if ($source === 'null' || !sameHost($source, $host)) response(403, false, 'Petición de otro sitio.');
}

/** ¿La URL $source es de este mismo host (con su puerto)? */
function sameHost(string $source, string $host): bool
{
    $parts = parse_url($source);
    if (!is_array($parts) || empty($parts['host'])) return false;
    $origin = strtolower($parts['host']) . (isset($parts['port']) ? ':' . $parts['port'] : '');
    return $origin === $host;
}

/** Contraseña para guardar (bcrypt). */
function passwordMake(string $plain): string
{
    return password_hash($plain, PASSWORD_DEFAULT);
}

/** ¿Coincide? Y, si el hash quedó viejo (otro costo o algoritmo), el nuevo para guardarlo: ['ok' => bool, 'rehash' => ?string]. */
function passwordCheck(string $plain, string $stored): array
{
    $ok = $stored !== '' && password_verify($plain, $stored);
    return ['ok' => $ok, 'rehash' => $ok && password_needs_rehash($stored, PASSWORD_DEFAULT) ? passwordMake($plain) : null];
}
