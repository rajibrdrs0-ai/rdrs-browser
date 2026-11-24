<div class="page-head"><h1>Attendance register</h1><?php if ($b): ?><div class="actions"><?= \App\Core\View::partial('partials/export_buttons') ?></div><?php endif; ?></div>
<form class="filters row g-2 align-items-end" method="get"><input type="hidden" name="r" value="attendance/report">
  <div class="col-md-6"><label class="form-label">Batch</label><select name="batch_id" class="form-select s2" required><?= options($batches, $b['id'] ?? '') ?></select></div>
  <div class="col-md-3"><label class="form-label">Month</label><input type="month" name="month" class="form-control" value="<?= e($month) ?>"></div>
  <div class="col-md-3"><button class="btn btn-primary w-100">Show</button></div></form>
<?php if ($b): $abbr = ['present' => ['P', 'success'], 'absent' => ['A', 'danger'], 'late' => ['L', 'warning'], 'leave' => ['LV', 'info']]; ?>
<div class="card"><div class="card-header"><?= e($b['batch_code']) ?> · <?= e($b['course']) ?> · <?= e(date('F Y', strtotime($month . '-01'))) ?> · <?= count($dates) ?> classes</div>
<div class="table-responsive"><table class="table table-sm table-bordered mb-0 small text-center">
  <thead><tr><th class="text-start">Student</th><?php foreach ($dates as $d): ?><th><?= date('d', strtotime($d)) ?></th><?php endforeach; ?><th>P</th><th>A</th><th>%</th></tr></thead>
  <tbody><?php foreach ($students as $s): $p = $a = 0; ?><tr><td class="text-start text-nowrap"><?= e($s['name']) ?></td>
    <?php foreach ($dates as $d): $st = $matrix[$s['id']][$d] ?? null; if (in_array($st, ['present', 'late'])) $p++; if ($st === 'absent') $a++; ?><td class="text-<?= $abbr[$st][1] ?? 'muted' ?>"><?= $abbr[$st][0] ?? '·' ?></td><?php endforeach; ?>
    <td><?= $p ?></td><td><?= $a ?></td><td class="fw-semibold <?= $dates && $p * 100 / count($dates) < 75 ? 'text-danger' : '' ?>"><?= $dates ? round($p * 100 / count($dates)) : '—' ?></td></tr><?php endforeach; ?></tbody>
</table></div></div>
<?php endif; ?>
