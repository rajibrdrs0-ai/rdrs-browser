<div class="page-head"><h1><?= e(t('Courses')) ?></h1>
  <div class="actions"><?= \App\Core\View::partial('partials/export_buttons', ['perm' => 'courses.export']) ?>
  <?php if (can('courses.create')): ?><a class="btn btn-primary btn-sm" href="<?= url('courses/create') ?>"><i class="bi bi-plus-lg"></i> New Course</a><?php endif; ?></div></div>
<?= \App\Core\View::partial('partials/filters', ['f' => ['q' => 'Code or course name',
  'extra' => '<div class="col-6 col-md-2"><label class="form-label">Trade</label><select name="trade_id" class="form-select form-select-sm">' . options($lists['trades'], $_GET['trade_id'] ?? '', 'id', 'name', 'All') . '</select></div>'
  . '<div class="col-6 col-md-2"><label class="form-label">Category</label><select name="academic_category_id" class="form-select form-select-sm">' . options($lists['categories'], $_GET['academic_category_id'] ?? '', 'id', 'name', 'All') . '</select></div>'
  . '<div class="col-6 col-md-2"><label class="form-label">Status</label><select name="course_status_id" class="form-select form-select-sm">' . options($lists['statuses'], $_GET['course_status_id'] ?? '', 'id', 'name', 'All') . '</select></div>']]) ?>
<div class="card"><div class="table-responsive"><table class="table table-sm table-hover mb-0">
  <thead><tr><th>Code</th><th>Course</th><th>Trade</th><th>Category</th><th>Duration</th><th class="text-end">Fee</th><th class="text-center">Batches</th><th class="text-center">Students</th><th>Status</th><th></th></tr></thead>
  <tbody><?php foreach ($rows as $r): ?>
    <tr><td class="fw-semibold"><?= e($r['code']) ?></td><td><a href="<?= url('courses/view', ['id' => $r['id']]) ?>"><?= e($r['name']) ?></a></td><td><?= e($r['trade']) ?></td><td><?= e($r['category']) ?></td>
      <td><?= (int)$r['duration'] ?> <?= e($r['duration_unit']) ?></td><td class="text-end"><?= money($r['fee'], false) ?></td>
      <td class="text-center"><?= (int)$r['batches'] ?></td><td class="text-center"><?= (int)$r['students'] ?></td>
      <td><span class="badge text-bg-<?= e($r['status_color']) ?>"><?= e($r['status_name']) ?></span></td>
      <td class="table-actions"><a class="btn btn-sm btn-outline-primary" href="<?= url('courses/view', ['id' => $r['id']]) ?>"><i class="bi bi-eye"></i></a>
        <?php if (can('courses.edit')): ?><a class="btn btn-sm btn-outline-secondary" href="<?= url('courses/edit', ['id' => $r['id']]) ?>"><i class="bi bi-pencil"></i></a><?php endif; ?></td></tr>
  <?php endforeach; ?></tbody></table></div>
  <?= $rows ? '' : \App\Core\View::partial('partials/empty') ?>
</div>
