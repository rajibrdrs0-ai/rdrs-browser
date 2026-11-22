<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Database;

/**
 * Role-based access control with data scoping.
 *
 *   Permission  = what a user may do   (role permissions ± per-user overrides)
 *   Scope       = which data they see  (global / district / center / self)
 *   Level       = whom they may manage (only roles with a higher level number)
 */
final class Rbac
{
    /** @var array<string,true>|null */
    private static ?array $permissions = null;
    /** @var list<int>|null|false false = not computed */
    private static array|null|false $centerIds = false;

    public static function reset(): void
    {
        self::$permissions = null;
        self::$centerIds = false;
    }

    /** @return array<string,true> */
    public static function permissions(): array
    {
        if (self::$permissions !== null) {
            return self::$permissions;
        }
        $user = Auth::user();
        if ($user === null) {
            return self::$permissions = [];
        }
        $rows = Database::all(
            'SELECT p.slug
               FROM permissions p
              WHERE (p.id IN (SELECT permission_id FROM role_permissions WHERE role_id = ?)
                     OR p.id IN (SELECT permission_id FROM user_permissions WHERE user_id = ? AND is_granted = 1))
                AND p.id NOT IN (SELECT permission_id FROM user_permissions WHERE user_id = ? AND is_granted = 0)',
            [$user['role_id'], $user['id'], $user['id']]
        );
        self::$permissions = [];
        foreach ($rows as $row) {
            self::$permissions[(string) $row['slug']] = true;
        }
        return self::$permissions;
    }

    public static function can(string $permission): bool
    {
        return isset(self::permissions()[$permission]);
    }

    /** @param list<string> $permissions */
    public static function canAny(array $permissions): bool
    {
        foreach ($permissions as $permission) {
            if (self::can($permission)) {
                return true;
            }
        }
        return false;
    }

    public static function scope(): string
    {
        return (string) (Auth::user()['role_scope'] ?? 'self');
    }

    public static function isGlobal(): bool
    {
        return self::scope() === 'global';
    }

    /**
     * Centers the current user may see. NULL means "all centers" (global scope).
     * @return list<int>|null
     */
    public static function centerIds(): ?array
    {
        if (self::$centerIds !== false) {
            return self::$centerIds;
        }
        $user = Auth::user();
        if ($user === null) {
            return self::$centerIds = [];
        }
        $ids = match ((string) $user['role_scope']) {
            'global'   => null,
            'district' => array_map('intval', array_column(Database::all(
                'SELECT id FROM training_centers
                  WHERE district_id = ? OR district_id IN (SELECT district_id FROM user_district_access WHERE user_id = ?)',
                [$user['district_id'], $user['id']]
            ), 'id')),
            default    => array_values(array_unique(array_filter(array_merge(
                [$user['center_id'] !== null ? (int) $user['center_id'] : null],
                array_map('intval', array_column(Database::all(
                    'SELECT center_id FROM user_center_access WHERE user_id = ?', [$user['id']]
                ), 'center_id'))
            ), static fn($v): bool => $v !== null))),
        };
        return self::$centerIds = $ids;
    }

    public static function canAccessCenter(?int $centerId): bool
    {
        $ids = self::centerIds();
        if ($ids === null) {
            return true;
        }
        return $centerId !== null && in_array($centerId, $ids, true);
    }

    /**
     * SQL fragment restricting a center column to the user's scope.
     * @return array{0:string,1:list<int>}
     */
    public static function centerFilter(string $column): array
    {
        $ids = self::centerIds();
        if ($ids === null) {
            return ['1=1', []];
        }
        if ($ids === []) {
            return ['1=0', []];
        }
        return [$column . ' IN ' . Database::in($ids), $ids];
    }

    /** Staff record linked to the logged-in user (trainers), or null. */
    public static function staffId(): ?int
    {
        $id = Auth::user()['staff_id'] ?? null;
        return $id !== null ? (int) $id : null;
    }

    /**
     * Batch visibility: center scope for everyone, and for self-scoped users
     * (trainers) additionally only batches they teach.
     * @return array{0:string,1:list<int>}
     */
    public static function batchFilter(string $alias = 'b'): array
    {
        [$sql, $params] = self::centerFilter($alias . '.center_id');
        if (self::scope() === 'self') {
            $staffId = self::staffId() ?? 0;
            $sql .= " AND ({$alias}.lead_trainer_id = ? OR {$alias}.id IN (SELECT batch_id FROM batch_trainers WHERE staff_id = ?))";
            $params[] = $staffId;
            $params[] = $staffId;
        }
        return [$sql, $params];
    }

    /**
     * Trainee visibility. Center/district users see trainees registered at, or
     * enrolled in, their centers; trainers see trainees of the batches they teach.
     * @return array{0:string,1:list<int>}
     */
    public static function studentFilter(string $alias = 's'): array
    {
        if (self::isGlobal()) {
            return ['1=1', []];
        }
        if (self::scope() === 'self') {
            [$bSql, $bParams] = self::batchFilter('b');
            return ["{$alias}.id IN (SELECT e.student_id FROM enrollments e JOIN batches b ON b.id = e.batch_id WHERE {$bSql})", $bParams];
        }
        [$cSql, $cParams] = self::centerFilter($alias . '.center_id');
        [$eSql, $eParams] = self::centerFilter('e.center_id');
        return ["({$cSql} OR {$alias}.id IN (SELECT e.student_id FROM enrollments e WHERE {$eSql}))", array_merge($cParams, $eParams)];
    }

    /** Throw 403 unless the center is inside the user's scope. */
    public static function assertCenter(?int $centerId): void
    {
        if (!self::canAccessCenter($centerId)) {
            throw new \App\Core\HttpException(403, __('errors.forbidden'));
        }
    }

    /** Roles the current user may assign to others. @return list<array<string,mixed>> */
    public static function assignableRoles(): array
    {
        $user = Auth::user();
        if ($user === null) {
            return [];
        }
        $roles = Database::all('SELECT * FROM roles ORDER BY level, id');
        $isSuper = $user['role_slug'] === 'super_admin';
        return array_values(array_filter($roles, static function (array $role) use ($user, $isSuper): bool {
            if ($isSuper) {
                return true;
            }
            if ((int) $role['level'] <= (int) $user['role_level']) {
                return false;
            }
            // Non-global users cannot hand out global or district-wide roles.
            if ($user['role_scope'] !== 'global' && in_array($role['scope'], ['global', 'district'], true)) {
                return false;
            }
            return true;
        }));
    }

    /** May the current user edit / deactivate / reset this target user? */
    public static function canManageUser(array $target): bool
    {
        $user = Auth::user();
        if ($user === null || (int) $target['id'] === (int) $user['id']) {
            return false;
        }
        if ($user['role_slug'] !== 'super_admin' && (int) $target['role_level'] <= (int) $user['role_level']) {
            return false;
        }
        if (self::isGlobal()) {
            return true;
        }
        if ($target['center_id'] === null) {
            return false; // HO / district users are outside a center-scoped manager's reach
        }
        return self::canAccessCenter((int) $target['center_id']);
    }
}
