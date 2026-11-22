<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use App\Core\ValidationException;

/**
 * Admission of a trainee into a batch:
 *  - eligibility (batch open, age limits, not already enrolled, seats)
 *  - fee calculation from the course fee structure on the batch start date
 *  - fee invoices split into installments (first due on admission day)
 * Everything runs inside one transaction; database triggers additionally
 * enforce capacity and the fee arithmetic.
 */
final class Admission
{
    /** @return array{fees:list<array<string,mixed>>,gross:float,lines:list<array{fee_head_id:int,amount:float}>} */
    public static function fees(array $batch, bool $residential): array
    {
        $fees = FeeStructure::current((int) $batch['course_id'], (int) $batch['center_id'], (string) $batch['start_date']);
        $lines = [];
        foreach ($fees as $fee) {
            $isResidential = $fee['code'] === 'RESIDENTIAL';
            // Optional heads are charged only when chosen (currently: residential / hostel).
            if ((int) $fee['is_optional'] === 1 && !($isResidential && $residential)) {
                continue;
            }
            if ($fee['amount'] > 0) {
                $lines[] = ['fee_head_id' => (int) $fee['fee_head_id'], 'code' => (string) $fee['code'], 'amount' => (float) $fee['amount']];
            }
        }
        return ['fees' => $fees, 'gross' => round(array_sum(array_column($lines, 'amount')), 2), 'lines' => $lines];
    }

    /** @return list<string> eligibility problems (empty = OK) */
    public static function problems(array $student, array $batch): array
    {
        $errors = [];
        if (!in_array($batch['status'], ['admission_open', 'running'], true)) {
            $errors[] = __('admission.batch_not_open');
        }
        if ($student['status'] !== 'active') {
            $errors[] = __('admission.student_inactive');
        }
        if (Database::value('SELECT id FROM enrollments WHERE student_id = ? AND batch_id = ?', [$student['id'], $batch['id']]) !== null) {
            $errors[] = __('admission.already_enrolled');
        }
        $taken = (int) Database::value("SELECT COUNT(*) FROM enrollments WHERE batch_id = ? AND status IN ('admitted','running','completed')", [$batch['id']]);
        if ($taken >= (int) $batch['capacity']) {
            $errors[] = __('admission.batch_full');
        }
        $age = self::ageOn((string) $student['date_of_birth'], (string) $batch['start_date']);
        if ($batch['min_age'] !== null && $age < (int) $batch['min_age']) {
            $errors[] = __('admission.too_young', ['age' => $age, 'min' => $batch['min_age']]);
        }
        if ($batch['max_age'] !== null && $age > (int) $batch['max_age']) {
            $errors[] = __('admission.too_old', ['age' => $age, 'max' => $batch['max_age']]);
        }
        return $errors;
    }

    public static function ageOn(string $dob, string $date): int
    {
        return (new \DateTimeImmutable($dob))->diff(new \DateTimeImmutable($date))->y;
    }

