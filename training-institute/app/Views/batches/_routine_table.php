<?php
/** @var array $slots @var bool $showBatch @var ?callable $deleteUrl */
use App\Controllers\BatchController;
$byDay = [];
foreach ($slots as $s) { $byDay[$s['weekday']][] = $s; }
?>
<?php if ($slots === []): ?>
  <p class="text-body-secondary p-3 mb-0"><?= e(__('routine.empty')) ?></p>
<?php else: ?>
<div class="table-responsive"><table class="table table-sm mb-0 align-middle">
  <thead class="table-light"><tr><th class="ps-3"><?= e(__('routine.day')) ?></th><th><?= e(__('routine.time')) ?></th>
    <?php if ($showBatch): ?><th><?= e(__('batches.code')) ?></th><?php endif; ?>
    <th><?= e(__('routine.module')) ?></th><th><?= e(__('routine.trainer')) ?></th><th><?= e(__('routine.room')) ?></th><?php if ($deleteUrl !== null): ?><th></th><?php endif; ?></tr></thead>
  <tbody>
  <?php foreach (BatchController::DAYS as $day): if (empty($byDay[$day])) { continue; } foreach ($byDay[$day] as $i => $s): ?>
    <tr>
      <?php if ($i === 0): ?><td class="ps-3 fw-semibold" rowspan="<?= count($byDay[$day]) ?>"><?= e(__('batches.days_long.' . $day)) ?></td><?php endif; ?>
      <td class="text-nowrap"><?= e(substr((string) $s['start_time'], 0, 5)) ?>–<?= e(substr((string) $s['end_time'], 0, 5)) ?>
        <div class="small text-body-secondary"><?= e(__('routine.types.' . $s['session_type'])) ?></div></td>
      <?php if ($showBatch): ?><td><a href="<?= e(url('/batches/' . $s['batch_id'])) ?>"><?= e($s['batch_code']) ?></a><div class="small text-body-secondary"><?= e(loc($s, 'course')) ?></div></td><?php endif; ?>
      <td class="small"><?= $s['module_en'] !== null ? num($s['sequence_no']) . '. ' . e(loc(['m_en' => $s['module_en'], 'm_bn' => $s['module_bn']], 'm')) : '—' ?></td>
      <td><?= e($s['staff_name'] !== null ? loc(['n_en' => $s['staff_name'], 'n_bn' => $s['staff_name_bn']], 'n') : '—') ?></td>
      <td><?= e($s['room_name'] ?? '—') ?></td>
      <?php if ($deleteUrl !== null): ?><td class="text-end pe-3">
        <form method="post" action="<?= e($deleteUrl($s)) ?>" class="d-inline" data-confirm="<?= e(__('common.confirm')) ?>"><?= csrf_field() ?><?= method_field('DELETE') ?>
          <button class="btn btn-sm btn-link text-danger p-0" title="<?= e(__('routine.deleted')) ?>"><i class="bi bi-trash"></i></button></form></td><?php endif; ?>
    </tr>
  <?php endforeach; endforeach; ?>
  </tbody>
</table></div>
<?php endif; ?>
