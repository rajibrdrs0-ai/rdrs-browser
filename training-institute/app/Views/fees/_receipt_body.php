<?php
/** @var array $p @var array $items @var array $institute @var string $copy */
use App\Services\Money;
$bn = current_locale() === 'bn';
?>
<div class="receipt <?= $p['status'] === 'cancelled' ? 'receipt-cancelled' : '' ?>">
  <div class="receipt-head">
    <div class="receipt-brand"><span class="brand-mark">R</span></div>
    <div class="flex-grow-1 text-center">
      <div class="fw-bold fs-5"><?= e($bn ? $institute['bn'] : $institute['en']) ?></div>
      <div class="small"><?= e(loc($p, 'center')) ?> · <?= e($p['center_address']) ?><?= $p['center_phone'] ? ' · ' . e($p['center_phone']) : '' ?></div>
      <div class="receipt-title"><?= e(__('fees.receipt')) ?></div>
    </div>
    <div class="receipt-copy small text-end"><?= e($copy) ?></div>
  </div>
  <table class="receipt-meta"><tr>
    <td><?= e(__('fees.receipt_no')) ?>: <strong><?= e($p['receipt_no']) ?></strong></td>
    <td class="text-end"><?= e(__('fees.date')) ?>: <strong><?= e(fmt_date($p['payment_date'])) ?></strong></td></tr>
    <tr><td colspan="2"><?= e(__('fees.received_from')) ?>: <strong><?= e(loc($p, 'name')) ?></strong> (<?= e($p['registration_no']) ?>, <?= e(__('admission.roll')) ?> <?= e($p['roll_no']) ?>)</td></tr>
    <tr><td colspan="2"><?= e(__('fees.for')) ?>: <?= e(loc($p, 'course')) ?> · <?= e($p['batch_code']) ?> · <?= e($p['invoice_no']) ?> (<?= e(__('fees.installment', ['n' => num($p['installment_no'])])) ?>)</td></tr>
  </table>
  <?php $balanceAfter = max(0, (float) $p['invoice_amount'] - (float) $p['paid_before'] - (float) $p['amount']); ?>
  <table class="receipt-lines">
    <tr class="receipt-sub"><td colspan="2"><?= e(__('fees.invoice_breakdown')) ?></td></tr>
    <?php foreach ($items as $it): ?><tr class="receipt-item"><td><?= e(loc($it, 'name')) ?></td><td class="text-end"><?= money($it['amount']) ?></td></tr><?php endforeach; ?>
    <tr><td><?= e(__('fees.invoice_total')) ?></td><td class="text-end"><?= money($p['invoice_amount']) ?></td></tr>
    <?php if ((float) $p['paid_before'] > 0): ?><tr><td><?= e(__('fees.paid_before')) ?></td><td class="text-end"><?= money($p['paid_before']) ?></td></tr><?php endif; ?>
    <tr class="receipt-total"><td><?= e(__('fees.paid_now')) ?> — <?= e(__('fees.methods.' . $p['method'])) ?><?= $p['transaction_ref'] ? ' (' . e($p['transaction_ref']) . ')' : '' ?></td><td class="text-end text-nowrap"><?= money($p['amount']) ?></td></tr>
    <tr><td><?= e(__('fees.invoice_balance')) ?></td><td class="text-end"><?= money($balanceAfter) ?></td></tr>
  </table>
  <div class="receipt-words"><?= e(__('fees.in_words')) ?>: <strong><?= e(Money::words((float) $p['amount'])) ?></strong></div>
  <div class="small mt-1"><?= e(__('fees.remaining_due')) ?>: <strong><?= money($p['enrollment_due'] ?? 0) ?></strong></div>
  <div class="receipt-sign"><div><?= e(__('fees.received_by')) ?>: <?= e($p['receiver'] ?? '') ?></div><div class="sign-line"><?= e(__('fees.signature')) ?></div></div>
  <?php if ($p['status'] === 'cancelled'): ?><div class="receipt-stamp"><?= e(__('fees.cancelled_badge')) ?></div><?php endif; ?>
</div>
