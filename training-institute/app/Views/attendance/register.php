<?php
/** @var array $batch @var array $sessions @var array $enrollments @var array $marks @var string $month @var array $months @var array $absentees @var int $streakDays */
$this->layout('layouts/app', ['title' => __('attendance.register') . ' · ' . $batch['batch_code']]);
$cls = ['present' => 'text-success', 'absent' => 'text-danger fw-bold', 'late' => 'text-warning', 'excused' => 'text-info'];
$min = (float) $batch['min_attendance_percent'];
?>
<div class="page-head">
  <div><h1><?= e(__('attendance.register')) ?></h1><div class="small text-body-secondary"><a href="<?= e(url('/batches/' . $batch['id'])) ?>"><?= e($batch['batch_code']) ?></a> · <?= e(loc($batch, 'course')) ?></div></div>
  <div class="d-flex gap-2 align-items-center">
    <form method="get" class="d-flex gap-2 align-items-center"><label class="small" for="month"><?= e(__('attendance.month')) ?></label>
      <select class="form-select form-select-sm w-auto" id="month" name="month" data-autosubmit><?php foreach ($months as $m): ?><option value="<?= e($m) ?>" <?= $m === $month ? 'selected' : '' ?>><?= e(current_locale() === 'bn' ? bn_digits($m) : $m) ?></option><?php endforeach; ?></select></form>
    <?php if (can('attendance.take') && $batch['status'] === 'running'): ?><a class="btn btn-sm btn-primary" href="<?= e(url('/batches/' . $batch['id'] . '/attendance/take')) ?>"><i class="bi bi-check2-square me-1"></i><?= e(__('attendance.take')) ?></a><?php endif; ?>
  </div>
</div>
<?php if ($absentees !== []): ?>
<div class="alert alert-warning"><div class="fw-semibold"><i class="bi bi-exclamation-triangle me-1"></i><?= e(__('attendance.absent_streak', ['n' => num($streakDays)])) ?></div>
  <ul class="mb-1 ps-3 small"><?php foreach ($absentees as $a): ?><li><?= e($a['roll_no']) ?>. <a href="<?= e(url('/students/' . $a['student_id'])) ?>"><?= e(loc($a, 'name')) ?></a> — <?= e($a['mobile']) ?><?= ($a['guardian_mobile'] ?? $a['father_mobile']) ? ' / ' . e($a['guardian_mobile'] ?? $a['father_mobile']) : '' ?></li><?php endforeach; ?></ul>
  <div class="small"><?= e(__('attendance.absent_streak_help')) ?></div></div>
<?php endif; ?>
<div class="card">
  <?php if ($enrollments === []): ?><div class="card-body text-body-secondary"><?= e(__('attendance.no_students')) ?></div><?php else: ?>
  <div class="table-responsive"><table class="table table-sm table-bordered mb-0 align-middle small">
    <thead class="table-light"><tr>
      <th class="text-nowrap"><?= e(__('admission.roll')) ?></th><th class="text-nowrap"><?= e(__('students.name_en')) ?></th>
      <?php foreach ($sessions as $s): ?>
        <th class="text-center px-1" title="<?= e(($s['topic'] ?? '') . ' ' . ($s['staff_name'] ?? '')) ?>">
          <?= e(current_locale() === 'bn' ? bn_digits(substr((string) $s['session_date'], 8, 2)) : substr((string) $s['session_date'], 8, 2)) ?>
          <?php if ((int) $s['is_locked'] === 1): ?><i class="bi bi-lock-fill text-body-secondary d-block"></i>
          <?php elseif ($s['status'] === 'held' && can('attendance.lock')): ?>
            <form method="post" action="<?= e(url('/class-sessions/' . $s['id'] . '/lock')) ?>" data-confirm="<?= e(__('common.confirm')) ?>"><?= csrf_field() ?><button class="btn btn-link btn-sm p-0" title="<?= e(__('attendance.lock')) ?>"><i class="bi bi-unlock"></i></button></form>
          <?php endif; ?>
          <?php if ($s['status'] === 'held' && (int) $s['is_locked'] === 0 && can('attendance.take')): ?><a href="<?= e(url('/batches/' . $batch['id'] . '/attendance/take', ['date' => $s['session_date'], 'start' => substr((string) $s['start_time'], 0, 5)])) ?>" title="<?= e(__('common.edit')) ?>"><i class="bi bi-pencil"></i></a><?php endif; ?>
        </th>
      <?php endforeach; ?>
      <th class="text-center"><?= e(__('attendance.percent')) ?></th>
    </tr></thead>
    <tbody>
    <?php foreach ($enrollments as $en): $pct = $en['attendance_percent']; ?>
      <tr class="<?= in_array($en['status'], ['dropped_out', 'transferred'], true) ? 'text-body-secondary' : '' ?>">
        <td><?= e($en['roll_no']) ?></td>
        <td class="text-nowrap"><?= e(loc($en, 'name')) ?><?php if (!in_array($en['status'], ['admitted', 'running'], true)): ?> <span class="badge text-bg-secondary"><?= e(__('admission.status.' . $en['status'])) ?></span><?php endif; ?></td>
        <?php foreach ($sessions as $s): $m = $marks[(int) $en['id']][(int) $s['id']] ?? null; ?>
          <td class="text-center px-1 <?= $m !== null ? $cls[$m] : '' ?>"><?= $s['status'] !== 'held' ? '<span class="text-body-secondary">–</span>' : ($m !== null ? e(__('attendance.marks.' . $m)) : '') ?></td>
        <?php endforeach; ?>
        <td class="text-center <?= $pct !== null && (float) $pct < $min ? 'text-danger fw-semibold' : '' ?>"><?= $pct !== null ? num($pct, 1) . '%' : '—' ?></td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table></div>
  <div class="card-footer bg-body small text-body-secondary"><?= e(__('attendance.legend')) ?> · <?= e(__('admission.min_attendance', ['pct' => num($min, 0)])) ?><?php if ($sessions === []): ?> · <?= e(__('attendance.no_sessions')) ?><?php endif; ?></div>
  <?php endif; ?>
</div>
