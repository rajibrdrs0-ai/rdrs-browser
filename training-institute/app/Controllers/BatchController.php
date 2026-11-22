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
use App\Services\Audit;
use App\Services\Auth;
use App\Services\FeeStructure;
use App\Services\Rbac;
use App\Services\Sequence;

/**
 * Batches: scheduling, trainers, status workflow and the weekly routine.
 * Trainers (self scope) only see batches they teach.
 */
final class BatchController extends Controller
{
    public const STATUSES = ['planned', 'admission_open', 'running', 'completed', 'cancelled'];
    public const DAYS = ['sat', 'sun', 'mon', 'tue', 'wed', 'thu', 'fri'];
    public const SHIFTS = ['morning', 'day', 'evening', 'weekend'];
    /** Allowed status transitions. */
    public const FLOW = [
        'planned'        => ['admission_open', 'cancelled'],
        'admission_open' => ['running', 'planned', 'cancelled'],
        'running'        => ['completed'],
        'completed'      => [],
        'cancelled'      => [],
    ];
    private const ACTIVE = "('planned','admission_open','running')";

    public function index(Request $request): Response
    {
        [$scopeSql, $params] = Rbac::batchFilter('b');
        $where = [$scopeSql];
        foreach (['center_id' => 'b.center_id', 'course_id' => 'b.course_id'] as $key => $col) {
            $v = $this->intOrNull($request->query($key));
            if ($v !== null) {
                $where[] = "{$col} = ?";
                $params[] = $v;
            }
        }
        $status = (string) $request->query('status', '');
        if (in_array($status, self::STATUSES, true)) {
            $where[] = 'b.status = ?';
            $params[] = $status;
        } elseif ($status === '') {
            $where[] = 'b.status IN ' . self::ACTIVE;
        }
        $q = trim((string) $request->query('q', ''));
        if ($q !== '') {
            $where[] = '(b.batch_code LIKE ? OR b.name LIKE ? OR co.name_en LIKE ? OR co.name_bn LIKE ?)';
            $like = '%' . addcslashes($q, '%_\\') . '%';
            array_push($params, $like, $like, $like, $like);
        }
        $result = $this->paginate(
            "b.*, co.code AS course_code, co.name_en AS course_en, co.name_bn AS course_bn, c.code AS center_code,
             c.name_en AS center_en, c.name_bn AS center_bn, s.name AS trainer_name, s.name_bn AS trainer_name_bn,
             (SELECT COUNT(*) FROM enrollments e WHERE e.batch_id = b.id AND e.status IN ('admitted','running','completed')) AS enrolled",
            'FROM batches b JOIN courses co ON co.id = b.course_id JOIN training_centers c ON c.id = b.center_id
             LEFT JOIN staff s ON s.id = b.lead_trainer_id WHERE ' . implode(' AND ', $where),
            $params, 'b.start_date DESC, b.id DESC'
        );
        return $this->view('batches/index', $result + [
            'centers' => $this->centers(),
            'courses' => Database::all('SELECT id, code, name_en, name_bn FROM courses WHERE is_active = 1 ORDER BY name_en'),
            'filters' => ['q' => $q, 'center_id' => $request->query('center_id', ''), 'course_id' => $request->query('course_id', ''), 'status' => $status],
        ]);
    }

