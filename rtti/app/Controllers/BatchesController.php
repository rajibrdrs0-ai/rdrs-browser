<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Audit;
use App\Core\Auth;
use App\Core\Controller;
use App\Core\DB;
use App\Core\Export;
use App\Core\Sequence;
use App\Services\EnrollmentService;

final class BatchesController extends Controller
{
    public function index(): void
    {
        $this->authorize('batches.view');
        [$where, $params] = $this->centerFilter('b.center_id');
        if ($q = input('q')) {
            $where .= ' AND (b.batch_code LIKE :q OR b.name LIKE :q2 OR c.name LIKE :q3)';
            $params += ['q' => "%$q%", 'q2' => "%$q%", 'q3' => "%$q%"];
        }
        if ($c = input_id('course_id')) {
            $where .= ' AND b.course_id = :c';
            $params['c'] = $c;
        }
        if ($st = input('status')) {
            $where .= ' AND bs.code = :st';
            $params['st'] = $st;
        }
        if ($t = input_id('trainer_id')) {
            $where .= ' AND b.trainer_id = :t';
            $params['t'] = $t;
        }
        if ($from = input_date('from')) {
            $where .= ' AND b.end_date >= :from';
            $params['from'] = $from;
        }
        if ($to = input_date('to')) {
            $where .= ' AND b.start_date <= :to';
            $params['to'] = $to;
        }
        $sql = "SELECT b.*, c.name course, c.code course_code, ctr.name center, d.name district, bs.name status, bs.color, st.name trainer,
                (SELECT COUNT(*) FROM enrollments e WHERE e.batch_id = b.id AND e.status IN ('pending','active','completed')) enrolled
            FROM batches b JOIN courses c ON c.id = b.course_id JOIN centers ctr ON ctr.id = b.center_id JOIN districts d ON d.id = ctr.district_id
            JOIN batch_statuses bs ON bs.id = b.batch_status_id LEFT JOIN staff st ON st.id = b.trainer_id
            WHERE b.deleted_at IS NULL $where ORDER BY b.start_date DESC";
        if ($fmt = Export::requested()) {
            $this->authorize('batches.export');
            Export::send($fmt, 'Batch List', ['batch_code' => 'Batch ID', 'name' => 'Batch Name', 'course' => 'Course', 'center' => 'Center', 'district' => 'District',
                'trainer' => 'Trainer', 'start_date' => 'Start', 'end_date' => 'End', 'class_schedule' => 'Schedule', 'max_students' => 'Capacity', 'enrolled' => 'Enrolled', 'status' => 'Status'], DB::all($sql, $params));
        }
        $this->render('batches/index', ['title' => 'Batches', 'p' => DB::paginate($sql, $params),
            'courses' => DB::all('SELECT id, name FROM courses WHERE deleted_at IS NULL ORDER BY name'),
            'statuses' => DB::all('SELECT code id, name FROM batch_statuses WHERE is_active = 1 ORDER BY id'),
            'breadcrumbs' => ['Academics' => null, 'Batches' => null]]);
    }

    private function lists(?array $b): array
    {
        $centerId = (int)old('center_id', $b['center_id'] ?? Auth::defaultCenterId());
        return [
            'courses' => DB::all("SELECT c.id, CONCAT(c.code, ' – ', c.name) name FROM courses c JOIN course_statuses cs ON cs.id = c.course_status_id WHERE c.deleted_at IS NULL AND (cs.code = 'ACTIVE' OR c.id = :cid) ORDER BY c.name", ['cid' => $b['course_id'] ?? 0]),
            'centers' => Auth::centerOptions(),
            'statuses' => DB::all('SELECT id, name FROM batch_statuses WHERE is_active = 1 ORDER BY id'),
            'trainers' => $centerId ? DB::all("SELECT id, CONCAT(name, ' (', employee_code, ')') name FROM staff WHERE deleted_at IS NULL AND is_trainer = 1 AND employment_status = 'active' AND center_id = :c ORDER BY name", ['c' => $centerId]) : [],
        ];
    }

    public function create(): void
    {
        $this->authorize('batches.create');
        $this->render('batches/form', ['title' => 'New Batch', 'b' => ['course_id' => input_int('course_id')] ?: null, 'isEdit' => false, 'lists' => $this->lists(null),
            'breadcrumbs' => ['Batches' => url('batches/index'), 'New' => null]]);
    }

    public function edit(): void
    {
        $this->authorize('batches.edit');
        $b = $this->load();
        $this->render('batches/form', ['title' => 'Edit Batch', 'b' => $b, 'isEdit' => true, 'lists' => $this->lists($b),
            'breadcrumbs' => ['Batches' => url('batches/index'), $b['batch_code'] => url('batches/view', ['id' => $b['id']]), 'Edit' => null]]);
    }

