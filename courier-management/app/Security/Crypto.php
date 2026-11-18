<?php
declare(strict_types=1);

namespace App\Security;

use App\Core\Config;
use RuntimeException;

/**
 * Authenticated symmetric encryption (libsodium XSalsa20-Poly1305) keyed by
 * APP_KEY. Used for secrets stored in the DB, e.g. partner courier API keys.
 */
final class Crypto
{
    private static function key(): string
    {
        $raw = (string) Config::get('app.key', '');
        if (str_starts_with($raw, 'base64:')) {
            $raw = substr($raw, 7);
        }
        $key = base64_decode($raw, true);
        if ($key === false || strlen($key) !== SODIUM_CRYPTO_SECRETBOX_KEYBYTES) {
            throw new RuntimeException('APP_KEY must be a base64-encoded 32-byte key.');
        }
        return $key;
    }

    public static function encrypt(string $plain): string
    {
        $nonce = random_bytes(SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
        return base64_encode($nonce . sodium_crypto_secretbox($plain, $nonce, self::key()));
    }

    public static function decrypt(string $payload): string
    {
        $decoded = base64_decode($payload, true);
        if ($decoded === false || strlen($decoded) <= SODIUM_CRYPTO_SECRETBOX_NONCEBYTES) {
            throw new RuntimeException('Invalid encrypted payload.');
        }
        $nonce = substr($decoded, 0, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
        $cipher = substr($decoded, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
        $plain = sodium_crypto_secretbox_open($cipher, $nonce, self::key());
        if ($plain === false) {
            throw new RuntimeException('Decryption failed (tampered data or wrong APP_KEY).');
        }
        return $plain;
    }

    /** Keyed hash for lookups of secret values (API keys, reset tokens). */
    public static function hmac(string $value): string
    {
        return hash_hmac('sha256', $value, self::key());
    }
}
