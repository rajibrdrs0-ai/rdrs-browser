<?php
declare(strict_types=1);

namespace App\Core;

/**
 * Authentication, RBAC permission checks and center-wise data scoping.
 */
final class Auth
{
    private static ?array $user = null;
    private static ?array $perms = null;
    /** @var int[]|null null = all centers */
    private static ?array $centers = null;
    private static bool $centersLoaded = false;

    public static function attempt(string $username, string $password): array
    {
        $max = (int)Settings::get('max_login_attempts', 5);
        $lockMin = (int)Settings::get('lockout_minutes', 15);
        $u = DB::row('SELECT * FROM users WHERE username = :u OR email = :u2 LIMIT 1', ['u' => $username, 'u2' => $username]);

        $log = function (bool $ok, ?int $uid, string $note) use ($username): void {
            DB::insert('login_logs', [
                'user_id' => $uid, 'username' => mb_substr($username, 0, 60), 'success' => $ok ? 1 : 0,
                'ip_address' => client_ip(), 'user_agent' => mb_substr((string)($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 255), 'note' => $note,
            ]);
        };

        // Throttle by IP as well (protects against username enumeration / spraying)
        $ipFails = (int)DB::value('SELECT COUNT(*) FROM login_logs WHERE ip_address = :ip AND success = 0 AND created_at > (NOW() - INTERVAL 15 MINUTE)', ['ip' => client_ip()]);
        if ($ipFails >= 20) {
            $log(false, $u['id'] ?? null, 'ip throttled');
            return [false, 'Too many failed attempts from this computer. Please wait 15 minutes.'];
        }

        if (!$u) {
            password_verify($password, '$2y$10$usesomesillystringfore7hnbRJHxXVLeakoG8K30oukPsA.ztMG'); // timing equaliser
            $log(false, null, 'unknown user');
            return [false, 'Invalid username or password.'];
        }
        if ($u['locked_until'] && strtotime($u['locked_until']) > time()) {
            $log(false, (int)$u['id'], 'locked');
            return [false, 'Account locked due to repeated failed logins. Try again after ' . date('h:i A', strtotime($u['locked_until'])) . '.'];
        }
        if (!password_verify($password, $u['password_hash'])) {
            $fails = (int)$u['failed_attempts'] + 1;
            $lock = $fails >= $max ? date('Y-m-d H:i:s', time() + $lockMin * 60) : null;
            DB::update('users', ['failed_attempts' => $lock ? 0 : $fails, 'locked_until' => $lock], 'id = :id', ['id' => $u['id']]);
            $log(false, (int)$u['id'], 'bad password');
            return [false, $lock ? "Too many failed attempts. Account locked for $lockMin minutes." : 'Invalid username or password.'];
        }
        if (!(int)$u['is_active']) {
            $log(false, (int)$u['id'], 'inactive');
            return [false, 'Your account is deactivated. Contact the administrator.'];
        }
        if (password_needs_rehash($u['password_hash'], PASSWORD_DEFAULT)) {
            DB::update('users', ['password_hash' => password_hash($password, PASSWORD_DEFAULT)], 'id = :id', ['id' => $u['id']]);
        }
        session_regenerate_id(true);
        $token = bin2hex(random_bytes(16));
        DB::update('users', ['failed_attempts' => 0, 'locked_until' => null, 'last_login_at' => date('Y-m-d H:i:s'), 'session_token' => $token], 'id = :id', ['id' => $u['id']]);
        $_SESSION['uid'] = (int)$u['id'];
        $_SESSION['utoken'] = $token;
        $_SESSION['last_activity'] = time();
        $_SESSION['ua'] = hash('sha256', (string)($_SERVER['HTTP_USER_AGENT'] ?? ''));
        $_SESSION['lang'] = $u['language'] ?: Settings::get('default_language', 'en');
        $log(true, (int)$u['id'], 'login');
        return [true, ''];
    }

    public static function logout(): void
    {
        if (self::id()) {
            DB::update('users', ['session_token' => null], 'id = :id', ['id' => self::id()]);
        }
        $_SESSION = [];
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_regenerate_id(true);
            session_destroy();
        }
        self::$user = null;
    }

