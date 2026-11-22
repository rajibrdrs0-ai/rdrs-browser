<?php
/** @var array $e @var array $invoices @var array $items @var array $history @var array $targets @var float $paidValid */
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
      <span class="small fw-normal text-body-secondary"><?= e(__('admission.fees_note')) ?></span></div>
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
    <?php if ($history !== []): ?>
    <div class="card"><div class="card-header bg-body fw-semibold"><?= e(__('admission.history')) ?></div>
      <ul class="list-group list-group-flush small">
        <?php foreach ($history as $h): ?><li class="list-group-item"><?= e(fmt_date(substr((string) $h['changed_at'], 0, 10))) ?> · <?= e(__('admission.status.' . ($h['old_status'] ?? 'admitted'))) ?> → <strong><?= e(__('admission.status.' . $h['new_status'])) ?></strong> · <?= e($h['reason'] ?? '') ?> <span class="text-body-secondary">(<?= e($h['user_name'] ?? '—') ?>)</span></li><?php endforeach; ?>
      </ul></div>
    <?php endif; ?>
  </div>
</div>
