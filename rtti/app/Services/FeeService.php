<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Audit;
use App\Core\Auth;
use App\Core\DB;
use App\Core\Sequence;

/**
 * Fee collection. Every receipt is linked to a valid enrollment, is numbered automatically,
 * and writes an immutable ledger entry. Reversal never deletes – it adds a counter entry.
 */
final class FeeService
{
    public static function collect(array $d): int
    {
        return DB::transaction(function () use ($d) {
            $e = DB::row('SELECT e.*, s.student_code, c.code AS center_code FROM enrollments e
                JOIN students s ON s.id = e.student_id JOIN centers c ON c.id = e.center_id WHERE e.id = :id FOR UPDATE', ['id' => (int)$d['enrollment_id']]);
            if (!$e) {
                throw new \DomainException('Enrollment not found.');
            }
            if (PHP_SAPI !== 'cli' && !Auth::canAccessCenter((int)$e['center_id'])) {
                throw new \DomainException('This enrollment belongs to another center.');
            }
            if (!in_array($e['status'], ['pending', 'active', 'completed'], true)) {
                throw new \DomainException("Cannot collect fees for a {$e['status']} enrollment.");
            }
            $amount = round((float)$d['amount'], 2);
            $due = round((float)$e['payable_amount'] - (float)$e['paid_amount'], 2);
            if ($amount <= 0) {
                throw new \DomainException('Amount must be greater than zero.');
            }
            if ($amount > $due) {
                throw new \DomainException('Amount (' . number_format($amount, 2) . ') exceeds the outstanding due (' . number_format($due, 2) . ').');
            }
            $method = DB::row('SELECT * FROM payment_methods WHERE id = :id AND is_active = 1', ['id' => (int)($d['payment_method_id'] ?? 0)]);
            if (!$method) {
                throw new \DomainException('Please select a valid payment method.');
            }
            if ((int)$method['requires_reference'] && trim((string)($d['transaction_ref'] ?? '')) === '') {
                throw new \DomainException("Transaction ID / reference is required for {$method['name']} payments.");
            }
            $date = $d['payment_date'] ?: date('Y-m-d');
            if ($date > date('Y-m-d')) {
                throw new \DomainException('Payment date cannot be in the future.');
            }
            $receiptNo = Sequence::next('receipt_no_format', 'MR-{CENTER}-{YY}-{SEQ5}', ['center' => $e['center_code']]);
            $id = DB::insert('fee_collections', [
                'receipt_no' => $receiptNo,
                'transaction_ref' => nullify(trim((string)($d['transaction_ref'] ?? ''))),
                'enrollment_id' => $e['id'],
                'student_id' => $e['student_id'],
                'center_id' => $e['center_id'],
                'payment_date' => $date,
                'payable_snapshot' => $e['payable_amount'],
                'previous_paid' => $e['paid_amount'],
                'amount' => $amount,
                'due_after' => round($due - $amount, 2),
                'payment_method_id' => $method['id'],
                'account_id' => !empty($d['account_id']) ? (int)$d['account_id'] : null,
                'received_by' => Auth::id() ?? (int)DB::value('SELECT MIN(id) FROM users'),
                'remarks' => nullify($d['remarks'] ?? null),
            ]);
            DB::query('UPDATE enrollments SET paid_amount = paid_amount + :a WHERE id = :id', ['a' => $amount, 'id' => $e['id']]);
            EnrollmentService::ledger((int)$e['id'], (int)$e['student_id'], (int)$e['center_id'], $date, 'payment', 0, $amount, "Payment received, receipt $receiptNo", $id);
            Audit::log('fees', 'collect', $id, "Receipt $receiptNo: " . number_format($amount, 2) . " from {$e['student_code']} ({$e['enrollment_code']})", null, null, (int)$e['center_id']);
            return $id;
        });
    }

    public static function reverse(int $feeId, string $reason): void
    {
        DB::transaction(function () use ($feeId, $reason) {
            $f = DB::row('SELECT * FROM fee_collections WHERE id = :id FOR UPDATE', ['id' => $feeId]);
            if (!$f) {
                throw new \DomainException('Receipt not found.');
            }
            if ($f['status'] !== 'posted') {
                throw new \DomainException('This receipt is already reversed.');
            }
            if (!Auth::canAccessCenter((int)$f['center_id'])) {
                throw new \DomainException('This receipt belongs to another center.');
            }
            $e = DB::row('SELECT * FROM enrollments WHERE id = :id FOR UPDATE', ['id' => $f['enrollment_id']]);
            if ((float)$e['paid_amount'] < (float)$f['amount']) {
                throw new \DomainException('Cannot reverse: enrollment paid balance is lower than the receipt amount (it may have been transferred). Reverse on the active enrollment instead.');
            }
            DB::update('fee_collections', ['status' => 'reversed', 'reversed_by' => Auth::id(), 'reversed_at' => date('Y-m-d H:i:s'), 'reversal_reason' => mb_substr($reason, 0, 255)], 'id = :id', ['id' => $feeId]);
            DB::query('UPDATE enrollments SET paid_amount = paid_amount - :a WHERE id = :id', ['a' => $f['amount'], 'id' => $f['enrollment_id']]);
            EnrollmentService::ledger((int)$f['enrollment_id'], (int)$f['student_id'], (int)$f['center_id'], date('Y-m-d'), 'reversal', (float)$f['amount'], 0, "Reversal of receipt {$f['receipt_no']}: $reason", $feeId);
            Audit::log('fees', 'reverse', $feeId, "Receipt {$f['receipt_no']} reversed: $reason", $f, null, (int)$f['center_id']);
        });
    }
}
