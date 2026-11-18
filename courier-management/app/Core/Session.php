<?php
declare(strict_types=1);

namespace App\Core;

/**
 * Hardened PHP session wrapper.
 *  - strict mode, cookie-only, HttpOnly, SameSite, Secure (on HTTPS)
 *  - idle + absolute timeouts
 *  - periodic ID rotation and rotation on privilege change (login/logout)
 *  - user-agent fingerprint to reduce session-hijack impact
 *  - flash messages and "old input" for form re-population
 */
final class Session
{
    private static bool $started = false;

    public static function start(Request $request): void
    {
        if (self::$started || session_status() === PHP_SESSION_ACTIVE) {
            self::$started = true;
            return;
        }

        $cfg = (array) Config::get('security.session', []);
        $secure = $cfg['secure'] ?? null;
        $secure = $secure === null ? $request->isSecure() : (bool) $secure;

        ini_set('session.use_strict_mode', '1');
        ini_set('session.use_only_cookies', '1');
        ini_set('session.use_trans_sid', '0');
        ini_set('session.cookie_httponly', '1');
        ini_set('session.sid_length', '48');
        ini_set('session.sid_bits_per_character', '6');
        ini_set('session.gc_maxlifetime', (string) max((int) ($cfg['absolute_timeout'] ?? 43200), 1800));

        $savePath = (string) ($cfg['save_path'] ?? '');
        if ($savePath !== '' && is_dir($savePath) && is_writable($savePath)) {
            session_save_path($savePath);
        }

        session_name((string) ($cfg['name'] ?? 'CMS_SESSID'));
        session_set_cookie_params([
            'lifetime' => 0,
            'path'     => $request->basePath() !== '' ? $request->basePath() . '/' : '/',
            'secure'   => $secure,
            'httponly' => true,
            'samesite' => (string) ($cfg['samesite'] ?? 'Lax'),
        ]);

        session_start();
        self::$started = true;

        self::enforceLifetime($request, $cfg);
        self::ageFlashData();
    }

    /** @param array<string,mixed> $cfg */
    private static function enforceLifetime(Request $request, array $cfg): void
    {
        $now = time();
        $meta = $_SESSION['_meta'] ?? null;
        $fingerprint = hash('sha256', $request->userAgent());

        if (!is_array($meta)) {
            $_SESSION['_meta'] = ['created' => $now, 'last' => $now, 'rotated' => $now, 'fp' => $fingerprint];
            return;
        }

        $idle = (int) ($cfg['idle_timeout'] ?? 1800);
        $absolute = (int) ($cfg['absolute_timeout'] ?? 43200);
        $expired = ($now - (int) $meta['last']) > $idle || ($now - (int) $meta['created']) > $absolute;
        $hijacked = !hash_equals((string) $meta['fp'], $fingerprint);

        if ($expired || $hijacked) {
            self::destroy();
            session_start();
            $_SESSION['_meta'] = ['created' => $now, 'last' => $now, 'rotated' => $now, 'fp' => $fingerprint];
            $_SESSION['_flash']['new']['warning'] = $expired ? 'session_expired' : 'session_invalid';
            return;
        }

        $_SESSION['_meta']['last'] = $now;
        if (($now - (int) $meta['rotated']) > (int) ($cfg['regenerate_every'] ?? 900)) {
            self::regenerate();
        }
    }

    /** Rotate the session id (call on login, logout, role switch). */
    public static function regenerate(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_regenerate_id(true);
            $_SESSION['_meta']['rotated'] = time();
        }
    }

    public static function get(string $key, mixed $default = null): mixed
    {
        return $_SESSION[$key] ?? $default;
    }

    public static function set(string $key, mixed $value): void
    {
        $_SESSION[$key] = $value;
    }

    public static function has(string $key): bool
    {
        return isset($_SESSION[$key]);
    }

    public static function forget(string $key): void
    {
        unset($_SESSION[$key]);
    }

    public static function pull(string $key, mixed $default = null): mixed
    {
        $value = $_SESSION[$key] ?? $default;
        unset($_SESSION[$key]);
        return $value;
    }

    /** Flash data survives exactly one subsequent request. */
    public static function flash(string $key, mixed $value): void
    {
        $_SESSION['_flash']['new'][$key] = $value;
    }

    public static function getFlash(string $key, mixed $default = null): mixed
    {
        return $_SESSION['_flash']['old'][$key] ?? $_SESSION['_flash']['new'][$key] ?? $default;
    }

    private static function ageFlashData(): void
    {
        $_SESSION['_flash']['old'] = $_SESSION['_flash']['new'] ?? [];
        $_SESSION['_flash']['new'] = [];
    }

    public static function destroy(): void
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            return;
        }
        $_SESSION = [];
        $params = session_get_cookie_params();
        setcookie(session_name(), '', [
            'expires'  => time() - 42000,
            'path'     => $params['path'],
            'secure'   => $params['secure'],
            'httponly' => $params['httponly'],
            'samesite' => $params['samesite'] ?? 'Lax',
        ]);
        session_destroy();
        self::$started = false;
    }

    public static function id(): string
    {
        return session_id() ?: '';
    }
}
