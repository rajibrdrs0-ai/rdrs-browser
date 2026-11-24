<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Audit;
use App\Core\Auth;
use App\Core\Controller;
use App\Core\DB;
use App\Core\Export;
use App\Core\Sequence;
use App\Core\Upload;

/**
 * Expense vouchers with maker-checker approval. Approved vouchers are locked;
 * they can only be cancelled (never deleted) with a reason.
 */
final class ExpensesController extends Controller
{
    public function index(): void
    {
        $this->authorize('expenses.view');
        [$where, $params] = $this->centerFilter('x.center_id');
        $from = input_date('from') ?? date('Y-m-01', strtotime('-2 months'));
        $to = input_date('to') ?? date('Y-m-d');
        $where .= ' AND x.expense_date BETWEEN :from AND :to';
        $params += ['from' => $from, 'to' => $to];
        if ($q = input('q')) {
            $where .= ' AND (x.voucher_no LIKE :q OR x.description LIKE :q2 OR x.paid_to LIKE :q3)';
            $params += ['q' => "%$q%", 'q2' => "%$q%", 'q3' => "%$q%"];
        }
        if ($h = input_id('expense_head_id')) {
            $where .= ' AND x.expense_head_id = :h';
            $params['h'] = $h;
        }
        if (in_array($st = input('status'), ['pending', 'approved', 'rejected', 'cancelled'], true)) {
            $where .= ' AND x.status = :st';
            $params['st'] = $st;
        }
        $sql = "SELECT x.*, h.name head, ec.name category, ctr.name center, pm.name method, pu.name prepared_by_name, au.name approved_by_name
            FROM expenses x JOIN expense_heads h ON h.id = x.expense_head_id JOIN expense_categories ec ON ec.id = h.expense_category_id JOIN centers ctr ON ctr.id = x.center_id
            JOIN payment_methods pm ON pm.id = x.payment_method_id JOIN users pu ON pu.id = x.prepared_by LEFT JOIN users au ON au.id = x.approved_by
            WHERE 1=1 $where ORDER BY x.expense_date DESC, x.id DESC";
        $tot = DB::row("SELECT COALESCE(SUM(IF(x.status='approved', x.amount, 0)),0) approved, COALESCE(SUM(IF(x.status='pending', x.amount, 0)),0) pending FROM expenses x WHERE 1=1 $where", $params);
        if ($fmt = Export::requested()) {
            $this->authorize('expenses.export');
            Export::send($fmt, 'Expense Register', ['voucher_no' => 'Voucher', 'expense_date' => 'Date', 'center' => 'Center', 'category' => 'Category', 'head' => 'Expense Head',
                'description' => 'Description', 'paid_to' => 'Paid To', 'method' => 'Method', 'amount' => 'Amount', 'status' => 'Status', 'prepared_by_name' => 'Prepared By', 'approved_by_name' => 'Approved By'],
                DB::all($sql, $params), ['subtitle' => fmt_date($from) . ' – ' . fmt_date($to), 'totals' => ['voucher_no' => 'TOTAL APPROVED', 'amount' => number_format((float)$tot['approved'], 2)]]);
        }
        $byHead = DB::all("SELECT h.name, SUM(x.amount) total FROM expenses x JOIN expense_heads h ON h.id = x.expense_head_id WHERE x.status = 'approved' $where GROUP BY h.id ORDER BY total DESC LIMIT 6", $params);
        $this->render('expenses/index', ['title' => 'Expenses', 'p' => DB::paginate($sql, $params), 'tot' => $tot, 'byHead' => $byHead, 'from' => $from, 'to' => $to,
            'heads' => DB::all('SELECT id, name FROM expense_heads ORDER BY name'), 'breadcrumbs' => ['Accounts' => null, 'Expenses' => null]]);
    }

    public function create(): void
    {
        $this->authorize('expenses.create');
        $this->render('expenses/form', ['title' => 'New Expense', 'centers' => Auth::centerOptions(),
            'heads' => DB::all('SELECT h.id, CONCAT(c.name, " › ", h.name) name FROM expense_heads h JOIN expense_categories c ON c.id = h.expense_category_id WHERE h.is_active = 1 ORDER BY c.name, h.name'),
            'methods' => DB::all('SELECT id, name FROM payment_methods WHERE is_active = 1 ORDER BY id'),
            'accounts' => DB::all('SELECT id, name FROM accounts WHERE is_active = 1' . (Auth::isHeadOffice() ? '' : ' AND (center_id IS NULL' . str_replace(' AND ', ' OR ', Auth::scope('center_id')) . ')') . ' ORDER BY name'),
            'breadcrumbs' => ['Expenses' => url('expenses/index'), 'New' => null]]);
    }

