<div class="page-head"><h1><?= e(t('Expenses')) ?></h1>
  <div class="actions"><?= \App\Core\View::partial('partials/export_buttons', ['perm' => 'expenses.export']) ?>
  <?php if (can('expenses.create')): ?><a class="btn btn-primary btn-sm" href="<?= url('expenses/create') ?>"><i class="bi bi-plus-lg"></i> Add Expense</a><?php endif; ?></div></div>
<?= \App\Core\View::partial('partials/filters', ['f' => ['q' => 'Voucher, description, paid to', 'center' => true, 'district' => true, 'dates' => true, 'default_from' => $from, 'default_to' => $to,
  'extra' => '<div class="col-6 col-md-2"><label class="form-label">Expense head</label><select name="expense_head_id" class="form-select form-select-sm">' . options($heads, $_GET['expense_head_id'] ?? '', 'id', 'name', 'All') . '</select></div>'
  . '<div class="col-6 col-md-1"><label class="form-label">Status</label><select name="status" class="form-select form-select-sm">' . kv_options(['pending' => 'Pending', 'approved' => 'Approved', 'rejected' => 'Rejected', 'cancelled' => 'Cancelled'], $_GET['status'] ?? '', 'All') . '</select></div>']]) ?>
<div class="row g-3 mb-3">
  <div class="col-md-3"><div class="card stat"><span class="ico ico-amber"><i class="bi bi-receipt"></i></span><span><span class="v d-block"><?= money($tot['approved']) ?></span><span class="l">Approved expenses</span></span></div></div>
  <div class="col-md-3"><div class="card stat"><span class="ico ico-purple"><i class="bi bi-hourglass"></i></span><span><span class="v d-block"><?= money($tot['pending']) ?></span><span class="l">Awaiting approval</span></span></div></div>
  <div class="col-md-6"><div class="card p-2 small h-100"><b class="mb-1">Top expense heads</b><div class="d-flex flex-wrap gap-3"><?php foreach ($byHead as $h): ?><span><?= e($h['name']) ?>: <b><?= money($h['total'], false) ?></b></span><?php endforeach; ?></div></div></div></div>
<div class="card"><div class="table-responsive"><table class="table table-sm table-hover mb-0">
  <thead><tr><th>Voucher</th><th>Date</th><th>Center</th><th>Head</th><th>Description</th><th class="text-end">Amount</th><th>Prepared / approved</th><th>Status</th><th></th></tr></thead>
  <tbody><?php foreach ($p['rows'] as $r): ?>
    <tr><td><a class="fw-semibold" href="<?= url('expenses/view', ['id' => $r['id']]) ?>"><?= e($r['voucher_no']) ?></a></td><td><?= e(fmt_date($r['expense_date'])) ?></td><td><?= e($r['center']) ?></td>
      <td><?= e($r['head']) ?><br><small class="text-muted"><?= e($r['category']) ?></small></td><td class="small"><?= e($r['description']) ?><?= $r['supporting_document'] ? ' <i class="bi bi-paperclip"></i>' : '' ?></td>
      <td class="text-end"><?= money($r['amount'], false) ?></td><td class="small"><?= e($r['prepared_by_name']) ?><br><span class="text-muted"><?= e($r['approved_by_name']) ?></span></td><td><?= status_badge($r['status']) ?></td>
      <td class="table-actions"><a class="btn btn-sm btn-outline-secondary" target="_blank" href="<?= url('expenses/voucher', ['id' => $r['id']]) ?>"><i class="bi bi-printer"></i></a></td></tr>
  <?php endforeach; ?></tbody></table></div>
  <?= $p['rows'] ? '' : \App\Core\View::partial('partials/empty') ?>
  <?= \App\Core\View::partial('partials/pagination', ['p' => $p]) ?></div>
