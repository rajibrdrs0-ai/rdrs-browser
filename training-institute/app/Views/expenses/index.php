<?php
/** @var array $rows @var int $total @var int $page @var int $pages @var array $byCategory @var float $pendingTotal @var array $centers @var array $categories @var array $filters */
$this->layout('layouts/app', ['title' => __('expenses.title')]);
$badge = ['pending' => 'warning', 'approved' => 'success', 'rejected' => 'secondary', 'cancelled' => 'secondary'];
$me = auth()['id'];
?>
<div class="page-head"><h1><?= e(__('expenses.title')) ?></h1>
  <?php if (can('expense.create')): ?><a class="btn btn-primary btn-sm" href="<?= e(url('/expenses/create')) ?>"><i class="bi bi-plus-lg me-1"></i><?= e(__('expenses.add')) ?></a><?php endif; ?></div>
<form class="card card-body mb-3" method="get"><div class="row g-2 align-items-end">
  <div class="col-6 col-md-2"><?= form_input('from', __('fees.from'), $filters['from'], ['type' => 'date', 'class' => 'form-control form-control-sm']) ?></div>
  <div class="col-6 col-md-2"><?= form_input('to', __('fees.to'), $filters['to'], ['type' => 'date', 'class' => 'form-control form-control-sm']) ?></div>
  <div class="col-6 col-md-3"><?= form_select('center_id', __('expenses.center'), options($centers, 'name', 'id', 'code'), $filters['center_id'], ['class' => 'form-select form-select-sm', 'placeholder' => __('common.all')]) ?></div>
  <div class="col-6 col-md-2"><?= form_select('category_id', __('expenses.category'), options($categories), $filters['category_id'], ['class' => 'form-select form-select-sm', 'placeholder' => __('common.all')]) ?></div>
  <div class="col-8 col-md-2"><?= form_select('status', __('common.status'), ['pending' => __('waivers.status.pending'), 'approved' => __('waivers.status.approved'), 'rejected' => __('waivers.status.rejected')], $filters['status'], ['class' => 'form-select form-select-sm', 'placeholder' => __('common.all')]) ?></div>
  <div class="col-4 col-md-1 d-grid"><button class="btn btn-sm btn-outline-primary"><i class="bi bi-funnel"></i></button></div>
</div></form>
<div class="row g-3 mb-3">
  <div class="col-md-4"><div class="card h-100"><div class="card-body"><div class="small text-body-secondary"><?= e(__('expenses.pending_total')) ?></div><div class="fs-4 fw-semibold text-warning"><?= money($pendingTotal) ?></div></div></div></div>
  <div class="col-md-8"><div class="card h-100"><div class="card-body"><div class="small text-body-secondary mb-1"><?= e(__('expenses.by_category')) ?></div>
    <div class="d-flex flex-wrap gap-3 small"><?php foreach ($byCategory as $c): ?><span><?= e(loc(['c_en' => $c['cat_en'], 'c_bn' => $c['cat_bn']], 'c')) ?>: <strong><?= money($c['total']) ?></strong></span><?php endforeach; ?>
    <?php if ($byCategory === []): ?><span class="text-body-secondary">—</span><?php endif; ?></div></div></div></div>
</div>
<div class="card"><div class="table-responsive"><table class="table table-sm mb-0 align-middle">
  <thead class="table-light"><tr><th class="ps-3"><?= e(__('expenses.voucher')) ?></th><th><?= e(__('expenses.date')) ?></th><th><?= e(__('expenses.category')) ?></th><th><?= e(__('expenses.narration')) ?></th>
    <th class="text-end"><?= e(__('expenses.amount')) ?></th><th><?= e(__('common.status')) ?></th><th class="pe-3"></th></tr></thead>
  <tbody>
  <?php foreach ($rows as $x): ?>
    <tr><td class="ps-3 small"><?= e($x['voucher_no']) ?><div class="text-body-secondary"><?= e($x['center_code']) ?> · <?= e($x['account_name']) ?></div></td>
      <td><?= e(fmt_date($x['expense_date'])) ?></td><td><?= e(loc(['c_en' => $x['cat_en'], 'c_bn' => $x['cat_bn']], 'c')) ?></td>
      <td class="small"><?= e($x['narration']) ?><?= $x['payee'] ? ' · ' . e($x['payee']) : '' ?>
        <?php if ($x['attachment_path']): ?> <a href="<?= e(url('/expenses/' . $x['id'] . '/attachment')) ?>" target="_blank" rel="noopener"><i class="bi bi-paperclip"></i><?= e(__('expenses.view_bill')) ?></a><?php endif; ?>
        <div class="text-body-secondary"><?= e($x['requester']) ?><?= $x['approver'] ? ' → ' . e($x['approver']) : '' ?><?= $x['decision_note'] ? ': ' . e($x['decision_note']) : '' ?></div></td>
      <td class="text-end fw-semibold"><?= money($x['amount']) ?></td>
      <td><span class="badge text-bg-<?= $badge[$x['status']] ?>"><?= e(__('waivers.status.' . ($x['status'] === 'cancelled' ? 'rejected' : $x['status']))) ?></span></td>
      <td class="pe-3"><?php if ($x['status'] === 'pending' && can('expense.approve') && (int) $x['requested_by'] !== (int) $me): ?>
        <form method="post" action="<?= e(url('/expenses/' . $x['id'] . '/decide')) ?>" class="d-flex gap-1 flex-wrap" data-confirm="<?= e(__('common.confirm')) ?>"><?= csrf_field() ?>
          <input class="form-control form-control-sm" style="max-width:8rem" name="note" placeholder="<?= e(__('waivers.note')) ?>" aria-label="<?= e(__('waivers.note')) ?>">
          <button class="btn btn-sm btn-success" name="decision" value="approve"><?= e(__('waivers.approve')) ?></button>
          <button class="btn btn-sm btn-outline-danger" name="decision" value="reject"><?= e(__('waivers.reject')) ?></button></form><?php endif; ?></td></tr>
  <?php endforeach; ?>
  <?php if ($rows === []): ?><tr><td colspan="7" class="text-center text-body-secondary py-4"><?= e(__('common.no_records')) ?></td></tr><?php endif; ?>
  </tbody></table></div>
  <?= $this->insert('partials/pagination', ['total' => $total, 'page' => $page, 'pages' => $pages, 'base' => '/expenses', 'query' => $filters]) ?>
</div>
