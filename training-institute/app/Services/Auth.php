<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Config;
use App\Core\Database;
use App\Core\Request;
use App\Core\Session;
use App\Security\Csrf;
use App\Security\Password;

/**
 * Authentication: login by username, e-mail or mobile; brute-force throttling
 * per identifier and per IP; account lockout; session fixation protection;
 * transparent re-hash of legacy hashes to Argon2id.
 */
final class Auth
{
    private const SESSION_KEY = 'auth_user_id';
    /** @var array<string,mixed>|null|false false = not loaded yet */
    private static array|null|false $user = false;

    /**
     * @return array{ok:bool,error?:string,minutes?:int}
     */
    public static function attempt(string $identifier, string $password, Request $request): array
    {
        $identifier = mb_strtolower(trim($identifier));
        $ip = $request->ip();
        $max = (int) Config::get('security.login.max_attempts', 5);
        $lockMinutes = (int) Config::get('security.login.lockout_minutes', 15);

        $recentByIdentifier = (int) Database::value(
            'SELECT COUNT(*) FROM login_attempts WHERE identifier = ? AND successful = 0 AND attempted_at > (NOW() - INTERVAL ? MINUTE)',
            [$identifier, $lockMinutes]
        );
        $recentByIp = (int) Database::value(
            'SELECT COUNT(*) FROM login_attempts WHERE ip_address = ? AND successful = 0 AND attempted_at > (NOW() - INTERVAL ? MINUTE)',
            [$ip, $lockMinutes]
        );
        if ($recentByIdentifier >= $max || $recentByIp >= $max * 4) {
            return ['ok' => false, 'error' => 'auth.throttled', 'minutes' => $lockMinutes];
        }

        $phone = normalize_phone($identifier);
        $user = Database::first(
            'SELECT id, password_hash, status, locked_until, failed_login_count, center_id
               FROM users
              WHERE deleted_at IS NULL AND (username = ? OR email = ? OR mobile = ?)
              LIMIT 1',
            [$identifier, $identifier, $phone !== '' ? $phone : $identifier]
        );

        // Always run a hash verification so response time does not reveal whether the account exists.
        $hash = $user['password_hash'] ?? '$2y$12$w4niV2ih8fd5PrWyovgJcuhDLi3YjHYV68OuHtWsUcxysZsvB5dsS';
        $valid = Password::verify($password, (string) $hash);

        $locked = $user !== null && $user['locked_until'] !== null && strtotime((string) $user['locked_until']) > time();
        $active = $user !== null && $user['status'] === 'active';

        Database::query('INSERT INTO login_attempts (identifier, ip_address, successful) VALUES (?, ?, ?)',
            [$identifier, $ip, ($valid && !$locked && $active) ? 1 : 0]);

        if ($user === null || !$valid) {
            if ($user !== null) {
                $count = (int) $user['failed_login_count'] + 1;
                Database::query(
                    'UPDATE users SET failed_login_count = ?, locked_until = IF(? >= ?, NOW() + INTERVAL ? MINUTE, locked_until) WHERE id = ?',
                    [min($count, 255), $count, $max, $lockMinutes, $user['id']]
                );
            }
            return ['ok' => false, 'error' => 'auth.failed'];
        }
        if ($locked) {
            return ['ok' => false, 'error' => 'auth.locked', 'minutes' => $lockMinutes];
        }
        if (!$active) {
            return ['ok' => false, 'error' => 'auth.inactive'];
        }

        Database::setAuditContext((int) $user['id'], $user['center_id'] !== null ? (int) $user['center_id'] : null, $ip);
        $rehash = Password::needsRehash((string) $user['password_hash']) ? Password::hash($password) : null;
        Database::query(
            'UPDATE users SET failed_login_count = 0, locked_until = NULL, last_login_at = NOW(), last_login_ip = ?,
                    password_hash = COALESCE(?, password_hash) WHERE id = ?',
            [$ip, $rehash, $user['id']]
        );
        Database::query(
            "INSERT INTO audit_logs (user_id, center_id, action, entity_type, entity_id, ip_address, user_agent)
             VALUES (?, ?, 'login', 'user', ?, ?, ?)",
            [$user['id'], $user['center_id'], $user['id'], $ip, $request->userAgent()]
        );

        Session::regenerate();
        Csrf::rotate();
        Session::set(self::SESSION_KEY, (int) $user['id']);
        self::$user = false;
        Rbac::reset();
        return ['ok' => true];
    }

    /** @return array<string,mixed>|null */
    public static function user(): ?array
    {
        if (self::$user !== false) {
            return self::$user;
        }
        $id = Session::get(self::SESSION_KEY);
        if (!is_int($id)) {
            return self::$user = null;
        }
        $user = Database::first(
            'SELECT u.*, r.slug AS role_slug, r.name_en AS role_name_en, r.name_bn AS role_name_bn,
                    r.scope AS role_scope, r.level AS role_level,
                    c.name_en AS center_name_en, c.name_bn AS center_name_bn,
                    d.name_en AS district_name_en, d.name_bn AS district_name_bn
               FROM users u
               JOIN roles r ON r.id = u.role_id
               LEFT JOIN training_centers c ON c.id = u.center_id
               LEFT JOIN districts d ON d.id = u.district_id
              WHERE u.id = ? AND u.deleted_at IS NULL AND u.status = ?',
            [$id, 'active']
        );
        if ($user === null) {
            Session::forget(self::SESSION_KEY);
        }
        return self::$user = $user;
    }

    public static function id(): ?int
    {
        $user = self::user();
        return $user !== null ? (int) $user['id'] : null;
    }

    public static function check(): bool
    {
        return self::user() !== null;
    }

    public static function logout(Request $request): void
    {
        $id = self::id();
        if ($id !== null) {
            Database::query(
                "INSERT INTO audit_logs (user_id, action, entity_type, entity_id, ip_address) VALUES (?, 'logout', 'user', ?, ?)",
                [$id, $id, $request->ip()]
            );
        }
        Session::destroy();
        self::$user = null;
        Rbac::reset();
    }

    public static function changePassword(int $userId, string $newPassword): void
    {
        Database::query(
            'UPDATE users SET password_hash = ?, must_change_password = 0, password_changed_at = NOW(), updated_by = ? WHERE id = ?',
            [Password::hash($newPassword), self::id() ?? $userId, $userId]
        );
        Session::regenerate();
        self::$user = false;
    }
}
