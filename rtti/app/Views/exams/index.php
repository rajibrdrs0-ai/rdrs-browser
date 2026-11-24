<div class="page-head"><h1><?= e(t('Examinations')) ?></h1>
  <div class="actions"><?php if (can('exams.create')): ?><button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#xModal"><i class="bi bi-plus-lg"></i> New examination</button><?php endif; ?></div></div>
<?= \App\Core\View::partial('partials/filters', ['f' => ['center' => true, 'district' => true,
  'extra' => '<div class="col-md-4"><label class="form-label">Batch</label><select name="batch_id" class="form-select form-select-sm s2">' . options($batches, $_GET['batch_id'] ?? '', 'id', 'name', 'All') . '</select></div>']]) ?>
<div class="card"><div class="table-responsive"><table class="table table-sm table-hover mb-0">
  <thead><tr><th>Date</th><th>Examination</th><th>Batch</th><th>Center</th><th class="text-center">Marked</th><th>Status</th><th></th></tr></thead>
  <tbody><?php foreach ($p['rows'] as $r): ?>
    <tr><td><?= e(fmt_date($r['exam_date'])) ?></td><td><a href="<?= url('exams/marks', ['id' => $r['id']]) ?>"><?= e($r['name']) ?></a> <small class="text-muted"><?= e($types[$r['exam_type']]) ?></small><?= $r['is_final'] ? ' <span class="badge text-bg-dark">Final</span>' : '' ?></td>
      <td><?= e($r['batch_code']) ?><br><small class="text-muted"><?= e($r['course']) ?></small></td><td><?= e($r['center']) ?></td><td class="text-center"><?= (int)$r['marked'] ?></td><td><?= status_badge($r['status']) ?></td>
      <td class="table-actions"><a class="btn btn-sm btn-outline-primary" href="<?= url('exams/marks', ['id' => $r['id']]) ?>" title="Marks"><i class="bi bi-pencil-square"></i></a>
        <a class="btn btn-sm btn-outline-secondary" href="<?= url('exams/result', ['id' => $r['id']]) ?>" title="Result sheet"><i class="bi bi-list-ol"></i></a></td></tr>
  <?php endforeach; ?></tbody></table></div>
  <?= $p['rows'] ? '' : \App\Core\View::partial('partials/empty') ?>
  <?= \App\Core\View::partial('partials/pagination', ['p' => $p]) ?></div>
<?php if (can('exams.create')): ?>
<div class="modal fade" id="xModal" tabindex="-1"><div class="modal-dialog"><form class="modal-content" method="post" action="<?= url('exams/save') ?>"><?= csrf_field() ?>
  <div class="modal-header"><h5 class="modal-title">New examination</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
  <div class="modal-body row g-2">
    <div class="col-12"><label class="form-label req">Batch</label><select name="batch_id" class="form-select s2" required><?= options($batches, $_GET['batch_id'] ?? '') ?></select></div>
    <div class="col-md-8"><label class="form-label req">Exam name</label><input name="name" class="form-control" required value="Final Assessment"></div>
    <div class="col-md-4"><label class="form-label req">Type</label><select name="exam_type" class="form-select"><?= kv_options($types, 'final', '') ?></select></div>
    <div class="col-md-4"><label class="form-label req">Date</label><input type="date" name="exam_date" class="form-control" required value="<?= date('Y-m-d') ?>"></div>
    <div class="col-md-4"><label class="form-label">Theory full marks</label><input type="number" step="0.01" name="theory_full_marks" class="form-control" value="60"></div>
    <div class="col-md-4"><label class="form-label">Practical full marks</label><input type="number" step="0.01" name="practical_full_marks" class="form-control" value="40"></div>
    <div class="col-12 small text-muted">Full marks above are used only when the course has no subjects. Otherwise each subject's marks distribution applies.</div>
    <div class="col-12"><div class="form-check"><input class="form-check-input" type="checkbox" name="is_final" value="1" id="isFinal" checked><label class="form-check-label" for="isFinal">Final examination (result decides pass/fail & certificate)</label></div></div>
  </div>
  <div class="modal-footer"><button class="btn btn-primary">Create</button></div></form></div></div>
<?php endif; ?>
