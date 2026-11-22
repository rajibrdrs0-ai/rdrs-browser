<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Application;
use App\Core\Database;

/** Application-level audit entries (database triggers add their own). */
final class Audit
{
    public static function log(string $action, string $entity, int|string|null $entityId, ?array $old = null, ?array $new = null, ?int $centerId = null): void
    {
        $request = Application::getInstance()->request();
        Database::query(
            'INSERT INTO audit_logs (user_id, center_id, action, entity_type, entity_id, old_value, new_value, ip_address, user_agent)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)',
            [
                Auth::id(),
                $centerId ?? (Auth::user()['center_id'] ?? null),
                $action,
                $entity,
                $entityId,
                $old !== null ? json_encode($old, JSON_UNESCAPED_UNICODE) : null,
                $new !== null ? json_encode($new, JSON_UNESCAPED_UNICODE) : null,
                $request?->ip(),
                $request?->userAgent(),
            ]
        );
    }

    /** Only the keys whose values changed (for compact old/new pairs). @return array{0:array,1:array} */
    public static function diff(array $old, array $new): array
    {
        $before = $after = [];
        foreach ($new as $key => $value) {
            if (array_key_exists($key, $old) && (string) $old[$key] !== (string) $value) {
                $before[$key] = $old[$key];
                $after[$key] = $value;
            }
        }
        return [$before, $after];
    }
}
