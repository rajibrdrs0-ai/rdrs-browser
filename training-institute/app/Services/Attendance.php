<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Database;

/** Attendance helpers: cached percentages and absence streaks. */
final class Attendance
{
    /** Recompute enrollments.attendance_percent for one batch (held sessions only). */
    public static function refreshBatch(int $batchId): void
    {
        Database::query(
            "UPDATE enrollments e
                LEFT JOIN (
                    SELECT sa.enrollment_id,
                           COUNT(*) AS marked,
                           SUM(sa.status IN ('present','late')) AS attended
                      FROM student_attendance sa
                      JOIN class_sessions cs ON cs.id = sa.class_session_id AND cs.status = 'held'
                     WHERE cs.batch_id = ?
                     GROUP BY sa.enrollment_id
                ) x ON x.enrollment_id = e.id
                SET e.attendance_percent = IF(x.marked IS NULL OR x.marked = 0, NULL, ROUND(100 * x.attended / x.marked, 2))
              WHERE e.batch_id = ?",
            [$batchId, $batchId]
        );
    }

    /**
     * Trainees whose most recent N held sessions were all absences.
     * @return list<array<string,mixed>>
     */
    public static function consecutiveAbsentees(int $batchId, int $days): array
    {
        if ($days < 1) {
            return [];
        }
        $sessions = array_map('intval', array_column(Database::all(
            "SELECT id FROM class_sessions WHERE batch_id = ? AND status = 'held' ORDER BY session_date DESC, start_time DESC LIMIT " . (int) $days,
            [$batchId]), 'id'));
        if (count($sessions) < $days) {
            return [];
        }
        return Database::all(
            "SELECT e.id AS enrollment_id, e.roll_no, s.id AS student_id, s.name_en, s.name_bn, s.mobile, s.guardian_mobile, s.father_mobile
               FROM enrollments e JOIN students s ON s.id = e.student_id
              WHERE e.batch_id = ? AND e.status IN ('admitted','running')
                AND (SELECT COUNT(*) FROM student_attendance sa WHERE sa.enrollment_id = e.id AND sa.status = 'absent'
                        AND sa.class_session_id IN " . Database::in($sessions) . ") = ?
              ORDER BY CAST(e.roll_no AS UNSIGNED)",
            array_merge([$batchId], $sessions, [$days])
        );
    }
}
