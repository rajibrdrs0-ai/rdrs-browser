<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Audit;
use App\Core\Auth;
use App\Core\DB;
use App\Core\Sequence;
use App\Core\Settings;

final class CertificateService
{
    /**
     * Check configured completion requirements.
     * @return string[] list of unmet requirements (empty = eligible)
     */
    public static function problems(array $e): array
    {
        $p = [];
        if (!in_array($e['status'], ['active', 'completed'], true)) {
            $p[] = "Enrollment status is {$e['status']}.";
        }
        if (DB::exists("SELECT 1 FROM certificates WHERE enrollment_id = :e AND status = 'issued'", ['e' => $e['id']])) {
            $p[] = 'Certificate already issued.';
        }
        if (Settings::get('cert_require_pass', '1') === '1' && $e['result_status'] !== 'pass') {
            $p[] = $e['result_status'] ? "Final result: {$e['result_status']}." : 'Final exam result not published.';
        }
        if (Settings::get('cert_require_attendance', '1') === '1') {
            $min = (float)DB::value('SELECT min_attendance_percent FROM courses WHERE id = :c', ['c' => $e['course_id']]);
            $att = EnrollmentService::attendancePercent((int)$e['id']);
            if ($min > 0 && ($att === null || $att < $min)) {
                $p[] = 'Attendance ' . ($att === null ? 'not recorded' : $att . '%') . " (minimum $min%).";
            }
        }
        if (Settings::get('cert_require_full_payment', '1') === '1' && (float)$e['due_amount'] > 0) {
            $p[] = 'Fee due: ' . number_format((float)$e['due_amount'], 2) . '.';
        }
        if (Settings::get('cert_require_batch_completed', '0') === '1') {
            $code = DB::value('SELECT bs.code FROM batches b JOIN batch_statuses bs ON bs.id = b.batch_status_id WHERE b.id = :b', ['b' => $e['batch_id']]);
            if ($code !== 'COMPLETED') {
                $p[] = 'Batch is not completed yet.';
            }
        }
        return $p;
    }

    public static function issue(int $enrollmentId, string $issueDate, ?int $templateId = null): int
    {
        return DB::transaction(function () use ($enrollmentId, $issueDate, $templateId) {
            $e = DB::row('SELECT e.*, b.end_date FROM enrollments e JOIN batches b ON b.id = e.batch_id WHERE e.id = :id FOR UPDATE', ['id' => $enrollmentId]);
            if (!$e) {
                throw new \DomainException('Enrollment not found.');
            }
            if (PHP_SAPI !== 'cli' && !Auth::canAccessCenter((int)$e['center_id'])) {
                throw new \DomainException('This enrollment belongs to another center.');
            }
            if ($problems = self::problems($e)) {
                throw new \DomainException('Not eligible: ' . implode(' ', $problems));
            }
            $templateId ??= (int)DB::value('SELECT id FROM certificate_templates WHERE is_default = 1 AND is_active = 1 LIMIT 1') ?: null;
            $no = Sequence::next('certificate_no_format', 'RTTI/{YYYY}/{SEQ6}');
            $completion = $e['completion_date'] ?: min($e['end_date'], $issueDate);
            $id = DB::insert('certificates', [
                'certificate_no' => $no,
                'verification_token' => bin2hex(random_bytes(16)),
                'enrollment_id' => $e['id'], 'student_id' => $e['student_id'], 'course_id' => $e['course_id'],
                'batch_id' => $e['batch_id'], 'center_id' => $e['center_id'], 'template_id' => $templateId,
                'completion_date' => $completion, 'issue_date' => $issueDate, 'grade' => $e['result_grade'],
                'issued_by' => Auth::id(),
            ]);
            DB::update('enrollments', ['status' => 'completed', 'completion_date' => $completion], 'id = :id', ['id' => $e['id']]);
            Audit::log('certificates', 'issue', $id, "Certificate $no issued ({$e['enrollment_code']})", null, null, (int)$e['center_id']);
            return $id;
        });
    }
}
