<?php $merit = ($_GET['merit'] ?? '') === '1'; ?>
<div class="page-head"><h1><?= $merit ? 'Merit list' : 'Result sheet' ?> · <?= e($x['name']) ?></h1>
  <div class="actions"><?= \App\Core\View::partial('partials/export_buttons') ?><a class="btn btn-outline-secondary btn-sm" href="<?= url('exams/result', ['id' => $x['id'], 'merit' => $merit ? 0 : 1]) ?>"><?= $merit ? 'Alphabetical' : 'Merit order' ?></a></div></div>
<p class="text-muted small"><?= e($x['course']) ?> · <?= e($x['batch_code']) ?> · <?= e($x['center']) ?> · <?= e(fmt_date($x['exam_date'])) ?> · <?= status_badge($x['status']) ?></p>
<?php $pass = count(array_filter($rows, fn($r) => $r['result'] === 'pass')); ?>
<div class="row g-3 mb-3"><div class="col-4"><div class="card p-2 text-center"><div class="small text-muted">Examinees</div><b><?= count($rows) ?></b></div></div>
<div class="col-4"><div class="card p-2 text-center"><div class="small text-muted">Passed</div><b class="text-success"><?= $pass ?></b></div></div>
<div class="col-4"><div class="card p-2 text-center"><div class="small text-muted">Pass rate</div><b><?= $rows ? round($pass * 100 / count($rows)) : 0 ?>%</b></div></div></div>
<div class="card"><div class="table-responsive"><table class="table table-sm table-hover mb-0">
  <thead><tr><th>Pos.</th><th>Student</th><th class="text-end">Theory</th><th class="text-end">Practical</th><th class="text-end">Total</th><th class="text-end">%</th><th>Grade</th><th>Result</th></tr></thead>
  <tbody><?php foreach ($rows as $r): ?><tr><td><?= (int)$r['position'] ?></td><td><?= e($r['name']) ?> <small class="text-muted"><?= e($r['student_code']) ?></small></td>
    <td class="text-end"><?= e($r['theory']) ?></td><td class="text-end"><?= e($r['practical']) ?></td><td class="text-end fw-semibold"><?= e($r['total']) ?> / <?= e($r['full']) ?></td><td class="text-end"><?= e($r['percent']) ?></td><td><?= e($r['grade']) ?></td><td><?= status_badge($r['result']) ?></td></tr><?php endforeach; ?></tbody>
</table></div></div>