    public function show(Request $request, string $id): Response
    {
        $batch = $this->find((int) $id);
        return $this->view('batches/show', [
            'batch'    => $batch,
            'trainers' => Database::all(
                "SELECT s.id, s.name, s.name_bn, s.mobile, bt.role, d.name_en AS designation_en, d.name_bn AS designation_bn
                   FROM batch_trainers bt JOIN staff s ON s.id = bt.staff_id JOIN designations d ON d.id = s.designation_id
                  WHERE bt.batch_id = ? ORDER BY bt.role = 'lead' DESC, s.name", [$batch['id']]),
            'fees'     => FeeStructure::current((int) $batch['course_id'], (int) $batch['center_id'], (string) $batch['start_date']),
            'routine'  => $this->routineRows('cr.batch_id = ?', [$batch['id']]),
            'modules'  => Database::all('SELECT id, sequence_no, title_en, title_bn FROM course_modules WHERE course_id = ? ORDER BY sequence_no', [$batch['course_id']]),
            'centerTrainers' => $this->trainersOf((int) $batch['center_id']),
            'rooms'    => Database::all('SELECT id, name FROM rooms WHERE center_id = ? AND is_active = 1 ORDER BY name', [$batch['center_id']]),
            'enrolledCount' => (int) $batch['enrolled'],
            'enrollments' => Database::all(
                "SELECT e.id, e.roll_no, e.status, e.attendance_percent, e.net_fee, s.id AS student_id, s.name_en, s.name_bn, s.mobile, s.photo_path,
                        COALESCE(d.due, 0) AS due, COALESCE(d.overdue, 0) AS overdue
                   FROM enrollments e JOIN students s ON s.id = e.student_id LEFT JOIN v_enrollment_dues d ON d.enrollment_id = e.id
                  WHERE e.batch_id = ? AND e.status <> 'cancelled' ORDER BY CAST(e.roll_no AS UNSIGNED)", [$batch['id']]),
            'next'     => self::FLOW[$batch['status']],
            'canManage'=> Rbac::can('batch.manage'),
            'canRoutine' => Rbac::can('routine.manage') && in_array($batch['status'], ['planned', 'admission_open', 'running'], true),
        ]);
    }

    public function create(Request $request): Response
    {
        return $this->view('batches/form', $this->formData(null, $this->intOrNull($request->query('center_id'))));
    }

    public function store(Request $request): Response
    {
        $data = $this->validated($request, null);
        $id = Database::transaction(function () use ($data): int {
            $center = (string) Database::value('SELECT code FROM training_centers WHERE id = ?', [$data['center_id']]);
            $course = (string) Database::value('SELECT code FROM courses WHERE id = ?', [$data['course_id']]);
            $year = substr((string) $data['start_date'], 0, 4);
            $seq = Sequence::next('batch:' . $center . '-' . $course, $year); // key <= 39 chars, period = year
            $code = substr($center . '-' . $course . '-' . $year . '-' . str_pad((string) $seq, 2, '0', STR_PAD_LEFT), 0, 30);
            Database::query(
                'INSERT INTO batches (batch_code, center_id, course_id, name, shift, start_date, end_date, class_start_time, class_end_time,
                                      class_days, capacity, room_id, lead_trainer_id, funding_source, status, created_by)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
                [$code, $data['center_id'], $data['course_id'], $data['name'], $data['shift'], $data['start_date'], $data['end_date'],
                 $data['class_start_time'], $data['class_end_time'], $data['class_days'], $data['capacity'], $data['room_id'],
                 $data['lead_trainer_id'], $data['funding_source'], $data['status'], Auth::id()]
            );
            $id = Database::lastInsertId();
            $this->syncTrainers($id, $data['lead_trainer_id'], $data['assistant_ids']);
            Audit::log('create', 'batch', $id, null, $data + ['batch_code' => $code], $data['center_id']);
            return $id;
        });
        Session::flash('success', __('batches.saved'));
        return $this->redirect('/batches/' . $id);
    }

    public function edit(Request $request, string $id): Response
    {
        $batch = $this->find((int) $id);
        if (in_array($batch['status'], ['completed', 'cancelled'], true)) {
            throw new HttpException(403, __('batches.closed'));
        }
        return $this->view('batches/form', $this->formData($batch, (int) $batch['center_id']));
    }

    public function update(Request $request, string $id): Response
    {
        $batch = $this->find((int) $id);
        if (in_array($batch['status'], ['completed', 'cancelled'], true)) {
            throw new HttpException(403, __('batches.closed'));
        }
        $data = $this->validated($request, $batch);
        Database::transaction(function () use ($batch, $data): void {
            Database::query(
                'UPDATE batches SET name = ?, shift = ?, start_date = ?, end_date = ?, class_start_time = ?, class_end_time = ?, class_days = ?,
                        capacity = ?, room_id = ?, lead_trainer_id = ?, funding_source = ? WHERE id = ?',
                [$data['name'], $data['shift'], $data['start_date'], $data['end_date'], $data['class_start_time'], $data['class_end_time'],
                 $data['class_days'], $data['capacity'], $data['room_id'], $data['lead_trainer_id'], $data['funding_source'], $batch['id']]
            );
            $this->syncTrainers((int) $batch['id'], $data['lead_trainer_id'], $data['assistant_ids']);
            Audit::log('update', 'batch', (int) $batch['id'], ...[...Audit::diff($batch, $data), (int) $batch['center_id']]);
        });
        Session::flash('success', __('batches.saved'));
        return $this->redirect('/batches/' . $batch['id']);
    }

    public function changeStatus(Request $request, string $id): Response
    {
        $batch = $this->find((int) $id);
        $to = (string) $request->input('status', '');
        if (!in_array($to, self::FLOW[$batch['status']], true)) {
            throw new ValidationException(['status' => [__('batches.bad_transition')]]);
        }
        $active = (int) Database::value("SELECT COUNT(*) FROM enrollments WHERE batch_id = ? AND status IN ('admitted','running')", [$batch['id']]);
        if ($to === 'cancelled' && $active > 0) {
            throw new ValidationException(['status' => [__('batches.cancel_has_students', ['count' => $active])]]);
        }
        if ($to === 'running' && $batch['lead_trainer_id'] === null) {
            throw new ValidationException(['status' => [__('batches.needs_trainer')]]);
        }
        Database::transaction(static function () use ($batch, $to): void {
            Database::query('UPDATE batches SET status = ? WHERE id = ?', [$to, $batch['id']]);
            if ($to === 'running') {
                Database::query("UPDATE enrollments SET status = 'running' WHERE batch_id = ? AND status = 'admitted'", [$batch['id']]);
            }
            Audit::log('status_change', 'batch', (int) $batch['id'], ['status' => $batch['status']], ['status' => $to], (int) $batch['center_id']);
        });
        Session::flash('success', __('batches.status_changed', ['status' => __('batches.status.' . $to)]));
        return $this->redirect('/batches/' . $batch['id']);
    }

    // ---- routine -------------------------------------------------------------

    public function storeRoutine(Request $request, string $id): Response
    {
        $batch = $this->find((int) $id);
        if (!in_array($batch['status'], ['planned', 'admission_open', 'running'], true)) {
            throw new HttpException(403, __('batches.closed'));
        }
        $input = $request->all();
        $data = $this->validate([
            'weekday'      => 'required|in:' . implode(',', self::DAYS),
            'start_time'   => 'required|time',
            'end_time'     => 'required|time|after_time:start_time',
            'module_id'    => 'nullable|integer',
            'staff_id'     => 'required|integer',
            'room_id'      => 'nullable|integer',
            'session_type' => 'required|in:theory,practical,both',
        ], $input);

        $errors = [];
        if (!in_array($data['weekday'], explode(',', (string) $batch['class_days']), true)) {
            $errors['weekday'][] = __('routine.not_class_day');
        }
        $moduleId = ($data['module_id'] ?? '') !== '' ? (int) $data['module_id'] : null;
        if ($moduleId !== null && Database::value('SELECT id FROM course_modules WHERE id = ? AND course_id = ?', [$moduleId, $batch['course_id']]) === null) {
            $errors['module_id'][] = __('validation.in', ['field' => __('routine.module')]);
        }
        $staffId = (int) $data['staff_id'];
        if (!in_array($staffId, array_map('intval', array_column($this->trainersOf((int) $batch['center_id']), 'id')), true)) {
            $errors['staff_id'][] = __('validation.in', ['field' => __('routine.trainer')]);
        }
        $roomId = ($data['room_id'] ?? '') !== '' ? (int) $data['room_id'] : ($batch['room_id'] !== null ? (int) $batch['room_id'] : null);
        if ($roomId !== null && Database::value('SELECT id FROM rooms WHERE id = ? AND center_id = ?', [$roomId, $batch['center_id']]) === null) {
            $errors['room_id'][] = __('validation.in', ['field' => __('routine.room')]);
        }
        if ($errors === []) {
            foreach ($this->conflicts($batch, (string) $data['weekday'], (string) $data['start_time'], (string) $data['end_time'], $staffId, $roomId) as $c) {
                $errors['start_time'][] = $c;
            }
        }
        if ($errors !== []) {
            throw new ValidationException($errors);
        }
        Database::query(
            'INSERT INTO class_routines (batch_id, weekday, start_time, end_time, module_id, staff_id, room_id, session_type) VALUES (?, ?, ?, ?, ?, ?, ?, ?)',
            [$batch['id'], $data['weekday'], $data['start_time'], $data['end_time'], $moduleId, $staffId, $roomId, $data['session_type']]
        );
        // Teaching a slot makes the trainer part of the batch (so they can take attendance).
        Database::query("INSERT IGNORE INTO batch_trainers (batch_id, staff_id, role) VALUES (?, ?, 'assistant')", [$batch['id'], $staffId]);
        Audit::log('create', 'class_routine', Database::lastInsertId(), null, $data, (int) $batch['center_id']);
        Session::flash('success', __('routine.saved'));
        return $this->redirect('/batches/' . $batch['id'] . '#routine');
    }

    public function deleteRoutine(Request $request, string $id, string $slotId): Response
    {
        $batch = $this->find((int) $id);
        $slot = Database::first('SELECT * FROM class_routines WHERE id = ? AND batch_id = ?', [(int) $slotId, $batch['id']]) ?? throw new HttpException(404);
        Database::query('DELETE FROM class_routines WHERE id = ?', [$slot['id']]);
        Audit::log('delete', 'class_routine', (int) $slot['id'], $slot, null, (int) $batch['center_id']);
        Session::flash('success', __('routine.deleted'));
        return $this->redirect('/batches/' . $batch['id'] . '#routine');
    }

    /** Weekly timetable across batches in scope (trainers: their own classes). */
    public function timetable(Request $request): Response
    {
        [$scopeSql, $params] = Rbac::batchFilter('b');
        $where = [$scopeSql, 'b.status IN ' . self::ACTIVE];
        $centerId = $this->intOrNull($request->query('center_id'));
        if ($centerId !== null) {
            $where[] = 'b.center_id = ?';
            $params[] = $centerId;
        }
        if (Rbac::scope() === 'self') {
            $where[] = '(cr.staff_id = ? OR b.lead_trainer_id = ?)';
            $params[] = Rbac::staffId() ?? 0;
            $params[] = Rbac::staffId() ?? 0;
        }
        return $this->view('batches/timetable', [
            'slots'    => $this->routineRows(implode(' AND ', $where), $params),
            'centers'  => $this->centers(),
            'centerId' => $centerId,
        ]);
    }

    // ---- helpers ------------------------------------------------------------

    /**
     * Clash detection against other active batches whose date range overlaps.
     * @return list<string>
     */
    private function conflicts(array $batch, string $day, string $start, string $end, int $staffId, ?int $roomId): array
    {
        $rows = Database::all(
            "SELECT cr.*, b.batch_code, b.id AS b_id, r.name AS room_name, s.name AS staff_name
               FROM class_routines cr JOIN batches b ON b.id = cr.batch_id
               LEFT JOIN rooms r ON r.id = cr.room_id LEFT JOIN staff s ON s.id = cr.staff_id
              WHERE cr.weekday = ? AND cr.start_time < ? AND ? < cr.end_time
                AND b.status IN " . self::ACTIVE . " AND b.start_date <= ? AND ? <= b.end_date
                AND (cr.batch_id = ? OR cr.staff_id = ? OR (? IS NOT NULL AND cr.room_id = ?))",
            [$day, $end, $start, $batch['end_date'], $batch['start_date'], $batch['id'], $staffId, $roomId, $roomId]
        );
        $messages = [];
        foreach ($rows as $r) {
            $slot = substr((string) $r['start_time'], 0, 5) . '–' . substr((string) $r['end_time'], 0, 5);
            if ((int) $r['b_id'] === (int) $batch['id']) {
                $messages[] = __('routine.clash_batch', ['slot' => $slot]);
            } elseif ((int) $r['staff_id'] === $staffId) {
                $messages[] = __('routine.clash_trainer', ['name' => (string) $r['staff_name'], 'batch' => (string) $r['batch_code'], 'slot' => $slot]);
            } else {
                $messages[] = __('routine.clash_room', ['room' => (string) $r['room_name'], 'batch' => (string) $r['batch_code'], 'slot' => $slot]);
            }
        }
        return array_values(array_unique($messages));
    }

    /** @return list<array<string,mixed>> */
    private function routineRows(string $where, array $params): array
    {
        return Database::all(
            "SELECT cr.*, b.batch_code, b.name AS batch_name, b.center_id, c.code AS center_code, co.name_en AS course_en, co.name_bn AS course_bn,
                    m.sequence_no, m.title_en AS module_en, m.title_bn AS module_bn, s.name AS staff_name, s.name_bn AS staff_name_bn, r.name AS room_name
               FROM class_routines cr
               JOIN batches b ON b.id = cr.batch_id
               JOIN courses co ON co.id = b.course_id
               JOIN training_centers c ON c.id = b.center_id
               LEFT JOIN course_modules m ON m.id = cr.module_id
               LEFT JOIN staff s ON s.id = cr.staff_id
               LEFT JOIN rooms r ON r.id = cr.room_id
              WHERE {$where}
              ORDER BY FIELD(cr.weekday, 'sat','sun','mon','tue','wed','thu','fri'), cr.start_time", $params);
    }

    private function syncTrainers(int $batchId, ?int $leadId, array $assistantIds): void
    {
        Database::query("DELETE FROM batch_trainers WHERE batch_id = ? AND staff_id NOT IN (SELECT staff_id FROM class_routines WHERE batch_id = ? AND staff_id IS NOT NULL)",
            [$batchId, $batchId]);
        Database::query("UPDATE batch_trainers SET role = 'assistant' WHERE batch_id = ?", [$batchId]);
        foreach (array_unique($assistantIds) as $sid) {
            Database::query("INSERT IGNORE INTO batch_trainers (batch_id, staff_id, role) VALUES (?, ?, 'assistant')", [$batchId, $sid]);
        }
        if ($leadId !== null) {
            Database::query("INSERT INTO batch_trainers (batch_id, staff_id, role) VALUES (?, ?, 'lead') ON DUPLICATE KEY UPDATE role = 'lead'", [$batchId, $leadId]);
        }
    }

    /** @return array<string,mixed> */
    private function find(int $id): array
    {
        [$scopeSql, $params] = Rbac::batchFilter('b');
        $batch = Database::first(
            "SELECT b.*, co.code AS course_code, co.name_en AS course_en, co.name_bn AS course_bn, co.duration_months, co.total_hours,
                    c.code AS center_code, c.name_en AS center_en, c.name_bn AS center_bn, r.name AS room_name,
                    s.name AS trainer_name, s.name_bn AS trainer_name_bn,
                    (SELECT COUNT(*) FROM enrollments e WHERE e.batch_id = b.id AND e.status IN ('admitted','running','completed')) AS enrolled
               FROM batches b JOIN courses co ON co.id = b.course_id JOIN training_centers c ON c.id = b.center_id
               LEFT JOIN rooms r ON r.id = b.room_id LEFT JOIN staff s ON s.id = b.lead_trainer_id
              WHERE b.id = ? AND {$scopeSql}", array_merge([$id], $params));
        if ($batch === null) {
            // Exists but outside scope -> 403; truly missing -> 404.
            throw new HttpException(Database::value('SELECT id FROM batches WHERE id = ?', [$id]) !== null ? 403 : 404);
        }
        return $batch;
    }

    /** @return list<array<string,mixed>> */
    private function trainersOf(int $centerId): array
    {
        return Database::all(
            "SELECT s.id, s.name, s.name_bn, s.employee_code FROM staff s JOIN designations d ON d.id = s.designation_id
              WHERE s.center_id = ? AND s.status = 'active' AND d.is_trainer = 1 ORDER BY s.name", [$centerId]);
    }

    /** @return list<array<string,mixed>> */
    private function centers(): array
    {
        [$sql, $params] = Rbac::centerFilter('c.id');
        return Database::all("SELECT c.id, c.code, c.name_en, c.name_bn FROM training_centers c
                               WHERE c.is_active = 1 AND c.center_type = 'training_center' AND {$sql} ORDER BY c.name_en", $params);
    }

    /** @return array<string,mixed> */
    private function formData(?array $batch, ?int $centerId): array
    {
        $centers = $this->centers();
        if ($centerId === null && count($centers) === 1) {
            $centerId = (int) $centers[0]['id'];
        }
        if ($centerId !== null && !Rbac::canAccessCenter($centerId)) {
            $centerId = null;
        }
        return [
            'batch'      => $batch,
            'centers'    => $centers,
            'centerId'   => $centerId,
            'courses'    => Database::all('SELECT id, code, name_en, name_bn, duration_months FROM courses WHERE is_active = 1 ORDER BY name_en'),
            'trainers'   => $centerId !== null ? $this->trainersOf($centerId) : [],
            'rooms'      => $centerId !== null ? Database::all('SELECT id, name, capacity FROM rooms WHERE center_id = ? AND is_active = 1 ORDER BY name', [$centerId]) : [],
            'assistants' => $batch !== null ? array_map('intval', array_column(Database::all(
                "SELECT staff_id FROM batch_trainers WHERE batch_id = ? AND role <> 'lead'", [$batch['id']]), 'staff_id')) : [],
        ];
    }

    /** @return array<string,mixed> */
    private function validated(Request $request, ?array $batch): array
    {
        $input = $request->all();
        $input['capacity'] = en_digits(trim((string) ($input['capacity'] ?? '')));
        $rules = [
            'name'             => 'required|max:100',
            'shift'            => 'required|in:' . implode(',', self::SHIFTS),
            'start_date'       => 'required|date',
            'end_date'         => 'required|date|after_or_equal:start_date',
            'class_start_time' => 'nullable|time',
            'class_end_time'   => 'nullable|time',
            'capacity'         => 'required|integer|min_value:1|max_value:500',
            'room_id'          => 'nullable|integer',
            'lead_trainer_id'  => 'nullable|integer',
            'funding_source'   => 'nullable|max:120',
        ];
        if ($batch === null) {
            $rules['center_id'] = 'required|integer';
            $rules['course_id'] = 'required|integer';
            $rules['status'] = 'required|in:planned,admission_open';
        }
        $data = $this->validate($rules, $input);
        $centerId = $batch !== null ? (int) $batch['center_id'] : (int) $data['center_id'];
        $errors = [];

        if ($batch === null) {
            if (!Rbac::canAccessCenter($centerId)
                || Database::value("SELECT id FROM training_centers WHERE id = ? AND center_type = 'training_center' AND is_active = 1", [$centerId]) === null) {
                $errors['center_id'][] = __('users.center_not_allowed');
            }
            if (Database::value('SELECT id FROM courses WHERE id = ? AND is_active = 1', [(int) $data['course_id']]) === null) {
                $errors['course_id'][] = __('validation.in', ['field' => __('batches.course')]);
            }
        }
        $days = array_values(array_intersect(self::DAYS, (array) $request->input('class_days', [])));
        if ($days === []) {
            $errors['class_days'][] = __('batches.days_required');
        }
        $startT = (string) ($data['class_start_time'] ?? '');
        $endT = (string) ($data['class_end_time'] ?? '');
        if ($startT !== '' && $endT !== '' && $endT <= $startT) {
            $errors['class_end_time'][] = __('batches.time_order');
        }
        $roomId = ($data['room_id'] ?? '') !== '' ? (int) $data['room_id'] : null;
        if ($roomId !== null && Database::value('SELECT id FROM rooms WHERE id = ? AND center_id = ?', [$roomId, $centerId]) === null) {
            $errors['room_id'][] = __('validation.in', ['field' => __('batches.room')]);
        }
        $trainerIds = array_map('intval', array_column($this->trainersOf($centerId), 'id'));
        $leadId = ($data['lead_trainer_id'] ?? '') !== '' ? (int) $data['lead_trainer_id'] : null;
        if ($leadId !== null && !in_array($leadId, $trainerIds, true)) {
            $errors['lead_trainer_id'][] = __('validation.in', ['field' => __('batches.lead_trainer')]);
        }
        $assistants = array_values(array_filter(array_map('intval', (array) $request->input('assistant_ids', [])),
            static fn(int $sid): bool => in_array($sid, $trainerIds, true) && $sid !== $leadId));
        if ($batch !== null && (int) $data['capacity'] < (int) $batch['enrolled']) {
            $errors['capacity'][] = __('batches.capacity_below', ['count' => $batch['enrolled']]);
        }
        if ($errors !== []) {
            throw new ValidationException($errors);
        }
        return [
            'center_id' => $centerId,
            'course_id' => $batch !== null ? (int) $batch['course_id'] : (int) $data['course_id'],
            'name' => trim((string) $data['name']), 'shift' => $data['shift'],
            'start_date' => $data['start_date'], 'end_date' => $data['end_date'],
            'class_start_time' => $startT !== '' ? $startT : null, 'class_end_time' => $endT !== '' ? $endT : null,
            'class_days' => implode(',', $days), 'capacity' => (int) $data['capacity'], 'room_id' => $roomId,
            'lead_trainer_id' => $leadId, 'assistant_ids' => $assistants,
            'funding_source' => ($data['funding_source'] ?? '') !== '' ? trim((string) $data['funding_source']) : null,
            'status' => $batch['status'] ?? $data['status'],
        ];
    }
}
