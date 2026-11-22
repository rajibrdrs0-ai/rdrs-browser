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
use App\Services\Admission;
use App\Services\Audit;
use App\Services\Rbac;

/** Admission of trainees into batches, enrolment record, status changes and transfers. */
final class EnrollmentController extends Controller
{
    public function create(Request $request): Response
    {
        $student = $this->findStudent((int) $request->query('student_id', 0));
        $batches = $this->openBatches();
        $batchId = $this->intOrNull($request->query('batch_id'));
        $batch = null;
        $preview = null;
        $problems = [];
        if ($batchId !== null) {
            $batch = $this->findBatch($batchId);
            $residential = (string) $request->query('residential', '0') === '1';
            $preview = Admission::fees($batch, $residential);
            $preview['installments'] = Admission::splitInstallments($preview['lines'], max(1, min(6, (int) $request->query('installments', 1))));
            $problems = Admission::problems($student, $batch);
        }
        return $this->view('enrollments/create', [
            'student' => $student, 'batches' => $batches, 'batch' => $batch, 'preview' => $preview, 'problems' => $problems,
            'residential' => (string) $request->query('residential', '0') === '1',
            'installments' => max(1, min(6, (int) $request->query('installments', 1))),
        ]);
    }

    public function store(Request $request): Response
    {
        $data = $this->validate([
            'student_id'     => 'required|integer',
            'batch_id'       => 'required|integer',
            'installments'   => 'required|integer|min_value:1|max_value:6',
            'admission_date' => 'required|date',
            'sponsor'        => 'nullable|max:120',
        ]);
        $student = $this->findStudent((int) $data['student_id']);
        $batch = $this->findBatch((int) $data['batch_id']);
        if ((string) $data['admission_date'] > date('Y-m-d')) {
            throw new ValidationException(['admission_date' => [__('admission.future_date')]]);
        }
        $id = Admission::admit($student, $batch, (bool) $request->input('is_residential', 0), (int) $data['installments'],
            (string) $data['admission_date'], ($data['sponsor'] ?? '') !== '' ? trim((string) $data['sponsor']) : null);
        Session::flash('success', __('admission.done', ['name' => $student['name_en'], 'batch' => $batch['batch_code']]));
        return $this->redirect('/enrollments/' . $id);
    }

