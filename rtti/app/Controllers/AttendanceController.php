<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Audit;
use App\Core\Auth;
use App\Core\Controller;
use App\Core\DB;
use App\Core\Export;

final class AttendanceController extends Controller
{
    private function batchOptions(): array
    {
        return DB::all("SELECT b.id, CONCAT(b.batch_code, ' – ', c.name, ' (', bs.name, ')') name FROM batches b JOIN courses c ON c.id = b.course_id
            JOIN batch_statuses bs ON bs.id = b.batch_status_id WHERE b.deleted_at IS NULL" . Auth::scope('b.center_id') . " ORDER BY bs.code = 'RUNNING' DESC, b.start_date DESC");
    }

    private function batch(int $id): array
    {
        $b = DB::row('SELECT b.*, c.name course, ctr.name center FROM batches b JOIN courses c ON c.id = b.course_id JOIN centers ctr ON ctr.id = b.center_id WHERE b.id = :id AND b.deleted_at IS NULL', ['id' => $id]) ?? $this->notFound('Batch');
        $this->authorizeCenter((int)$b['center_id']);
        return $b;
    }

    /** List of attendance sheets */
    public function index(): void
    {
        $this->authorize('attendance.view');
        [$where, $params] = $this->centerFilter('b.center_id');
        if ($bid = input_id('batch_id')) {
            $where .= ' AND a.batch_id = :b';
            $params['b'] = $bid;
        }
        if ($from = input_date('from')) {
            $where .= ' AND a.attendance_date >= :from';
            $params['from'] = $from;
        }
        if ($to = input_date('to')) {
            $where .= ' AND a.attendance_date <= :to';
            $params['to'] = $to;
        }
        $sql = "SELECT a.*, b.batch_code, c.name course, ctr.name center, st.name trainer,
                COUNT(d.id) total, SUM(d.status='present') present, SUM(d.status='late') late, SUM(d.status='absent') absent, SUM(d.status='leave') leave_
            FROM attendance a JOIN batches b ON b.id = a.batch_id JOIN courses c ON c.id = b.course_id JOIN centers ctr ON ctr.id = b.center_id
            LEFT JOIN staff st ON st.id = a.trainer_id LEFT JOIN attendance_details d ON d.attendance_id = a.id
            WHERE 1=1 $where GROUP BY a.id ORDER BY a.attendance_date DESC, a.id DESC";
        $this->render('attendance/index', ['title' => 'Attendance', 'p' => DB::paginate($sql, $params), 'batches' => $this->batchOptions(),
            'breadcrumbs' => ['Academics' => null, 'Attendance' => null]]);
    }

    /** Take / edit attendance for a batch and date */
    public function take(): void
    {
        $this->authorize('attendance.take');
        $bid = input_int('batch_id');
        $date = input_date('date') ?? date('Y-m-d');
        $b = $bid ? $this->batch($bid) : null;
        $rows = [];
        $sheet = null;
        if ($b) {
            $sheet = DB::row('SELECT * FROM attendance WHERE batch_id = :b AND attendance_date = :d', ['b' => $b['id'], 'd' => $date]);
            $rows = DB::all("SELECT e.id, s.student_code, s.name, s.photo, d.status, d.remarks,
                    (SELECT ROUND(SUM(x.status IN ('present','late'))*100/COUNT(*),0) FROM attendance_details x WHERE x.enrollment_id = e.id) pct
                FROM enrollments e JOIN students s ON s.id = e.student_id
                LEFT JOIN attendance_details d ON d.enrollment_id = e.id AND d.attendance_id = :a
                WHERE e.batch_id = :b AND (e.status IN ('active','completed') OR d.id IS NOT NULL) ORDER BY s.name", ['a' => $sheet['id'] ?? 0, 'b' => $b['id']]);
        }
        $this->render('attendance/take', ['title' => 'Take Attendance', 'b' => $b, 'date' => $date, 'rows' => $rows, 'sheet' => $sheet, 'batches' => $this->batchOptions(),
            'trainers' => $b ? DB::all("SELECT id, name FROM staff WHERE is_trainer = 1 AND deleted_at IS NULL AND center_id = :c ORDER BY name", ['c' => $b['center_id']]) : [],
            'breadcrumbs' => ['Attendance' => url('attendance/index'), 'Take' => null]]);
    }

    public function save(): void
    {
        $this->requirePost();
        $this->authorize('attendance.take');
        $b = $this->batch(input_int('batch_id'));
        $date = input_date('date');
        if (!$date || $date > date('Y-m-d')) {
            $this->fail('Attendance date is invalid or in the future.');
        }
        if ($date < $b['start_date']) {
            $this->fail('Date is before the batch start date.');
        }
        $marks = (array)($_POST['status'] ?? []);
        if (!$marks) {
            $this->fail('No students to record.');
        }
        $valid = DB::column("SELECT id FROM enrollments WHERE batch_id = :b", ['b' => $b['id']]);
        DB::transaction(function () use ($b, $date, $marks, $valid) {
            $sheet = DB::row('SELECT * FROM attendance WHERE batch_id = :b AND attendance_date = :d FOR UPDATE', ['b' => $b['id'], 'd' => $date]);
            $data = ['topic' => nullify(input('topic')), 'trainer_id' => input_id('trainer_id') ?? $b['trainer_id'], 'taken_by' => Auth::id()];
            if ($sheet) {
                DB::update('attendance', $data, 'id = :id', ['id' => $sheet['id']]);
                $aid = (int)$sheet['id'];
            } else {
                $aid = DB::insert('attendance', $data + ['batch_id' => $b['id'], 'attendance_date' => $date]);
            }
            foreach ($marks as $eid => $st) {
                if (!in_array((int)$eid, array_map('intval', $valid), true) || !in_array($st, ['present', 'absent', 'late', 'leave'], true)) {
                    continue;
                }
                DB::query('INSERT INTO attendance_details (attendance_id, enrollment_id, status, remarks) VALUES (:a, :e, :s, :r)
                    ON DUPLICATE KEY UPDATE status = VALUES(status), remarks = VALUES(remarks)', ['a' => $aid, 'e' => (int)$eid, 's' => $st, 'r' => nullify($_POST['remarks'][$eid] ?? null)]);
            }
            Audit::log('attendance', $sheet ? 'update' : 'create', $aid, "Attendance {$b['batch_code']} on $date (" . count($marks) . ' students)', null, null, (int)$b['center_id']);
        });
        flash('success', 'Attendance saved for ' . fmt_date($date) . '.');
        redirect(url('attendance/take', ['batch_id' => $b['id'], 'date' => $date]));
    }

    /** Monthly / batch-wise attendance register (students x dates) */
    public function report(): void
    {
        $this->authorize('attendance.view');
        $bid = input_int('batch_id');
        $month = preg_match('/^\d{4}-\d{2}$/', (string)input('month')) ? (string)input('month') : date('Y-m');
        $b = $bid ? $this->batch($bid) : null;
        $dates = $matrix = $students = [];
        if ($b) {
            $dates = DB::column("SELECT attendance_date FROM attendance WHERE batch_id = :b AND DATE_FORMAT(attendance_date, '%Y-%m') = :m ORDER BY attendance_date", ['b' => $b['id'], 'm' => $month]);
            $students = DB::all("SELECT e.id, s.student_code, s.name FROM enrollments e JOIN students s ON s.id = e.student_id WHERE e.batch_id = :b AND e.status NOT IN ('cancelled','transferred') ORDER BY s.name", ['b' => $b['id']]);
            foreach (DB::all("SELECT a.attendance_date, d.enrollment_id, d.status FROM attendance a JOIN attendance_details d ON d.attendance_id = a.id
                WHERE a.batch_id = :b AND DATE_FORMAT(a.attendance_date, '%Y-%m') = :m", ['b' => $b['id'], 'm' => $month]) as $r) {
                $matrix[$r['enrollment_id']][$r['attendance_date']] = $r['status'];
            }
            if ($fmt = Export::requested()) {
                $cols = ['student_code' => 'Student ID', 'name' => 'Name'];
                foreach ($dates as $d) {
                    $cols[$d] = date('d', strtotime($d));
                }
                $cols['pct'] = '%';
                $rows = [];
                $abbr = ['present' => 'P', 'absent' => 'A', 'late' => 'L', 'leave' => 'LV'];
                foreach ($students as $s) {
                    $row = $s;
                    $att = 0;
                    foreach ($dates as $d) {
                        $st = $matrix[$s['id']][$d] ?? '';
                        $row[$d] = $abbr[$st] ?? '';
                        $att += in_array($st, ['present', 'late'], true) ? 1 : 0;
                    }
                    $row['pct'] = $dates ? round($att * 100 / count($dates)) : '';
                    $rows[] = $row;
                }
                Export::send($fmt, "Attendance {$b['batch_code']} " . date('F Y', strtotime($month . '-01')), $cols, $rows, ['subtitle' => $b['course'] . ' · ' . $b['center']]);
            }
        }
        $this->render('attendance/report', ['title' => 'Attendance Report', 'b' => $b, 'month' => $month, 'dates' => $dates, 'students' => $students, 'matrix' => $matrix,
            'batches' => $this->batchOptions(), 'breadcrumbs' => ['Attendance' => url('attendance/index'), 'Monthly report' => null]]);
    }

    /** Trainer / staff attendance */
    public function staff(): void
    {
        $this->authorize('attendance.staff');
        $centers = Auth::centerOptions();
        $cid = input_int('center_id') ?: (int)(Auth::defaultCenterId() ?? ($centers[0]['id'] ?? 0));
        $this->authorizeCenter($cid);
        $date = input_date('date') ?? date('Y-m-d');
        if (is_post()) {
            csrf_check();
            if ($date > date('Y-m-d')) {
                $this->fail('Date cannot be in the future.');
            }
            $ids = DB::column('SELECT id FROM staff WHERE center_id = :c AND deleted_at IS NULL', ['c' => $cid]);
            foreach ((array)($_POST['status'] ?? []) as $sid => $st) {
                if (in_array((int)$sid, array_map('intval', $ids), true) && in_array($st, ['present', 'absent', 'late', 'leave'], true)) {
                    DB::query('INSERT INTO staff_attendance (staff_id, attendance_date, status, remarks, taken_by) VALUES (:s,:d,:st,:r,:u)
                        ON DUPLICATE KEY UPDATE status = VALUES(status), remarks = VALUES(remarks), taken_by = VALUES(taken_by)',
                        ['s' => (int)$sid, 'd' => $date, 'st' => $st, 'r' => nullify($_POST['remarks'][$sid] ?? null), 'u' => Auth::id()]);
                }
            }
            Audit::log('attendance', 'staff', null, "Staff attendance saved for $date", null, null, $cid);
            flash('success', 'Staff attendance saved.');
            redirect(url('attendance/staff', ['center_id' => $cid, 'date' => $date]));
        }
        $rows = DB::all("SELECT st.id, st.employee_code, st.name, st.is_trainer, dg.name designation, a.status, a.remarks,
                (SELECT ROUND(SUM(x.status IN ('present','late'))*100/COUNT(*),0) FROM staff_attendance x WHERE x.staff_id = st.id AND x.attendance_date >= DATE_SUB(:d2, INTERVAL 30 DAY)) pct
            FROM staff st LEFT JOIN designations dg ON dg.id = st.designation_id LEFT JOIN staff_attendance a ON a.staff_id = st.id AND a.attendance_date = :d
            WHERE st.center_id = :c AND st.deleted_at IS NULL AND st.employment_status IN ('active','on_leave') ORDER BY st.is_trainer DESC, st.name", ['d' => $date, 'd2' => $date, 'c' => $cid]);
        $this->render('attendance/staff', ['title' => 'Trainer & Staff Attendance', 'rows' => $rows, 'centers' => $centers, 'cid' => $cid, 'date' => $date,
            'breadcrumbs' => ['Attendance' => url('attendance/index'), 'Staff' => null]]);
    }
}
