<?php
/** @var array $rows @var int $total @var int $page @var int $pages @var array $totals @var array $centers @var array $batches @var array $filters */
$this->layout('layouts/app', ['title' => __('fees.dues')]);
?>
<div class="page-head"><h1><?= e(__('fees.dues')) ?></h1>
  <div class="small"><?= e(__('fees.outstanding')) ?>: <strong><?= money($totals['due']) ?></strong> · <?= e(__('fees.overdue')) ?>: <strong class="text-danger"><?= money($totals['overdue']) ?></strong></div></div>
<form class="card card-body mb-3" method="get"><div class="row g-2 align-items-end">
  <div class="col-6 col-md-4"><?= form_select('center_id', __('batches.center'), options($centers, 'name', 'id', 'code'), $filters['center_id'], ['class' => 'form-select form-select-sm', 'placeholder' => __('common.all')]) ?></div>
  <div class="col-6 col-md-4"><?= form_select('batch_id', __('admission.batch'), array_column($batches, 'batch_code', 'id'), $filters['batch_id'], ['class' => 'form-select form-select-sm', 'placeholder' => __('common.all')]) ?></div>
  <div class="col-6 col-md-2"><div class="form-check"><input class="form-check-input" type="checkbox" name="overdue" value="1" id="overdue" <?= $filters['overdue'] === '1' ? 'checked' : '' ?>><label class="form-check-label small" for="overdue"><?= e(__('fees.overdue_only')) ?></label></div></div>
  <div class="col-6 col-md-2 d-grid"><button class="btn btn-sm btn-outline-primary"><i class="bi bi-funnel"></i></button></div>
</div></form>
<div class="card"><div class="table-responsive"><table class="table table-hover mb-0 align-middle">
  <thead class="table-light"><tr><th><?= e(__('students.name_en')) ?></th><th><?= e(__('batches.code')) ?></th><th class="d-none d-md-table-cell"><?= e(__('students.mobile')) ?></th>
    <th class="d-none d-lg-table-cell"><?= e(__('fees.next_due')) ?></th><th class="text-end"><?= e(__('fees.overdue')) ?></th><th class="text-end"><?= e(__('admission.due')) ?></th><th></th></tr></thead>
  <tbody>
  <?php foreach ($rows as $r): ?>
    <tr><td><a href="<?= e(url('/enrollments/' . $r['enrollment_id'])) ?>"><?= e(loc($r, 'name')) ?></a><div class="small text-body-secondary"><?= e($r['registration_no']) ?></div></td>
      <td><?= e($r['batch_code']) ?><?php if (!in_array($r['status'], ['admitted', 'running'], true)): ?> <span class="badge text-bg-secondary"><?= e(__('admission.status.' . $r['status'])) ?></span><?php endif; ?></td>
      <td class="d-none d-md-table-cell small"><?= e($r['mobile']) ?><?php if ($r['guardian_mobile'] ?? $r['father_mobile']): ?><br><span class="text-body-secondary"><?= e(__('fees.guardian')) ?>: <?= e($r['guardian_mobile'] ?? $r['father_mobile']) ?></span><?php endif; ?></td>
      <td class="d-none d-lg-table-cell small <?= $r['next_due'] !== null && $r['next_due'] < date('Y-m-d') ? 'text-danger' : '' ?>"><?= e(fmt_date($r['next_due'])) ?></td>
      <td class="text-end <?= (float) $r['overdue'] > 0 ? 'text-danger fw-semibold' : 'text-body-secondary' ?>"><?= money($r['overdue']) ?></td>
      <td class="text-end fw-semibold"><?= money($r['due']) ?></td>
      <td class="text-end"><?php if (can('fee.collect')): ?><a class="btn btn-sm btn-outline-primary" href="<?= e(url('/fees/collect/' . $r['enrollment_id'])) ?>"><?= e(__('fees.collect')) ?></a><?php endif; ?></td></tr>
  <?php endforeach; ?>
  <?php if ($rows === []): ?><tr><td colspan="7" class="text-center text-body-secondary py-4"><?= e(__('common.no_records')) ?></td></tr><?php endif; ?>
  </tbody></table></div>
  <?= $this->insert('partials/pagination', ['total' => $total, 'page' => $page, 'pages' => $pages, 'base' => '/fees/dues', 'query' => $filters]) ?>
</div>
