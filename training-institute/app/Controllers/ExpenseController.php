<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Config;
use App\Core\Controller;
use App\Core\Database;
use App\Core\HttpException;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Core\ValidationException;
use App\Security\FileUpload;
use App\Services\Audit;
use App\Services\Auth;
use App\Services\Finance;
use App\Services\Rbac;

/** Center expenses: entry (with bill/voucher scan) → approval by another person → cash book. */
final class ExpenseController extends Controller
{
    public function index(Request $request): Response
    {
        [$scope, $params] = Rbac::centerFilter('x.center_id');
        $where = [$scope];
        $from = (string) $request->query('from', date('Y-m-01'));
        $to = (string) $request->query('to', date('Y-m-d'));
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $from)) { $from = date('Y-m-01'); }
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $to)) { $to = date('Y-m-d'); }
        $where[] = 'x.expense_date BETWEEN ? AND ?';
        array_push($params, $from, $to);
        foreach (['center_id' => 'x.center_id', 'category_id' => 'x.category_id'] as $key => $col) {
            $v = $this->intOrNull($request->query($key));
            if ($v !== null) {
                $where[] = "{$col} = ?";
                $params[] = $v;
            }
        }
        $status = (string) $request->query('status', '');
        if (in_array($status, ['pending', 'approved', 'rejected'], true)) {
            $where[] = 'x.status = ?';
            $params[] = $status;
        }
        $fromSql = 'FROM expenses x JOIN expense_categories ec ON ec.id = x.category_id JOIN training_centers c ON c.id = x.center_id
                    JOIN finance_accounts a ON a.id = x.account_id JOIN users ru ON ru.id = x.requested_by LEFT JOIN users au ON au.id = x.approved_by
                    WHERE ' . implode(' AND ', $where);
        $result = $this->paginate('x.*, ec.name_en AS cat_en, ec.name_bn AS cat_bn, c.code AS center_code, a.name AS account_name,
                                   ru.name AS requester, au.name AS approver', $fromSql, $params, 'x.expense_date DESC, x.id DESC', 50);
        return $this->view('expenses/index', $result + [
            'byCategory' => Database::all("SELECT ec.name_en AS cat_en, ec.name_bn AS cat_bn, SUM(x.amount) AS total {$fromSql} AND x.status = 'approved'
                                            GROUP BY ec.id, ec.name_en, ec.name_bn ORDER BY total DESC", $params),
            'pendingTotal' => (float) Database::value("SELECT COALESCE(SUM(x.amount), 0) {$fromSql} AND x.status = 'pending'", $params),
            'centers'    => $this->centers(),
            'categories' => Database::all('SELECT * FROM expense_categories WHERE is_active = 1 ORDER BY name_en'),
            'filters'    => ['from' => $from, 'to' => $to, 'center_id' => $request->query('center_id', ''), 'category_id' => $request->query('category_id', ''), 'status' => $status],
        ]);
    }

    public function create(Request $request): Response
    {
        $centers = $this->centers();
        $centerId = $this->intOrNull($request->query('center_id')) ?? (count($centers) === 1 ? (int) $centers[0]['id'] : null);
        if ($centerId !== null && !Rbac::canAccessCenter($centerId)) {
            $centerId = null;
        }
        return $this->view('expenses/form', [
            'centers' => $centers, 'centerId' => $centerId,
            'accounts' => $centerId !== null ? Finance::accountsOf($centerId) : [],
            'categories' => Database::all('SELECT * FROM expense_categories WHERE is_active = 1 ORDER BY name_en'),
        ]);
    }

    public function store(Request $request): Response
    {
        $input = $request->all();
        $input['amount'] = en_digits(trim((string) ($input['amount'] ?? '')));
        $data = $this->validate([
            'center_id'    => 'required|integer',
            'category_id'  => 'required|integer',
            'account_id'   => 'required|integer',
            'expense_date' => 'required|date',
            'amount'       => 'required|decimal:2|min_value:1|max_value:100000000',
            'payee'        => 'nullable|max:120',
            'narration'    => 'required|min:3|max:255',
        ], $input);
        $centerId = (int) $data['center_id'];
        $errors = [];
        if (!Rbac::canAccessCenter($centerId)) {
            $errors['center_id'][] = __('users.center_not_allowed');
        }
        if (Database::value('SELECT id FROM finance_accounts WHERE id = ? AND center_id = ? AND is_active = 1', [(int) $data['account_id'], $centerId]) === null) {
            $errors['account_id'][] = __('fees.account_center');
        }
        if (Database::value('SELECT id FROM expense_categories WHERE id = ? AND is_active = 1', [(int) $data['category_id']]) === null) {
            $errors['category_id'][] = __('validation.in', ['field' => __('expenses.category')]);
        }
        if ((string) $data['expense_date'] > date('Y-m-d')) {
            $errors['expense_date'][] = __('fees.date_range');
        }
        if ($errors !== []) {
            throw new ValidationException($errors);
        }
        $attachment = null;
        if (($file = $request->file('attachment')) !== null) {
            try {
                $attachment = FileUpload::store($file, 'expenses/' . $centerId, (array) Config::get('security.uploads.allowed_documents', []));
            } catch (HttpException $e) {
                if ($e->getStatusCode() >= 500) {
                    throw $e;
                }
                throw new ValidationException(['attachment' => [__('students.file_type')]]);
            }
        }
        $code = (string) Database::value('SELECT code FROM training_centers WHERE id = ?', [$centerId]);
        $id = Database::transaction(static function () use ($data, $centerId, $code, $attachment): int {
            $voucher = Finance::nextNumber('EXP', $code, (string) $data['expense_date']);
            Database::query(
                'INSERT INTO expenses (voucher_no, center_id, category_id, account_id, expense_date, amount, payee, narration, attachment_path, requested_by)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
                [$voucher, $centerId, (int) $data['category_id'], (int) $data['account_id'], $data['expense_date'], (float) $data['amount'],
                 ($data['payee'] ?? '') !== '' ? trim((string) $data['payee']) : null, trim((string) $data['narration']), $attachment, Auth::id()]);
            $id = Database::lastInsertId();
            Audit::log('create', 'expense', $id, null, $data + ['voucher_no' => $voucher], $centerId);
            return $id;
        });
        Session::flash('success', __('expenses.saved'));
        return $this->redirect('/expenses?status=pending');
    }

    public function decide(Request $request, string $id): Response
    {
        $x = $this->find((int) $id);
        $data = $this->validate(['decision' => 'required|in:approve,reject', 'note' => 'nullable|max:255']);
        $note = ($data['note'] ?? '') !== '' ? trim((string) $data['note']) : null;
        if ($x['status'] !== 'pending') {
            throw new ValidationException(['decision' => [__('waivers.not_pending')]]);
        }
        if ((int) $x['requested_by'] === Auth::id()) {
            throw new ValidationException(['decision' => [__('waivers.own_request')]]);
        }
        if ($data['decision'] === 'reject') {
            if ($note === null) {
                throw new ValidationException(['note' => [__('waivers.reason_required')]]);
            }
            Database::query("UPDATE expenses SET status = 'rejected', approved_by = ?, decision_note = ? WHERE id = ?", [Auth::id(), $note, $x['id']]);
            Session::flash('success', __('expenses.rejected'));
            return $this->back();
        }
        $balance = Finance::balance((int) $x['account_id']);
        if ($balance < (float) $x['amount']) {
            throw new ValidationException(['decision' => [__('finance.insufficient', ['balance' => money($balance)])]]);
        }
        Database::query("UPDATE expenses SET status = 'approved', approved_by = ?, decision_note = ? WHERE id = ?", [Auth::id(), $note, $x['id']]);
        Session::flash('success', __('expenses.approved', ['amount' => money($x['amount'])]));
        return $this->back();
    }

    public function attachment(Request $request, string $id): Response
    {
        $x = $this->find((int) $id);
        if ($x['attachment_path'] === null) {
            throw new HttpException(404);
        }
        $path = FileUpload::absolutePath((string) $x['attachment_path']);
        return Response::download($path, $x['voucher_no'] . '.' . pathinfo($path, PATHINFO_EXTENSION),
            (new \finfo(FILEINFO_MIME_TYPE))->file($path) ?: 'application/octet-stream', true);
    }

    /** @return array<string,mixed> */
    private function find(int $id): array
    {
        [$scope, $params] = Rbac::centerFilter('x.center_id');
        $x = Database::first("SELECT x.* FROM expenses x WHERE x.id = ? AND {$scope}", array_merge([$id], $params));
        if ($x === null) {
            throw new HttpException(Database::value('SELECT id FROM expenses WHERE id = ?', [$id]) !== null ? 403 : 404);
        }
        return $x;
    }

    /** @return list<array<string,mixed>> */
    private function centers(): array
    {
        [$sql, $params] = Rbac::centerFilter('c.id');
        return Database::all("SELECT c.id, c.code, c.name_en, c.name_bn FROM training_centers c WHERE c.is_active = 1 AND {$sql} ORDER BY c.center_type = 'head_office' DESC, c.name_en", $params);
    }
}
