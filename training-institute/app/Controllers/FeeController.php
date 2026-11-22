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
use App\Core\View;
use App\Services\Audit;
use App\Services\Auth;
use App\Services\Finance;
use App\Services\Rbac;

/** Fee collection desk: search, dues, money receipts, cancellation, daily collection. */
final class FeeController extends Controller
{
    public function index(Request $request): Response
    {
        [$scope, $params] = Rbac::centerFilter('e.center_id');
        $q = trim(en_digits((string) $request->query('q', '')));
        $results = [];
        if ($q !== '') {
            $like = '%' . addcslashes($q, '%_\\') . '%';
            $results = Database::all(
                "SELECT e.id, e.status, e.roll_no, b.batch_code, s.id AS student_id, s.registration_no, s.name_en, s.name_bn, s.mobile, s.photo_path,
                        d.invoiced, d.paid, d.due, d.overdue
                   FROM enrollments e JOIN students s ON s.id = e.student_id JOIN batches b ON b.id = e.batch_id
                   JOIN v_enrollment_dues d ON d.enrollment_id = e.id
                  WHERE {$scope} AND (s.name_en LIKE ? OR s.name_bn LIKE ? OR s.registration_no LIKE ? OR s.mobile LIKE ? OR b.batch_code LIKE ?)
                  ORDER BY d.due DESC, s.name_en LIMIT 50",
                array_merge($params, [$like, $like, $like, $like, $like]));
        }
        [$pScope, $pParams] = Rbac::centerFilter('p.center_id');
        [$dScope, $dParams] = Rbac::centerFilter('d.center_id');
        return $this->view('fees/index', [
            'q' => $q, 'results' => $results,
            'today' => Database::first(
                "SELECT COUNT(*) AS receipts, COALESCE(SUM(p.amount), 0) AS total FROM fee_payments p
                  WHERE p.status = 'valid' AND p.payment_date = CURRENT_DATE AND {$pScope}", $pParams),
            'month' => (float) Database::value(
                "SELECT COALESCE(SUM(p.amount), 0) FROM fee_payments p WHERE p.status = 'valid'
                    AND p.payment_date >= DATE_FORMAT(CURRENT_DATE, '%Y-%m-01') AND {$pScope}", $pParams),
            'dues' => Database::first(
                "SELECT COALESCE(SUM(d.due), 0) AS due, COALESCE(SUM(d.overdue), 0) AS overdue, SUM(d.overdue > 0) AS overdue_count
                   FROM v_enrollment_dues d WHERE d.due > 0 AND {$dScope}", $dParams),
        ]);
    }

    public function dues(Request $request): Response
    {
        [$scope, $params] = Rbac::centerFilter('d.center_id');
        $where = ['d.due > 0', $scope];
        foreach (['center_id' => 'd.center_id', 'batch_id' => 'd.batch_id'] as $key => $col) {
            $v = $this->intOrNull($request->query($key));
            if ($v !== null) {
                $where[] = "{$col} = ?";
                $params[] = $v;
            }
        }
        if ((string) $request->query('overdue') === '1') {
            $where[] = 'd.overdue > 0';
        }
        $from = 'FROM v_enrollment_dues d JOIN enrollments e ON e.id = d.enrollment_id JOIN batches b ON b.id = d.batch_id
                 JOIN students s ON s.id = d.student_id JOIN training_centers c ON c.id = d.center_id WHERE ' . implode(' AND ', $where);
        $result = $this->paginate(
            "d.*, e.status, e.roll_no, b.batch_code, s.name_bn, s.guardian_mobile, s.father_mobile, c.code AS center_code,
             (SELECT MIN(i.due_date) FROM fee_invoices i WHERE i.enrollment_id = d.enrollment_id AND i.status IN ('unpaid','partial')) AS next_due",
            $from, $params, 'd.overdue DESC, d.due DESC', 50);
        $totals = Database::first('SELECT COALESCE(SUM(d.due), 0) AS due, COALESCE(SUM(d.overdue), 0) AS overdue ' . $from, $params);
        [$bScope, $bParams] = Rbac::batchFilter('b');
        return $this->view('fees/dues', $result + [
            'totals'  => $totals,
            'centers' => $this->centers(),
            'batches' => Database::all("SELECT b.id, b.batch_code FROM batches b WHERE b.status IN ('admission_open','running','completed') AND {$bScope} ORDER BY b.batch_code", $bParams),
            'filters' => ['center_id' => $request->query('center_id', ''), 'batch_id' => $request->query('batch_id', ''), 'overdue' => $request->query('overdue', '')],
        ]);
    }

    public function collectForm(Request $request, string $enrollmentId): Response
    {
        $e = $this->findEnrollment((int) $enrollmentId);
        $invoices = Database::all("SELECT * FROM fee_invoices WHERE enrollment_id = ? AND status IN ('unpaid','partial') ORDER BY installment_no", [$e['id']]);
        $selected = $this->intOrNull($request->query('invoice_id')) ?? ($invoices[0]['id'] ?? null);
        return $this->view('fees/collect', [
            'e' => $e, 'invoices' => $invoices, 'selected' => $selected,
            'accounts' => Finance::accountsOf((int) $e['center_id']),
            'receipts' => Database::all(
                'SELECT p.*, u.name AS receiver FROM fee_payments p JOIN fee_invoices i ON i.id = p.invoice_id LEFT JOIN users u ON u.id = p.received_by
                  WHERE i.enrollment_id = ? ORDER BY p.id DESC', [$e['id']]),
        ]);
    }

    public function collect(Request $request, string $enrollmentId): Response
    {
        $e = $this->findEnrollment((int) $enrollmentId);
        $input = $request->all();
        $input['amount'] = en_digits(trim((string) ($input['amount'] ?? '')));
        $data = $this->validate([
            'invoice_id'      => 'required|integer',
            'amount'          => 'required|decimal:2|min_value:0.01',
            'account_id'      => 'required|integer',
            'method'          => 'required|in:' . implode(',', array_keys(Finance::METHOD_ACCOUNTS)),
            'transaction_ref' => 'nullable|max:60',
            'payment_date'    => 'required|date',
            'remarks'         => 'nullable|max:255',
        ], $input);
        $invoice = Database::first(
            'SELECT i.*, c.code AS center_code FROM fee_invoices i JOIN training_centers c ON c.id = i.center_id WHERE i.id = ? AND i.enrollment_id = ?',
            [(int) $data['invoice_id'], $e['id']]) ?? throw new ValidationException(['invoice_id' => [__('fees.invoice_closed')]]);
        $id = Finance::collect($invoice, (float) $data['amount'], (int) $data['account_id'], (string) $data['method'],
            ($data['transaction_ref'] ?? '') !== '' ? trim((string) $data['transaction_ref']) : null,
            (string) $data['payment_date'], ($data['remarks'] ?? '') !== '' ? trim((string) $data['remarks']) : null);
        Session::flash('success', __('fees.collected', ['amount' => money($data['amount'])]));
        return $this->redirect('/fees/receipts/' . $id);
    }

    public function receipts(Request $request): Response
    {
        [$scope, $params] = Rbac::centerFilter('p.center_id');
        $where = [$scope];
        $from = (string) $request->query('from', date('Y-m-01'));
        $to = (string) $request->query('to', date('Y-m-d'));
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $from)) { $from = date('Y-m-01'); }
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $to)) { $to = date('Y-m-d'); }
        $where[] = 'p.payment_date BETWEEN ? AND ?';
        array_push($params, $from, $to);
        $centerId = $this->intOrNull($request->query('center_id'));
        if ($centerId !== null) {
            $where[] = 'p.center_id = ?';
            $params[] = $centerId;
        }
        $status = (string) $request->query('status', '');
        if (in_array($status, ['valid', 'cancelled'], true)) {
            $where[] = 'p.status = ?';
            $params[] = $status;
        }
        $q = trim(en_digits((string) $request->query('q', '')));
        if ($q !== '') {
            $where[] = '(p.receipt_no LIKE ? OR s.name_en LIKE ? OR s.registration_no LIKE ? OR p.transaction_ref LIKE ?)';
            $like = '%' . addcslashes($q, '%_\\') . '%';
            array_push($params, $like, $like, $like, $like);
        }
        $fromSql = 'FROM fee_payments p JOIN fee_invoices i ON i.id = p.invoice_id JOIN enrollments e ON e.id = i.enrollment_id
                    JOIN students s ON s.id = e.student_id JOIN finance_accounts a ON a.id = p.account_id
                    LEFT JOIN users u ON u.id = p.received_by WHERE ' . implode(' AND ', $where);
        $result = $this->paginate(
            'p.*, s.name_en, s.name_bn, s.registration_no, i.invoice_no, i.installment_no, a.name AS account_name, u.name AS receiver',
            $fromSql, $params, 'p.id DESC', 50);
        $summary = Database::all(
            "SELECT p.method, COUNT(*) AS n, SUM(p.amount) AS total {$fromSql} AND p.status = 'valid' GROUP BY p.method ORDER BY total DESC", $params);
        return $this->view('fees/receipts', $result + [
            'summary' => $summary, 'centers' => $this->centers(),
            'filters' => ['from' => $from, 'to' => $to, 'center_id' => $centerId, 'status' => $status, 'q' => $q],
        ]);
    }

