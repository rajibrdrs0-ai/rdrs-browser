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

/**
 * Fee waivers / scholarships: requested by front-desk staff, approved by
 * someone else (database CHECK). Center managers may approve only up to the
 * configured share of the gross fee; above that, district / Head Office.
 */
final class WaiverController extends Controller
{
    public const TYPES = ['discount', 'scholarship', 'sponsor', 'poor_fund', 'staff_quota', 'other'];

    public function index(Request $request): Response
    {
        [$scope, $params] = Rbac::centerFilter('e.center_id');
        $status = (string) $request->query('status', 'pending');
        $where = [$scope];
        if (in_array($status, ['pending', 'approved', 'rejected'], true)) {
            $where[] = 'w.status = ?';
            $params[] = $status;
        }
        $rows = Database::all(
            'SELECT w.*, e.gross_fee, e.center_id, b.batch_code, s.name_en, s.name_bn, s.registration_no, ru.name AS requester, au.name AS approver,
                    (SELECT COALESCE(SUM(x.amount), 0) FROM fee_waivers x WHERE x.enrollment_id = w.enrollment_id AND x.status = "approved") AS approved_total
               FROM fee_waivers w JOIN enrollments e ON e.id = w.enrollment_id JOIN batches b ON b.id = e.batch_id JOIN students s ON s.id = e.student_id
               JOIN users ru ON ru.id = w.requested_by LEFT JOIN users au ON au.id = w.approved_by
              WHERE ' . implode(' AND ', $where) . ' ORDER BY w.id DESC LIMIT 200', $params);
        [$rScope, $rParams] = Rbac::centerFilter('r.center_id');
        $rWhere = [$rScope];
        if (in_array($status, ['pending', 'approved', 'rejected'], true)) {
            $rWhere[] = 'r.status = ?';
            $rParams[] = $status;
        }
        $refunds = Database::all(
            'SELECT r.*, b.batch_code, s.name_en, s.name_bn, s.registration_no, ru.name AS requester, au.name AS approver
               FROM refunds r JOIN enrollments e ON e.id = r.enrollment_id JOIN batches b ON b.id = e.batch_id JOIN students s ON s.id = e.student_id
               JOIN users ru ON ru.id = r.requested_by LEFT JOIN users au ON au.id = r.approved_by
              WHERE ' . implode(' AND ', $rWhere) . ' ORDER BY r.id DESC LIMIT 200', $rParams);
        $limit = (float) (Database::value("SELECT setting_value FROM settings WHERE setting_key = 'max_waiver_percent_center'") ?? 25);
        return $this->view('waivers/index', ['waivers' => $rows, 'refunds' => $refunds, 'status' => $status, 'limit' => $limit]);
    }

    public function store(Request $request, string $enrollmentId): Response
    {
        $e = $this->findEnrollment((int) $enrollmentId);
        $input = $request->all();
        $input['amount'] = en_digits(trim((string) ($input['amount'] ?? '')));
        $data = $this->validate([
            'waiver_type' => 'required|in:' . implode(',', self::TYPES),
            'amount'      => 'required|decimal:2|min_value:1',
            'reason'      => 'required|min:5|max:255',
        ], $input);
        $open = Finance::openBalance((int) $e['id']);
        $pending = (float) Database::value("SELECT COALESCE(SUM(amount), 0) FROM fee_waivers WHERE enrollment_id = ? AND status = 'pending'", [$e['id']]);
        if ((float) $data['amount'] > $open - $pending) {
            throw new ValidationException(['amount' => [__('waivers.exceeds_open', ['open' => money(max(0, $open - $pending))])]]);
        }
        Database::query('INSERT INTO fee_waivers (enrollment_id, waiver_type, amount, reason, requested_by) VALUES (?, ?, ?, ?, ?)',
            [$e['id'], $data['waiver_type'], (float) $data['amount'], trim((string) $data['reason']), Auth::id()]);
        Audit::log('request', 'fee_waiver', Database::lastInsertId(), null, $data, (int) $e['center_id']);
        Session::flash('success', __('waivers.requested'));
        return $this->redirect('/enrollments/' . $e['id']);
    }

    public function decide(Request $request, string $id): Response
    {
        $w = Database::first('SELECT * FROM fee_waivers WHERE id = ?', [(int) $id]) ?? throw new HttpException(404);
        $e = $this->findEnrollment((int) $w['enrollment_id']);
        $data = $this->validate(['decision' => 'required|in:approve,reject', 'note' => 'nullable|max:255']);
        $note = ($data['note'] ?? '') !== '' ? trim((string) $data['note']) : null;
        if ($w['status'] !== 'pending') {
            throw new ValidationException(['decision' => [__('waivers.not_pending')]]);
        }
        if ((int) $w['requested_by'] === Auth::id()) {
            throw new ValidationException(['decision' => [__('waivers.own_request')]]);
        }
        if ($data['decision'] === 'reject') {
            if ($note === null) {
                throw new ValidationException(['note' => [__('waivers.reason_required')]]);
            }
            Database::query("UPDATE fee_waivers SET status = 'rejected', approved_by = ?, approved_at = NOW(), decision_note = ? WHERE id = ?", [Auth::id(), $note, $w['id']]);
            Session::flash('success', __('waivers.rejected'));
        } else {
            if (!Finance::canApproveWaiverAmount($e, (float) $w['amount'])) {
                throw new ValidationException(['decision' => [__('waivers.above_limit')]]);
            }
            Finance::approveWaiver($w, $note);
            Session::flash('success', __('waivers.approved', ['amount' => money($w['amount'])]));
        }
        return $this->back();
    }

    /** @return array<string,mixed> */
    private function findEnrollment(int $id): array
    {
        [$scope, $params] = Rbac::centerFilter('e.center_id');
        $e = Database::first("SELECT e.* FROM enrollments e WHERE e.id = ? AND {$scope}", array_merge([$id], $params));
        if ($e === null) {
            throw new HttpException(Database::value('SELECT id FROM enrollments WHERE id = ?', [$id]) !== null ? 403 : 404);
        }
        return $e;
    }
}
