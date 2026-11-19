<?php
/**
 * Cifrado de datos que alguien necesita volver a ver (RFC, teléfonos, tokens de terceros, credenciales de canales).
 * NO es para contraseñas (password_hash) ni para API keys (SHA-256): esas no se descifran nunca.
 *
 *     $guardado = Crypto::encrypt($rfc);            // "v2:…" → a la columna
 *     $rfc      = Crypto::decrypt($guardado);       // null si no es v2, si lo alteraron o si la llave no es la suya
 *     $indice   = Crypto::blindIndex($rfc);         // columna al lado del cifrado para BUSCAR por el dato
 *     $token    = Crypto::randomToken();            // reseteo de contraseña, invitaciones (64 caracteres hex)
 *
 * Formato v2: "v2:" + base64(nonce 12 B | tag 16 B | cifrado), AES-256-GCM. El nonce es aleatorio en cada llamada
 * (el mismo texto da cifrados distintos) y el tag detecta cualquier byte cambiado. El prefijo deja lugar para rotar
 * algoritmo o llave sin adivinar formatos.
 *
 * Llaves en el .env del proyecto, generadas con:
 *     php -r "echo 'CRYPTO_KEY='.base64_encode(random_bytes(32)).PHP_EOL.'CRYPTO_INDEX_KEY='.base64_encode(random_bytes(32)).PHP_EOL;"
 * Sin llave (o con una inválida) lanza LogicException: nunca cifra con un valor de respaldo.
 */
final class Crypto
{
    private const PREFIX = 'v2:';
    private const CIPHER = 'aes-256-gcm';
    private const NONCE  = 12;
    private const TAG    = 16;

    /** Cifra con AES-256-GCM. Cada llamada da un resultado distinto aunque el texto sea el mismo. */
    public static function encrypt(string $plain): string
    {
        $nonce  = random_bytes(self::NONCE);
        $tag    = '';
        $cipher = openssl_encrypt($plain, self::CIPHER, self::key('CRYPTO_KEY'), OPENSSL_RAW_DATA, $nonce, $tag, self::PREFIX, self::TAG);
        if ($cipher === false) throw new RuntimeException('Crypto: no se pudo cifrar (' . (openssl_error_string() ?: 'openssl') . ').');
        return self::PREFIX . base64_encode($nonce . $tag . $cipher);
    }

    /** El texto original, o null si no tiene formato v2, si se alteró algún byte o si se cifró con otra llave. */
    public static function decrypt(string $cipher): ?string
    {
        $key = self::key('CRYPTO_KEY');                       // sin llave falla aquí, no con un null silencioso
        if (!str_starts_with($cipher, self::PREFIX)) return null;
        $raw = base64_decode(substr($cipher, strlen(self::PREFIX)), true);
        if ($raw === false || strlen($raw) < self::NONCE + self::TAG) return null;

        $plain = openssl_decrypt(
            substr($raw, self::NONCE + self::TAG), self::CIPHER, $key, OPENSSL_RAW_DATA,
            substr($raw, 0, self::NONCE), substr($raw, self::NONCE, self::TAG), self::PREFIX
        );
        return $plain === false ? null : $plain;
    }

    /**
     * Huella para buscar por un dato cifrado: HMAC-SHA256 en hex (64 caracteres), siempre igual para el mismo valor.
     * Se guarda en una columna al lado del cifrado y se busca por ella (WHERE RfcIndice = ?). Sustituye a encryptDet().
     * Usa CRYPTO_INDEX_KEY, distinta de CRYPTO_KEY: quien tenga los índices no puede descifrar nada.
     */
    public static function blindIndex(string $value): string
    {
        $key = self::key('CRYPTO_INDEX_KEY');
        if (hash_equals(self::key('CRYPTO_KEY'), $key)) {
            throw new LogicException('Crypto: CRYPTO_INDEX_KEY debe ser distinta de CRYPTO_KEY.');
        }
        return hash_hmac('sha256', $value, $key);
    }

    /** Token aleatorio en hex (el doble de caracteres que bytes): 32 bytes → 64 caracteres. */
    public static function randomToken(int $bytes = 32): string
    {
        if ($bytes < 16) throw new InvalidArgumentException('Crypto: un token necesita al menos 16 bytes.');
        return bin2hex(random_bytes($bytes));
    }

    /** La llave de 32 bytes que trae el .env en base64. Lanza LogicException si falta o no mide 32 bytes. */
    private static function key(string $name): string
    {
        $value = (string) ($_ENV[$name] ?? (getenv($name) ?: ''));
        if ($value === '') {
            throw new LogicException("Crypto: falta $name en el .env (32 bytes en base64). Ver framework/CHANGELOG.md 1.1.0.");
        }
        $key = base64_decode($value, true);
        if ($key === false || strlen($key) !== 32) {
            throw new LogicException("Crypto: $name debe ser de 32 bytes en base64 (base64_encode(random_bytes(32))).");
        }
        return $key;
    }
}
