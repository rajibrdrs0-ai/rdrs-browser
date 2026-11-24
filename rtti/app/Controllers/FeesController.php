<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Controller;
use App\Core\DB;
use App\Core\Export;
use App\Services\FeeService;

final class FeesController extends Controller
{
    /** Fee collection register */
    public function index(): void
    {
        $this->authorize('fees.view');
        [$where, $params] = $this->centerFilter('f.center_id');
        $from = input_date('from') ?? date('Y-m-01');
        $to = input_date('to') ?? date('Y-m-d');
        $where .= ' AND f.payment_date BETWEEN :from AND :to';
        $params += ['from' => $from, 'to' => $to];
        if ($q = input('q')) {
            $where .= ' AND (f.receipt_no LIKE :q OR f.transaction_ref LIKE :q2 OR s.student_code LIKE :q3 OR s.name LIKE :q4 OR e.enrollment_code LIKE :q5)';
            $params += ['q' => "%$q%", 'q2' => "%$q%", 'q3' => "%$q%", 'q4' => "%$q%", 'q5' => "%$q%"];
        }
        if ($m = input_id('payment_method_id')) {
            $where .= ' AND f.payment_method_id = :m';
            $params['m'] = $m;
        }
        if ($c = input_id('course_id')) {
            $where .= ' AND e.course_id = :c';
            $params['c'] = $c;
        }
        if ($u = input_id('received_by')) {
            $where .= ' AND f.received_by = :u';
            $params['u'] = $u;
        }
        if (in_array($st = input('status'), ['posted', 'reversed'], true)) {
            $where .= ' AND f.status = :st';
            $params['st'] = $st;
        }
        $sql = "SELECT f.*, s.student_code, s.name student, e.enrollment_code, c.name course, b.batch_code, ctr.name center, pm.name method, u.name received_by_name
            FROM fee_collections f JOIN students s ON s.id = f.student_id JOIN enrollments e ON e.id = f.enrollment_id JOIN courses c ON c.id = e.course_id
            JOIN batches b ON b.id = e.batch_id JOIN centers ctr ON ctr.id = f.center_id JOIN payment_methods pm ON pm.id = f.payment_method_id JOIN users u ON u.id = f.received_by
            WHERE 1=1 $where ORDER BY f.payment_date DESC, f.id DESC";
        $totals = DB::row("SELECT COUNT(*) n, COALESCE(SUM(IF(f.status='posted', f.amount, 0)),0) posted, COALESCE(SUM(IF(f.status='reversed', f.amount, 0)),0) reversed
            FROM fee_collections f JOIN students s ON s.id = f.student_id JOIN enrollments e ON e.id = f.enrollment_id WHERE 1=1 $where", $params);
        if ($fmt = Export::requested()) {
            $this->authorize('fees.export');
            $rows = DB::all($sql, $params);
            Export::send($fmt, 'Fee Collection Register', [
                'receipt_no' => 'Receipt No', 'payment_date' => 'Date', 'student_code' => 'Student ID', 'student' => 'Student', 'enrollment_code' => 'Enrollment',
                'course' => 'Course', 'batch_code' => 'Batch', 'center' => 'Center', 'method' => 'Method', 'transaction_ref' => 'Transaction ID',
                'amount' => 'Amount', 'due_after' => 'Due After', 'received_by_name' => 'Received By', 'status' => 'Status',
            ], $rows, ['subtitle' => fmt_date($from) . ' – ' . fmt_date($to), 'totals' => ['receipt_no' => 'TOTAL (posted)', 'amount' => number_format((float)$totals['posted'], 2)]]);
        }
        $byMethod = DB::all("SELECT pm.name, SUM(f.amount) total FROM fee_collections f JOIN payment_methods pm ON pm.id = f.payment_method_id JOIN students s ON s.id = f.student_id
            JOIN enrollments e ON e.id = f.enrollment_id WHERE f.status = 'posted' $where GROUP BY pm.id ORDER BY total DESC", $params);
        $this->render('fees/index', ['title' => 'Fee Collection', 'p' => DB::paginate($sql, $params), 'totals' => $totals, 'byMethod' => $byMethod, 'from' => $from, 'to' => $to,
            'methods' => DB::all('SELECT id, name FROM payment_methods ORDER BY id'),
            'courses' => DB::all('SELECT id, name FROM courses WHERE deleted_at IS NULL ORDER BY name'),
            'breadcrumbs' => ['Accounts' => null, 'Fee Collection' => null]]);
    }

    public function collect(): void
    {
        $this->authorize('fees.collect');
        $enrollments = [];
        $student = null;
        $sid = input_int('student_id');
        if ($eid = input_int('enrollment_id')) {
            $sid = (int)DB::value('SELECT student_id FROM enrollments WHERE id = :id', ['id' => $eid]);
        }
        if ($sid) {
            $student = DB::row('SELECT id, student_code, name, father_name, mobile, photo FROM students WHERE id = :id AND deleted_at IS NULL', ['id' => $sid]);
            $enrollments = DB::all("SELECT e.*, c.name course, b.batch_code, ctr.name center FROM enrollments e JOIN courses c ON c.id = e.course_id JOIN batches b ON b.id = e.batch_id
                JOIN centers ctr ON ctr.id = e.center_id WHERE e.student_id = :s AND e.status IN ('pending','active','completed')" . Auth::scope('e.center_id') . ' ORDER BY e.due_amount DESC, e.id DESC', ['s' => $sid]);
        }
        $this->render('fees/collect', ['title' => 'Collect Fee', 'student' => $student, 'enrollments' => $enrollments, 'selected' => $eid ?: ($enrollments[0]['id'] ?? 0),
            'methods' => DB::all('SELECT id, name, requires_reference FROM payment_methods WHERE is_active = 1 ORDER BY id'),
            'accounts' => DB::all('SELECT id, name FROM accounts WHERE is_active = 1' . (Auth::isHeadOffice() ? '' : ' AND (center_id IS NULL' . str_replace(' AND ', ' OR ', Auth::scope('center_id')) . ')') . ' ORDER BY name'),
            'breadcrumbs' => ['Fee Collection' => url('fees/index'), 'Collect' => null]]);
    }

    public function store(): void
    {
        $this->requirePost();
        $this->authorize('fees.collect');
        $this->validate(['enrollment_id|Enrollment' => 'required|exists:enrollments', 'amount|Amount' => 'required|numeric|min:0.01', 'payment_date|Payment date' => 'required|date', 'payment_method_id|Payment method' => 'required']);
        try {
            $id = FeeService::collect([
                'enrollment_id' => input_int('enrollment_id'), 'amount' => input_money('amount'), 'payment_date' => input_date('payment_date'),
                'payment_method_id' => input_int('payment_method_id'), 'account_id' => input_id('account_id'), 'transaction_ref' => input('transaction_ref'), 'remarks' => input('remarks'),
            ]);
        } catch (\DomainException $e) {
            $this->fail($e->getMessage());
        }
        clear_old();
        flash('success', 'Payment received. Receipt ' . DB::value('SELECT receipt_no FROM fee_collections WHERE id = :id', ['id' => $id]) . ' generated.');
        redirect(url('fees/receipt', ['id' => $id, 'new' => 1]));
    }

    /** Printable money receipt (office + student copy) */
    public function receipt(): void
    {
        $this->authorize('fees.view');
        $f = DB::row('SELECT f.*, s.student_code, s.name student, s.father_name, s.mobile, e.enrollment_code, e.course_fee, e.discount, c.name course, b.batch_code,
                ctr.name center, ctr.address center_address, ctr.mobile center_mobile, pm.name method, u.name received_by_name, a.name account, ru.name reversed_by_name
            FROM fee_collections f JOIN students s ON s.id = f.student_id JOIN enrollments e ON e.id = f.enrollment_id JOIN courses c ON c.id = e.course_id
            JOIN batches b ON b.id = e.batch_id JOIN centers ctr ON ctr.id = f.center_id JOIN payment_methods pm ON pm.id = f.payment_method_id
            JOIN users u ON u.id = f.received_by LEFT JOIN accounts a ON a.id = f.account_id LEFT JOIN users ru ON ru.id = f.reversed_by WHERE f.id = :id', ['id' => input_int('id')]) ?? $this->notFound('Receipt');
        $this->authorizeCenter((int)$f['center_id']);
        $this->render('fees/receipt', ['title' => 'Money Receipt ' . $f['receipt_no'], 'f' => $f], 'print');
    }

    /** Reversal: never deletes – marks receipt reversed and adds a counter ledger entry */
    public function reverse(): void
    {
        $this->requirePost();
        $this->authorize('fees.reverse');
        $reason = trim((string)input('reason'));
        if (mb_strlen($reason) < 5) {
            $this->fail('Please enter a clear reason (at least 5 characters) for the reversal.');
        }
        try {
            FeeService::reverse(input_int('id'), $reason);
        } catch (\DomainException $e) {
            $this->fail($e->getMessage());
        }
        flash('success', 'Receipt reversed. The original transaction is preserved in the ledger.');
        back();
    }

    /** Outstanding dues */
    public function dues(): void
    {
        $this->authorize('fees.view');
        [$where, $params] = $this->centerFilter('e.center_id');
        if ($q = input('q')) {
            $where .= ' AND (s.student_code LIKE :q OR s.name LIKE :q2 OR s.mobile LIKE :q3 OR e.enrollment_code LIKE :q4)';
            $params += ['q' => "%$q%", 'q2' => "%$q%", 'q3' => "%$q%", 'q4' => "%$q%"];
        }
        foreach (['course_id' => 'e.course_id', 'batch_id' => 'e.batch_id'] as $k => $col) {
            if ($v = input_id($k)) {
                $where .= " AND $col = :$k";
                $params[$k] = $v;
            }
        }
        $sql = "SELECT e.*, s.student_code, s.name student, s.mobile, c.name course, b.batch_code, b.end_date, ctr.name center,
                (SELECT MAX(payment_date) FROM fee_collections f WHERE f.enrollment_id = e.id AND f.status = 'posted') last_payment,
                DATEDIFF(CURDATE(), e.enrollment_date) days
            FROM enrollments e JOIN students s ON s.id = e.student_id JOIN courses c ON c.id = e.course_id JOIN batches b ON b.id = e.batch_id
            JOIN centers ctr ON ctr.id = e.center_id WHERE e.status IN ('pending','active','completed') AND e.due_amount > 0 $where ORDER BY e.due_amount DESC";
        $total = (float)DB::value("SELECT COALESCE(SUM(e.due_amount),0) FROM enrollments e JOIN students s ON s.id = e.student_id WHERE e.status IN ('pending','active','completed') AND e.due_amount > 0 $where", $params);
        if ($fmt = Export::requested()) {
            Export::send($fmt, 'Due List', ['student_code' => 'Student ID', 'student' => 'Student', 'mobile' => 'Mobile', 'enrollment_code' => 'Enrollment', 'course' => 'Course',
                'batch_code' => 'Batch', 'center' => 'Center', 'payable_amount' => 'Payable', 'paid_amount' => 'Paid', 'due_amount' => 'Due', 'last_payment' => 'Last Payment'],
                DB::all($sql, $params), ['totals' => ['student_code' => 'TOTAL', 'due_amount' => number_format($total, 2)]]);
        }
        $this->render('fees/dues', ['title' => 'Due List', 'p' => DB::paginate($sql, $params), 'total' => $total,
            'courses' => DB::all('SELECT id, name FROM courses WHERE deleted_at IS NULL ORDER BY name'),
            'breadcrumbs' => ['Accounts' => null, 'Due List' => null]]);
    }

    /** Student-wise ledger */
    public function ledger(): void
    {
        $this->authorize('fees.view');
        $student = null;
        $entries = [];
        if ($sid = input_int('student_id')) {
            $student = DB::row('SELECT s.*, c.name center FROM students s JOIN centers c ON c.id = s.center_id WHERE s.id = :id', ['id' => $sid]) ?? $this->notFound('Student');
            $entries = DB::all('SELECT t.*, e.enrollment_code, c.name course, f.receipt_no, ctr.name center FROM payment_transactions t JOIN enrollments e ON e.id = t.enrollment_id
                JOIN courses c ON c.id = e.course_id JOIN centers ctr ON ctr.id = t.center_id LEFT JOIN fee_collections f ON f.id = t.fee_collection_id
                WHERE t.student_id = :s' . Auth::scope('t.center_id') . ' ORDER BY t.txn_date, t.id', ['s' => $sid]);
            if (!$entries && !Auth::canAccessCenter((int)$student['center_id'])) {
                $this->deny('This student belongs to another center.');
            }
            if ($fmt = Export::requested()) {
                $bal = 0;
                foreach ($entries as &$en) {
                    $bal += $en['debit'] - $en['credit'];
                    $en['balance'] = number_format($bal, 2);
                }
                unset($en);
                Export::send($fmt, "Student Ledger – {$student['student_code']} {$student['name']}", ['txn_date' => 'Date', 'enrollment_code' => 'Enrollment', 'course' => 'Course',
                    'txn_type' => 'Type', 'narration' => 'Narration', 'receipt_no' => 'Receipt', 'debit' => 'Debit', 'credit' => 'Credit', 'balance' => 'Balance'], $entries);
            }
        }
        $this->render('fees/ledger', ['title' => 'Student Ledger', 'student' => $student, 'entries' => $entries, 'breadcrumbs' => ['Accounts' => null, 'Student Ledger' => null]]);
    }

    /** Daily collection summary */
    public function daily(): void
    {
        $this->authorize('fees.view');
        $date = input_date('date') ?? date('Y-m-d');
        [$where, $params] = $this->centerFilter('f.center_id');
        $params['d'] = $date;
        $rows = DB::all("SELECT ctr.name center, pm.name method, u.name collector, COUNT(*) receipts, SUM(f.amount) total
            FROM fee_collections f JOIN centers ctr ON ctr.id = f.center_id JOIN payment_methods pm ON pm.id = f.payment_method_id JOIN users u ON u.id = f.received_by
            WHERE f.status = 'posted' AND f.payment_date = :d $where GROUP BY ctr.id, pm.id, u.id ORDER BY ctr.name, pm.name", $params);
        $receipts = DB::all("SELECT f.receipt_no, f.amount, f.status, s.name student, s.student_code, pm.name method, ctr.name center FROM fee_collections f JOIN students s ON s.id = f.student_id
            JOIN payment_methods pm ON pm.id = f.payment_method_id JOIN centers ctr ON ctr.id = f.center_id WHERE f.payment_date = :d $where ORDER BY f.id", $params);
        if ($fmt = Export::requested()) {
            Export::send($fmt, 'Daily Collection Summary – ' . fmt_date($date), ['center' => 'Center', 'method' => 'Method', 'collector' => 'Collected By', 'receipts' => 'Receipts', 'total' => 'Amount'],
                $rows, ['totals' => ['center' => 'TOTAL', 'receipts' => array_sum(array_column($rows, 'receipts')), 'total' => number_format(array_sum(array_column($rows, 'total')), 2)]]);
        }
        $this->render('fees/daily', ['title' => 'Daily Collection', 'rows' => $rows, 'receipts' => $receipts, 'date' => $date, 'breadcrumbs' => ['Fee Collection' => url('fees/index'), 'Daily summary' => null]]);
    }
}
