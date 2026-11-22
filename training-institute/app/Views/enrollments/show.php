<?php
/** @var array $e @var array $invoices @var array $items @var array $history @var array $targets @var float $paidValid
 *  @var array $waivers @var array $refunds @var array $receipts @var float $openBalance @var float $refundable */
use App\Controllers\WaiverController;
$this->layout('layouts/app', ['title' => __('admission.enrollment') . ' · ' . $e['batch_code']]);
$st = ['admitted' => 'info', 'running' => 'success', 'completed' => 'dark', 'dropped_out' => 'warning', 'transferred' => 'secondary', 'cancelled' => 'danger'];
$inv = ['unpaid' => 'warning', 'partial' => 'info', 'paid' => 'success', 'cancelled' => 'secondary'];
$active = in_array($e['status'], ['admitted', 'running'], true);
?>
<div class="page-head">
  <div class="d-flex align-items-center gap-3"><?= $this->insert('students/_avatar', ['s' => ['id' => $e['student_id'], 'photo_path' => $e['photo_path']], 'size' => 48]) ?>
    <div><h1><?= e(loc($e, 'name')) ?> <span class="badge text-bg-<?= $st[$e['status']] ?>"><?= e(__('admission.status.' . $e['status'])) ?></span></h1>
      <div class="small text-body-secondary"><a href="<?= e(url('/students/' . $e['student_id'])) ?>"><?= e($e['registration_no']) ?></a> ·
        <a href="<?= e(url('/batches/' . $e['batch_id'])) ?>"><?= e($e['batch_code']) ?></a> · <?= e(loc($e, 'course')) ?> · <?= e(__('admission.roll')) ?> <?= e($e['roll_no']) ?></div></div></div>
  <?php if ($openBalance > 0 && can('fee.collect')): ?><a class="btn btn-primary" href="<?= e(url('/fees/collect/' . $e['id'])) ?>"><i class="bi bi-cash-coin me-1"></i><?= e(__('fees.collect')) ?></a><?php endif; ?>