    public function receipt(Request $request, string $id): Response
    {
        $p = $this->findReceipt((int) $id);
        $data = ['p' => $p, 'items' => Database::all(
            'SELECT it.amount, h.name_en, h.name_bn FROM fee_invoice_items it JOIN fee_heads h ON h.id = it.fee_head_id WHERE it.invoice_id = ? ORDER BY h.sort_order',
            [$p['invoice_id']]),
            'institute' => [
                'en' => (string) (Database::value("SELECT setting_value FROM settings WHERE setting_key = 'institute_name_en'") ?? 'RTTI'),
                'bn' => (string) (Database::value("SELECT setting_value FROM settings WHERE setting_key = 'institute_name_bn'") ?? 'RTTI'),
            ]];
        if ((string) $request->query('print') === '1') {
            return Response::html(View::make('fees/receipt_print', $data));
        }
        return $this->view('fees/receipt', $data);
    }

    public function cancelReceipt(Request $request, string $id): Response
    {
        $p = $this->findReceipt((int) $id);
        $data = $this->validate(['reason' => 'required|min:5|max:255']);
        if ($p['status'] !== 'valid') {
            throw new ValidationException(['reason' => [__('fees.already_cancelled')]]);
        }
        // Money already handed out must stay covered: block if the account would go negative.
        if (Finance::balance((int) $p['account_id']) < (float) $p['amount']) {
            throw new ValidationException(['reason' => [__('fees.cancel_balance')]]);
        }
        Database::query("UPDATE fee_payments SET status = 'cancelled', cancel_reason = ?, cancelled_by = ? WHERE id = ?",
            [trim((string) $data['reason']), Auth::id(), $p['id']]);
        Session::flash('success', __('fees.receipt_cancelled', ['no' => $p['receipt_no']]));
        return $this->redirect('/fees/receipts/' . $p['id']);
    }

