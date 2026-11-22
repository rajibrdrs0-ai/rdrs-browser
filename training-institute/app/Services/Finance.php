<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use App\Core\ValidationException;

/**
 * Money movements. Database triggers keep invoices and the cash book in
 * step (and block edits/deletes); this service adds the business rules:
 * scope, account/method consistency, available balance, approval limits.
 */
final class Finance
{
    /** Payment method => allowed account types. */
    public const METHOD_ACCOUNTS = [
        'cash'    => ['cash'],
        'bkash'   => ['mobile_wallet'],
        'nagad'   => ['mobile_wallet'],
        'rocket'  => ['mobile_wallet'],
        'bank'    => ['bank'],
        'sponsor' => ['cash', 'bank', 'mobile_wallet'],
    ];

    public static function balance(int $accountId): float
    {
        return (float) Database::value(
            "SELECT COALESCE(SUM(CASE WHEN direction = 'in' THEN amount ELSE -amount END), 0) FROM account_transactions WHERE account_id = ?",
            [$accountId]);
    }

    /** @return list<array<string,mixed>> active accounts of one center, with balances */
    public static function accountsOf(int $centerId): array
    {
        return Database::all(
            'SELECT a.*, COALESCE(v.balance, 0) AS balance FROM finance_accounts a LEFT JOIN v_account_balances v ON v.account_id = a.id
              WHERE a.center_id = ? AND a.is_active = 1 ORDER BY FIELD(a.account_type, "cash", "mobile_wallet", "bank"), a.name', [$centerId]);
    }

    public static function nextNumber(string $prefix, string $centerCode, string $date): string
    {
        $period = substr(str_replace('-', '', $date), 0, 6);
        $seq = Sequence::next(strtolower($prefix) . ':' . $centerCode, $period);
        return $prefix . '-' . $centerCode . '-' . $period . '-' . str_pad((string) $seq, 5, '0', STR_PAD_LEFT);
    }

    /**
     * Collect money against one invoice. Returns the fee_payment id.
     * @param array<string,mixed> $invoice row incl. center_code
     */
    public static function collect(array $invoice, float $amount, int $accountId, string $method, ?string $ref, string $date, ?string $remarks): int
    {
        $errors = [];
        $balance = round((float) $invoice['amount'] - (float) $invoice['paid_amount'], 2);
        if (!in_array($invoice['status'], ['unpaid', 'partial'], true)) {
            $errors['invoice_id'][] = __('fees.invoice_closed');
        }
        if ($amount <= 0 || $amount > $balance) {
            $errors['amount'][] = __('fees.amount_range', ['max' => money($balance)]);
        }
        $account = Database::first('SELECT * FROM finance_accounts WHERE id = ? AND is_active = 1', [$accountId]);
        if ($account === null || (int) $account['center_id'] !== (int) $invoice['center_id']) {
            $errors['account_id'][] = __('fees.account_center');
        } elseif (!in_array($account['account_type'], self::METHOD_ACCOUNTS[$method] ?? [], true)) {
            $errors['account_id'][] = __('fees.account_method');
        }
        if (in_array($method, ['bkash', 'nagad', 'rocket', 'bank'], true) && ($ref === null || $ref === '')) {
            $errors['transaction_ref'][] = __('fees.ref_required');
        }
        if ($date > date('Y-m-d') || $date < (string) $invoice['invoice_date']) {
            $errors['payment_date'][] = __('fees.date_range');
        }
        if ($errors !== []) {
            throw new ValidationException($errors);
        }
        return Database::transaction(static function () use ($invoice, $amount, $accountId, $method, $ref, $date, $remarks): int {
            $receipt = self::nextNumber('MR', (string) $invoice['center_code'], $date);
            Database::query(
                'INSERT INTO fee_payments (receipt_no, invoice_id, center_id, account_id, payment_date, amount, method, transaction_ref, remarks, received_by)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
                [$receipt, $invoice['id'], $invoice['center_id'], $accountId, $date, $amount, $method, $ref, $remarks, Auth::id()]);
            return Database::lastInsertId();
        });
    }

    /**
     * Approve a waiver: the trigger lowers the enrolment's net fee; here the
     * open invoices are reduced too (latest installment first, never below
     * what is already paid).
     */
    public static function approveWaiver(array $waiver, ?string $note): void
    {
        Database::transaction(static function () use ($waiver, $note): void {
            $open = self::openBalance((int) $waiver['enrollment_id']);
            if ((float) $waiver['amount'] > $open) {
                throw new ValidationException(['amount' => [__('waivers.exceeds_open', ['open' => money($open)])]]);
            }
            Database::query("UPDATE fee_waivers SET status = 'approved', approved_by = ?, approved_at = NOW(), decision_note = ? WHERE id = ? AND status = 'pending'",
                [Auth::id(), $note, $waiver['id']]);
            $remaining = round((float) $waiver['amount'], 2);
            $invoices = Database::all(
                "SELECT * FROM fee_invoices WHERE enrollment_id = ? AND status IN ('unpaid','partial') ORDER BY installment_no DESC FOR UPDATE",
                [$waiver['enrollment_id']]);
            foreach ($invoices as $inv) {
                if ($remaining <= 0) {
                    break;
                }
                $reducible = round((float) $inv['amount'] - (float) $inv['paid_amount'], 2);
                $take = min($reducible, $remaining);
                $remaining = round($remaining - $take, 2);
                $newAmount = round((float) $inv['amount'] - $take, 2);
                if ($newAmount <= 0) {
                    Database::query("UPDATE fee_invoices SET status = 'cancelled' WHERE id = ?", [$inv['id']]);
                    continue;
                }
                $status = $newAmount <= (float) $inv['paid_amount'] ? 'paid' : $inv['status'];
                Database::query('UPDATE fee_invoices SET amount = ?, status = ? WHERE id = ?', [$newAmount, $status, $inv['id']]);
                // Keep the head breakdown equal to the invoice amount (reduce last heads first).
                $cut = $take;
                foreach (Database::all('SELECT i.* FROM fee_invoice_items i JOIN fee_heads h ON h.id = i.fee_head_id WHERE i.invoice_id = ? ORDER BY h.sort_order DESC', [$inv['id']]) as $item) {
                    if ($cut <= 0) {
                        break;
                    }
                    $part = min((float) $item['amount'], $cut);
                    $cut = round($cut - $part, 2);
                    if (round((float) $item['amount'] - $part, 2) <= 0) {
                        Database::query('DELETE FROM fee_invoice_items WHERE id = ?', [$item['id']]);
                    } else {
                        Database::query('UPDATE fee_invoice_items SET amount = amount - ? WHERE id = ?', [$part, $item['id']]);
                    }
                }
            }
        });
    }

    /** Unpaid part of the enrolment's open invoices. */
    public static function openBalance(int $enrollmentId): float
    {
        return round((float) Database::value(
            "SELECT COALESCE(SUM(amount - paid_amount), 0) FROM fee_invoices WHERE enrollment_id = ? AND status IN ('unpaid','partial')",
            [$enrollmentId]), 2);
    }

    /** Money received (valid receipts) minus refunds not rejected. */
    public static function refundable(int $enrollmentId): float
    {
        $paid = (float) Database::value(
            "SELECT COALESCE(SUM(p.amount), 0) FROM fee_payments p JOIN fee_invoices i ON i.id = p.invoice_id WHERE i.enrollment_id = ? AND p.status = 'valid'",
            [$enrollmentId]);
        $refunded = (float) Database::value("SELECT COALESCE(SUM(amount), 0) FROM refunds WHERE enrollment_id = ? AND status IN ('pending','approved','paid')", [$enrollmentId]);
        return round($paid - $refunded, 2);
    }

    /**
     * Center-level approvers may approve waivers only up to a percentage of the
     * gross fee (setting max_waiver_percent_center); above that a district
     * coordinator or Head Office must approve.
     */
    public static function canApproveWaiverAmount(array $enrollment, float $amount): bool
    {
        if (Rbac::scope() !== 'center') {
            return true;
        }
        $limit = (float) (Database::value("SELECT setting_value FROM settings WHERE setting_key = 'max_waiver_percent_center'") ?? 25);
        $gross = (float) $enrollment['gross_fee'];
        if ($gross <= 0) {
            return true;
        }
        $approved = (float) Database::value("SELECT COALESCE(SUM(amount), 0) FROM fee_waivers WHERE enrollment_id = ? AND status = 'approved'", [$enrollment['id']]);
        return (($approved + $amount) / $gross) * 100 <= $limit + 0.0001;
    }
}
