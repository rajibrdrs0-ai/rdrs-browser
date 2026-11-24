<?php
declare(strict_types=1);

namespace App\Core;

final class Audit
{
    /**
     * Record an important action. $old/$new are arrays (stored as JSON).
     */
    public static function log(string $module, string $action, $recordId = null, string $description = '', ?array $old = null, ?array $new = null, ?int $centerId = null): void
    {
        $strip = function (?array $a): ?string {
            if ($a === null) {
                return null;
            }
            unset($a['password_hash'], $a['session_token'], $a['_csrf'], $a['password']);
            return json_encode($a, JSON_UNESCAPED_UNICODE);
        };
        // Only keep changed fields on update
        if ($old !== null && $new !== null) {
            $changedOld = [];
            $changedNew = [];
            foreach ($new as $k => $v) {
                if (array_key_exists($k, $old) && (string)$old[$k] !== (string)$v) {
                    $changedOld[$k] = $old[$k];
                    $changedNew[$k] = $v;
                }
            }
            $old = $changedOld;
            $new = $changedNew;
        }
        try {
            DB::insert('audit_logs', [
                'user_id' => Auth::id(),
                'center_id' => $centerId,
                'module' => $module,
                'action' => $action,
                'record_id' => $recordId === null ? null : (string)$recordId,
                'description' => mb_substr($description, 0, 255),
                'old_values' => $strip($old),
                'new_values' => $strip($new),
                'ip_address' => client_ip(),
            ]);
        } catch (\Throwable $e) {
            error_log('Audit log failed: ' . $e->getMessage());
        }
    }
}
