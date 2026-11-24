<div class="page-head"><h1><?= e(t('Fee Collection')) ?></h1>
  <div class="actions"><?= \App\Core\View::partial('partials/export_buttons', ['perm' => 'fees.export']) ?>
    <a class="btn btn-outline-secondary btn-sm" href="<?= url('fees/daily') ?>"><i class="bi bi-calendar-day"></i> Daily summary</a>
    <?php if (can('fees.collect')): ?><a class="btn btn-primary btn-sm" href="<?= url('fees/collect') ?>"><i class="bi bi-cash"></i> New Fee Collection</a><?php endif; ?></div></div>
<?= \App\Core\View::partial('partials/filters', ['f' => ['q' => 'Receipt, transaction ID, student, enrollment', 'center' => true, 'district' => true, 'dates' => true, 'default_from' => $from, 'default_to' => $to,
  'extra' => '<div class="col-6 col-md-2"><label class="form-label">Method</label><select name="payment_method_id" class="form-select form-select-sm">' . options($methods, $_GET['payment_method_id'] ?? '', 'id', 'name', 'All') . '</select></div>'
  . '<div class="col-6 col-md-2"><label class="form-label">Course</label><select name="course_id" class="form-select form-select-sm">' . options($courses, $_GET['course_id'] ?? '', 'id', 'name', 'All') . '</select></div>'
  . '<div class="col-6 col-md-1"><label class="form-label">Status</label><select name="status" class="form-select form-select-sm">' . kv_options(['posted' => 'Posted', 'reversed' => 'Reversed'], $_GET['status'] ?? '', 'All') . '</select></div>']]) ?>
<div class="row g-3 mb-3">
  <div class="col-md-3"><div class="card stat"><span class="ico ico-green"><i class="bi bi-cash-stack"></i></span><span><span class="v d-block"><?= money($totals['posted']) ?></span><span class="l">Collected (<?= (int)$totals['n'] ?> receipts)</span></span></div></div>
  <div class="col-md-3"><div class="card stat"><span class="ico ico-red"><i class="bi bi-arrow-counterclockwise"></i></span><span><span class="v d-block"><?= money($totals['reversed']) ?></span><span class="l">Reversed</span></span></div></div>
  <div class="col-md-6"><div class="card p-2 small h-100"><b class="mb-1">By payment method</b><div class="d-flex flex-wrap gap-3"><?php foreach ($byMethod as $m): ?><span><?= e($m['name']) ?>: <b><?= money($m['total'], false) ?></b></span><?php endforeach; ?></div></div></div>
</div>
<div class="card"><div class="table-responsive"><table class="table table-sm table-hover mb-0">
  <thead><tr><th>Receipt</th><th>Date</th><th>Student</th><th>Course / batch</th><th>Center</th><th>Method</th><th class="text-end">Amount</th><th class="text-end">Due after</th><th>By</th><th>Status</th><th></th></tr></thead>
  <tbody><?php foreach ($p['rows'] as $r): $rev = $r['status'] === 'reversed'; ?>
    <tr class="<?= $rev ? 'text-muted' : '' ?>"><td class="fw-semibold<?= $rev ? ' text-decoration-line-through' : '' ?>"><?= e($r['receipt_no']) ?></td><td><?= e(fmt_date($r['payment_date'])) ?></td>
      <td><a href="<?= url('students/view', ['id' => $r['student_id']]) ?>"><?= e($r['student']) ?></a><br><small class="text-muted"><?= e($r['student_code']) ?></small></td>
      <td><?= e($r['course']) ?><br><small class="text-muted"><?= e($r['enrollment_code']) ?></small></td><td><?= e($r['center']) ?></td>
      <td><?= e($r['method']) ?><?= $r['transaction_ref'] ? '<br><small class="text-muted">' . e($r['transaction_ref']) . '</small>' : '' ?></td>
      <td class="text-end"><?= money($r['amount'], false) ?></td><td class="text-end"><?= money($r['due_after'], false) ?></td><td class="small"><?= e($r['received_by_name']) ?></td>
      <td><?= status_badge($r['status']) ?><?= $rev ? '<br><small>' . e($r['reversal_reason']) . '</small>' : '' ?></td>
      <td class="table-actions"><a class="btn btn-sm btn-outline-secondary" target="_blank" href="<?= url('fees/receipt', ['id' => $r['id']]) ?>"><i class="bi bi-printer"></i></a>
        <?php if (!$rev && can('fees.reverse')): ?><form method="post" action="<?= url('fees/reverse') ?>" class="d-inline" data-confirm="Reverse receipt <?= e($r['receipt_no']) ?>? The original entry is kept." data-reason><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int)$r['id'] ?>"><button class="btn btn-sm btn-outline-danger" title="Reverse"><i class="bi bi-arrow-counterclockwise"></i></button></form><?php endif; ?></td></tr>
  <?php endforeach; ?></tbody></table></div>
  <?= $p['rows'] ? '' : \App\Core\View::partial('partials/empty') ?>
  <?= \App\Core\View::partial('partials/pagination', ['p' => $p]) ?></div>
