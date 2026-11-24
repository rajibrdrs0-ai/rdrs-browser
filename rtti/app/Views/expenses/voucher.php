<div class="page a5">
  <?= \App\Core\View::partial('partials/print_header') ?>
  <h2 class="doc-title">Debit Voucher</h2>
  <?php if ($x['status'] !== 'approved'): ?><div class="text-center text-danger fw-bold mb-2">STATUS: <?= e(strtoupper($x['status'])) ?></div><?php endif; ?>
  <table class="table table-sm table-bordered">
    <tr><th style="width:25%">Voucher no.</th><td><?= e($x['voucher_no']) ?></td><th style="width:15%">Date</th><td><?= e(fmt_date($x['expense_date'])) ?></td></tr>
    <tr><th>Center</th><td colspan="3"><?= e($x['center']) ?></td></tr>
    <tr><th>Head of account</th><td colspan="3"><?= e($x['category']) ?> › <?= e($x['head']) ?> <?= e($x['ah_code']) ?></td></tr>
    <tr><th>Paid to</th><td colspan="3"><?= e($x['paid_to']) ?></td></tr>
    <tr><th>Particulars</th><td colspan="3"><?= e($x['description']) ?></td></tr>
    <tr><th>Payment</th><td><?= e($x['method']) ?> <?= e($x['account']) ?></td><th>Amount</th><td class="fw-bold"><?= money($x['amount']) ?></td></tr>
    <tr><th>In words</th><td colspan="3"><?= e(amount_in_words((float)$x['amount'])) ?></td></tr>
  </table>
  <div class="sig-row"><div>Prepared by<br><?= e($x['prepared_by_name']) ?></div><div>Approved by<br><?= e($x['approved_by_name']) ?></div><div>Received by</div></div>
</div>
