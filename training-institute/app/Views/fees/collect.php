<?php
/** @var array $e @var array $invoices @var ?int $selected @var array $accounts @var array $receipts */
use App\Services\Finance;
$this->layout('layouts/app', ['title' => __('fees.collect')]);
$invOptions = [];
foreach ($invoices as $i) {
    $invOptions[$i['id']] = $i['invoice_no'] . ' · ' . __('fees.installment', ['n' => $i['installment_no']]) . ' · ' . __('fees.balance') . ' ' . money((float) $i['amount'] - (float) $i['paid_amount']) . ' · ' . fmt_date($i['due_date']);
}
$selInv = null;
foreach ($invoices as $i) { if ((int) $i['id'] === (int) $selected) { $selInv = $i; } }
$accOptions = [];
foreach ($accounts as $a) { $accOptions[$a['id']] = $a['name'] . ' (' . __('finance.types.' . $a['account_type']) . ')'; }
$methods = array_combine(array_keys(Finance::METHOD_ACCOUNTS), array_map(static fn($m) => __('fees.methods.' . $m), array_keys(Finance::METHOD_ACCOUNTS)));
?>
<div class="page-head">
  <div class="d-flex align-items-center gap-3"><?= $this->insert('students/_avatar', ['s' => ['id' => $e['student_id'], 'photo_path' => $e['photo_path']], 'size' => 48]) ?>
    <div><h1><?= e(__('fees.collect')) ?></h1><div class="small text-body-secondary"><a href="<?= e(url('/enrollments/' . $e['id'])) ?>"><?= e(loc($e, 'name')) ?></a> · <?= e($e['registration_no']) ?> · <?= e($e['batch_code']) ?></div></div></div>
</div>
<div class="row g-3">
  <div class="col-lg-7">
    <?php if ($invoices === []): ?><div class="alert alert-success"><?= e(__('fees.no_open')) ?></div><?php else: ?>
    <form method="post" action="<?= e(url('/fees/collect/' . $e['id'])) ?>" class="card card-body" novalidate><?= csrf_field() ?>
      <div class="row g-3">
        <div class="col-12"><?= form_select('invoice_id', __('fees.invoice'), $invOptions, $selected ?? '', ['required' => true, 'placeholder' => false]) ?></div>
        <div class="col-sm-6"><?= form_input('amount', __('fees.amount'), $selInv !== null ? number_format((float) $selInv['amount'] - (float) $selInv['paid_amount'], 2, '.', '') : '', ['required' => true, 'inputmode' => 'decimal', 'class' => 'form-control form-control-lg text-end']) ?></div>
        <div class="col-sm-6"><?= form_input('payment_date', __('fees.date'), date('Y-m-d'), ['type' => 'date', 'required' => true, 'max' => date('Y-m-d')]) ?></div>
        <div class="col-sm-6"><?= form_select('method', __('fees.method'), $methods, 'cash', ['placeholder' => false, 'required' => true]) ?></div>
        <div class="col-sm-6"><?= form_select('account_id', __('fees.account'), $accOptions, $accounts[0]['id'] ?? '', ['placeholder' => false, 'required' => true]) ?></div>
        <div class="col-sm-6"><?= form_input('transaction_ref', __('fees.ref'), '', ['maxlength' => 60]) ?></div>
        <div class="col-sm-6"><?= form_input('remarks', __('fees.remarks'), '', ['maxlength' => 255]) ?></div>
        <div class="col-12"><button class="btn btn-primary btn-lg"><i class="bi bi-printer me-1"></i><?= e(__('fees.save')) ?></button></div>
      </div>
    </form>
    <?php endif; ?>
  </div>
  <div class="col-lg-5">
    <div class="card mb-3"><div class="card-body"><dl class="row small mb-0">
      <dt class="col-6"><?= e(__('admission.net')) ?></dt><dd class="col-6 text-end"><?= money($e['net_fee']) ?></dd>
      <dt class="col-6"><?= e(__('admission.paid')) ?></dt><dd class="col-6 text-end text-success"><?= money($e['paid'] ?? 0) ?></dd>
      <dt class="col-6"><?= e(__('admission.due')) ?></dt><dd class="col-6 text-end fw-semibold"><?= money($e['due'] ?? 0) ?></dd>
      <dt class="col-6"><?= e(__('fees.overdue')) ?></dt><dd class="col-6 text-end text-danger"><?= money($e['overdue'] ?? 0) ?></dd>
    </dl></div></div>
    <div class="card"><div class="card-header bg-body fw-semibold"><?= e(__('fees.history')) ?></div>
      <ul class="list-group list-group-flush small">
        <?php foreach ($receipts as $r): ?><li class="list-group-item d-flex justify-content-between <?= $r['status'] === 'cancelled' ? 'text-decoration-line-through text-body-secondary' : '' ?>">
          <span><a href="<?= e(url('/fees/receipts/' . $r['id'])) ?>"><?= e($r['receipt_no']) ?></a> · <?= e(fmt_date($r['payment_date'])) ?> · <?= e(__('fees.methods.' . $r['method'])) ?></span><span><?= money($r['amount']) ?></span></li><?php endforeach; ?>
        <?php if ($receipts === []): ?><li class="list-group-item text-body-secondary"><?= e(__('common.no_records')) ?></li><?php endif; ?>
      </ul></div>
  </div>
</div>