    /**
     * Create the enrolment and its invoices. Returns the new enrolment id.
     */
    public static function admit(array $student, array $batch, bool $residential, int $installments, string $admissionDate, ?string $sponsor): int
    {
        $problems = self::problems($student, $batch);
        if ($problems !== []) {
            throw new ValidationException(['batch_id' => $problems]);
        }
        $calc = self::fees($batch, $residential);
        $installments = max(1, min(6, $installments));

        return Database::transaction(static function () use ($student, $batch, $residential, $installments, $admissionDate, $sponsor, $calc): int {
            $roll = (int) Database::value('SELECT COALESCE(MAX(CAST(roll_no AS UNSIGNED)), 0) + 1 FROM enrollments WHERE batch_id = ? FOR UPDATE', [$batch['id']]);
            Database::query(
                'INSERT INTO enrollments (student_id, batch_id, center_id, roll_no, admission_date, is_residential, gross_fee, waiver_amount, net_fee,
                                          sponsor, status, created_by)
                 VALUES (?, ?, ?, ?, ?, ?, ?, 0, ?, ?, ?, ?)',
                [$student['id'], $batch['id'], $batch['center_id'], str_pad((string) $roll, 2, '0', STR_PAD_LEFT), $admissionDate,
                 $residential ? 1 : 0, $calc['gross'], $calc['gross'], $sponsor, $batch['status'] === 'running' ? 'running' : 'admitted', Auth::id()]
            );
            $enrollmentId = Database::lastInsertId();
            if ($calc['gross'] > 0) {
                self::createInvoices($enrollmentId, $batch, $calc['lines'], $installments, $admissionDate);
            }
            Audit::log('create', 'enrollment', $enrollmentId, null, [
                'student_id' => $student['id'], 'batch_id' => $batch['id'], 'gross_fee' => $calc['gross'],
                'installments' => $installments, 'residential' => $residential,
            ], (int) $batch['center_id']);
            return $enrollmentId;
        });
    }

    /**
     * Split fee lines into installments. Heads are allocated in order (admission
     * fee first) so the first installment always covers the admission fee.
     * @param list<array{fee_head_id:int,code:string,amount:float}> $lines
     */
    private static function createInvoices(int $enrollmentId, array $batch, array $lines, int $installments, string $admissionDate): void
    {
        $amounts = self::splitInstallments($lines, $installments);

        $remaining = $lines;
        $center = (string) Database::value('SELECT code FROM training_centers WHERE id = ?', [$batch['center_id']]);
        $start = new \DateTimeImmutable($admissionDate);
        foreach ($amounts as $i => $amount) {
            $due = $i === 0 ? $start : $start->modify('+' . $i . ' month');
            $batchEnd = new \DateTimeImmutable((string) $batch['end_date']);
            if ($due > $batchEnd) {
                $due = $batchEnd;
            }
            $period = $start->format('Ym');
            $invoiceNo = 'INV-' . $center . '-' . $period . '-' . str_pad((string) Sequence::next('invoice:' . $center, $period), 5, '0', STR_PAD_LEFT);
            Database::query(
                'INSERT INTO fee_invoices (invoice_no, enrollment_id, center_id, invoice_date, due_date, installment_no, amount, created_by)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?)',
                [$invoiceNo, $enrollmentId, $batch['center_id'], $admissionDate, $due->format('Y-m-d'), $i + 1, $amount, Auth::id()]
            );
            $invoiceId = Database::lastInsertId();

            // Greedy allocation of heads into this installment.
            $need = $amount;
            foreach ($remaining as $k => &$line) {
                if ($need <= 0.0) {
                    break;
                }
                if ($line['amount'] <= 0.0) {
                    continue;
                }
                $take = round(min($need, $line['amount']), 2);
                Database::query('INSERT INTO fee_invoice_items (invoice_id, fee_head_id, amount) VALUES (?, ?, ?)', [$invoiceId, $line['fee_head_id'], $take]);
                $line['amount'] = round($line['amount'] - $take, 2);
                $need = round($need - $take, 2);
            }
            unset($line);
        }
    }

    /**
     * Installment amounts: equal whole-taka parts, remainder on the last one,
     * but the first installment always covers at least the full admission fee.
     * @param list<array{code:string,amount:float}> $lines
     * @return list<float>
     */
    public static function splitInstallments(array $lines, int $installments): array
    {
        $total = round(array_sum(array_column($lines, 'amount')), 2);
        $admission = 0.0;
        foreach ($lines as $line) {
            if ($line['code'] === 'ADMISSION') {
                $admission += (float) $line['amount'];
            }
        }
        $installments = max(1, $installments);
        $base = floor($total / $installments);
        if ($installments > 1 && $base < $admission) {
            // First = admission fee, the rest spread over the remaining installments.
            $rest = $total - $admission;
            $each = floor($rest / ($installments - 1));
            $amounts = array_merge([$admission], array_fill(0, $installments - 1, $each));
            $amounts[$installments - 1] = round($rest - $each * ($installments - 2), 2);
        } else {
            $amounts = array_fill(0, $installments, $base);
            $amounts[$installments - 1] = round($total - $base * ($installments - 1), 2);
        }
        return array_values(array_filter(array_map(static fn($a): float => round((float) $a, 2), $amounts), static fn(float $a): bool => $a > 0));
    }

    /** Move an enrolment to another batch of the same course in the same center. */
    public static function transfer(array $enrollment, array $target, string $reason): int
    {
        if ((int) $target['id'] === (int) $enrollment['batch_id']) {
            throw new ValidationException(['batch_id' => [__('admission.same_batch')]]);
        }
        if ((int) $target['course_id'] !== (int) $enrollment['course_id'] || (int) $target['center_id'] !== (int) $enrollment['center_id']) {
            throw new ValidationException(['batch_id' => [__('admission.transfer_rule')]]);
        }
        if (!in_array($enrollment['status'], ['admitted', 'running'], true)) {
            throw new ValidationException(['batch_id' => [__('admission.not_active')]]);
        }
        $problems = [];
        if (!in_array($target['status'], ['admission_open', 'running'], true)) {
            $problems[] = __('admission.batch_not_open');
        }
        if (Database::value('SELECT id FROM enrollments WHERE student_id = ? AND batch_id = ?', [$enrollment['student_id'], $target['id']]) !== null) {
            $problems[] = __('admission.already_enrolled');
        }
        $taken = (int) Database::value("SELECT COUNT(*) FROM enrollments WHERE batch_id = ? AND status IN ('admitted','running','completed')", [$target['id']]);
        if ($taken >= (int) $target['capacity']) {
            $problems[] = __('admission.batch_full');
        }
        if ($problems !== []) {
            throw new ValidationException(['batch_id' => $problems]);
        }
        return Database::transaction(static function () use ($enrollment, $target, $reason): int {
            $roll = (int) Database::value('SELECT COALESCE(MAX(CAST(roll_no AS UNSIGNED)), 0) + 1 FROM enrollments WHERE batch_id = ? FOR UPDATE', [$target['id']]);
            Database::query(
                'INSERT INTO enrollments (student_id, batch_id, center_id, roll_no, admission_date, is_residential, gross_fee, waiver_amount, net_fee,
                                          sponsor, status, created_by)
                 VALUES (?, ?, ?, ?, CURRENT_DATE, ?, ?, ?, ?, ?, ?, ?)',
                [$enrollment['student_id'], $target['id'], $target['center_id'], str_pad((string) $roll, 2, '0', STR_PAD_LEFT),
                 $enrollment['is_residential'], $enrollment['gross_fee'], $enrollment['waiver_amount'], $enrollment['net_fee'], $enrollment['sponsor'],
                 $target['status'] === 'running' ? 'running' : 'admitted', Auth::id()]
            );
            $newId = Database::lastInsertId();
            // Invoices (and their receipts) follow the trainee; waivers too.
            Database::query('UPDATE fee_invoices SET enrollment_id = ? WHERE enrollment_id = ?', [$newId, $enrollment['id']]);
            Database::query('UPDATE fee_waivers SET enrollment_id = ? WHERE enrollment_id = ?', [$newId, $enrollment['id']]);
            Database::query("UPDATE enrollments SET status = 'transferred', status_reason = ?, gross_fee = 0, waiver_amount = 0, net_fee = 0 WHERE id = ?",
                [mb_substr($reason, 0, 255), $enrollment['id']]);
            Audit::log('transfer', 'enrollment', (int) $enrollment['id'], ['batch_id' => $enrollment['batch_id']],
                ['batch_id' => $target['id'], 'new_enrollment_id' => $newId, 'reason' => $reason], (int) $target['center_id']);
            return $newId;
        });
    }
}
