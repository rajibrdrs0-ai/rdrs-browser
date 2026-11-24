<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Audit;
use App\Core\Auth;
use App\Core\DB;

final class ExamService
{
    /** Subjects of the exam's course, or a single pseudo-subject (id 0) using the exam's full marks */
    public static function subjects(array $exam): array
    {
        $subs = DB::all('SELECT id, name, theory_full_marks, practical_full_marks FROM course_subjects s
            WHERE course_id = (SELECT course_id FROM batches WHERE id = :b) ORDER BY sort_order, id', ['b' => $exam['batch_id']]);
        if (!$subs) {
            $subs = [['id' => 0, 'name' => $exam['name'], 'theory_full_marks' => $exam['theory_full_marks'], 'practical_full_marks' => $exam['practical_full_marks']]];
        }
        return $subs;
    }

    public static function grade(float $percent): array
    {
        $g = DB::row('SELECT grade, grade_point, is_pass FROM grade_scales WHERE is_active = 1 AND :p >= min_percent AND :p2 <= max_percent + 0.0099 ORDER BY min_percent DESC LIMIT 1', ['p' => $percent, 'p2' => $percent]);
        return $g ?: ['grade' => '-', 'grade_point' => 0, 'is_pass' => 0];
    }

    /**
     * Compute per-enrollment totals. A student passes when the total percentage >= course pass mark
     * and the grade scale marks the grade as passing.
     */
    public static function results(array $exam): array
    {
        $subs = self::subjects($exam);
        $full = 0.0;
        foreach ($subs as $s) {
            $full += (float)$s['theory_full_marks'] + (float)$s['practical_full_marks'];
        }
        $passMark = (float)DB::value('SELECT c.pass_mark_percent FROM batches b JOIN courses c ON c.id = b.course_id WHERE b.id = :b', ['b' => $exam['batch_id']]);
        $rows = DB::all("SELECT e.id AS enrollment_id, e.enrollment_code, s.student_code, s.name,
                SUM(COALESCE(r.theory_marks,0)) theory, SUM(COALESCE(r.practical_marks,0)) practical, COUNT(r.id) entered, MAX(r.is_absent) absent
            FROM enrollments e JOIN students s ON s.id = e.student_id
            LEFT JOIN examination_results r ON r.enrollment_id = e.id AND r.examination_id = :x
            WHERE e.batch_id = :b AND e.status IN ('active','completed')
            GROUP BY e.id ORDER BY s.name", ['x' => $exam['id'], 'b' => $exam['batch_id']]);
        foreach ($rows as &$r) {
            $total = (float)$r['theory'] + (float)$r['practical'];
            $pct = $full > 0 ? round($total * 100 / $full, 2) : 0;
            $g = self::grade($pct);
            $r['total'] = $total;
            $r['full'] = $full;
            $r['percent'] = $pct;
            $r['grade'] = $g['grade'];
            $r['gpa'] = $g['grade_point'];
            $r['result'] = (int)$r['absent'] === 1 && (int)$r['entered'] > 0 && $total == 0 ? 'absent' : (($pct >= $passMark && (int)$g['is_pass']) ? 'pass' : 'fail');
        }
        unset($r);
        // merit position
        $sorted = $rows;
        usort($sorted, fn($a, $b) => $b['total'] <=> $a['total']);
        $pos = [];
        $rank = 0;
        $prev = null;
        foreach ($sorted as $i => $r) {
            if ($prev === null || $r['total'] < $prev) {
                $rank = $i + 1;
            }
            $prev = $r['total'];
            $pos[$r['enrollment_id']] = $rank;
        }
        foreach ($rows as &$r) {
            $r['position'] = $pos[$r['enrollment_id']];
        }
        return $rows;
    }

    public static function publish(int $examId): int
    {
        return DB::transaction(function () use ($examId) {
            $exam = DB::row('SELECT x.*, b.center_id FROM examinations x JOIN batches b ON b.id = x.batch_id WHERE x.id = :id FOR UPDATE', ['id' => $examId]);
            if (!$exam) {
                throw new \DomainException('Examination not found.');
            }
            if (!DB::exists('SELECT 1 FROM examination_results WHERE examination_id = :x', ['x' => $examId])) {
                throw new \DomainException('No marks have been entered yet.');
            }
            $rows = self::results($exam);
            if ((int)$exam['is_final'] === 1) {
                foreach ($rows as $r) {
                    DB::update('enrollments', ['result_percent' => $r['percent'], 'result_grade' => $r['grade'], 'result_status' => $r['result']], 'id = :id', ['id' => $r['enrollment_id']]);
                }
            }
            DB::update('examinations', ['status' => 'published', 'published_at' => date('Y-m-d H:i:s'), 'published_by' => Auth::id()], 'id = :id', ['id' => $examId]);
            Audit::log('exams', 'publish', $examId, "Results published: {$exam['name']}", null, null, (int)$exam['center_id']);
            return count($rows);
        });
    }
}
