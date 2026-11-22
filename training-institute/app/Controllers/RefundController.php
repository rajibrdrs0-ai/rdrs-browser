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

/** Refund requests → approval by another person → payout from a center account. */
final class RefundController extends Controller
{
    public function store(Request $request, string $enrollmentId): Response
    {
        $e = $this->findEnrollment((int) $enrollmentId);
        $input = $request->all();
        $input['amount'] = en_digits(trim((string) ($input['amount'] ?? '')));
        $data = $this->validate(['amount' => 'required|decimal:2|min_value:1', 'reason' => 'required|min:5|max:255'], $input);
        $max = Finance::refundable((int) $e['id']);
        if ((float) $data['amount'] > $max) {
            throw new ValidationException(['amount' => [__('refunds.exceeds', ['max' => money($max)])]]);
        }
        $account = Database::value("SELECT id FROM finance_accounts WHERE center_id = ? AND account_type = 'cash' AND is_active = 1 ORDER BY id LIMIT 1", [$e['center_id']]);
        if ($account === null) {
            throw new ValidationException(['amount' => [__('refunds.no_account')]]);
        }
        $no = Finance::nextNumber('RF', (string) $e['center_code'], date('Y-m-d'));
        Database::query('INSERT INTO refunds (refund_no, enrollment_id, center_id, account_id, amount, reason, requested_by) VALUES (?, ?, ?, ?, ?, ?, ?)',
            [$no, $e['id'], $e['center_id'], (int) $account, (float) $data['amount'], trim((string) $data['reason']), Auth::id()]);
        Audit::log('request', 'refund', Database::lastInsertId(), null, $data + ['refund_no' => $no], (int) $e['center_id']);
        Session::flash('success', __('refunds.requested', ['no' => $no]));
        return $this->redirect('/enrollments/' . $e['id']);
    }

    public function decide(Request $request, string $id): Response
    {
        $r = $this->find((int) $id);
        $data = $this->validate(['decision' => 'required|in:approve,reject', 'note' => 'nullable|max:255']);
        $note = ($data['note'] ?? '') !== '' ? trim((string) $data['note']) : null;
        if ($r['status'] !== 'pending') {
            throw new ValidationException(['decision' => [__('waivers.not_pending')]]);
        }
        if ((int) $r['requested_by'] === Auth::id()) {
            throw new ValidationException(['decision' => [__('waivers.own_request')]]);
        }
        if ($data['decision'] === 'reject' && $note === null) {
            throw new ValidationException(['note' => [__('waivers.reason_required')]]);
        }
        Database::query('UPDATE refunds SET status = ?, approved_by = ?, decision_note = ? WHERE id = ?',
            [$data['decision'] === 'approve' ? 'approved' : 'rejected', Auth::id(), $note, $r['id']]);
        Audit::log($data['decision'], 'refund', (int) $r['id'], ['status' => 'pending'], ['status' => $data['decision'], 'note' => $note], (int) $r['center_id']);
        Session::flash('success', __($data['decision'] === 'approve' ? 'refunds.approved' : 'refunds.rejected'));
        return $this->back();
    }

    public function pay(Request $request, string $id): Response
    {
        $r = $this->find((int) $id);
        $data = $this->validate(['account_id' => 'required|integer']);
        if ($r['status'] !== 'approved') {
            throw new ValidationException(['account_id' => [__('refunds.not_approved')]]);
        }
        $account = Database::first('SELECT * FROM finance_accounts WHERE id = ? AND center_id = ? AND is_active = 1', [(int) $data['account_id'], $r['center_id']]);
        if ($account === null) {
            throw new ValidationException(['account_id' => [__('fees.account_center')]]);
        }
        if (Finance::balance((int) $account['id']) < (float) $r['amount']) {
            throw new ValidationException(['account_id' => [__('finance.insufficient', ['balance' => money(Finance::balance((int) $account['id']))])]]);
        }
        Database::transaction(static function () use ($r, $account): void {
            Database::query('UPDATE refunds SET account_id = ? WHERE id = ?', [$account['id'], $r['id']]);
            Database::query("UPDATE refunds SET status = 'paid', paid_at = NOW(), paid_by = ? WHERE id = ?", [Auth::id(), $r['id']]);
        });
        Audit::log('pay', 'refund', (int) $r['id'], null, ['amount' => $r['amount'], 'account_id' => $account['id']], (int) $r['center_id']);
        Session::flash('success', __('refunds.paid', ['amount' => money($r['amount'])]));
        return $this->back();
    }

    /** @return array<string,mixed> */
    private function find(int $id): array
    {
        [$scope, $params] = Rbac::centerFilter('r.center_id');
        $r = Database::first("SELECT r.* FROM refunds r WHERE r.id = ? AND {$scope}", array_merge([$id], $params));
        if ($r === null) {
            throw new HttpException(Database::value('SELECT id FROM refunds WHERE id = ?', [$id]) !== null ? 403 : 404);
        }
        return $r;
    }

    /** @return array<string,mixed> */
    private function findEnrollment(int $id): array
    {
        [$scope, $params] = Rbac::centerFilter('e.center_id');
        $e = Database::first("SELECT e.*, c.code AS center_code FROM enrollments e JOIN training_centers c ON c.id = e.center_id WHERE e.id = ? AND {$scope}", array_merge([$id], $params));
        if ($e === null) {
            throw new HttpException(Database::value('SELECT id FROM enrollments WHERE id = ?', [$id]) !== null ? 403 : 404);
        }
        return $e;
    }
}
