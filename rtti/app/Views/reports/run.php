<?php
$extra = '';
if ($courses) $extra .= '<div class="col-6 col-md-2"><label class="form-label">Course</label><select name="course_id" class="form-select form-select-sm">' . options($courses, $_GET['course_id'] ?? '', 'id', 'name', 'All') . '</select></div>';
if ($batches) $extra .= '<div class="col-6 col-md-2"><label class="form-label">Batch</label><select name="batch_id" class="form-select form-select-sm s2">' . options($batches, $_GET['batch_id'] ?? '', 'id', 'name', 'All') . '</select></div>';
$hasDates = !empty($def['date']) || !empty($def['date_params']);
?>
<div class="page-head"><h1><?= e($def['title']) ?></h1><span class="text-muted small"><?= e($subtitle) ?></span><div class="actions"><?= \App\Core\View::partial('partials/export_buttons') ?></div></div>
<?= \App\Core\View::partial('partials/filters', ['f' => ['hidden' => ['type' => $type], 'q' => !empty($def['q']) ? 'Search' : '', 'center' => !empty($def['center']) || !empty($def['cwhere']), 'district' => !empty($def['center']) || !empty($def['cwhere']),
  'dates' => $hasDates, 'default_from' => $from, 'default_to' => $to, 'extra' => $extra]]) ?>
<div class="card"><div class="table-responsive"><table class="table table-sm table-hover table-striped mb-0">
  <thead><tr><?php foreach ($def['columns'] as $k => $h): ?><th class="<?= isset($totals[$k]) ? 'text-end' : '' ?>"><?= e(is_array($h) ? $h[0] : $h) ?></th><?php endforeach; ?></tr></thead>
  <tbody><?php foreach ($p['rows'] as $r): ?><tr><?php foreach ($def['columns'] as $k => $h): $v = is_array($h) ? $h[1]($r) : ($r[$k] ?? ''); ?>
    <td class="<?= isset($totals[$k]) || is_array($h) ? 'text-end' : '' ?>"><?php
      if (preg_match('/^\d{4}-\d{2}-\d{2}$/', (string)$v)) echo e(fmt_date($v));
      elseif (isset($totals[$k]) && is_numeric($v) && fmod((float)$v, 1) != 0) echo e(number_format((float)$v, 2));
      elseif (in_array($k, ['status', 'result_status', 'employment_status'], true)) echo status_badge((string)$v);
      else echo e($v); ?></td><?php endforeach; ?></tr><?php endforeach; ?></tbody>
  <?php if ($totals): ?><tfoot class="fw-bold table-light"><tr><?php $first = true; foreach ($def['columns'] as $k => $h): ?><td class="text-end"><?php if (isset($totals[$k])) echo e(number_format($totals[$k], fmod($totals[$k], 1) == 0 ? 0 : 2)); elseif (is_array($h) && isset($totals['income'], $totals['expense'])) echo e(number_format($totals['income'] - $totals['expense'], 2)); elseif (is_array($h) && isset($totals['received'], $totals['paid'])) echo e(number_format($totals['received'] - $totals['paid'], 2)); elseif ($first) echo 'Total (all pages)'; $first = false; ?></td><?php endforeach; ?></tr></tfoot><?php endif; ?>
</table></div>
<?= $p['rows'] ? '' : \App\Core\View::partial('partials/empty') ?>
<?= \App\Core\View::partial('partials/pagination', ['p' => $p]) ?></div>
