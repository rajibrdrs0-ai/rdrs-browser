<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Database;
use App\Core\HttpException;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Core\ValidationException;
use App\Services\Attendance;
use App\Services\Audit;
use App\Services\Auth;
use App\Services\Rbac;

/**
 * Trainee attendance per class session. Trainers mark attendance only for
 * batches they teach; a locked session can no longer be changed (also
 * enforced by a database trigger).
 */
final class AttendanceController extends Controller
{
    private const MARKS = ['present', 'absent', 'late', 'excused'];

    public function index(Request $request): Response
    {
        [$sql, $params] = Rbac::batchFilter('b');
        $batches = Database::all(
            "SELECT b.id, b.batch_code, b.name, b.class_days, c.code AS center_code, co.name_en AS course_en, co.name_bn AS course_bn,
                    co.min_attendance_percent,
                    (SELECT COUNT(*) FROM enrollments e WHERE e.batch_id = b.id AND e.status IN ('admitted','running')) AS active,
                    (SELECT COUNT(*) FROM class_sessions cs WHERE cs.batch_id = b.id AND cs.status = 'held') AS sessions,
                    (SELECT MAX(cs.session_date) FROM class_sessions cs WHERE cs.batch_id = b.id AND cs.status = 'held') AS last_session,
                    (SELECT COUNT(*) FROM class_sessions cs WHERE cs.batch_id = b.id AND cs.session_date = CURRENT_DATE) AS today,
                    (SELECT ROUND(AVG(e.attendance_percent), 1) FROM enrollments e WHERE e.batch_id = b.id AND e.status IN ('admitted','running')) AS avg_pct,
                    (SELECT COUNT(*) FROM enrollments e WHERE e.batch_id = b.id AND e.status IN ('admitted','running')
                        AND e.attendance_percent IS NOT NULL AND e.attendance_percent < co.min_attendance_percent) AS below
               FROM batches b JOIN training_centers c ON c.id = b.center_id JOIN courses co ON co.id = b.course_id
              WHERE b.status = 'running' AND {$sql} ORDER BY c.code, b.batch_code", $params);
        return $this->view('attendance/index', ['batches' => $batches]);
    }

    public function register(Request $request, string $id): Response
    {
        $batch = $this->findBatch((int) $id);
        $month = (string) $request->query('month', '');
        if (!preg_match('/^\d{4}-\d{2}$/', $month)) {
            $month = min(date('Y-m'), substr((string) $batch['end_date'], 0, 7));
            if ($month < substr((string) $batch['start_date'], 0, 7)) {
                $month = substr((string) $batch['start_date'], 0, 7);
            }
        }
        $sessions = Database::all(
            "SELECT cs.*, s.name AS staff_name FROM class_sessions cs LEFT JOIN staff s ON s.id = cs.staff_id
              WHERE cs.batch_id = ? AND DATE_FORMAT(cs.session_date, '%Y-%m') = ? ORDER BY cs.session_date, cs.start_time",
            [$batch['id'], $month]);
        $enrollments = Database::all(
            "SELECT e.id, e.roll_no, e.status, e.attendance_percent, s.name_en, s.name_bn, s.registration_no
               FROM enrollments e JOIN students s ON s.id = e.student_id
              WHERE e.batch_id = ? AND e.status <> 'cancelled' ORDER BY CAST(e.roll_no AS UNSIGNED)", [$batch['id']]);
        $marks = [];
        if ($sessions !== []) {
            foreach (Database::all('SELECT class_session_id, enrollment_id, status FROM student_attendance WHERE class_session_id IN '
                . Database::in(array_column($sessions, 'id')), array_map('intval', array_column($sessions, 'id'))) as $m) {
                $marks[(int) $m['enrollment_id']][(int) $m['class_session_id']] = $m['status'];
            }
        }
        $months = [];
        for ($d = new \DateTimeImmutable(substr((string) $batch['start_date'], 0, 7) . '-01'); $d->format('Y-m') <= substr((string) $batch['end_date'], 0, 7); $d = $d->modify('+1 month')) {
            $months[] = $d->format('Y-m');
        }
        return $this->view('attendance/register', [
            'batch' => $batch, 'sessions' => $sessions, 'enrollments' => $enrollments, 'marks' => $marks,
            'month' => $month, 'months' => $months,
            'streakDays' => $streak = max(1, (int) (Database::value("SELECT setting_value FROM settings WHERE setting_key = 'absence_sms_after_days'") ?? 2)),
            'absentees' => Attendance::consecutiveAbsentees((int) $batch['id'], $streak),
        ]);
    }

    public function take(Request $request, string $id): Response
    {
        $batch = $this->findBatch((int) $id);
        $date = (string) $request->query('date', date('Y-m-d'));
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
            $date = date('Y-m-d');
        }
        $weekday = strtolower(substr((new \DateTimeImmutable($date))->format('D'), 0, 3));
        $slots = Database::all(
            "SELECT cr.*, m.sequence_no, m.title_en, m.title_bn, s.name AS staff_name FROM class_routines cr
               LEFT JOIN course_modules m ON m.id = cr.module_id LEFT JOIN staff s ON s.id = cr.staff_id
              WHERE cr.batch_id = ? AND cr.weekday = ? ORDER BY cr.start_time", [$batch['id'], $weekday]);
        $start = (string) $request->query('start', $slots !== [] ? substr((string) $slots[0]['start_time'], 0, 5) : substr((string) ($batch['class_start_time'] ?? '09:00'), 0, 5));
        $session = Database::first('SELECT * FROM class_sessions WHERE batch_id = ? AND session_date = ? AND start_time = ?', [$batch['id'], $date, $start . ':00']);
        $existing = [];
        if ($session !== null) {
            foreach (Database::all('SELECT enrollment_id, status, remarks FROM student_attendance WHERE class_session_id = ?', [$session['id']]) as $row) {
                $existing[(int) $row['enrollment_id']] = $row;
            }
        }
        $slot = null;
        foreach ($slots as $s) {
            if (substr((string) $s['start_time'], 0, 5) === $start) {
                $slot = $s;
            }
        }
        return $this->view('attendance/take', [
            'batch' => $batch, 'date' => $date, 'start' => $start, 'slots' => $slots, 'slot' => $slot, 'session' => $session,
            'existing' => $existing, 'enrollments' => $this->activeEnrollments((int) $batch['id']),
            'modules' => Database::all('SELECT id, sequence_no, title_en, title_bn FROM course_modules WHERE course_id = ? ORDER BY sequence_no', [$batch['course_id']]),
            'trainers' => Database::all(
                "SELECT s.id, s.name, s.name_bn FROM batch_trainers bt JOIN staff s ON s.id = bt.staff_id WHERE bt.batch_id = ? ORDER BY bt.role = 'lead' DESC, s.name",
                [$batch['id']]),
        ]);
    }

    public function save(Request $request, string $id): Response
    {
        $batch = $this->findBatch((int) $id);
        $data = $this->validate([
            'session_date' => 'required|date',
            'start_time'   => 'required|time',
            'end_time'     => 'nullable|time',
            'status'       => 'required|in:held,cancelled,holiday',
            'module_id'    => 'nullable|integer',
            'staff_id'     => 'nullable|integer',
            'topic'        => 'nullable|max:200',
        ]);
        $date = (string) $data['session_date'];
        $errors = [];
        if ($batch['status'] !== 'running') {
            $errors['session_date'][] = __('attendance.batch_not_running');
        }
        if ($date < (string) $batch['start_date'] || $date > (string) $batch['end_date']) {
            $errors['session_date'][] = __('attendance.outside_batch');
        }
        if ($date > date('Y-m-d')) {
            $errors['session_date'][] = __('attendance.future');
        }
        $end = ($data['end_time'] ?? '') !== '' ? (string) $data['end_time'] : null;
        if ($end !== null && $end <= (string) $data['start_time']) {
            $errors['end_time'][] = __('batches.time_order');
        }
        // A trainer records classes as themself; managers pick one of the batch trainers.
        $staffId = Rbac::scope() === 'self' ? Rbac::staffId() : (($data['staff_id'] ?? '') !== '' ? (int) $data['staff_id'] : null);
        if ($staffId !== null && Database::value('SELECT staff_id FROM batch_trainers WHERE batch_id = ? AND staff_id = ?', [$batch['id'], $staffId]) === null
            && (int) ($batch['lead_trainer_id'] ?? 0) !== $staffId) {
            $errors['staff_id'][] = __('validation.in', ['field' => __('routine.trainer')]);
        }
        $moduleId = ($data['module_id'] ?? '') !== '' ? (int) $data['module_id'] : null;
        if ($moduleId !== null && Database::value('SELECT id FROM course_modules WHERE id = ? AND course_id = ?', [$moduleId, $batch['course_id']]) === null) {
            $errors['module_id'][] = __('validation.in', ['field' => __('routine.module')]);
        }
        if ($errors !== []) {
            throw new ValidationException($errors);
        }
        $start = substr((string) $data['start_time'], 0, 5) . ':00';
        $existing = Database::first('SELECT * FROM class_sessions WHERE batch_id = ? AND session_date = ? AND start_time = ?', [$batch['id'], $date, $start]);
        if ($existing !== null && (int) $existing['is_locked'] === 1) {
            throw new ValidationException(['session_date' => [__('attendance.locked')]]);
        }
        $marks = (array) $request->input('marks', []);
        $remarks = (array) $request->input('remarks', []);
        $active = array_map('intval', array_column($this->activeEnrollments((int) $batch['id']), 'id'));

        Database::transaction(function () use ($batch, $data, $date, $start, $end, $staffId, $moduleId, $existing, $marks, $remarks, $active): void {
            if ($existing === null) {
                Database::query(
                    'INSERT INTO class_sessions (batch_id, session_date, start_time, end_time, module_id, staff_id, topic, status, created_by)
                     VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)',
                    [$batch['id'], $date, $start, $end, $moduleId, $staffId, ($data['topic'] ?? '') !== '' ? trim((string) $data['topic']) : null, $data['status'], Auth::id()]);
                $sessionId = Database::lastInsertId();
            } else {
                $sessionId = (int) $existing['id'];
                Database::query('UPDATE class_sessions SET end_time = ?, module_id = ?, staff_id = ?, topic = ?, status = ? WHERE id = ?',
                    [$end, $moduleId, $staffId, ($data['topic'] ?? '') !== '' ? trim((string) $data['topic']) : null, $data['status'], $sessionId]);
            }
            if ($data['status'] === 'held') {
                foreach ($active as $enrollmentId) {
                    $mark = (string) ($marks[$enrollmentId] ?? 'present');
                    if (!in_array($mark, self::MARKS, true)) {
                        $mark = 'present';
                    }
                    $note = trim((string) ($remarks[$enrollmentId] ?? ''));
                    Database::query(
                        'INSERT INTO student_attendance (class_session_id, enrollment_id, status, remarks, recorded_by) VALUES (?, ?, ?, ?, ?)
                         ON DUPLICATE KEY UPDATE status = VALUES(status), remarks = VALUES(remarks), recorded_by = VALUES(recorded_by)',
                        [$sessionId, $enrollmentId, $mark, $note !== '' ? mb_substr($note, 0, 150) : null, Auth::id()]);
                }
            } else {
                Database::query('DELETE FROM student_attendance WHERE class_session_id = ?', [$sessionId]);
            }
            Attendance::refreshBatch((int) $batch['id']);
            Audit::log($existing === null ? 'create' : 'update', 'class_session', $sessionId, null,
                ['date' => $date, 'start' => $start, 'status' => $data['status'],
                 'absent' => count(array_filter($marks, static fn($m) => $m === 'absent'))], (int) $batch['center_id']);
        });
        Session::flash('success', __('attendance.saved', ['date' => fmt_date($date)]));
        return $this->redirect('/batches/' . $batch['id'] . '/attendance?month=' . substr($date, 0, 7));
    }

    public function lock(Request $request, string $sessionId): Response
    {
        $session = Database::first('SELECT * FROM class_sessions WHERE id = ?', [(int) $sessionId]) ?? throw new HttpException(404);
        $batch = $this->findBatch((int) $session['batch_id']);
        Database::query('UPDATE class_sessions SET is_locked = 1 WHERE id = ?', [$session['id']]);
        Audit::log('lock', 'class_session', (int) $session['id'], null, ['date' => $session['session_date']], (int) $batch['center_id']);
        Session::flash('success', __('attendance.locked_ok'));
        return $this->redirect('/batches/' . $batch['id'] . '/attendance?month=' . substr((string) $session['session_date'], 0, 7));
    }

    // ---- helpers ------------------------------------------------------------

    /** @return list<array<string,mixed>> */
    private function activeEnrollments(int $batchId): array
    {
        return Database::all(
            "SELECT e.id, e.roll_no, e.attendance_percent, s.id AS student_id, s.name_en, s.name_bn, s.registration_no, s.photo_path
               FROM enrollments e JOIN students s ON s.id = e.student_id
              WHERE e.batch_id = ? AND e.status IN ('admitted','running') ORDER BY CAST(e.roll_no AS UNSIGNED)", [$batchId]);
    }

    /** @return array<string,mixed> */
    private function findBatch(int $id): array
    {
        [$sql, $params] = Rbac::batchFilter('b');
        $batch = Database::first(
            "SELECT b.*, co.name_en AS course_en, co.name_bn AS course_bn, co.min_attendance_percent, c.code AS center_code
               FROM batches b JOIN courses co ON co.id = b.course_id JOIN training_centers c ON c.id = b.center_id
              WHERE b.id = ? AND {$sql}", array_merge([$id], $params));
        if ($batch === null) {
            throw new HttpException(Database::value('SELECT id FROM batches WHERE id = ?', [$id]) !== null ? 403 : 404);
        }
        return $batch;
    }
}
