<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\DB;

/**
 * Writes config/permissions.php into the permissions table and (optionally) seeds default role grants.
 */
final class PermissionSync
{
    public static function run(bool $resetRoleDefaults = false): array
    {
        $cfg = require ROOT_PATH . '/config/permissions.php';
        $added = 0;
        foreach ($cfg['modules'] as $module => $m) {
            foreach ($m['actions'] as $action) {
                $slug = "$module.$action";
                if (!DB::exists('SELECT 1 FROM permissions WHERE slug = :s', ['s' => $slug])) {
                    DB::insert('permissions', ['module' => $module, 'action' => $action, 'slug' => $slug, 'label' => $m['label'] . ' – ' . ucwords(str_replace('_', ' ', $action))]);
                    $added++;
                }
            }
        }
        $allSlugs = DB::pairs('SELECT slug, id FROM permissions');
        foreach ($cfg['roles'] as $slug => [$name, $allCenters, $desc, $patterns]) {
            $roleId = DB::value('SELECT id FROM roles WHERE slug = :s', ['s' => $slug]);
            $isNew = false;
            if (!$roleId) {
                $roleId = DB::insert('roles', ['name' => $name, 'slug' => $slug, 'description' => $desc, 'all_centers' => $allCenters, 'is_system' => 1]);
                $isNew = true;
            }
            if (!$isNew && !$resetRoleDefaults) {
                continue;
            }
            DB::query('DELETE FROM role_permissions WHERE role_id = :r', ['r' => $roleId]);
            foreach (self::expand($patterns, array_keys($allSlugs)) as $p) {
                DB::query('INSERT IGNORE INTO role_permissions (role_id, permission_id) VALUES (:r, :p)', ['r' => $roleId, 'p' => $allSlugs[$p]]);
            }
        }
        return ['permissions_added' => $added];
    }

    /** Expand patterns like 'fees.*', '*.*', '!settings.backup' */
    public static function expand(array $patterns, array $all): array
    {
        $set = [];
        foreach ($patterns as $p) {
            $neg = str_starts_with($p, '!');
            $p = ltrim($p, '!');
            $rx = '/^' . str_replace('\*', '[a-z_]+', preg_quote($p === '*' ? '*.*' : $p, '/')) . '$/';
            foreach ($all as $slug) {
                if (preg_match($rx, $slug)) {
                    if ($neg) {
                        unset($set[$slug]);
                    } else {
                        $set[$slug] = true;
                    }
                }
            }
        }
        return array_keys($set);
    }
}
