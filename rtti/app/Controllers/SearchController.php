<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Controller;
use App\Core\DB;

/**
 * Global search across modules. Results are grouped by module and filtered by
 * the user's permissions and center scope.
 */
final class SearchController extends Controller
{
    private function run(string $q, int $limit, ?string $only = null): array
    {
        $q = trim($q);
        if (mb_strlen($q) < 2) {
            return [];
        }
        $like = "%$q%";
        $groups = [];
        $add = function (string $key, string $label, string $perm, string $sql, array $params, callable $map) use (&$groups, $limit, $only) {
            if (($only && $only !== $key) || !Auth::can($perm)) {
                return;
            }
            $rows = DB::all($sql . " LIMIT $limit", $params);
            if ($rows) {
                $groups[] = ['key' => $key, 'label' => $label, 'items' => array_map($map, $rows)];
            }
        };
        $p5 = fn() => ['a' => $like, 'b' => $like, 'c' => $like, 'd' => $like, 'e' => $like];

        $add('students', 'Students', 'students.view', 'SELECT s.id, s.student_code, s.name, s.father_name, s.mobile, c.name center FROM students s JOIN centers c ON c.id = s.center_id
            WHERE s.deleted_at IS NULL AND (s.student_code LIKE :a OR s.name LIKE :b OR s.father_name LIKE :c OR s.mobile LIKE :d OR s.nid LIKE :e)' . Auth::scope('s.center_id') . ' ORDER BY s.student_code = :x DESC, s.name',
            $p5() + ['x' => $q], fn($r) => ['title' => "{$r['student_code']} – {$r['name']}", 'sub' => "Father: {$r['father_name']} · {$r['mobile']} · {$r['center']}", 'url' => url('students/view', ['id' => $r['id']])]);
        $add('enrollments', 'Enrollments', 'enrollments.view', 'SELECT e.id, e.enrollment_code, s.name, c.name course, e.status FROM enrollments e JOIN students s ON s.id = e.student_id JOIN courses c ON c.id = e.course_id
            WHERE e.enrollment_code LIKE :a' . Auth::scope('e.center_id') . ' ORDER BY e.id DESC', ['a' => $like],
            fn($r) => ['title' => "{$r['enrollment_code']} – {$r['name']}", 'sub' => "{$r['course']} · {$r['status']}", 'url' => url('enrollments/view', ['id' => $r['id']])]);
        $add('courses', 'Courses', 'courses.view', 'SELECT id, code, name, fee FROM courses WHERE deleted_at IS NULL AND (code LIKE :a OR name LIKE :b) ORDER BY name', ['a' => $like, 'b' => $like],
            fn($r) => ['title' => "{$r['code']} – {$r['name']}", 'sub' => 'Fee ' . money($r['fee']), 'url' => url('courses/view', ['id' => $r['id']])]);
        $add('batches', 'Batches', 'batches.view', 'SELECT b.id, b.batch_code, b.name, ctr.name center, b.start_date FROM batches b JOIN centers ctr ON ctr.id = b.center_id
            WHERE b.deleted_at IS NULL AND (b.batch_code LIKE :a OR b.name LIKE :b)' . Auth::scope('b.center_id') . ' ORDER BY b.start_date DESC', ['a' => $like, 'b' => $like],
            fn($r) => ['title' => "{$r['batch_code']} – {$r['name']}", 'sub' => "{$r['center']} · starts " . fmt_date($r['start_date']), 'url' => url('batches/view', ['id' => $r['id']])]);
        $add('certificates', 'Certificates', 'certificates.view', 'SELECT ce.id, ce.certificate_no, s.name, ce.status FROM certificates ce JOIN students s ON s.id = ce.student_id
            WHERE ce.certificate_no LIKE :a' . Auth::scope('ce.center_id'), ['a' => $like],
            fn($r) => ['title' => "{$r['certificate_no']} – {$r['name']}", 'sub' => ucfirst($r['status']), 'url' => url('certificates/view', ['id' => $r['id']])]);
        $add('receipts', 'Money receipts', 'fees.view', 'SELECT f.id, f.receipt_no, f.amount, f.payment_date, s.name, f.status FROM fee_collections f JOIN students s ON s.id = f.student_id
            WHERE (f.receipt_no LIKE :a OR f.transaction_ref LIKE :b)' . Auth::scope('f.center_id') . ' ORDER BY f.id DESC', ['a' => $like, 'b' => $like],
            fn($r) => ['title' => "{$r['receipt_no']} – {$r['name']}", 'sub' => money($r['amount']) . ' · ' . fmt_date($r['payment_date']) . ' · ' . $r['status'], 'url' => url('fees/receipt', ['id' => $r['id']])]);
        $add('staff', 'Staff', 'staff.view', 'SELECT st.id, st.employee_code, st.name, st.mobile, c.name center FROM staff st JOIN centers c ON c.id = st.center_id
            WHERE st.deleted_at IS NULL AND (st.employee_code LIKE :a OR st.name LIKE :b OR st.mobile LIKE :c)' . Auth::scope('st.center_id') . ' ORDER BY st.name', ['a' => $like, 'b' => $like, 'c' => $like],
            fn($r) => ['title' => "{$r['employee_code']} – {$r['name']}", 'sub' => "{$r['mobile']} · {$r['center']}", 'url' => url('staff/view', ['id' => $r['id']])]);
        $add('assets', 'Assets', 'assets.view', 'SELECT a.id, a.asset_code, a.name, a.serial_no, c.name center FROM assets a JOIN centers c ON c.id = a.center_id
            WHERE a.deleted_at IS NULL AND (a.asset_code LIKE :a OR a.name LIKE :b OR a.serial_no LIKE :c)' . Auth::scope('a.center_id') . ' ORDER BY a.asset_code', ['a' => $like, 'b' => $like, 'c' => $like],
            fn($r) => ['title' => "{$r['asset_code']} – {$r['name']}", 'sub' => trim("{$r['serial_no']} · {$r['center']}", ' ·'), 'url' => url('assets/view', ['id' => $r['id']])]);
        $add('expenses', 'Expense vouchers', 'expenses.view', 'SELECT x.id, x.voucher_no, x.description, x.amount FROM expenses x WHERE (x.voucher_no LIKE :a OR x.description LIKE :b)' . Auth::scope('x.center_id') . ' ORDER BY x.id DESC',
            ['a' => $like, 'b' => $like], fn($r) => ['title' => "{$r['voucher_no']} – " . mb_strimwidth($r['description'], 0, 50, '…'), 'sub' => money($r['amount']), 'url' => url('expenses/view', ['id' => $r['id']])]);
        return $groups;
    }

    public function suggest(): void
    {
        json_response(['groups' => $this->run((string)input('q', ''), 5)]);
    }

    public function index(): void
    {
        $q = (string)input('q', '');
        $only = input('module') ?: null;
        $groups = $this->run($q, $only ? 200 : 25, $only);
        $this->render('search/index', ['title' => 'Search', 'q' => $q, 'groups' => $groups, 'only' => $only, 'breadcrumbs' => ['Search' => null]]);
    }
}
