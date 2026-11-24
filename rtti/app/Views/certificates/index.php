<div class="page-head"><h1><?= e(t('Certificates')) ?></h1>
  <div class="actions"><?= \App\Core\View::partial('partials/export_buttons') ?>
  <?php if (can('certificates.create')): ?>
    <form class="d-flex gap-1" method="get"><input type="hidden" name="r" value="certificates/eligible">
      <select name="batch_id" class="form-select form-select-sm s2" style="min-width:260px" required data-placeholder="Select batch to issue certificates"><?= options($batches, '', 'id', 'name', '') ?></select>
      <button class="btn btn-primary btn-sm text-nowrap"><i class="bi bi-award"></i> Issue</button></form>
  <?php endif; ?></div></div>
<?= \App\Core\View::partial('partials/filters', ['f' => ['q' => 'Certificate no, student ID, name', 'center' => true, 'district' => true, 'dates' => true, 'date_label' => 'Issued',
  'extra' => '<div class="col-6 col-md-2"><label class="form-label">Course</label><select name="course_id" class="form-select form-select-sm">' . options($courses, $_GET['course_id'] ?? '', 'id', 'name', 'All') . '</select></div>'
  . '<div class="col-6 col-md-1"><label class="form-label">Status</label><select name="status" class="form-select form-select-sm">' . kv_options(['issued' => 'Issued', 'cancelled' => 'Cancelled'], $_GET['status'] ?? '', 'All') . '</select></div>']]) ?>
<div class="card"><div class="table-responsive"><table class="table table-sm table-hover mb-0">
  <thead><tr><th>Certificate no.</th><th>Issued</th><th>Student</th><th>Course / batch</th><th>Center</th><th>Grade</th><th class="text-center">Prints</th><th>Status</th><th></th></tr></thead>
  <tbody><?php foreach ($p['rows'] as $r): ?>
    <tr><td class="fw-semibold"><?= e($r['certificate_no']) ?></td><td><?= e(fmt_date($r['issue_date'])) ?></td>
      <td><a href="<?= url('students/view', ['id' => $r['student_id']]) ?>"><?= e($r['student']) ?></a><br><small class="text-muted"><?= e($r['student_code']) ?></small></td>
      <td><?= e($r['course']) ?><br><small class="text-muted"><?= e($r['batch_code']) ?></small></td><td><?= e($r['center']) ?></td><td><?= e($r['grade']) ?></td>
      <td class="text-center"><?= (int)$r['print_count'] ?></td><td><?= status_badge($r['status']) ?></td>
      <td class="table-actions"><a class="btn btn-sm btn-outline-primary" target="_blank" href="<?= url('certificates/view', ['id' => $r['id']]) ?>"><i class="bi bi-printer"></i></a>
        <?php if ($r['status'] === 'issued' && can('certificates.cancel')): ?><form method="post" action="<?= url('certificates/cancel') ?>" class="d-inline" data-confirm="Cancel certificate <?= e($r['certificate_no']) ?>?" data-reason><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int)$r['id'] ?>"><button class="btn btn-sm btn-outline-danger"><i class="bi bi-x-circle"></i></button></form><?php endif; ?></td></tr>
  <?php endforeach; ?></tbody></table></div>
  <?= $p['rows'] ? '' : \App\Core\View::partial('partials/empty') ?>
  <?= \App\Core\View::partial('partials/pagination', ['p' => $p]) ?>
</div>