    private function load(): array
    {
        $b = DB::row('SELECT b.*, c.name course, c.code course_code, ctr.name center, ctr.code center_code, bs.name status, bs.code status_code, bs.color, st.name trainer, st.mobile trainer_mobile
            FROM batches b JOIN courses c ON c.id = b.course_id JOIN centers ctr ON ctr.id = b.center_id JOIN batch_statuses bs ON bs.id = b.batch_status_id
            LEFT JOIN staff st ON st.id = b.trainer_id WHERE b.id = :id AND b.deleted_at IS NULL', ['id' => input_int('id')]) ?? $this->notFound('Batch');
        $this->authorizeCenter((int)$b['center_id']);
        return $b;
    }

    public function save(): void
    {
        $this->requirePost();
        $id = input_int('id');
        $this->authorize($id ? 'batches.edit' : 'batches.create');
        $old = $id ? $this->load() : null;
        $this->validate([
            'name|Batch name' => 'required|maxlen:150', 'course_id|Course' => 'required|exists:courses', 'center_id|Center' => 'required|exists:centers',
            'start_date|Start date' => 'required|date', 'end_date|End date' => 'required|date', 'max_students|Maximum students' => 'required|int|min:1|max:500',
            'batch_status_id|Status' => 'required|exists:batch_statuses', 'trainer_id|Trainer' => 'exists:staff',
        ]);
        $centerId = $this->resolveCenter(input_id('center_id'));
        if (input_date('end_date') < input_date('start_date')) {
            $this->fail('End date must be on or after the start date.');
        }
        if ($trainer = input_id('trainer_id')) {
            $tc = DB::value('SELECT center_id FROM staff WHERE id = :id AND is_trainer = 1', ['id' => $trainer]);
            if ($tc === null) {
                $this->fail('Selected staff member is not a trainer.');
            }
        }
        $days = array_intersect((array)($_POST['class_days'] ?? []), ['Sat', 'Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri']);
        $data = [
            'name' => input('name'), 'course_id' => input_id('course_id'), 'center_id' => $centerId, 'trainer_id' => input_id('trainer_id'),
            'start_date' => input_date('start_date'), 'end_date' => input_date('end_date'), 'class_days' => $days ? implode(', ', $days) : null,
            'class_start_time' => nullify(input('class_start_time')), 'class_end_time' => nullify(input('class_end_time')), 'room' => nullify(input('room')),
            'max_students' => input_int('max_students'), 'batch_status_id' => input_id('batch_status_id'), 'remarks' => nullify(input('remarks')),
        ];
        $data['class_schedule'] = trim(($data['class_days'] ?? '') . ' ' . ($data['class_start_time'] ? date('h:i A', strtotime($data['class_start_time'])) . ' – ' . date('h:i A', strtotime((string)$data['class_end_time'])) : '')) ?: nullify(input('class_schedule'));
        if ($old) {
            $enrolled = EnrollmentService::enrolledCount((int)$old['id']);
            if ($data['max_students'] < $enrolled) {
                $this->fail("Capacity cannot be lower than the number already enrolled ($enrolled).");
            }
            if ((int)$old['course_id'] !== $data['course_id'] && $enrolled > 0) {
                $this->fail('Course cannot be changed after students have been enrolled.');
            }
            if ((int)$old['center_id'] !== $data['center_id'] && $enrolled > 0) {
                $this->fail('Center cannot be changed after students have been enrolled. Use batch transfer instead.');
            }
            $newCode = DB::value('SELECT code FROM batch_statuses WHERE id = :id', ['id' => $data['batch_status_id']]);
            if ($newCode === 'COMPLETED' && $old['status_code'] !== 'COMPLETED') {
                $this->fail('Use the “Complete batch” button to complete a batch.');
            }
            DB::update('batches', $data, 'id = :id', ['id' => $old['id']]);
            Audit::log('batches', 'update', $old['id'], "Batch updated: {$old['batch_code']}", $old, $data, $centerId);
            $id = (int)$old['id'];
        } else {
            $id = DB::transaction(function () use ($data, $centerId) {
                $data['batch_code'] = Sequence::next('batch_code_format', '{CENTER}-{COURSE}-{YY}-{SEQ3}', [
                    'center' => DB::value('SELECT code FROM centers WHERE id = :c', ['c' => $centerId]),
                    'course' => preg_replace('/^[A-Z]+-/', '', (string)DB::value('SELECT code FROM courses WHERE id = :c', ['c' => $data['course_id']])),
                ]);
                $data['created_by'] = Auth::id();
                $id = DB::insert('batches', $data);
                Audit::log('batches', 'create', $id, "Batch created: {$data['batch_code']}", null, $data, $centerId);
                return $id;
            });
        }
        clear_old();
        flash('success', 'Batch saved.');
        redirect(url('batches/view', ['id' => $id]));
    }

    public function view(): void
    {
        $this->authorize('batches.view');
        $b = $this->load();
        $students = DB::all("SELECT e.*, s.student_code, s.name, s.mobile, s.photo,
                (SELECT ROUND(SUM(ad.status IN ('present','late'))*100/COUNT(*),1) FROM attendance_details ad WHERE ad.enrollment_id = e.id) attendance,
                (SELECT certificate_no FROM certificates ce WHERE ce.enrollment_id = e.id AND ce.status = 'issued' LIMIT 1) certificate_no
            FROM enrollments e JOIN students s ON s.id = e.student_id WHERE e.batch_id = :b ORDER BY FIELD(e.status,'active','pending','completed','dropped','cancelled','transferred'), s.name", ['b' => $b['id']]);
        $sessions = (int)DB::value('SELECT COUNT(*) FROM attendance WHERE batch_id = :b', ['b' => $b['id']]);
        $exams = DB::all('SELECT * FROM examinations WHERE batch_id = :b ORDER BY exam_date', ['b' => $b['id']]);
        $routine = DB::all('SELECT r.*, st.name trainer FROM class_routines r LEFT JOIN staff st ON st.id = r.trainer_id WHERE r.batch_id = :b ORDER BY FIELD(r.day_of_week,6,0,1,2,3,4,5), r.start_time', ['b' => $b['id']]);
        $assignments = DB::all('SELECT * FROM assignments WHERE deleted_at IS NULL AND (batch_id = :b OR (batch_id IS NULL AND course_id = :c)) ORDER BY deadline DESC', ['b' => $b['id'], 'c' => $b['course_id']]);
        $fin = DB::row("SELECT SUM(payable_amount) payable, SUM(paid_amount) paid, SUM(due_amount) due FROM enrollments WHERE batch_id = :b AND status IN ('pending','active','completed')", ['b' => $b['id']]);
        $this->render('batches/view', ['title' => $b['batch_code'], 'b' => $b, 'students' => $students, 'sessions' => $sessions, 'exams' => $exams, 'routine' => $routine,
            'assignments' => $assignments, 'fin' => $fin, 'enrolled' => EnrollmentService::enrolledCount((int)$b['id']),
            'breadcrumbs' => ['Batches' => url('batches/index'), $b['batch_code'] => null]]);
    }

    /** Complete batch: closes enrollment, marks passed students as completed */
    public function complete(): void
    {
        $this->requirePost();
        $this->authorize('batches.complete');
        $b = $this->load();
        if ($b['status_code'] === 'COMPLETED') {
            $this->fail('Batch is already completed.');
        }
        if ($b['end_date'] > date('Y-m-d') && !input('force')) {
            $this->fail('The batch end date (' . fmt_date($b['end_date']) . ') has not been reached yet.');
        }
        DB::transaction(function () use ($b) {
            $completed = (int)DB::value("SELECT id FROM batch_statuses WHERE code = 'COMPLETED'");
            DB::update('batches', ['batch_status_id' => $completed, 'completed_at' => date('Y-m-d H:i:s')], 'id = :id', ['id' => $b['id']]);
            $n = DB::query("UPDATE enrollments SET status = 'completed', completion_date = :d WHERE batch_id = :b AND status = 'active' AND result_status = 'pass'", ['d' => min(date('Y-m-d'), $b['end_date']), 'b' => $b['id']])->rowCount();
            Audit::log('batches', 'complete', $b['id'], "Batch completed: {$b['batch_code']} ($n trainees passed)", null, null, (int)$b['center_id']);
        });
        flash('success', 'Batch marked as completed. Trainees who passed are now “completed”.');
        redirect(url('batches/view', ['id' => $b['id']]));
    }

    public function delete(): void
    {
        $this->requirePost();
        $this->authorize('batches.delete');
        $b = $this->load();
        if (DB::exists('SELECT 1 FROM enrollments WHERE batch_id = :b', ['b' => $b['id']])) {
            $this->fail('This batch has enrollments and cannot be deleted. Set the status to Cancelled instead.');
        }
        DB::update('batches', ['deleted_at' => date('Y-m-d H:i:s')], 'id = :id', ['id' => $b['id']]);
        Audit::log('batches', 'delete', $b['id'], "Batch deleted: {$b['batch_code']}", $b, null, (int)$b['center_id']);
        flash('success', 'Batch deleted.');
        redirect(url('batches/index'));
    }
}