    /** Validates the current session; returns the user row or null. */
    public static function user(): ?array
    {
        if (self::$user !== null) {
            return self::$user;
        }
        $uid = $_SESSION['uid'] ?? null;
        if (!$uid) {
            return null;
        }
        $timeout = (int)Settings::get('session_timeout_minutes', 60) * 60;
        if (time() - (int)($_SESSION['last_activity'] ?? 0) > $timeout) {
            self::logout();
            flash('warning', 'Your session expired due to inactivity. Please sign in again.');
            return null;
        }
        if (($_SESSION['ua'] ?? '') !== hash('sha256', (string)($_SERVER['HTTP_USER_AGENT'] ?? ''))) {
            self::logout();
            return null;
        }
        $u = DB::row('SELECT u.*, s.employee_code, c.name AS default_center_name
                      FROM users u LEFT JOIN staff s ON s.id = u.staff_id LEFT JOIN centers c ON c.id = u.default_center_id
                      WHERE u.id = :id', ['id' => $uid]);
        if (!$u || !(int)$u['is_active'] || !hash_equals((string)$u['session_token'], (string)($_SESSION['utoken'] ?? ''))) {
            self::logout();
            flash('warning', 'You have been signed out. Please sign in again.');
            return null;
        }
        $_SESSION['last_activity'] = time();
        return self::$user = $u;
    }

    public static function id(): ?int
    {
        return isset($_SESSION['uid']) ? (int)$_SESSION['uid'] : null;
    }

    public static function check(): bool
    {
        return self::user() !== null;
    }

    public static function roles(): array
    {
        $uid = self::id();
        return $uid ? DB::all('SELECT r.* FROM roles r JOIN user_roles ur ON ur.role_id = r.id WHERE ur.user_id = :u', ['u' => $uid]) : [];
    }

    public static function isSuperAdmin(): bool
    {
        foreach (self::roles() as $r) {
            if ($r['slug'] === 'super_admin') {
                return true;
            }
        }
        return false;
    }

    public static function permissions(): array
    {
        if (self::$perms !== null) {
            return self::$perms;
        }
        $uid = self::id();
        if (!$uid) {
            return self::$perms = [];
        }
        if (self::isSuperAdmin()) {
            return self::$perms = ['*' => true];
        }
        $slugs = DB::column('SELECT DISTINCT p.slug FROM permissions p
            JOIN role_permissions rp ON rp.permission_id = p.id
            JOIN user_roles ur ON ur.role_id = rp.role_id WHERE ur.user_id = :u', ['u' => $uid]);
        return self::$perms = array_fill_keys($slugs, true);
    }

    public static function can(string $perm): bool
    {
        $p = self::permissions();
        return isset($p['*']) || isset($p[$perm]);
    }

    public static function canAny(array $perms): bool
    {
        foreach ($perms as $p) {
            if (self::can($p)) {
                return true;
            }
        }
        return false;
    }

    /**
     * Center IDs this user may access. null = all centers (Head Office).
     * @return int[]|null
     */
    public static function centerIds(): ?array
    {
        if (self::$centersLoaded) {
            return self::$centers;
        }
        self::$centersLoaded = true;
        $uid = self::id();
        if (!$uid) {
            return self::$centers = [];
        }
        foreach (self::roles() as $r) {
            if ((int)$r['all_centers'] === 1) {
                return self::$centers = null;
            }
        }
        return self::$centers = array_map('intval', DB::column('SELECT center_id FROM user_centers WHERE user_id = :u', ['u' => $uid]));
    }

    public static function isHeadOffice(): bool
    {
        return self::centerIds() === null;
    }

    public static function canAccessCenter(?int $centerId): bool
    {
        $ids = self::centerIds();
        return $ids === null || ($centerId !== null && in_array($centerId, $ids, true));
    }

    /**
     * SQL fragment restricting $column to the user's centers (ids are ints, safe to inline).
     * Returns '' for head-office users.
     */
    public static function scope(string $column): string
    {
        $ids = self::centerIds();
        if ($ids === null) {
            return '';
        }
        return ' AND ' . $column . ' IN (' . DB::inInts($ids) . ')';
    }

    /** Centers available in dropdowns for this user */
    public static function centerOptions(bool $activeOnly = true): array
    {
        $sql = 'SELECT c.id, c.code, c.name, c.district_id, d.name AS district FROM centers c JOIN districts d ON d.id = c.district_id WHERE 1=1'
            . ($activeOnly ? ' AND c.is_active = 1' : '') . self::scope('c.id') . ' ORDER BY c.is_head_office DESC, c.name';
        return DB::all($sql);
    }

    public static function defaultCenterId(): ?int
    {
        $u = self::user();
        if ($u && $u['default_center_id'] && self::canAccessCenter((int)$u['default_center_id'])) {
            return (int)$u['default_center_id'];
        }
        $ids = self::centerIds();
        return $ids ? $ids[0] : null;
    }
}
