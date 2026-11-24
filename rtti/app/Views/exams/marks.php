<?php $locked = $x['status'] === 'published' && !can('exams.publish'); $editable = can('exams.marks') && !$locked; ?>
<div class="page-head"><h1><?= e($x['name']) ?></h1><?= status_badge($x['status']) ?><?= $x['is_final'] ? ' <span class="badge text-bg-dark">Final</span>' : '' ?>
  <div class="actions"><a class="btn btn-outline-secondary btn-sm" href="<?= url('exams/result', ['id' => $x['id']]) ?>"><i class="bi bi-list-ol"></i> Result sheet</a>
  <a class="btn btn-outline-secondary btn-sm" href="<?= url('exams/result', ['id' => $x['id'], 'merit' => 1]) ?>"><i class="bi bi-trophy"></i> Merit list</a>
  <?php if (can('exams.publish')): ?><form method="post" action="<?= url('exams/publish', ['id' => $x['id']]) ?>" class="d-inline" data-confirm="Publish results<?= $x['is_final'] ? ' and update final course results' : '' ?>?"><?= csrf_field() ?><button class="btn btn-success btn-sm"><i class="bi bi-megaphone"></i> <?= $x['status'] === 'published' ? 'Re-publish' : 'Publish results' ?></button></form><?php endif; ?></div></div>
<p class="text-muted small"><?= e($x['course']) ?> · <?= e($x['batch_code']) ?> · <?= e($x['center']) ?> · <?= e(fmt_date($x['exam_date'])) ?> · pass mark <?= e($x['pass_mark_percent']) ?>%</p>
<?php if ($locked): ?><div class="alert alert-info py-2 small">Results are published. Marks are read-only.</div><?php endif; ?>
<form method="post" action="<?= url('exams/savemarks', ['id' => $x['id']]) ?>"><?= csrf_field() ?>
<div class="card"><div class="table-responsive"><table class="table table-sm table-bordered mb-0 align-middle">
  <thead><tr><th rowspan="2">Student</th><?php foreach ($subjects as $s): ?><th colspan="3" class="text-center"><?= e($s['name']) ?></th><?php endforeach; ?></tr>
    <tr><?php foreach ($subjects as $s): ?><th class="small">Theory /<?= e((float)$s['theory_full_marks']) ?></th><th class="small">Practical /<?= e((float)$s['practical_full_marks']) ?></th><th class="small">Abs</th><?php endforeach; ?></tr></thead>
  <tbody><?php foreach ($students as $st): ?>
    <tr><td class="text-nowrap"><?= e($st['name']) ?><br><small class="text-muted"><?= e($st['student_code']) ?></small></td>
    <?php foreach ($subjects as $s): $m = $marks[$st['id']][$s['id']] ?? []; $n = 'm[' . (int)$st['id'] . '][' . (int)$s['id'] . ']'; ?>
      <td style="width:90px"><input name="<?= $n ?>[t]" type="number" step="0.5" min="0" max="<?= e($s['theory_full_marks']) ?>" class="form-control form-control-sm" value="<?= e($m['theory_marks'] ?? '') ?>"<?= (float)$s['theory_full_marks'] == 0 || !$editable ? ' disabled' : '' ?>></td>
      <td style="width:90px"><input name="<?= $n ?>[p]" type="number" step="0.5" min="0" max="<?= e($s['practical_full_marks']) ?>" class="form-control form-control-sm" value="<?= e($m['practical_marks'] ?? '') ?>"<?= (float)$s['practical_full_marks'] == 0 || !$editable ? ' disabled' : '' ?>></td>
      <td class="text-center"><input type="checkbox" class="form-check-input" name="<?= $n ?>[a]" value="1"<?= !empty($m['is_absent']) ? ' checked' : '' ?><?= $editable ? '' : ' disabled' ?>></td>
    <?php endforeach; ?></tr>
  <?php endforeach; ?></tbody></table></div>
  <?= $students ? '' : \App\Core\View::partial('partials/empty', ['message' => 'No active students in this batch.']) ?>
  <?php if ($editable && $students): ?><div class="card-footer bg-white text-end"><button class="btn btn-primary"><i class="bi bi-save"></i> Save marks</button></div><?php endif; ?>
</div></form>
