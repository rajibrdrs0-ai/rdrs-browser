<?php
/** @var array $accounts @var ?int $accountId @var string $from @var string $to @var float $opening @var array $rows */
$this->layout('layouts/app', ['title' => __('finance.cashbook')]);
$acc = [];
foreach ($accounts as $a) { $acc[$a['id']] = $a['center_code'] . ' — ' . $a['name']; }
$running = $opening; $in = 0.0; $out = 0.0;
?>
<div class="page-head"><h1><?= e(__('finance.cashbook')) ?></h1></div>
<form class="card card-body mb-3" method="get"><div class="row g-2 align-items-end">
  <div class="col-md-5"><?= form_select('account_id', __('finance.account'), $acc, $accountId ?? '', ['class' => 'form-select form-select-sm', 'placeholder' => false]) ?></div>
  <div class="col-5 col-md-3"><?= form_input('from', __('fees.from'), $from, ['type' => 'date', 'class' => 'form-control form-control-sm']) ?></div>
  <div class="col-5 col-md-3"><?= form_input('to', __('fees.to'), $to, ['type' => 'date', 'class' => 'form-control form-control-sm']) ?></div>
  <div class="col-2 col-md-1 d-grid"><button class="btn btn-sm btn-outline-primary"><i class="bi bi-funnel"></i></button></div>
</div></form>
<div class="card"><div class="table-responsive"><table class="table table-sm mb-0 align-middle">
  <thead class="table-light"><tr><th class="ps-3"><?= e(__('expenses.date')) ?></th><th><?= e(__('finance.particulars')) ?></th><th class="text-end"><?= e(__('finance.in')) ?></th><th class="text-end"><?= e(__('finance.out')) ?></th><th class="text-end pe-3"><?= e(__('finance.running')) ?></th></tr></thead>
  <tbody>
    <tr class="table-secondary"><td class="ps-3"><?= e(fmt_date($from)) ?></td><td colspan="3"><?= e(__('finance.opening_balance')) ?></td><td class="text-end pe-3 fw-semibold text-nowrap"><?= money($opening) ?></td></tr>
    <?php foreach ($rows as $t): $isIn = $t['direction'] === 'in'; $running += $isIn ? (float) $t['amount'] : -(float) $t['amount']; $isIn ? $in += (float) $t['amount'] : $out += (float) $t['amount']; ?>
      <tr><td class="ps-3"><?= e(fmt_date($t['txn_date'])) ?></td>
        <td><span class="badge text-bg-light border"><?= e(__('finance.sources.' . $t['source_type'])) ?></span> <?= e($t['narration']) ?><span class="small text-body-secondary"> · <?= e($t['user_name'] ?? '') ?></span></td>
        <td class="text-end text-success"><?= $isIn ? money($t['amount'], false) : '' ?></td><td class="text-end text-danger"><?= $isIn ? '' : money($t['amount'], false) ?></td>
        <td class="text-end pe-3"><?= money($running, false) ?></td></tr>
    <?php endforeach; ?>
    <?php if ($rows === []): ?><tr><td colspan="5" class="ps-3 text-body-secondary"><?= e(__('finance.no_txn')) ?></td></tr><?php endif; ?>
    <tr class="table-secondary fw-semibold"><td class="ps-3"><?= e(fmt_date($to)) ?></td><td><?= e(__('finance.closing_balance')) ?></td><td class="text-end"><?= money($in, false) ?></td><td class="text-end"><?= money($out, false) ?></td><td class="text-end pe-3 text-nowrap"><?= money($running) ?></td></tr>
  </tbody></table></div></div>
