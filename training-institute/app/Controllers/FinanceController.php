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
use App\Services\Audit;
use App\Services\Auth;
use App\Services\Finance;
use App\Services\Rbac;
use App\Services\Sequence;

/** Cash / bank / mobile-wallet accounts, fund transfers and the cash book. */
final class FinanceController extends Controller
{
    public function accounts(Request $request): Response
    {
        [$scope, $params] = Rbac::centerFilter('a.center_id');
        return $this->view('finance/accounts', [
            'accounts' => Database::all(
                "SELECT a.*, c.code AS center_code, c.name_en AS center_en, c.name_bn AS center_bn, COALESCE(v.balance, 0) AS balance,
                        COALESCE(v.total_in, 0) AS total_in, COALESCE(v.total_out, 0) AS total_out
                   FROM finance_accounts a JOIN training_centers c ON c.id = a.center_id LEFT JOIN v_account_balances v ON v.account_id = a.id
                  WHERE {$scope} ORDER BY c.center_type = 'head_office' DESC, c.name_en, FIELD(a.account_type, 'cash','mobile_wallet','bank'), a.name", $params),
            'centers' => $this->centers(),
        ]);
    }

    public function storeAccount(Request $request): Response
    {
        $input = $request->all();
        $input['opening_balance'] = en_digits(trim((string) ($input['opening_balance'] ?? '0'))) ?: '0';
        $data = $this->validate([
            'center_id'       => 'required|integer',
            'account_type'    => 'required|in:cash,bank,mobile_wallet',
            'name'            => 'required|max:120',
            'bank_name'       => 'nullable|max:120',
            'account_number'  => 'nullable|max:40',
            'opening_balance' => 'required|decimal:2|min_value:0|max_value:1000000000',
        ], $input);
        $centerId = (int) $data['center_id'];
        if (!Rbac::canAccessCenter($centerId)) {
            throw new ValidationException(['center_id' => [__('users.center_not_allowed')]]);
        }
        if (Database::value('SELECT id FROM finance_accounts WHERE center_id = ? AND name = ?', [$centerId, trim((string) $data['name'])]) !== null) {
            throw new ValidationException(['name' => [__('validation.unique', ['field' => __('finance.account_name')])]]);
        }
        Database::query('INSERT INTO finance_accounts (center_id, account_type, name, bank_name, account_number, opening_balance) VALUES (?, ?, ?, ?, ?, ?)',
            [$centerId, $data['account_type'], trim((string) $data['name']), ($data['bank_name'] ?? '') ?: null, ($data['account_number'] ?? '') ?: null, (float) $data['opening_balance']]);
        Audit::log('create', 'finance_account', Database::lastInsertId(), null, $data, $centerId);
        Session::flash('success', __('finance.account_saved'));
        return $this->redirect('/finance/accounts');
    }

    public function cashbook(Request $request): Response
    {
        [$scope, $params] = Rbac::centerFilter('a.center_id');
        $accounts = Database::all("SELECT a.id, a.name, a.account_type, c.code AS center_code FROM finance_accounts a JOIN training_centers c ON c.id = a.center_id
                                    WHERE {$scope} ORDER BY c.code, a.name", $params);
        $accountId = $this->intOrNull($request->query('account_id')) ?? (isset($accounts[0]) ? (int) $accounts[0]['id'] : null);
        if ($accountId !== null && !in_array($accountId, array_map('intval', array_column($accounts, 'id')), true)) {
            throw new HttpException(403);
        }
        $from = (string) $request->query('from', date('Y-m-01'));
        $to = (string) $request->query('to', date('Y-m-d'));
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $from)) { $from = date('Y-m-01'); }
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $to)) { $to = date('Y-m-d'); }
        $opening = 0.0;
        $rows = [];
        if ($accountId !== null) {
            $opening = (float) Database::value(
                "SELECT COALESCE(SUM(CASE WHEN direction = 'in' THEN amount ELSE -amount END), 0) FROM account_transactions WHERE account_id = ? AND txn_date < ?",
                [$accountId, $from]);
            $rows = Database::all(
                'SELECT t.*, u.name AS user_name FROM account_transactions t LEFT JOIN users u ON u.id = t.created_by
                  WHERE t.account_id = ? AND t.txn_date BETWEEN ? AND ? ORDER BY t.txn_date, t.id', [$accountId, $from, $to]);
        }
        return $this->view('finance/cashbook', ['accounts' => $accounts, 'accountId' => $accountId, 'from' => $from, 'to' => $to, 'opening' => $opening, 'rows' => $rows]);
    }

    public function transfers(Request $request): Response
    {
        [$scopeFrom, $pFrom] = Rbac::centerFilter('fa.center_id');
        [$scopeTo, $pTo] = Rbac::centerFilter('ta.center_id');
        $rows = Database::all(
            "SELECT t.*, fa.name AS from_name, fc.code AS from_center, ta.name AS to_name, tc.code AS to_center, cu.name AS creator, ku.name AS confirmer,
                    ({$scopeTo}) AS can_confirm_scope
               FROM fund_transfers t JOIN finance_accounts fa ON fa.id = t.from_account_id JOIN training_centers fc ON fc.id = fa.center_id
               JOIN finance_accounts ta ON ta.id = t.to_account_id JOIN training_centers tc ON tc.id = ta.center_id
               JOIN users cu ON cu.id = t.created_by LEFT JOIN users ku ON ku.id = t.confirmed_by
              WHERE ({$scopeFrom}) OR ({$scopeTo}) ORDER BY t.id DESC LIMIT 200",
            array_merge($pTo, $pFrom, $pTo));
        [$scope, $params] = Rbac::centerFilter('a.center_id');
        return $this->view('finance/transfers', [
            'rows' => $rows,
            'fromAccounts' => Database::all("SELECT a.id, a.name, c.code AS center_code, COALESCE(v.balance, 0) AS balance FROM finance_accounts a
                                               JOIN training_centers c ON c.id = a.center_id LEFT JOIN v_account_balances v ON v.account_id = a.id
                                              WHERE a.is_active = 1 AND {$scope} ORDER BY c.code, a.name", $params),
            // Money may go to any active account (e.g. a center remitting to Head Office).
            'toAccounts' => Database::all("SELECT a.id, a.name, c.code AS center_code FROM finance_accounts a JOIN training_centers c ON c.id = a.center_id
                                            WHERE a.is_active = 1 ORDER BY c.center_type = 'head_office' DESC, c.code, a.name"),
        ]);
    }

    public function storeTransfer(Request $request): Response
    {
        $input = $request->all();
        $input['amount'] = en_digits(trim((string) ($input['amount'] ?? '')));
        $data = $this->validate([
            'from_account_id' => 'required|integer', 'to_account_id' => 'required|integer|different:from_account_id',
            'amount' => 'required|decimal:2|min_value:1', 'transfer_date' => 'required|date',
            'reference' => 'nullable|max:80', 'narration' => 'nullable|max:255',
        ], $input);
        $from = Database::first('SELECT * FROM finance_accounts WHERE id = ? AND is_active = 1', [(int) $data['from_account_id']]);
        $to = Database::first('SELECT * FROM finance_accounts WHERE id = ? AND is_active = 1', [(int) $data['to_account_id']]);
        $errors = [];
        if ($from === null || !Rbac::canAccessCenter((int) $from['center_id'])) {
            $errors['from_account_id'][] = __('fees.account_center');
        }
        if ($to === null) {
            $errors['to_account_id'][] = __('validation.in', ['field' => __('finance.to_account')]);
        }
        if ((string) $data['transfer_date'] > date('Y-m-d')) {
            $errors['transfer_date'][] = __('fees.date_range');
        }
        if ($errors === [] && Finance::balance((int) $from['id']) < (float) $data['amount']) {
            $errors['amount'][] = __('finance.insufficient', ['balance' => money(Finance::balance((int) $from['id']))]);
        }
        if ($errors !== []) {
            throw new ValidationException($errors);
        }
        $period = substr(str_replace('-', '', (string) $data['transfer_date']), 0, 6);
        $no = 'FT-' . $period . '-' . str_pad((string) Sequence::next('transfer', $period), 5, '0', STR_PAD_LEFT);
        Database::query('INSERT INTO fund_transfers (transfer_no, from_account_id, to_account_id, transfer_date, amount, reference, narration, created_by)
                         VALUES (?, ?, ?, ?, ?, ?, ?, ?)',
            [$no, $from['id'], $to['id'], $data['transfer_date'], (float) $data['amount'], ($data['reference'] ?? '') ?: null, ($data['narration'] ?? '') ?: null, Auth::id()]);
        Audit::log('create', 'fund_transfer', Database::lastInsertId(), null, $data + ['transfer_no' => $no], (int) $from['center_id']);
        Session::flash('success', __('finance.transfer_created', ['no' => $no]));
        return $this->redirect('/finance/transfers');
    }

    /** Receiving side (or a supervisor) confirms the money arrived; a different person than the sender. */
    public function decideTransfer(Request $request, string $id): Response
    {
        $t = Database::first('SELECT t.*, fa.center_id AS from_center, ta.center_id AS to_center FROM fund_transfers t
                                JOIN finance_accounts fa ON fa.id = t.from_account_id JOIN finance_accounts ta ON ta.id = t.to_account_id WHERE t.id = ?', [(int) $id])
            ?? throw new HttpException(404);
        $data = $this->validate(['decision' => 'required|in:confirm,cancel']);
        if ($t['status'] !== 'pending') {
            throw new ValidationException(['decision' => [__('waivers.not_pending')]]);
        }
        if ($data['decision'] === 'cancel') {
            if ((int) $t['created_by'] !== Auth::id() && !Rbac::canAccessCenter((int) $t['from_center'])) {
                throw new HttpException(403);
            }
            Database::query("UPDATE fund_transfers SET status = 'cancelled' WHERE id = ?", [$t['id']]);
            Session::flash('success', __('finance.transfer_cancelled'));
            return $this->redirect('/finance/transfers');
        }
        if (!Rbac::canAccessCenter((int) $t['to_center'])) {
            throw new HttpException(403, __('finance.confirm_scope'));
        }
        if ((int) $t['created_by'] === Auth::id()) {
            throw new ValidationException(['decision' => [__('waivers.own_request')]]);
        }
        if (Finance::balance((int) $t['from_account_id']) < (float) $t['amount']) {
            throw new ValidationException(['decision' => [__('finance.insufficient', ['balance' => money(Finance::balance((int) $t['from_account_id']))])]]);
        }
        Database::query("UPDATE fund_transfers SET status = 'completed', confirmed_by = ?, confirmed_at = NOW() WHERE id = ?", [Auth::id(), $t['id']]);
        Audit::log('confirm', 'fund_transfer', (int) $t['id'], ['status' => 'pending'], ['status' => 'completed'], (int) $t['to_center']);
        Session::flash('success', __('finance.transfer_confirmed'));
        return $this->redirect('/finance/transfers');
    }

    /** @return list<array<string,mixed>> */
    private function centers(): array
    {
        [$sql, $params] = Rbac::centerFilter('c.id');
        return Database::all("SELECT c.id, c.code, c.name_en, c.name_bn FROM training_centers c WHERE c.is_active = 1 AND {$sql} ORDER BY c.center_type = 'head_office' DESC, c.name_en", $params);
    }
}