    // ---- helpers ------------------------------------------------------------

    /** @return array<string,mixed> */
    private function findEnrollment(int $id): array
    {
        [$scope, $params] = Rbac::centerFilter('e.center_id');
        $e = Database::first(
            "SELECT e.*, b.batch_code, co.name_en AS course_en, co.name_bn AS course_bn, s.registration_no, s.name_en, s.name_bn, s.mobile, s.photo_path,
                    d.invoiced, d.paid, d.due, d.overdue
               FROM enrollments e JOIN batches b ON b.id = e.batch_id JOIN courses co ON co.id = b.course_id JOIN students s ON s.id = e.student_id
               LEFT JOIN v_enrollment_dues d ON d.enrollment_id = e.id
              WHERE e.id = ? AND {$scope}", array_merge([$id], $params));
        if ($e === null) {
            throw new HttpException(Database::value('SELECT id FROM enrollments WHERE id = ?', [$id]) !== null ? 403 : 404);
        }
        return $e;
    }

    /** @return array<string,mixed> */
    private function findReceipt(int $id): array
    {
        [$scope, $params] = Rbac::centerFilter('p.center_id');
        $p = Database::first(
            "SELECT p.*, i.invoice_no, i.installment_no, i.amount AS invoice_amount, i.paid_amount AS invoice_paid, i.enrollment_id,
                    e.roll_no, b.batch_code, co.name_en AS course_en, co.name_bn AS course_bn,
                    s.registration_no, s.name_en, s.name_bn, s.mobile, c.code AS center_code, c.name_en AS center_en, c.name_bn AS center_bn,
                    c.address AS center_address, c.phone AS center_phone, a.name AS account_name, u.name AS receiver, cu.name AS canceller,
                    (SELECT due FROM v_enrollment_dues WHERE enrollment_id = e.id) AS enrollment_due,
                    (SELECT COALESCE(SUM(x.amount), 0) FROM fee_payments x
                      WHERE x.invoice_id = p.invoice_id AND x.status = 'valid' AND x.id < p.id) AS paid_before
               FROM fee_payments p JOIN fee_invoices i ON i.id = p.invoice_id JOIN enrollments e ON e.id = i.enrollment_id
               JOIN batches b ON b.id = e.batch_id JOIN courses co ON co.id = b.course_id JOIN students s ON s.id = e.student_id
               JOIN training_centers c ON c.id = p.center_id JOIN finance_accounts a ON a.id = p.account_id
               LEFT JOIN users u ON u.id = p.received_by LEFT JOIN users cu ON cu.id = p.cancelled_by
              WHERE p.id = ? AND {$scope}", array_merge([$id], $params));
        if ($p === null) {
            throw new HttpException(Database::value('SELECT id FROM fee_payments WHERE id = ?', [$id]) !== null ? 403 : 404);
        }
        return $p;
    }

    /** @return list<array<string,mixed>> */
    private function centers(): array
    {
        [$sql, $params] = Rbac::centerFilter('c.id');
        return Database::all("SELECT c.id, c.code, c.name_en, c.name_bn FROM training_centers c WHERE c.is_active = 1 AND {$sql} ORDER BY c.name_en", $params);
    }
}