    public function show(Request $request, string $id): Response
    {
        $enrollment = $this->find((int) $id);
        $invoices = Database::all('SELECT * FROM fee_invoices WHERE enrollment_id = ? ORDER BY installment_no, id', [$enrollment['id']]);
        $items = [];
        if ($invoices !== []) {
            foreach (Database::all(
                'SELECT i.*, h.name_en, h.name_bn FROM fee_invoice_items i JOIN fee_heads h ON h.id = i.fee_head_id
                  WHERE i.invoice_id IN ' . Database::in(array_column($invoices, 'id')) . ' ORDER BY h.sort_order',
                array_map('intval', array_column($invoices, 'id'))) as $item) {
                $items[(int) $item['invoice_id']][] = $item;
            }
        }
        return $this->view('enrollments/show', [
            'e'         => $enrollment,
            'invoices'  => $invoices,
            'items'     => $items,
            'history'   => Database::all(
                'SELECT l.*, u.name AS user_name FROM enrollment_status_logs l LEFT JOIN users u ON u.id = l.changed_by
                  WHERE l.enrollment_id = ? ORDER BY l.id DESC', [$enrollment['id']]),
            'targets'   => Rbac::can('enrollment.transfer') && in_array($enrollment['status'], ['admitted', 'running'], true)
                ? Database::all("SELECT id, batch_code, name, status FROM batches WHERE course_id = ? AND center_id = ? AND id <> ?
                                   AND status IN ('admission_open','running') ORDER BY start_date",
                    [$enrollment['course_id'], $enrollment['center_id'], $enrollment['batch_id']])
                : [],
            'paidValid' => (float) Database::value(
                "SELECT COALESCE(SUM(p.amount), 0) FROM fee_payments p JOIN fee_invoices i ON i.id = p.invoice_id
                  WHERE i.enrollment_id = ? AND p.status = 'valid'", [$enrollment['id']]),
        ]);
    }

    public function changeStatus(Request $request, string $id): Response
    {
        $e = $this->find((int) $id);
        $data = $this->validate(['status' => 'required|in:dropped_out,cancelled', 'reason' => 'required|min:5|max:255']);
        if (!in_array($e['status'], ['admitted', 'running'], true)) {
            throw new ValidationException(['status' => [__('admission.not_active')]]);
        }
        $paid = (float) Database::value(
            "SELECT COALESCE(SUM(p.amount), 0) FROM fee_payments p JOIN fee_invoices i ON i.id = p.invoice_id WHERE i.enrollment_id = ? AND p.status = 'valid'",
            [$e['id']]);
        if ($data['status'] === 'cancelled' && $paid > 0) {
            throw new ValidationException(['status' => [__('admission.cancel_paid')]]);
        }
        Database::transaction(static function () use ($e, $data): void {
            Database::query('UPDATE enrollments SET status = ?, status_reason = ? WHERE id = ?', [$data['status'], trim((string) $data['reason']), $e['id']]);
            // Nothing more is owed for future installments once a trainee leaves.
            Database::query("UPDATE fee_invoices SET status = 'cancelled' WHERE enrollment_id = ? AND status = 'unpaid'", [$e['id']]);
        });
        Session::flash('success', __('admission.status_changed', ['status' => __('admission.status.' . $data['status'])]));
        return $this->redirect('/enrollments/' . $e['id']);
    }

    public function transfer(Request $request, string $id): Response
    {
        $e = $this->find((int) $id);
        $data = $this->validate(['batch_id' => 'required|integer', 'reason' => 'required|min:5|max:255']);
        $target = $this->findBatch((int) $data['batch_id']);
        $newId = Admission::transfer($e, $target, trim((string) $data['reason']));
        Session::flash('success', __('admission.transferred', ['batch' => $target['batch_code']]));
        return $this->redirect('/enrollments/' . $newId);
    }

    // ---- helpers ------------------------------------------------------------

    /** @return array<string,mixed> */
    private function find(int $id): array
    {
        [$sql, $params] = Rbac::batchFilter('b');
        $e = Database::first(
            "SELECT e.*, b.batch_code, b.name AS batch_name, b.course_id, b.status AS batch_status, b.start_date, b.end_date,
                    co.name_en AS course_en, co.name_bn AS course_bn, co.min_attendance_percent,
                    c.code AS center_code, c.name_en AS center_en, c.name_bn AS center_bn,
                    s.registration_no, s.name_en, s.name_bn, s.mobile, s.photo_path,
                    COALESCE(d.paid, 0) AS paid, COALESCE(d.due, e.net_fee) AS due
               FROM enrollments e JOIN batches b ON b.id = e.batch_id JOIN courses co ON co.id = b.course_id
               JOIN training_centers c ON c.id = e.center_id JOIN students s ON s.id = e.student_id
               LEFT JOIN v_enrollment_dues d ON d.enrollment_id = e.id
              WHERE e.id = ? AND {$sql}", array_merge([$id], $params));
        if ($e === null) {
            throw new HttpException(Database::value('SELECT id FROM enrollments WHERE id = ?', [$id]) !== null ? 403 : 404);
        }
        return $e;
    }

    /** @return array<string,mixed> */
    private function findStudent(int $id): array
    {
        [$sql, $params] = Rbac::studentFilter('s');
        $student = Database::first("SELECT s.* FROM students s WHERE s.id = ? AND s.deleted_at IS NULL AND {$sql}", array_merge([$id], $params));
        if ($student === null) {
            throw new HttpException(Database::value('SELECT id FROM students WHERE id = ?', [$id]) !== null ? 403 : 404);
        }
        return $student;
    }

    /** @return array<string,mixed> */
    private function findBatch(int $id): array
    {
        [$sql, $params] = Rbac::batchFilter('b');
        $batch = Database::first(
            "SELECT b.*, co.min_age, co.max_age, co.name_en AS course_en, co.name_bn AS course_bn
               FROM batches b JOIN courses co ON co.id = b.course_id WHERE b.id = ? AND {$sql}", array_merge([$id], $params));
        if ($batch === null) {
            throw new ValidationException(['batch_id' => [__('users.center_not_allowed')]]);
        }
        return $batch;
    }

    /** @return list<array<string,mixed>> */
    private function openBatches(): array
    {
        [$sql, $params] = Rbac::batchFilter('b');
        return Database::all(
            "SELECT b.id, b.batch_code, b.name, b.status, b.capacity, b.start_date, c.code AS center_code, co.name_en AS course_en, co.name_bn AS course_bn,
                    (SELECT COUNT(*) FROM enrollments e WHERE e.batch_id = b.id AND e.status IN ('admitted','running','completed')) AS enrolled
               FROM batches b JOIN courses co ON co.id = b.course_id JOIN training_centers c ON c.id = b.center_id
              WHERE b.status IN ('admission_open','running') AND {$sql} ORDER BY c.code, b.start_date", $params);
    }
}
