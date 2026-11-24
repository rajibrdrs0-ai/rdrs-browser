<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Audit;
use App\Core\Auth;
use App\Core\Controller;
use App\Core\DB;
use App\Core\Export;
use App\Services\CertificateService;
use App\Services\EnrollmentService;

final class EnrollmentsController extends Controller
{
    public const STATUSES = ['pending' => 'Pending approval', 'active' => 'Active', 'completed' => 'Completed', 'dropped' => 'Dropped', 'cancelled' => 'Cancelled', 'transferred' => 'Transferred'];

    public function index(): void
    {
        $this->authorize('enrollments.view');
        [$where, $params] = $this->centerFilter('e.center_id');
        if ($q = input('q')) {
            $where .= ' AND (e.enrollment_code LIKE :q OR s.student_code LIKE :q2 OR s.name LIKE :q3 OR s.mobile LIKE :q4)';
            $params += ['q' => "%$q%", 'q2' => "%$q%", 'q3' => "%$q%", 'q4' => "%$q%"];
        }
        foreach (['course_id' => 'e.course_id', 'batch_id' => 'e.batch_id'] as $k => $col) {
            if ($v = input_id($k)) {
                $where .= " AND $col = :$k";
                $params[$k] = $v;
            }
        }
        if (isset(self::STATUSES[$st = (string)input('status')])) {
            $where .= ' AND e.status = :st';
            $params['st'] = $st;
        }
        if ($from = input_date('from')) {
            $where .= ' AND e.enrollment_date >= :from';
            $params['from'] = $from;
        }
        if ($to = input_date('to')) {
            $where .= ' AND e.enrollment_date <= :to';
            $params['to'] = $to;
        }
        if (input('due') === '1') {
            $where .= ' AND e.due_amount > 0';
        }
        $sql = "SELECT e.*, s.student_code, s.name student, s.mobile, c.name course, b.batch_code, ctr.name center
            FROM enrollments e JOIN students s ON s.id = e.student_id JOIN courses c ON c.id = e.course_id
            JOIN batches b ON b.id = e.batch_id JOIN centers ctr ON ctr.id = e.center_id WHERE 1=1 $where ORDER BY e.id DESC";
        if ($fmt = Export::requested()) {
            $this->authorize('enrollments.export');
            $rows = DB::all($sql, $params);
            Export::send($fmt, 'Student Enrollments', [
                'enrollment_code' => 'Enrollment ID', 'enrollment_date' => 'Date', 'student_code' => 'Student ID', 'student' => 'Student', 'mobile' => 'Mobile',
                'course' => 'Course', 'batch_code' => 'Batch', 'center' => 'Center', 'course_fee' => 'Course Fee', 'discount' => 'Discount',
                'payable_amount' => 'Payable', 'paid_amount' => 'Paid', 'due_amount' => 'Due', 'status' => 'Status', 'result_grade' => 'Grade',
            ], $rows, ['totals' => ['enrollment_code' => 'TOTAL', 'payable_amount' => number_format(array_sum(array_column($rows, 'payable_amount')), 2),
                'paid_amount' => number_format(array_sum(array_column($rows, 'paid_amount')), 2), 'due_amount' => number_format(array_sum(array_column($rows, 'due_amount')), 2)]]);
        }
        $this->render('enrollments/index', [
            'title' => 'Student Enrollment', 'p' => DB::paginate($sql, $params), 'statuses' => self::STATUSES,
            'courses' => DB::all('SELECT id, name FROM courses WHERE deleted_at IS NULL ORDER BY name'),
            'breadcrumbs' => ['Students' => null, 'Enrollment' => null],
        ]);
    }