    public function store(): void
    {
        $this->requirePost();
        $this->authorize('expenses.create');
        $this->validate(['expense_date|Date' => 'required|date', 'center_id|Center' => 'required', 'expense_head_id|Expense head' => 'required|exists:expense_heads',
            'description|Description' => 'required|maxlen:255', 'amount|Amount' => 'required|numeric|min:0.01', 'payment_method_id|Payment method' => 'required|exists:payment_methods']);
        $centerId = $this->resolveCenter(input_id('center_id'));
        if (input_date('expense_date') > date('Y-m-d')) {
            $this->fail('Expense date cannot be in the future.');
        }
        try {
            $doc = Upload::store('document', 'expenses', Upload::DOCS, 5);
        } catch (\RuntimeException $e) {
            $this->fail($e->getMessage());
        }
        $autoApprove = can('expenses.approve') && input('approve_now') === '1';
        $id = DB::transaction(function () use ($centerId, $doc, $autoApprove) {
            $no = Sequence::next('voucher_no_format', 'DV-{CENTER}-{YY}-{SEQ5}', ['center' => DB::value('SELECT code FROM centers WHERE id = :c', ['c' => $centerId])]);
            $id = DB::insert('expenses', [
                'voucher_no' => $no, 'expense_date' => input_date('expense_date'), 'center_id' => $centerId, 'expense_head_id' => input_id('expense_head_id'),
                'description' => input('description'), 'amount' => input_money('amount'), 'payment_method_id' => input_id('payment_method_id'), 'account_id' => input_id('account_id'),
                'paid_to' => nullify(input('paid_to')), 'supporting_document' => $doc['path'] ?? null, 'remarks' => nullify(input('remarks')), 'prepared_by' => Auth::id(),
                'status' => $autoApprove ? 'approved' : 'pending', 'approved_by' => $autoApprove ? Auth::id() : null, 'approved_at' => $autoApprove ? date('Y-m-d H:i:s') : null,
            ]);
            Audit::log('expenses', 'create', $id, "Expense voucher $no: " . number_format(input_money('amount'), 2), null, null, $centerId);
            if (!$autoApprove) {
                DB::insert('notifications', ['center_id' => $centerId, 'permission_slug' => 'expenses.approve', 'title' => 'Expense awaiting approval',
                    'message' => "$no – " . number_format(input_money('amount'), 2) . ' – ' . input('description'), 'link' => url('expenses/view', ['id' => $id])]);
            }
            return $id;
        });
        clear_old();
        flash('success', $autoApprove ? 'Expense saved and approved.' : 'Expense saved and sent for approval.');
        redirect(url('expenses/view', ['id' => $id]));
    }

    private function load(): array
    {
        $x = DB::row('SELECT x.*, h.name head, ec.name category, ah.code ah_code, ah.name ah_name, ctr.name center, ctr.address center_address, pm.name method, a.name account,
                pu.name prepared_by_name, au.name approved_by_name
            FROM expenses x JOIN expense_heads h ON h.id = x.expense_head_id JOIN expense_categories ec ON ec.id = h.expense_category_id LEFT JOIN account_heads ah ON ah.id = h.account_head_id
            JOIN centers ctr ON ctr.id = x.center_id JOIN payment_methods pm ON pm.id = x.payment_method_id LEFT JOIN accounts a ON a.id = x.account_id
            JOIN users pu ON pu.id = x.prepared_by LEFT JOIN users au ON au.id = x.approved_by WHERE x.id = :id', ['id' => input_int('id')]) ?? $this->notFound('Expense');
        $this->authorizeCenter((int)$x['center_id']);
        return $x;
    }

    public function view(): void
    {
        $this->authorize('expenses.view');
        $x = $this->load();
        $history = DB::all("SELECT a.*, u.name uname FROM audit_logs a LEFT JOIN users u ON u.id = a.user_id WHERE a.module = 'expenses' AND a.record_id = :id ORDER BY a.id", ['id' => (string)$x['id']]);
        $this->render('expenses/view', ['title' => 'Voucher ' . $x['voucher_no'], 'x' => $x, 'history' => $history, 'breadcrumbs' => ['Expenses' => url('expenses/index'), $x['voucher_no'] => null]]);
    }

    public function voucher(): void
    {
        $this->authorize('expenses.view');
        $x = $this->load();
        $this->render('expenses/voucher', ['title' => 'Debit Voucher ' . $x['voucher_no'], 'x' => $x], 'print');
    }

    public function decide(): void
    {
        $this->requirePost();
        $this->authorize('expenses.approve');
        $x = $this->load();
        if ($x['status'] !== 'pending') {
            $this->fail('Only pending vouchers can be approved or rejected.');
        }
        if ((int)$x['prepared_by'] === Auth::id() && !Auth::isSuperAdmin() && setting('expense_self_approval', '0') !== '1') {
            $this->fail('You cannot approve a voucher you prepared (maker-checker rule).');
        }
        $approve = input('decision') === 'approve';
        $reason = trim((string)input('reason'));
        if (!$approve && mb_strlen($reason) < 3) {
            $this->fail('A reason is required to reject.');
        }
        DB::update('expenses', ['status' => $approve ? 'approved' : 'rejected', 'approved_by' => Auth::id(), 'approved_at' => date('Y-m-d H:i:s'), 'rejection_reason' => $approve ? null : $reason], 'id = :id', ['id' => $x['id']]);
        Audit::log('expenses', $approve ? 'approve' : 'reject', $x['id'], "Voucher {$x['voucher_no']} " . ($approve ? 'approved' : "rejected: $reason"), null, null, (int)$x['center_id']);
        DB::insert('notifications', ['user_id' => $x['prepared_by'], 'title' => 'Expense ' . ($approve ? 'approved' : 'rejected'), 'message' => $x['voucher_no'] . ($approve ? '' : " – $reason"), 'link' => url('expenses/view', ['id' => $x['id']])]);
        flash('success', 'Voucher ' . ($approve ? 'approved.' : 'rejected.'));
        back();
    }

    public function cancel(): void
    {
        $this->requirePost();
        $this->authorize('expenses.cancel');
        $x = $this->load();
        $reason = trim((string)input('reason'));
        if (mb_strlen($reason) < 3) {
            $this->fail('A reason is required.');
        }
        if (!in_array($x['status'], ['pending', 'approved'], true)) {
            $this->fail('This voucher cannot be cancelled.');
        }
        DB::update('expenses', ['status' => 'cancelled', 'rejection_reason' => "Cancelled: $reason"], 'id = :id', ['id' => $x['id']]);
        Audit::log('expenses', 'cancel', $x['id'], "Voucher {$x['voucher_no']} cancelled: $reason", $x, null, (int)$x['center_id']);
        flash('success', 'Voucher cancelled. The record is preserved.');
        back();
    }
}
