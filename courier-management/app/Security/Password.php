<?php
declare(strict_types=1);

namespace App\Security;

use App\Core\Config;

/**
 * Password hashing policy.
 * Argon2id when the PHP build supports it, otherwise bcrypt (cost 12).
 * Legacy hashes are transparently upgraded on successful login via needsRehash().
 */
final class Password
{
    public static function algorithm(): string|int
    {
        return defined('PASSWORD_ARGON2ID') ? PASSWORD_ARGON2ID : PASSWORD_BCRYPT;
    }

    /** @return array<string,int> */
    private static function options(): array
    {
        if (self::algorithm() === PASSWORD_BCRYPT) {
            return ['cost' => (int) Config::get('security.password.bcrypt_cost', 12)];
        }
        return (array) Config::get('security.password.argon2_options', []);
    }

    public static function hash(string $plain): string
    {
        return password_hash($plain, self::algorithm(), self::options());
    }

    public static function verify(string $plain, string $hash): bool
    {
        return $hash !== '' && password_verify($plain, $hash);
    }

    public static function needsRehash(string $hash): bool
    {
        return password_needs_rehash($hash, self::algorithm(), self::options());
    }

    /**
     * Returns a list of translation keys for every rule the password breaks
     * (empty list = acceptable).
     *
     * @return list<string>
     */
    public static function policyViolations(string $plain): array
    {
        $cfg = (array) Config::get('security.password', []);
        $errors = [];
        if (mb_strlen($plain) < (int) ($cfg['min_length'] ?? 8)) {
            $errors[] = 'password.too_short';
        }
        if (($cfg['require_upper'] ?? true) && !preg_match('/\p{Lu}/u', $plain)) {
            $errors[] = 'password.needs_upper';
        }
        if (($cfg['require_lower'] ?? true) && !preg_match('/\p{Ll}/u', $plain)) {
            $errors[] = 'password.needs_lower';
        }
        if (($cfg['require_digit'] ?? true) && !preg_match('/\d/', $plain)) {
            $errors[] = 'password.needs_digit';
        }
        if (($cfg['require_symbol'] ?? false) && !preg_match('/[^\p{L}\d]/u', $plain)) {
            $errors[] = 'password.needs_symbol';
        }
        return $errors;
    }

    /** Cryptographically secure numeric OTP (delivery OTP, 2FA). */
    public static function otp(int $digits = 6): string
    {
        $max = (10 ** $digits) - 1;
        return str_pad((string) random_int(0, $max), $digits, '0', STR_PAD_LEFT);
    }

    /** Random URL-safe token; store only its SHA-256 hash in the database. */
    public static function token(int $bytes = 32): string
    {
        return rtrim(strtr(base64_encode(random_bytes($bytes)), '+/', '-_'), '=');
    }
}
