<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Audit;
use App\Core\Auth;
use App\Core\DB;
use App\Core\Sequence;

/**
 * Enrollment business rules:
 *  - batch must be open for enrollment and have free capacity
 *  - a student cannot be enrolled twice in the same batch unless an authorised reason is recorded
 *  - fee = center-specific fee structure (if any) else course fee; payable = fee - discount
 *  - every charge / discount is written to the append-only ledger (payment_transactions)
 */
final class EnrollmentService
{
    public static function courseFee(int $courseId, int $centerId, ?string $onDate = null): float
    {
        $onDate ??= date('Y-m-d');
        $fee = DB::value('SELECT fee FROM fee_structures WHERE course_id = :c AND (center_id = :ctr OR center_id IS NULL) AND is_active = 1 AND effective_from <= :d
                          ORDER BY center_id IS NULL, effective_from DESC LIMIT 1', ['c' => $courseId, 'ctr' => $centerId, 'd' => $onDate]);
        if ($fee === null) {
            $fee = DB::value('SELECT fee FROM courses WHERE id = :c', ['c' => $courseId]);
        }
        return round((float)$fee, 2);
    }

    public static function enrolledCount(int $batchId): int
    {
        return (int)DB::value("SELECT COUNT(*) FROM enrollments WHERE batch_id = :b AND status IN ('pending','active','completed')", ['b' => $batchId]);
    }

    /**
     * @param array $d student_id, batch_id, enrollment_date, discount, discount_reason, remarks,
     *                 override_reason, initial_payment, payment_method_id, account_id, transaction_ref, require_approval(bool)
     * @return array{enrollment_id:int, receipt_id:?int}
     */
    public static function enroll(array $d): array
    {
        return DB::transaction(function () use ($d) {
            $student = DB::row('SELECT * FROM students WHERE id = :id AND deleted_at IS NULL FOR UPDATE', ['id' => (int)$d['student_id']]);
            if (!$student) {
                throw new \DomainException('Student not found.');
            }
            if ($student['status'] === 'blacklisted') {
                throw new \DomainException('This student is blacklisted and cannot be enrolled.');
            }
            $batch = DB::row('SELECT b.*, bs.allows_enrollment, bs.name AS status_name, c.code AS course_code, c.name AS course_name
                FROM batches b JOIN batch_statuses bs ON bs.id = b.batch_status_id JOIN courses c ON c.id = b.course_id
                WHERE b.id = :id AND b.deleted_at IS NULL FOR UPDATE', ['id' => (int)$d['batch_id']]);
            if (!$batch) {
                throw new \DomainException('Batch not found.');
            }
            if (!Auth::canAccessCenter((int)$batch['center_id']) && PHP_SAPI !== 'cli') {
                throw new \DomainException('You cannot enroll students into a batch of another center.');
            }
            if (!(int)$batch['allows_enrollment']) {
                throw new \DomainException("Batch status is '{$batch['status_name']}' – enrollment is closed.");
            }
            $count = self::enrolledCount((int)$batch['id']);
            if ($count >= (int)$batch['max_students']) {
                throw new \DomainException("Batch is full ({$count}/{$batch['max_students']}).");
            }
            $dup = DB::value("SELECT enrollment_code FROM enrollments WHERE student_id = :s AND batch_id = :b AND status IN ('pending','active','completed')",
                ['s' => $student['id'], 'b' => $batch['id']]);
            $override = trim((string)($d['override_reason'] ?? ''));
            if ($dup && $override === '') {
                throw new \DomainException("Student is already enrolled in this batch ($dup). Record an authorised exception reason to enroll again.");
            }
            if ($dup && !Auth::can('enrollments.approve') && PHP_SAPI !== 'cli') {
                throw new \DomainException('Only users with enrollment approval permission may record a duplicate-enrollment exception.');
            }

            $date = $d['enrollment_date'] ?: date('Y-m-d');
            $fee = self::courseFee((int)$batch['course_id'], (int)$batch['center_id'], $date);
            $discount = round(max(0, (float)($d['discount'] ?? 0)), 2);
            if ($discount > $fee) {
                throw new \DomainException('Discount cannot be greater than the course fee (' . number_format($fee, 2) . ').');
            }
            if ($discount > 0 && trim((string)($d['discount_reason'] ?? '')) === '') {
                throw new \DomainException('Please enter the reason for the discount.');
            }
            $payable = round($fee - $discount, 2);
            $initial = round(max(0, (float)($d['initial_payment'] ?? 0)), 2);
            if ($initial > $payable) {
                throw new \DomainException('Initial payment cannot exceed the payable amount (' . number_format($payable, 2) . ').');
            }
            $status = !empty($d['require_approval']) ? 'pending' : 'active';
            $code = Sequence::next('enrollment_id_format', 'ENR-{YYYY}-{SEQ6}');
            $eid = DB::insert('enrollments', [
                'enrollment_code' => $code,
                'student_id' => $student['id'],
                'course_id' => $batch['course_id'],
                'batch_id' => $batch['id'],
                'center_id' => $batch['center_id'],
                'enrollment_date' => $date,
                'course_fee' => $fee,
                'discount' => $discount,
                'discount_reason' => nullify($d['discount_reason'] ?? null),
                'payable_amount' => $payable,
                'status' => $status,
                'approved_by' => $status === 'active' ? Auth::id() : null,
                'approved_at' => $status === 'active' ? date('Y-m-d H:i:s') : null,
                'duplicate_override_reason' => $dup ? $override : null,
                'remarks' => nullify($d['remarks'] ?? null),
                'created_by' => Auth::id(),
            ]);
            self::ledger($eid, (int)$student['id'], (int)$batch['center_id'], $date, 'charge', $fee, 0, 'Course fee: ' . $batch['course_name']);
            if ($discount > 0) {
                self::ledger($eid, (int)$student['id'], (int)$batch['center_id'], $date, 'discount', 0, $discount, 'Discount: ' . ($d['discount_reason'] ?? ''));
            }
            Audit::log('enrollments', 'create', $eid, "Enrolled {$student['student_code']} in {$batch['batch_code']}" . ($dup ? " (duplicate exception: $override)" : ''), null, null, (int)$batch['center_id']);

            $receiptId = null;
            if ($initial > 0) {
                $receiptId = FeeService::collect([
                    'enrollment_id' => $eid,
                    'amount' => $initial,
                    'payment_date' => $date,
                    'payment_method_id' => (int)($d['payment_method_id'] ?? 0),
                    'account_id' => $d['account_id'] ?? null,
                    'transaction_ref' => $d['transaction_ref'] ?? null,
                    'remarks' => 'Admission payment',
                ]);
            }
            if ($student['status'] === 'alumni' || $student['status'] === 'inactive') {
                DB::update('students', ['status' => 'active'], 'id = :id', ['id' => $student['id']]);
            }
            return ['enrollment_id' => $eid, 'receipt_id' => $receiptId];
        });
    }

    public static function ledger(int $enrollmentId, int $studentId, int $centerId, string $date, string $type, float $debit, float $credit, string $narration, ?int $feeId = null): void
    {
        DB::insert('payment_transactions', [
            'enrollment_id' => $enrollmentId, 'student_id' => $studentId, 'center_id' => $centerId, 'fee_collection_id' => $feeId,
            'txn_date' => $date, 'txn_type' => $type, 'debit' => round($debit, 2), 'credit' => round($credit, 2),
            'narration' => mb_substr($narration, 0, 255), 'created_by' => Auth::id(),
        ]);
    }

    public static function approve(int $id): void
    {
        DB::transaction(function () use ($id) {
            $e = DB::row('SELECT * FROM enrollments WHERE id = :id FOR UPDATE', ['id' => $id]);
            if (!$e || $e['status'] !== 'pending') {
                throw new \DomainException('Only pending enrollments can be approved.');
            }
            DB::update('enrollments', ['status' => 'active', 'approved_by' => Auth::id(), 'approved_at' => date('Y-m-d H:i:s')], 'id = :id', ['id' => $id]);
            Audit::log('enrollments', 'approve', $id, 'Enrollment approved ' . $e['enrollment_code'], null, null, (int)$e['center_id']);
        });
    }

    /** Cancel / drop. Paid money is NOT deleted; outstanding due is written off with an adjustment entry. */
    public static function cancel(int $id, string $reason, string $newStatus = 'cancelled'): void
    {
        if (!in_array($newStatus, ['cancelled', 'dropped'], true)) {
            throw new \DomainException('Invalid status.');
        }
        DB::transaction(function () use ($id, $reason, $newStatus) {
            $e = DB::row('SELECT * FROM enrollments WHERE id = :id FOR UPDATE', ['id' => $id]);
            if (!$e || !in_array($e['status'], ['pending', 'active'], true)) {
                throw new \DomainException('Only pending or active enrollments can be cancelled.');
            }
            if (DB::exists("SELECT 1 FROM certificates WHERE enrollment_id = :e AND status = 'issued'", ['e' => $id])) {
                throw new \DomainException('A certificate has been issued for this enrollment. Cancel the certificate first.');
            }
            $due = (float)$e['payable_amount'] - (float)$e['paid_amount'];
            if ($due > 0) {
                self::ledger($id, (int)$e['student_id'], (int)$e['center_id'], date('Y-m-d'), 'adjustment', 0, $due, "Due written off on $newStatus: $reason");
            }
            DB::update('enrollments', [
                'status' => $newStatus, 'cancelled_by' => Auth::id(), 'cancelled_at' => date('Y-m-d H:i:s'), 'cancel_reason' => mb_substr($reason, 0, 255),
                'payable_amount' => (float)$e['paid_amount'],
            ], 'id = :id', ['id' => $id]);
            Audit::log('enrollments', $newStatus, $id, "Enrollment {$e['enrollment_code']} $newStatus: $reason", $e, null, (int)$e['center_id']);
        });
    }

    /**
     * Batch transfer: the old enrollment is closed as 'transferred' and a new one is opened in the
     * target batch carrying the same fee terms and the money already paid.
     */
    public static function transfer(int $id, int $newBatchId, string $reason): int
    {
        return DB::transaction(function () use ($id, $newBatchId, $reason) {
            $e = DB::row('SELECT * FROM enrollments WHERE id = :id FOR UPDATE', ['id' => $id]);
            if (!$e || !in_array($e['status'], ['pending', 'active'], true)) {
                throw new \DomainException('Only pending or active enrollments can be transferred.');
            }
            $nb = DB::row('SELECT b.*, bs.allows_enrollment FROM batches b JOIN batch_statuses bs ON bs.id = b.batch_status_id WHERE b.id = :id AND b.deleted_at IS NULL FOR UPDATE', ['id' => $newBatchId]);
            if (!$nb || (int)$nb['id'] === (int)$e['batch_id']) {
                throw new \DomainException('Choose a different target batch.');
            }
            if ((int)$nb['course_id'] !== (int)$e['course_id']) {
                throw new \DomainException('Target batch must be of the same course.');
            }
            if (!(int)$nb['allows_enrollment']) {
                throw new \DomainException('Target batch is not open for enrollment.');
            }
            if (PHP_SAPI !== 'cli' && !Auth::canAccessCenter((int)$nb['center_id'])) {
                throw new \DomainException('You are not assigned to the target batch center.');
            }
            if (self::enrolledCount($newBatchId) >= (int)$nb['max_students']) {
                throw new \DomainException('Target batch is full.');
            }
            $paid = (float)$e['paid_amount'];
            $code = Sequence::next('enrollment_id_format', 'ENR-{YYYY}-{SEQ6}');
            $newId = DB::insert('enrollments', [
                'enrollment_code' => $code, 'student_id' => $e['student_id'], 'course_id' => $e['course_id'], 'batch_id' => $newBatchId,
                'center_id' => $nb['center_id'], 'enrollment_date' => date('Y-m-d'), 'course_fee' => $e['course_fee'], 'discount' => $e['discount'],
                'discount_reason' => $e['discount_reason'], 'payable_amount' => $e['payable_amount'], 'paid_amount' => $paid, 'status' => 'active',
                'approved_by' => Auth::id(), 'approved_at' => date('Y-m-d H:i:s'), 'transferred_from_id' => $id,
                'remarks' => 'Transferred from ' . $e['enrollment_code'] . ': ' . $reason, 'created_by' => Auth::id(),
            ]);
            // Ledger: close the old enrollment balance, open the new one with the same balance
            $due = (float)$e['payable_amount'] - $paid;
            self::ledger($id, (int)$e['student_id'], (int)$e['center_id'], date('Y-m-d'), 'adjustment', 0, $due, "Transferred to $code");
            self::ledger($newId, (int)$e['student_id'], (int)$nb['center_id'], date('Y-m-d'), 'charge', $due, 0, "Balance brought from {$e['enrollment_code']} (paid " . number_format($paid, 2) . ')');
            DB::update('enrollments', ['status' => 'transferred', 'payable_amount' => $paid, 'cancel_reason' => "Transferred to $code: $reason",
                'cancelled_by' => Auth::id(), 'cancelled_at' => date('Y-m-d H:i:s')], 'id = :id', ['id' => $id]);
            Audit::log('enrollments', 'transfer', $id, "Transferred {$e['enrollment_code']} to $code ($reason)", null, null, (int)$e['center_id']);
            return $newId;
        });
    }

    /** Attendance % for an enrollment (present + late count as attended) */
    public static function attendancePercent(int $enrollmentId): ?float
    {
        $r = DB::row("SELECT COUNT(*) total, SUM(status IN ('present','late')) attended FROM attendance_details WHERE enrollment_id = :e", ['e' => $enrollmentId]);
        if (!$r || (int)$r['total'] === 0) {
            return null;
        }
        return round((int)$r['attended'] * 100 / (int)$r['total'], 1);
    }
}
