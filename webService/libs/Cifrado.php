<?php
/**
 * Cifrado de datos del colegio que hay que volver a leer (matrículas y correos de tutores), AES-256-CBC con IV fijo.
 * Era apiService/core/crypto.php (funciones encrypt()/decrypt()); se pasó a libs porque en Windows y macOS ese nombre
 * choca con el Crypto.php del framework 1.5.0. Los datos ya guardados se siguen leyendo igual.
 *
 *     Cifrado::encrypt('ALU-001')    // lo que se guarda en la base
 *     Cifrado::decrypt($guardado)    // false si no se pudo descifrar
 *
 * El IV fijo permite buscar por la matrícula cifrada (WHERE matricula = ?). Para datos nuevos usa Crypto (v2, GCM).
 */
final class Cifrado
{
    private const METHOD = 'AES-256-CBC';

    public static function encrypt(?string $plain): string
    {
        [$key, $iv] = self::keys();
        return base64_encode((string) openssl_encrypt((string) $plain, self::METHOD, $key, 0, $iv));
    }

    public static function decrypt(?string $stored): string|false
    {
        [$key, $iv] = self::keys();
        return openssl_decrypt(base64_decode((string) $stored), self::METHOD, $key, 0, $iv);
    }

    /** Llave e IV desde el .env. El respaldo mantiene compatibilidad con los datos ya cifrados. */
    private static function keys(): array
    {
        return [hash('sha256', $_ENV['CRYPTO_KEY'] ?? 'Hg25Ls4'), substr(hash('sha256', $_ENV['CRYPTO_IV'] ?? '101712'), 0, 16)];
    }
}