    public function create(): void
    {
        $this->authorize('enrollments.create');
        $student = null;
        if ($sid = input_int('student_id')) {
            $student = DB::row('SELECT id, student_code, name, mobile, center_id FROM students WHERE id = :id AND deleted_at IS NULL', ['id' => $sid]);
        }
        $this->render('enrollments/create', [
            'title' => 'New Enrollment', 'student' => $student,
            'courses' => DB::all("SELECT c.id, CONCAT(c.code, ' – ', c.name) name FROM courses c JOIN course_statuses cs ON cs.id = c.course_status_id WHERE c.deleted_at IS NULL AND cs.code = 'ACTIVE' ORDER BY c.name"),
            'methods' => DB::all('SELECT id, name, requires_reference FROM payment_methods WHERE is_active = 1 ORDER BY id'),
            'accounts' => DB::all('SELECT id, name FROM accounts WHERE is_active = 1' . (Auth::isHeadOffice() ? '' : ' AND (center_id IS NULL' . str_replace(' AND ', ' OR ', Auth::scope('center_id')) . ')') . ' ORDER BY name'),
            'preBatch' => input_int('batch_id'),
            'preCourse' => input_int('batch_id') ? (int)DB::value('SELECT course_id FROM batches WHERE id = :b', ['b' => input_int('batch_id')]) : 0,
            'breadcrumbs' => ['Enrollment' => url('enrollments/index'), 'New' => null],
        ]);
    }

