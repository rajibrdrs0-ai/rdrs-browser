<div class="page-head"><h1><?= e(t('Batches')) ?></h1>
  <div class="actions"><?= \App\Core\View::partial('partials/export_buttons', ['perm' => 'batches.export']) ?>
  <?php if (can('batches.create')): ?><a class="btn btn-primary btn-sm" href="<?= url('batches/create') ?>"><i class="bi bi-plus-lg"></i> New Batch</a><?php endif; ?></div></div>
<?= \App\Core\View::partial('partials/filters', ['f' => ['q' => 'Batch ID, name, course', 'center' => true, 'district' => true, 'dates' => true, 'date_label' => 'Active',
  'extra' => '<div class="col-6 col-md-2"><label class="form-label">Course</label><select name="course_id" class="form-select form-select-sm">' . options($courses, $_GET['course_id'] ?? '', 'id', 'name', 'All') . '</select></div>'
  . '<div class="col-6 col-md-2"><label class="form-label">Status</label><select name="status" class="form-select form-select-sm">' . options($statuses, $_GET['status'] ?? '', 'id', 'name', 'All') . '</select></div>']]) ?>
<div class="card"><div class="table-responsive"><table class="table table-sm table-hover mb-0">
  <thead><tr><th>Batch</th><th>Course</th><th>Center</th><th>Trainer</th><th>Period</th><th>Schedule</th><th class="text-center">Enrolled</th><th>Status</th><th></th></tr></thead>
  <tbody><?php foreach ($p['rows'] as $r): $pct = $r['max_students'] ? round($r['enrolled'] * 100 / $r['max_students']) : 0; ?>
    <tr><td><a class="fw-semibold" href="<?= url('batches/view', ['id' => $r['id']]) ?>"><?= e($r['batch_code']) ?></a><br><small class="text-muted"><?= e($r['name']) ?></small></td>
      <td><?= e($r['course']) ?></td><td><?= e($r['center']) ?><br><small class="text-muted"><?= e($r['district']) ?></small></td><td><?= e($r['trainer']) ?></td>
      <td class="small"><?= e(fmt_date($r['start_date'])) ?><br><?= e(fmt_date($r['end_date'])) ?></td><td class="small"><?= e($r['class_schedule']) ?></td>
      <td class="text-center" style="min-width:90px"><?= (int)$r['enrolled'] ?>/<?= (int)$r['max_students'] ?><div class="progress" style="height:4px"><div class="progress-bar <?= $pct >= 100 ? 'bg-danger' : '' ?>" style="width:<?= min(100, $pct) ?>%"></div></div></td>
      <td><span class="badge text-bg-<?= e($r['color']) ?>"><?= e($r['status']) ?></span></td>
      <td class="table-actions"><a class="btn btn-sm btn-outline-primary" href="<?= url('batches/view', ['id' => $r['id']]) ?>"><i class="bi bi-eye"></i></a></td></tr>
  <?php endforeach; ?></tbody></table></div>
  <?= $p['rows'] ? '' : \App\Core\View::partial('partials/empty') ?>
  <?= \App\Core\View::partial('partials/pagination', ['p' => $p]) ?>
</div>
