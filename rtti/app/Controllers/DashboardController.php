<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Controller;
use App\Core\DB;

final class DashboardController extends Controller
{
    public function index(): void
    {
        if (!Auth::can('dashboard.view')) {
            $this->render('dashboard/welcome', ['title' => 'Welcome']);
            return;
        }
        $from = input_date('from') ?? date('Y-m-01', strtotime('-11 months'));
        $to = input_date('to') ?? date('Y-m-d');
        // Center filter (scoped). Uses alias-specific column names.
        $cf = function (string $col) {
            $sql = Auth::scope($col);
            if ($c = input_id('center_id')) {
                $this->authorizeCenter($c);
                $sql .= " AND $col = " . (int)$c;
            }
            if ($d = input_id('district_id')) {
                $sql .= " AND $col IN (SELECT id FROM centers WHERE district_id = " . (int)$d . ')';
            }
            return $sql;
        };
        $p = ['f' => $from, 't' => $to];

        $stats = [
            'students' => (int)DB::value('SELECT COUNT(*) FROM students s WHERE s.deleted_at IS NULL' . $cf('s.center_id')),
            'active_students' => (int)DB::value("SELECT COUNT(DISTINCT e.student_id) FROM enrollments e WHERE e.status = 'active'" . $cf('e.center_id')),
            'new_students' => (int)DB::value('SELECT COUNT(*) FROM students s WHERE s.deleted_at IS NULL AND s.registration_date BETWEEN :f AND :t' . $cf('s.center_id'), $p),
            'enrollments' => (int)DB::value("SELECT COUNT(*) FROM enrollments e WHERE e.status NOT IN ('cancelled','transferred') AND e.enrollment_date BETWEEN :f AND :t" . $cf('e.center_id'), $p),
            'running_courses' => (int)DB::value("SELECT COUNT(DISTINCT b.course_id) FROM batches b JOIN batch_statuses bs ON bs.id = b.batch_status_id WHERE b.deleted_at IS NULL AND bs.code = 'RUNNING'" . $cf('b.center_id')),
            'running_batches' => (int)DB::value("SELECT COUNT(*) FROM batches b JOIN batch_statuses bs ON bs.id = b.batch_status_id WHERE b.deleted_at IS NULL AND bs.code = 'RUNNING'" . $cf('b.center_id')),
            'completed_batches' => (int)DB::value("SELECT COUNT(*) FROM batches b JOIN batch_statuses bs ON bs.id = b.batch_status_id WHERE b.deleted_at IS NULL AND bs.code = 'COMPLETED'" . $cf('b.center_id')),
            'pending_fee' => (float)DB::value("SELECT COALESCE(SUM(e.due_amount),0) FROM enrollments e WHERE e.status IN ('pending','active','completed')" . $cf('e.center_id')),
            'collection' => (float)DB::value("SELECT COALESCE(SUM(f.amount),0) FROM fee_collections f WHERE f.status = 'posted' AND f.payment_date BETWEEN :f AND :t" . $cf('f.center_id'), $p),
            'expenses' => (float)DB::value("SELECT COALESCE(SUM(x.amount),0) FROM expenses x WHERE x.status = 'approved' AND x.expense_date BETWEEN :f AND :t" . $cf('x.center_id'), $p),
            'staff' => (int)DB::value("SELECT COUNT(*) FROM staff st WHERE st.deleted_at IS NULL AND st.employment_status = 'active'" . $cf('st.center_id')),
            'centers' => (int)DB::value('SELECT COUNT(*) FROM centers c WHERE c.is_active = 1 AND c.is_head_office = 0' . $cf('c.id')),
            'certificates' => (int)DB::value("SELECT COUNT(*) FROM certificates ce WHERE ce.status = 'issued' AND ce.issue_date BETWEEN :f AND :t" . $cf('ce.center_id'), $p),
            'pending_expenses' => (int)DB::value("SELECT COUNT(*) FROM expenses x WHERE x.status = 'pending'" . $cf('x.center_id')),
            'pending_enrollments' => (int)DB::value("SELECT COUNT(*) FROM enrollments e WHERE e.status = 'pending'" . $cf('e.center_id')),
        ];

        // Monthly series
        $months = [];
        $cur = new \DateTime(date('Y-m-01', strtotime($from)));
        $end = new \DateTime(date('Y-m-01', strtotime($to)));
        while ($cur <= $end && count($months) < 36) {
            $months[$cur->format('Y-m')] = $cur->format('M y');
            $cur->modify('+1 month');
        }
        $series = function (string $sql) use ($p, $months) {
            $rows = DB::pairs($sql, $p);
            return array_map(fn($k) => round((float)($rows[$k] ?? 0), 2), array_keys($months));
        };
        $chart = [
            'labels' => array_values($months),
            'registrations' => $series("SELECT DATE_FORMAT(s.registration_date,'%Y-%m'), COUNT(*) FROM students s WHERE s.deleted_at IS NULL AND s.registration_date BETWEEN :f AND :t" . $cf('s.center_id') . ' GROUP BY 1'),
            'enrollments' => $series("SELECT DATE_FORMAT(e.enrollment_date,'%Y-%m'), COUNT(*) FROM enrollments e WHERE e.status NOT IN ('cancelled','transferred') AND e.enrollment_date BETWEEN :f AND :t" . $cf('e.center_id') . ' GROUP BY 1'),
            'income' => $series("SELECT DATE_FORMAT(f.payment_date,'%Y-%m'), SUM(f.amount) FROM fee_collections f WHERE f.status = 'posted' AND f.payment_date BETWEEN :f AND :t" . $cf('f.center_id') . ' GROUP BY 1'),
            'expense' => $series("SELECT DATE_FORMAT(x.expense_date,'%Y-%m'), SUM(x.amount) FROM expenses x WHERE x.status = 'approved' AND x.expense_date BETWEEN :f AND :t" . $cf('x.center_id') . ' GROUP BY 1'),
        ];
        $byCourse = DB::all("SELECT c.name label, COUNT(*) v FROM enrollments e JOIN courses c ON c.id = e.course_id WHERE e.status NOT IN ('cancelled','transferred') AND e.enrollment_date BETWEEN :f AND :t" . $cf('e.center_id') . ' GROUP BY c.id ORDER BY v DESC LIMIT 8', $p);
        $byDistrict = DB::all("SELECT d.name label, COUNT(*) v FROM enrollments e JOIN centers ct ON ct.id = e.center_id JOIN districts d ON d.id = ct.district_id WHERE e.status NOT IN ('cancelled','transferred') AND e.enrollment_date BETWEEN :f AND :t" . $cf('e.center_id') . ' GROUP BY d.id ORDER BY v DESC', $p);
        $batchStatus = DB::all('SELECT bs.name label, bs.color, COUNT(b.id) v FROM batch_statuses bs LEFT JOIN batches b ON b.batch_status_id = bs.id AND b.deleted_at IS NULL' . $cf('b.center_id') . ' GROUP BY bs.id ORDER BY bs.id');
        $completion = DB::all("SELECT e.status label, COUNT(*) v FROM enrollments e WHERE e.enrollment_date BETWEEN :f AND :t" . $cf('e.center_id') . ' GROUP BY e.status', $p);

        $centerPerf = DB::all("SELECT ct.id, ct.name, d.name district,
              (SELECT COUNT(*) FROM enrollments e WHERE e.center_id = ct.id AND e.status NOT IN ('cancelled','transferred') AND e.enrollment_date BETWEEN :f AND :t) enrollments,
              (SELECT COUNT(*) FROM batches b JOIN batch_statuses bs ON bs.id = b.batch_status_id WHERE b.center_id = ct.id AND b.deleted_at IS NULL AND bs.code = 'RUNNING') running,
              (SELECT COALESCE(SUM(f.amount),0) FROM fee_collections f WHERE f.center_id = ct.id AND f.status = 'posted' AND f.payment_date BETWEEN :f2 AND :t2) income,
              (SELECT COALESCE(SUM(x.amount),0) FROM expenses x WHERE x.center_id = ct.id AND x.status = 'approved' AND x.expense_date BETWEEN :f3 AND :t3) expense,
              (SELECT COALESCE(SUM(e.due_amount),0) FROM enrollments e WHERE e.center_id = ct.id AND e.status IN ('pending','active','completed')) due,
              (SELECT COUNT(*) FROM certificates ce WHERE ce.center_id = ct.id AND ce.status = 'issued' AND ce.issue_date BETWEEN :f4 AND :t4) certs
            FROM centers ct JOIN districts d ON d.id = ct.district_id WHERE ct.is_active = 1" . $cf('ct.id') . ' ORDER BY ct.is_head_office, ct.name',
            $p + ['f2' => $from, 't2' => $to, 'f3' => $from, 't3' => $to, 'f4' => $from, 't4' => $to]);

        $upcoming = DB::all("SELECT b.id, b.batch_code, b.name, b.start_date, ct.name center, c.name course,
              (SELECT COUNT(*) FROM enrollments e WHERE e.batch_id = b.id AND e.status IN ('pending','active')) enrolled, b.max_students
            FROM batches b JOIN batch_statuses bs ON bs.id = b.batch_status_id JOIN centers ct ON ct.id = b.center_id JOIN courses c ON c.id = b.course_id
            WHERE b.deleted_at IS NULL AND bs.code = 'UPCOMING'" . $cf('b.center_id') . ' ORDER BY b.start_date LIMIT 6');
        $recent = DB::all("SELECT e.id, e.enrollment_code, e.enrollment_date, s.id sid, s.name, s.student_code, c.name course, ct.name center
            FROM enrollments e JOIN students s ON s.id = e.student_id JOIN courses c ON c.id = e.course_id JOIN centers ct ON ct.id = e.center_id
            WHERE 1=1" . $cf('e.center_id') . ' ORDER BY e.id DESC LIMIT 7');
        $activity = DB::all('SELECT a.*, u.name uname FROM audit_logs a LEFT JOIN users u ON u.id = a.user_id
            WHERE a.module NOT IN ("security","export")' . (Auth::isHeadOffice() ? '' : ' AND (a.center_id IS NULL AND a.user_id = ' . (int)Auth::id() . ' OR 1=1' . Auth::scope('a.center_id') . ')') . ' ORDER BY a.id DESC LIMIT 8');

        $this->render('dashboard/index', compact('stats', 'chart', 'byCourse', 'byDistrict', 'batchStatus', 'completion', 'centerPerf', 'upcoming', 'recent', 'activity', 'from', 'to') + ['title' => 'Dashboard', 'charts' => true]);
    }
}