    /** AJAX: fee and capacity for a selected batch */
    public function batchinfo(): void
    {
        $this->authorize('enrollments.create');
        $b = DB::row('SELECT b.*, c.name course, ctr.name center, bs.name status, bs.allows_enrollment, st.name trainer
            FROM batches b JOIN courses c ON c.id = b.course_id JOIN centers ctr ON ctr.id = b.center_id JOIN batch_statuses bs ON bs.id = b.batch_status_id
            LEFT JOIN staff st ON st.id = b.trainer_id WHERE b.id = :id', ['id' => input_int('id')]);
        if (!$b || !Auth::canAccessCenter((int)$b['center_id'])) {
            json_response(['ok' => false, 'message' => 'Batch not found.'], 404);
        }
        $enrolled = EnrollmentService::enrolledCount((int)$b['id']);
        $dup = ($sid = input_int('student_id')) ? DB::value("SELECT enrollment_code FROM enrollments WHERE student_id = :s AND batch_id = :b AND status IN ('pending','active','completed')", ['s' => $sid, 'b' => $b['id']]) : null;
        json_response(['ok' => true, 'fee' => EnrollmentService::courseFee((int)$b['course_id'], (int)$b['center_id']), 'enrolled' => $enrolled, 'max' => (int)$b['max_students'],
            'center' => $b['center'], 'status' => $b['status'], 'open' => (int)$b['allows_enrollment'] === 1, 'start' => fmt_date($b['start_date']), 'end' => fmt_date($b['end_date']),
            'schedule' => $b['class_schedule'], 'trainer' => $b['trainer'], 'duplicate' => $dup]);
    }

    public function store(): void
    {
        $this->requirePost();
        $this->authorize('enrollments.create');
        $this->validate(['student_id|Student' => 'required|exists:students', 'batch_id|Batch' => 'required|exists:batches', 'enrollment_date|Enrollment date' => 'required|date',
            'discount|Discount' => 'numeric|min:0', 'initial_payment|Initial payment' => 'numeric|min:0']);
        $student = DB::row('SELECT * FROM students WHERE id = :id', ['id' => input_int('student_id')]);
        // Students from any center may join a batch here (lifetime ID); the batch center must be in scope (checked in service).
        if (input_date('enrollment_date') > date('Y-m-d')) {
            $this->fail('Enrollment date cannot be in the future.');
        }
        $needApproval = setting('enrollment_requires_approval', '0') === '1' && !can('enrollments.approve');
        try {
            $r = EnrollmentService::enroll([
                'student_id' => $student['id'], 'batch_id' => input_int('batch_id'), 'enrollment_date' => input_date('enrollment_date'),
                'discount' => input_money('discount'), 'discount_reason' => input('discount_reason'), 'remarks' => input('remarks'),
                'override_reason' => input('override_reason'), 'initial_payment' => can('fees.collect') ? input_money('initial_payment') : 0,
                'payment_method_id' => input_int('payment_method_id'), 'account_id' => input_id('account_id'), 'transaction_ref' => input('transaction_ref'),
                'require_approval' => $needApproval,
            ]);
        } catch (\DomainException $e) {
            $this->fail($e->getMessage());
        }
        clear_old();
        flash('success', 'Enrollment saved' . ($needApproval ? ' and sent for approval.' : '.') . ($r['receipt_id'] ? ' Money receipt generated.' : ''));
        if ($r['receipt_id']) {
            redirect(url('enrollments/view', ['id' => $r['enrollment_id'], 'print_receipt' => $r['receipt_id']]));
        }
        redirect(url('enrollments/view', ['id' => $r['enrollment_id']]));
    }

    private function load(int $id): array
    {
        $e = DB::row('SELECT e.*, s.student_code, s.name student, s.father_name, s.mobile, s.photo, c.name course, c.code course_code, c.min_attendance_percent,
                b.batch_code, b.name batch, b.start_date, b.end_date, b.class_schedule, bs.name batch_status, ctr.name center,
                au.name approved_by_name, cu.name created_by_name, xu.name cancelled_by_name, tf.enrollment_code transferred_from_code
            FROM enrollments e JOIN students s ON s.id = e.student_id JOIN courses c ON c.id = e.course_id JOIN batches b ON b.id = e.batch_id
            JOIN batch_statuses bs ON bs.id = b.batch_status_id JOIN centers ctr ON ctr.id = e.center_id
            LEFT JOIN users au ON au.id = e.approved_by LEFT JOIN users cu ON cu.id = e.created_by LEFT JOIN users xu ON xu.id = e.cancelled_by
            LEFT JOIN enrollments tf ON tf.id = e.transferred_from_id WHERE e.id = :id', ['id' => $id]) ?? $this->notFound('Enrollment');
        $this->authorizeCenter((int)$e['center_id']);
        return $e;
    }

    public function view(): void
    {
        $this->authorize('enrollments.view');
        $e = $this->load(input_int('id'));
        $ledger = DB::all('SELECT t.*, u.name uname FROM payment_transactions t LEFT JOIN users u ON u.id = t.created_by WHERE t.enrollment_id = :e ORDER BY t.id', ['e' => $e['id']]);
        $att = DB::row("SELECT COUNT(*) total, SUM(status='present') present, SUM(status='late') late, SUM(status='absent') absent, SUM(status='leave') leave_ FROM attendance_details WHERE enrollment_id = :e", ['e' => $e['id']]);
        $exams = DB::all("SELECT x.name, x.exam_type, x.exam_date, x.status, SUM(COALESCE(r.theory_marks,0)+COALESCE(r.practical_marks,0)) obtained
            FROM examinations x JOIN examination_results r ON r.examination_id = x.id AND r.enrollment_id = :e GROUP BY x.id ORDER BY x.exam_date", ['e' => $e['id']]);
        $cert = DB::row("SELECT * FROM certificates WHERE enrollment_id = :e ORDER BY id DESC LIMIT 1", ['e' => $e['id']]);
        $otherBatches = DB::all("SELECT b.id, CONCAT(b.batch_code, ' – ', ctr.name, ' (', bs.name, ')') name FROM batches b JOIN batch_statuses bs ON bs.id = b.batch_status_id
            JOIN centers ctr ON ctr.id = b.center_id WHERE b.deleted_at IS NULL AND b.course_id = :c AND b.id <> :b AND bs.allows_enrollment = 1" . Auth::scope('b.center_id'), ['c' => $e['course_id'], 'b' => $e['batch_id']]);
        $this->render('enrollments/view', [
            'title' => 'Enrollment ' . $e['enrollment_code'], 'e' => $e, 'ledger' => $ledger, 'att' => $att, 'exams' => $exams, 'cert' => $cert,
            'attPct' => EnrollmentService::attendancePercent((int)$e['id']), 'certProblems' => CertificateService::problems($e), 'otherBatches' => $otherBatches,
            'breadcrumbs' => ['Enrollment' => url('enrollments/index'), $e['enrollment_code'] => null],
        ]);
    }

    public function approve(): void
    {
        $this->requirePost();
        $this->authorize('enrollments.approve');
        $e = $this->load(input_int('id'));
        try {
            EnrollmentService::approve((int)$e['id']);
        } catch (\DomainException $ex) {
            $this->fail($ex->getMessage());
        }
        flash('success', 'Enrollment approved.');
        back();
    }

    public function cancel(): void
    {
        $this->requirePost();
        $this->authorize('enrollments.cancel');
        $e = $this->load(input_int('id'));
        $reason = trim((string)input('reason'));
        if (mb_strlen($reason) < 3) {
            $this->fail('A reason is required.');
        }
        try {
            EnrollmentService::cancel((int)$e['id'], $reason, input('as') === 'dropped' ? 'dropped' : 'cancelled');
        } catch (\DomainException $ex) {
            $this->fail($ex->getMessage());
        }
        flash('success', 'Enrollment ' . (input('as') === 'dropped' ? 'marked as dropped' : 'cancelled') . '. Payments already received remain on record.');
        back();
    }

    public function transfer(): void
    {
        $this->requirePost();
        $this->authorize('enrollments.transfer');
        $e = $this->load(input_int('id'));
        $reason = trim((string)input('reason'));
        if (mb_strlen($reason) < 3) {
            $this->fail('A reason is required for batch transfer.');
        }
        try {
            $new = EnrollmentService::transfer((int)$e['id'], input_int('new_batch_id'), $reason);
        } catch (\DomainException $ex) {
            $this->fail($ex->getMessage());
        }
        flash('success', 'Student transferred to the new batch.');
        redirect(url('enrollments/view', ['id' => $new]));
    }

    /** Change discount / remarks (ledger-adjusted, audited) */
    public function update(): void
    {
        $this->requirePost();
        $this->authorize('enrollments.edit');
        $e = $this->load(input_int('id'));
        if (!in_array($e['status'], ['pending', 'active'], true)) {
            $this->fail('Only pending or active enrollments can be changed.');
        }
        $newDiscount = input_money('discount');
        $reason = trim((string)input('discount_reason'));
        try {
            DB::transaction(function () use ($e, $newDiscount, $reason) {
                $row = DB::row('SELECT * FROM enrollments WHERE id = :id FOR UPDATE', ['id' => $e['id']]);
                $delta = round($newDiscount - (float)$row['discount'], 2);
                if ($delta != 0.0) {
                    if ($newDiscount < 0 || $newDiscount > (float)$row['course_fee']) {
                        throw new \DomainException('Discount must be between 0 and the course fee.');
                    }
                    if ($newDiscount > 0 && $reason === '') {
                        throw new \DomainException('Enter the reason for the discount.');
                    }
                    $payable = round((float)$row['course_fee'] - $newDiscount, 2);
                    if ($payable < (float)$row['paid_amount']) {
                        throw new \DomainException('Payable would become less than the amount already paid. Reverse a receipt first.');
                    }
                    EnrollmentService::ledger((int)$row['id'], (int)$row['student_id'], (int)$row['center_id'], date('Y-m-d'), $delta > 0 ? 'discount' : 'adjustment',
                        $delta < 0 ? -$delta : 0, $delta > 0 ? $delta : 0, 'Discount changed from ' . number_format((float)$row['discount'], 2) . ' to ' . number_format($newDiscount, 2) . ": $reason");
                    DB::update('enrollments', ['discount' => $newDiscount, 'discount_reason' => nullify($reason), 'payable_amount' => $payable], 'id = :id', ['id' => $row['id']]);
                }
                DB::update('enrollments', ['remarks' => nullify(input('remarks'))], 'id = :id', ['id' => $row['id']]);
                Audit::log('enrollments', 'update', $row['id'], 'Enrollment updated ' . $row['enrollment_code'], $row, ['discount' => $newDiscount, 'remarks' => input('remarks')], (int)$row['center_id']);
            });
        } catch (\DomainException $ex) {
            $this->fail($ex->getMessage());
        }
        flash('success', 'Enrollment updated.');
        back();
    }
}