</div>
<div class="row g-3">
  <div class="col-lg-4">
    <div class="card mb-3"><div class="card-body"><dl class="row small mb-0">
      <dt class="col-6"><?= e(__('admission.admission_date')) ?></dt><dd class="col-6"><?= e(fmt_date($e['admission_date'])) ?></dd>
      <dt class="col-6"><?= e(__('admission.residential')) ?></dt><dd class="col-6"><?= e(__((int) $e['is_residential'] === 1 ? 'common.yes' : 'common.no')) ?></dd>
      <dt class="col-6"><?= e(__('admission.gross')) ?></dt><dd class="col-6 text-end"><?= money($e['gross_fee']) ?></dd>
      <dt class="col-6"><?= e(__('admission.waiver')) ?></dt><dd class="col-6 text-end"><?= money($e['waiver_amount']) ?></dd>
      <dt class="col-6"><?= e(__('admission.net')) ?></dt><dd class="col-6 text-end fw-semibold"><?= money($e['net_fee']) ?></dd>
      <dt class="col-6"><?= e(__('admission.paid')) ?></dt><dd class="col-6 text-end text-success"><?= money($e['paid']) ?></dd>
      <dt class="col-6"><?= e(__('admission.due')) ?></dt><dd class="col-6 text-end <?= (float) $e['due'] > 0 ? 'text-danger fw-semibold' : '' ?>"><?= money($e['due']) ?></dd>
      <?php if ($e['sponsor']): ?><dt class="col-6"><?= e(__('admission.sponsor')) ?></dt><dd class="col-6"><?= e($e['sponsor']) ?></dd><?php endif; ?>
      <dt class="col-6"><?= e(__('admission.attendance')) ?></dt><dd class="col-6 text-end"><?= $e['attendance_percent'] !== null ? num($e['attendance_percent'], 1) . '%' : '—' ?></dd>
    </dl><div class="small text-body-secondary mt-2"><?= e(__('admission.min_attendance', ['pct' => num($e['min_attendance_percent'], 0)])) ?></div></div></div>

    <?php if ($active && can('enrollment.update_status')): ?>
    <form method="post" action="<?= e(url('/enrollments/' . $e['id'] . '/status')) ?>" class="card card-body mb-3" data-confirm="<?= e(__('common.confirm')) ?>" novalidate><?= csrf_field() ?>
      <h2 class="h6"><?= e(__('admission.change_status')) ?></h2>
      <div class="mb-2"><?= form_select('status', __('admission.status_label'), ['dropped_out' => __('admission.drop_out')] + ($paidValid <= 0 ? ['cancelled' => __('admission.cancel')] : []), 'dropped_out', ['class' => 'form-select form-select-sm', 'placeholder' => false]) ?></div>
      <div class="mb-2"><?= form_input('reason', __('admission.reason'), '', ['class' => 'form-control form-control-sm', 'required' => true, 'minlength' => 5]) ?></div>
      <button class="btn btn-sm btn-outline-danger"><?= e(__('common.save')) ?></button>
    </form>
    <?php endif; ?>
    <?php if ($openBalance > 0 && can('waiver.request') && in_array($e['status'], ['admitted', 'running'], true)): ?>
    <form method="post" action="<?= e(url('/enrollments/' . $e['id'] . '/waivers')) ?>" class="card card-body mb-3" novalidate><?= csrf_field() ?>
      <h2 class="h6"><?= e(__('waivers.request')) ?></h2>
      <div class="row g-2">
        <div class="col-6"><?= form_select('waiver_type', __('waivers.type'), array_combine(WaiverController::TYPES, array_map(static fn($t) => __('waivers.types.' . $t), WaiverController::TYPES)), 'poor_fund', ['class' => 'form-select form-select-sm', 'placeholder' => false]) ?></div>
        <div class="col-6"><?= form_input('amount', __('waivers.amount'), '', ['class' => 'form-control form-control-sm', 'inputmode' => 'decimal', 'required' => true, 'id' => 'waiver_amount']) ?></div>
        <div class="col-12"><?= form_input('reason', __('waivers.reason'), '', ['class' => 'form-control form-control-sm', 'required' => true, 'id' => 'waiver_reason']) ?></div>
      </div>
      <button class="btn btn-sm btn-outline-primary mt-2"><?= e(__('waivers.submit')) ?></button>
    </form>
    <?php endif; ?>
    <?php if ($refundable > 0 && can('refund.request')): ?>
    <form method="post" action="<?= e(url('/enrollments/' . $e['id'] . '/refunds')) ?>" class="card card-body mb-3" novalidate><?= csrf_field() ?>
      <h2 class="h6"><?= e(__('refunds.request')) ?> <span class="small fw-normal text-body-secondary"><?= e(__('refunds.refundable', ['amount' => money($refundable)])) ?></span></h2>
      <div class="row g-2">
        <div class="col-5"><?= form_input('amount', __('refunds.amount'), '', ['class' => 'form-control form-control-sm', 'inputmode' => 'decimal', 'required' => true, 'id' => 'refund_amount']) ?></div>
        <div class="col-7"><?= form_input('reason', __('refunds.reason'), '', ['class' => 'form-control form-control-sm', 'required' => true, 'id' => 'refund_reason']) ?></div>
      </div>
      <button class="btn btn-sm btn-outline-secondary mt-2"><?= e(__('refunds.request')) ?></button>
    </form>
    <?php endif; ?>
    <?php if ($targets !== []): ?>
    <form method="post" action="<?= e(url('/enrollments/' . $e['id'] . '/transfer')) ?>" class="card card-body mb-3" data-confirm="<?= e(__('common.confirm')) ?>" novalidate><?= csrf_field() ?>
      <h2 class="h6"><?= e(__('admission.transfer')) ?></h2><p class="small text-body-secondary"><?= e(__('admission.transfer_help')) ?></p>
      <div class="mb-2"><?php $t = []; foreach ($targets as $b) { $t[$b['id']] = $b['batch_code'] . ' — ' . $b['name']; } ?><?= form_select('batch_id', __('admission.batch'), $t, '', ['class' => 'form-select form-select-sm', 'required' => true]) ?></div>
      <div class="mb-2"><?= form_input('reason', __('admission.reason'), '', ['class' => 'form-control form-control-sm', 'required' => true, 'id' => 'transfer_reason']) ?></div>
      <button class="btn btn-sm btn-outline-primary"><?= e(__('admission.transfer')) ?></button>
    </form>
    <?php endif; ?>
  </div>

  <div class="col-lg-8">
    <div class="card mb-3"><div class="card-header bg-body fw-semibold d-flex justify-content-between"><span><i class="bi bi-receipt me-1"></i><?= e(__('admission.invoices')) ?></span>
      </div>
      <div class="table-responsive"><table class="table table-sm mb-0 align-middle">
        <thead class="table-light"><tr><th class="ps-3">#</th><th><?= e(__('admission.invoice_no')) ?></th><th><?= e(__('admission.due_on')) ?></th><th class="text-end"><?= e(__('admission.amount')) ?></th><th class="text-end"><?= e(__('admission.paid')) ?></th><th><?= e(__('admission.status_label')) ?></th></tr></thead>
        <tbody>
        <?php foreach ($invoices as $i): $overdue = in_array($i['status'], ['unpaid', 'partial'], true) && $i['due_date'] < date('Y-m-d'); ?>
          <tr><td class="ps-3"><?= num($i['installment_no']) ?></td>
            <td><?= e($i['invoice_no']) ?><div class="small text-body-secondary"><?php foreach ($items[(int) $i['id']] ?? [] as $it): ?><?= e(loc($it, 'name')) ?> <?= money($it['amount'], false) ?>; <?php endforeach; ?></div></td>
            <td class="<?= $overdue ? 'text-danger fw-semibold' : '' ?>"><?= e(fmt_date($i['due_date'])) ?></td>
            <td class="text-end"><?= money($i['amount']) ?></td><td class="text-end"><?= money($i['paid_amount']) ?></td>
            <td><span class="badge text-bg-<?= $inv[$i['status']] ?>"><?= e(__('admission.invoice_status.' . $i['status'])) ?></span></td></tr>
        <?php endforeach; ?>
        <?php if ($invoices === []): ?><tr><td colspan="6" class="ps-3 text-body-secondary"><?= e(__('common.no_records')) ?></td></tr><?php endif; ?>
        </tbody></table></div></div>
    <?php if ($receipts !== []): ?>
    <div class="card mb-3"><div class="card-header bg-body fw-semibold"><i class="bi bi-receipt-cutoff me-1"></i><?= e(__('fees.receipts')) ?></div>
      <ul class="list-group list-group-flush small">
        <?php foreach ($receipts as $r): ?><li class="list-group-item d-flex justify-content-between <?= $r['status'] === 'cancelled' ? 'text-body-secondary' : '' ?>">
          <span><a href="<?= e(url('/fees/receipts/' . $r['id'])) ?>"><?= e($r['receipt_no']) ?></a> · <?= e(fmt_date($r['payment_date'])) ?> · <?= e(__('fees.methods.' . $r['method'])) ?>
            <?php if ($r['status'] === 'cancelled'): ?><span class="badge text-bg-danger"><?= e(__('fees.status.cancelled')) ?></span><?php endif; ?></span>
          <span class="<?= $r['status'] === 'cancelled' ? 'text-decoration-line-through' : 'fw-semibold' ?>"><?= money($r['amount']) ?></span></li><?php endforeach; ?>
      </ul></div>
    <?php endif; ?>
    <?php if ($waivers !== [] || $refunds !== []): $wb = ['pending' => 'warning', 'approved' => 'success', 'rejected' => 'secondary', 'paid' => 'dark']; ?>
    <div class="card mb-3"><div class="card-header bg-body fw-semibold"><i class="bi bi-check2-circle me-1"></i><?= e(__('waivers.title')) ?></div>
      <ul class="list-group list-group-flush small">
        <?php foreach ($waivers as $w): ?><li class="list-group-item d-flex justify-content-between"><span><?= e(__('waivers.types.' . $w['waiver_type'])) ?> · <?= e($w['reason']) ?> <span class="text-body-secondary">(<?= e($w['requester']) ?><?= $w['approver'] ? ' → ' . e($w['approver']) : '' ?>)</span></span>
          <span><span class="badge text-bg-<?= $wb[$w['status']] ?>"><?= e(__('waivers.status.' . $w['status'])) ?></span> <?= money($w['amount']) ?></span></li><?php endforeach; ?>
        <?php foreach ($refunds as $r): ?><li class="list-group-item d-flex justify-content-between"><span><?= e($r['refund_no']) ?> · <?= e($r['reason']) ?></span>
          <span><span class="badge text-bg-<?= $wb[$r['status']] ?>"><?= e(__('waivers.status.' . $r['status'])) ?></span> −<?= money($r['amount']) ?></span></li><?php endforeach; ?>
      </ul></div>
    <?php endif; ?>
    <?php if ($history !== []): ?>
    <div class="card"><div class="card-header bg-body fw-semibold"><?= e(__('admission.history')) ?></div>
      <ul class="list-group list-group-flush small">
        <?php foreach ($history as $h): ?><li class="list-group-item"><?= e(fmt_date(substr((string) $h['changed_at'], 0, 10))) ?> · <?= e(__('admission.status.' . ($h['old_status'] ?? 'admitted'))) ?> → <strong><?= e(__('admission.status.' . $h['new_status'])) ?></strong> · <?= e($h['reason'] ?? '') ?> <span class="text-body-secondary">(<?= e($h['user_name'] ?? '—') ?>)</span></li><?php endforeach; ?>
      </ul></div>
    <?php endif; ?>
  </div>
</div>
