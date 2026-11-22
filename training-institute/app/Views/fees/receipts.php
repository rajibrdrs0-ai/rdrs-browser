<?php
/** @var array $rows @var int $total @var int $page @var int $pages @var array $summary @var array $centers @var array $filters */
$this->layout('layouts/app', ['title' => __('fees.receipts')]);
$sum = array_sum(array_map(static fn($s) => (float) $s['total'], $summary));
?>
<div class="page-head"><h1><?= e(__('fees.receipts')) ?></h1></div>
<form class="card card-body mb-3" method="get"><div class="row g-2 align-items-end">
  <div class="col-6 col-md-2"><?= form_input('from', __('fees.from'), $filters['from'], ['type' => 'date', 'class' => 'form-control form-control-sm']) ?></div>
  <div class="col-6 col-md-2"><?= form_input('to', __('fees.to'), $filters['to'], ['type' => 'date', 'class' => 'form-control form-control-sm']) ?></div>
  <div class="col-6 col-md-3"><?= form_select('center_id', __('batches.center'), options($centers, 'name', 'id', 'code'), $filters['center_id'] ?? '', ['class' => 'form-select form-select-sm', 'placeholder' => __('common.all')]) ?></div>
  <div class="col-6 col-md-2"><?= form_select('status', __('common.status'), ['valid' => __('fees.status.valid'), 'cancelled' => __('fees.status.cancelled')], $filters['status'], ['class' => 'form-select form-select-sm', 'placeholder' => __('common.all')]) ?></div>
  <div class="col-8 col-md-2"><?= form_input('q', __('common.search'), $filters['q'], ['class' => 'form-control form-control-sm']) ?></div>
  <div class="col-4 col-md-1 d-grid"><button class="btn btn-sm btn-outline-primary"><i class="bi bi-funnel"></i></button></div>
</div></form>
<div class="card mb-3"><div class="card-body d-flex flex-wrap gap-4 small">
  <div><div class="text-body-secondary"><?= e(__('fees.summary')) ?></div><div class="fs-5 fw-semibold"><?= money($sum) ?></div></div>
  <?php foreach ($summary as $s): ?><div><div class="text-body-secondary"><?= e(__('fees.methods.' . $s['method'])) ?> (<?= num($s['n']) ?>)</div><div class="fw-semibold"><?= money($s['total']) ?></div></div><?php endforeach; ?>
</div></div>
<div class="card"><div class="table-responsive"><table class="table table-hover table-sm mb-0 align-middle">
  <thead class="table-light"><tr><th class="ps-3"><?= e(__('fees.receipt_no')) ?></th><th><?= e(__('fees.date')) ?></th><th><?= e(__('students.name_en')) ?></th>
    <th class="d-none d-md-table-cell"><?= e(__('fees.method')) ?></th><th class="d-none d-lg-table-cell"><?= e(__('fees.received_by')) ?></th><th class="text-end pe-3"><?= e(__('fees.amount')) ?></th></tr></thead>
  <tbody>
  <?php foreach ($rows as $r): ?>
    <tr class="<?= $r['status'] === 'cancelled' ? 'text-body-secondary' : '' ?>"><td class="ps-3"><a href="<?= e(url('/fees/receipts/' . $r['id'])) ?>"><?= e($r['receipt_no']) ?></a><?php if ($r['status'] === 'cancelled'): ?> <span class="badge text-bg-danger"><?= e(__('fees.status.cancelled')) ?></span><?php endif; ?></td>
      <td><?= e(fmt_date($r['payment_date'])) ?></td><td><?= e(loc($r, 'name')) ?><div class="small text-body-secondary"><?= e($r['registration_no']) ?></div></td>
      <td class="d-none d-md-table-cell"><?= e(__('fees.methods.' . $r['method'])) ?><?= $r['transaction_ref'] ? '<div class="small text-body-secondary">' . e($r['transaction_ref']) . '</div>' : '' ?></td>
      <td class="d-none d-lg-table-cell small"><?= e($r['receiver'] ?? '') ?></td>
      <td class="text-end pe-3 <?= $r['status'] === 'cancelled' ? 'text-decoration-line-through' : 'fw-semibold' ?>"><?= money($r['amount']) ?></td></tr>
  <?php endforeach; ?>
  <?php if ($rows === []): ?><tr><td colspan="6" class="text-center text-body-secondary py-4"><?= e(__('common.no_records')) ?></td></tr><?php endif; ?>
  </tbody></table></div>
  <?= $this->insert('partials/pagination', ['total' => $total, 'page' => $page, 'pages' => $pages, 'base' => '/fees/receipts', 'query' => $filters]) ?>
</div>
