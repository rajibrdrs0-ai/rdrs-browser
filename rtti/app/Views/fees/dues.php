<div class="page-head"><h1><?= e(t('Due List')) ?></h1><span class="badge text-bg-danger fs-6"><?= money($total) ?></span><div class="actions"><?= \App\Core\View::partial('partials/export_buttons') ?></div></div>
<?= \App\Core\View::partial('partials/filters', ['f' => ['q' => 'Student ID, name, mobile, enrollment', 'center' => true, 'district' => true,
  'extra' => '<div class="col-6 col-md-2"><label class="form-label">Course</label><select name="course_id" class="form-select form-select-sm">' . options($courses, $_GET['course_id'] ?? '', 'id', 'name', 'All') . '</select></div>']]) ?>
<div class="card"><div class="table-responsive"><table class="table table-sm table-hover mb-0">
  <thead><tr><th>Student</th><th>Mobile</th><th>Course / batch</th><th>Center</th><th class="text-end">Payable</th><th class="text-end">Paid</th><th class="text-end">Due</th><th>Last payment</th><th></th></tr></thead>
  <tbody><?php foreach ($p['rows'] as $r): ?>
    <tr><td><a href="<?= url('students/view', ['id' => $r['student_id']]) ?>"><?= e($r['student']) ?></a><br><small class="text-muted"><?= e($r['student_code']) ?></small></td><td><?= e($r['mobile']) ?></td>
      <td><?= e($r['course']) ?><br><small class="text-muted"><?= e($r['batch_code']) ?> · ends <?= e(fmt_date($r['end_date'])) ?></small></td><td><?= e($r['center']) ?></td>
      <td class="text-end"><?= money($r['payable_amount'], false) ?></td><td class="text-end"><?= money($r['paid_amount'], false) ?></td><td class="text-end text-danger fw-semibold"><?= money($r['due_amount'], false) ?></td>
      <td class="small"><?= e(fmt_date($r['last_payment'])) ?: '<span class="text-muted">never</span>' ?></td>
      <td class="table-actions"><?php if (can('fees.collect')): ?><a class="btn btn-sm btn-outline-success" href="<?= url('fees/collect', ['enrollment_id' => $r['id']]) ?>"><i class="bi bi-cash"></i> Collect</a><?php endif; ?></td></tr>
  <?php endforeach; ?></tbody></table></div>
  <?= $p['rows'] ? '' : \App\Core\View::partial('partials/empty', ['message' => 'No outstanding dues.']) ?>
  <?= \App\Core\View::partial('partials/pagination', ['p' => $p]) ?></div>
