<?php
/** @var array $p @var array $items @var array $institute */
$this->layout('layouts/app', ['title' => $p['receipt_no']]);
?>
<div class="page-head">
  <h1><?= e(__('fees.receipt')) ?> <?= e($p['receipt_no']) ?></h1>
  <div class="d-flex gap-2">
    <a class="btn btn-sm btn-primary" href="<?= e(url('/fees/receipts/' . $p['id'], ['print' => 1])) ?>" target="_blank" rel="noopener"><i class="bi bi-printer me-1"></i><?= e(__('fees.print')) ?></a>
    <a class="btn btn-sm btn-outline-secondary" href="<?= e(url('/enrollments/' . $p['enrollment_id'])) ?>"><?= e(__('admission.enrollment')) ?></a>
  </div>
</div>
<div class="row g-3">
  <div class="col-lg-8"><div class="card"><div class="card-body"><?= $this->insert('fees/_receipt_body', ['p' => $p, 'items' => $items, 'institute' => $institute, 'copy' => __('fees.copy_trainee')]) ?></div></div></div>
  <div class="col-lg-4">
    <?php if ($p['status'] === 'cancelled'): ?>
      <div class="alert alert-danger"><strong><?= e(__('fees.cancelled_badge')) ?></strong> · <?= e(fmt_date(substr((string) $p['cancelled_at'], 0, 10))) ?><br><?= e(__('fees.cancelled_by')) ?>: <?= e($p['canceller'] ?? '') ?><br><?= e($p['cancel_reason']) ?></div>
    <?php elseif (can('fee.cancel_receipt')): ?>
      <form method="post" action="<?= e(url('/fees/receipts/' . $p['id'] . '/cancel')) ?>" class="card card-body" data-confirm="<?= e(__('common.confirm')) ?>" novalidate><?= csrf_field() ?>
        <h2 class="h6 text-danger"><?= e(__('fees.cancel')) ?></h2>
        <div class="mb-2"><?= form_input('reason', __('fees.cancel_reason'), '', ['required' => true, 'minlength' => 5, 'class' => 'form-control form-control-sm']) ?></div>
        <button class="btn btn-sm btn-outline-danger"><?= e(__('fees.cancel')) ?></button>
      </form>
    <?php endif; ?>
  </div>
</div>
