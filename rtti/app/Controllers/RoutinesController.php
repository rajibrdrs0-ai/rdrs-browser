<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Audit;
use App\Core\Auth;
use App\Core\Controller;
use App\Core\DB;

/** Class routine (weekly timetable) and trainer workload */
final class RoutinesController extends Controller
{
    public const DAYS = [6 => 'Saturday', 0 => 'Sunday', 1 => 'Monday', 2 => 'Tuesday', 3 => 'Wednesday', 4 => 'Thursday', 5 => 'Friday'];

    public function index(): void
    {
        $this->authorize('routines.view');
        [$where, $params] = $this->centerFilter('b.center_id');
        if ($bid = input_id('batch_id')) {
            $where .= ' AND r.batch_id = :b';
            $params['b'] = $bid;
        }
        if ($t = input_id('trainer_id')) {
            $where .= ' AND r.trainer_id = :t';
            $params['t'] = $t;
        }
        $rows = DB::all("SELECT r.*, b.batch_code, b.center_id, c.name course, ctr.name center, st.name trainer
            FROM class_routines r JOIN batches b ON b.id = r.batch_id JOIN courses c ON c.id = b.course_id JOIN centers ctr ON ctr.id = b.center_id
            JOIN batch_statuses bs ON bs.id = b.batch_status_id LEFT JOIN staff st ON st.id = r.trainer_id
            WHERE b.deleted_at IS NULL AND bs.code IN ('UPCOMING','RUNNING','ON_HOLD') $where ORDER BY r.start_time", $params);
        $grid = [];
        foreach ($rows as $r) {
            $grid[(int)$r['day_of_week']][] = $r;
        }
        $scopeB = Auth::scope('b.center_id');
        $workload = DB::all("SELECT st.id, st.name, st.employee_code, ctr.name center,
                COUNT(DISTINCT r.batch_id) batches, COUNT(r.id) slots,
                ROUND(SUM(TIME_TO_SEC(TIMEDIFF(r.end_time, r.start_time)))/3600, 1) weekly_hours,
                (SELECT COUNT(*) FROM attendance a WHERE a.trainer_id = st.id AND a.attendance_date >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)) classes_30d
            FROM staff st JOIN centers ctr ON ctr.id = st.center_id
            LEFT JOIN class_routines r ON r.trainer_id = st.id AND r.batch_id IN (SELECT b.id FROM batches b JOIN batch_statuses bs ON bs.id = b.batch_status_id WHERE bs.code IN ('UPCOMING','RUNNING') AND b.deleted_at IS NULL)
            WHERE st.is_trainer = 1 AND st.deleted_at IS NULL AND st.employment_status = 'active'" . Auth::scope('st.center_id') . ' GROUP BY st.id ORDER BY weekly_hours DESC, st.name');
        $this->render('routines/index', ['title' => 'Class Routine', 'grid' => $grid, 'workload' => $workload,
            'batches' => DB::all("SELECT b.id, CONCAT(b.batch_code, ' – ', c.name) name FROM batches b JOIN courses c ON c.id = b.course_id JOIN batch_statuses bs ON bs.id = b.batch_status_id
                WHERE b.deleted_at IS NULL AND bs.code IN ('UPCOMING','RUNNING','ON_HOLD') $scopeB ORDER BY b.batch_code"),
            'trainers' => DB::all("SELECT id, CONCAT(name, ' (', employee_code, ')') name FROM staff WHERE deleted_at IS NULL AND is_trainer = 1 AND employment_status = 'active'" . Auth::scope('center_id') . ' ORDER BY name'),
            'breadcrumbs' => ['Academics' => null, 'Class Routine' => null]]);
    }

    public function save(): void
    {
        $this->requirePost();
        $this->authorize('routines.manage');
        $this->validate(['batch_id|Batch' => 'required|exists:batches', 'day_of_week|Day' => 'required|in:0,1,2,3,4,5,6', 'start_time|Start' => 'required', 'end_time|End' => 'required', 'trainer_id|Trainer' => 'exists:staff']);
        $b = DB::row('SELECT * FROM batches WHERE id = :id', ['id' => input_int('batch_id')]);
        $this->authorizeCenter((int)$b['center_id']);
        $start = (string)input('start_time');
        $end = (string)input('end_time');
        if ($end <= $start) {
            $this->fail('End time must be after start time.');
        }
        $day = input_int('day_of_week');
        $overlap = 'r.day_of_week = :d AND r.start_time < :e AND r.end_time > :s AND b.deleted_at IS NULL AND bs.code IN (\'UPCOMING\',\'RUNNING\',\'ON_HOLD\')';
        $base = 'SELECT b.batch_code FROM class_routines r JOIN batches b ON b.id = r.batch_id JOIN batch_statuses bs ON bs.id = b.batch_status_id WHERE ' . $overlap;
        if ($c = DB::value($base . ' AND r.batch_id = :b', ['d' => $day, 'e' => $end, 's' => $start, 'b' => $b['id']])) {
            $this->fail('This batch already has a class at that time.');
        }
        if (($t = input_id('trainer_id')) && ($c = DB::value($base . ' AND r.trainer_id = :t', ['d' => $day, 'e' => $end, 's' => $start, 't' => $t]))) {
            $this->fail("Trainer clash: the trainer has another class at that time ($c).");
        }
        if (($room = input('room')) && ($c = DB::value($base . ' AND r.room = :room AND b.center_id = :ctr', ['d' => $day, 'e' => $end, 's' => $start, 'room' => $room, 'ctr' => $b['center_id']]))) {
            $this->fail("Room clash: $room is used by $c at that time.");
        }
        $id = DB::insert('class_routines', ['batch_id' => $b['id'], 'day_of_week' => $day, 'start_time' => $start, 'end_time' => $end,
            'subject' => nullify(input('subject')), 'trainer_id' => input_id('trainer_id') ?? $b['trainer_id'], 'room' => nullify($room)]);
        Audit::log('routines', 'create', $id, "Routine slot added to {$b['batch_code']}", null, null, (int)$b['center_id']);
        flash('success', 'Class added to routine.');
        redirect(url('routines/index', ['batch_id' => $b['id']]));
    }

    public function delete(): void
    {
        $this->requirePost();
        $this->authorize('routines.manage');
        $r = DB::row('SELECT r.*, b.center_id, b.batch_code FROM class_routines r JOIN batches b ON b.id = r.batch_id WHERE r.id = :id', ['id' => input_int('id')]) ?? $this->notFound();
        $this->authorizeCenter((int)$r['center_id']);
        DB::query('DELETE FROM class_routines WHERE id = :id', ['id' => $r['id']]);
        Audit::log('routines', 'delete', $r['id'], "Routine slot removed from {$r['batch_code']}", $r, null, (int)$r['center_id']);
        flash('success', 'Class removed from routine.');
        back();
    }
}
