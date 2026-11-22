<?php
/** @var array $batch @var array $trainers @var array $fees @var array $routine @var array $modules @var array $centerTrainers
 *  @var array $rooms @var int $enrolledCount @var array $next @var bool $canManage @var bool $canRoutine */
use App\Controllers\BatchController;
use App\Services\FeeStructure;
$this->layout('layouts/app', ['title' => $batch['batch_code']]);
$classDays = array_filter(explode(',', (string) $batch['class_days']));
$pct = (int) $batch['capacity'] > 0 ? min(100, (int) round(100 * $enrolledCount / (int) $batch['capacity'])) : 0;
?>
<div class="page-head">
  <div>
    <h1><?= e($batch['batch_code']) ?> <?= $this->insert('batches/_status', ['status' => $batch['status']]) ?></h1>
    <div class="small text-body-secondary"><?= e($batch['name']) ?> · <?= e(loc($batch, 'course')) ?> · <a href="<?= e(url('/centers/' . $batch['center_id'])) ?>"><?= e(loc($batch, 'center')) ?></a></div>
  </div>
  <div class="d-flex flex-wrap gap-2">
    <?php if ($canManage): foreach ($next as $to): ?>
      <form method="post" action="<?= e(url('/batches/' . $batch['id'] . '/status')) ?>" data-confirm="<?= e(__('common.confirm')) ?>"><?= csrf_field() ?>
        <input type="hidden" name="status" value="<?= e($to) ?>">
        <button class="btn btn-sm <?= $to === 'cancelled' ? 'btn-outline-danger' : 'btn-outline-primary' ?>"><?= e(__('batches.actions.' . $to)) ?></button></form>
    <?php endforeach; endif; ?>
    <?php if ($canManage && !in_array($batch['status'], ['completed', 'cancelled'], true)): ?>
      <a class="btn btn-sm btn-outline-secondary" href="<?= e(url('/batches/' . $batch['id'] . '/edit')) ?>"><i class="bi bi-pencil me-1"></i><?= e(__('common.edit')) ?></a>
    <?php endif; ?>
  </div>
</div>

<div class="row g-3">
  <div class="col-lg-4">
    <div class="card mb-3"><div class="card-body">
      <div class="d-flex justify-content-between small mb-1"><span><?= e(__('batches.enrolled')) ?></span><span><?= e(__('batches.seats', ['enrolled' => num($enrolledCount), 'capacity' => num($batch['capacity'])])) ?></span></div>
      <div class="progress mb-3" role="progressbar" aria-valuenow="<?= $pct ?>" aria-valuemin="0" aria-valuemax="100"><div class="progress-bar bg-success" style="width: <?= $pct ?>%"></div></div>
      <dl class="row small mb-0">
        <dt class="col-5"><?= e(__('batches.start_date')) ?></dt><dd class="col-7"><?= e(fmt_date($batch['start_date'])) ?></dd>
        <dt class="col-5"><?= e(__('batches.end_date')) ?></dt><dd class="col-7"><?= e(fmt_date($batch['end_date'])) ?></dd>
        <dt class="col-5"><?= e(__('batches.shift')) ?></dt><dd class="col-7"><?= e(__('batches.shifts.' . $batch['shift'])) ?></dd>
        <dt class="col-5"><?= e(__('batches.class_time')) ?></dt><dd class="col-7"><?= $batch['class_start_time'] !== null ? e(substr((string) $batch['class_start_time'], 0, 5) . '–' . substr((string) $batch['class_end_time'], 0, 5)) : '—' ?></dd>
        <dt class="col-5"><?= e(__('batches.class_days')) ?></dt><dd class="col-7"><?= e(implode(', ', array_map(static fn($d) => __('batches.days.' . $d), $classDays))) ?></dd>
        <dt class="col-5"><?= e(__('batches.room')) ?></dt><dd class="col-7"><?= e($batch['room_name'] ?? '—') ?></dd>
        <dt class="col-5"><?= e(__('batches.funding')) ?></dt><dd class="col-7"><?= e($batch['funding_source'] ?? '—') ?></dd>
      </dl>
    </div></div>
    <div class="card mb-3"><div class="card-header bg-body fw-semibold"><i class="bi bi-people me-1"></i><?= e(__('batches.trainers')) ?></div>
      <ul class="list-group list-group-flush">
        <?php foreach ($trainers as $t): ?><li class="list-group-item d-flex justify-content-between"><span><?= e(loc($t, 'name')) ?><?php if ($t['role'] === 'lead'): ?> <span class="badge text-bg-success"><?= e(__('batches.lead_trainer')) ?></span><?php endif; ?></span><span class="small text-body-secondary"><?= e($t['mobile']) ?></span></li><?php endforeach; ?>
        <?php if ($trainers === []): ?><li class="list-group-item text-body-secondary"><?= e(__('batches.no_trainers')) ?></li><?php endif; ?>
      </ul></div>
    <div class="card"><div class="card-header bg-body fw-semibold"><i class="bi bi-cash-stack me-1"></i><?= e(__('batches.fees')) ?></div>
      <table class="table table-sm mb-0">
        <?php foreach ($fees as $f): ?><tr><td class="ps-3"><?= e(loc($f, 'name')) ?><?= $f['is_optional'] ? ' <span class="badge text-bg-light border">' . e(__('courses.optional')) . '</span>' : '' ?></td><td class="text-end pe-3"><?= money($f['amount']) ?></td></tr><?php endforeach; ?>
        <?php if ($fees === []): ?><tr><td class="ps-3 text-body-secondary"><?= e(__('courses.no_fees')) ?></td></tr>
        <?php else: ?><tr class="fw-semibold"><td class="ps-3"><?= e(__('courses.mandatory_total')) ?></td><td class="text-end pe-3"><?= money(FeeStructure::total($fees)) ?></td></tr><?php endif; ?>
      </table></div>
  </div>

  <div class="col-lg-8" id="routine">
    <div class="card"><div class="card-header bg-body fw-semibold"><i class="bi bi-calendar3-week me-1"></i><?= e(__('routine.weekly')) ?></div>
      <?= $this->insert('batches/_routine_table', ['slots' => $routine, 'showBatch' => false,
          'deleteUrl' => $canRoutine ? static fn(array $s): string => url('/batches/' . $batch['id'] . '/routine/' . $s['id']) : null]) ?>
      <?php if ($canRoutine): ?>
      <form method="post" action="<?= e(url('/batches/' . $batch['id'] . '/routine')) ?>" class="card-body border-top" novalidate><?= csrf_field() ?>
        <h2 class="h6"><?= e(__('routine.add')) ?></h2>
        <?= field_error('start_time') ?>
        <div class="row g-2 align-items-end">
          <div class="col-6 col-md-2"><?= form_select('weekday', __('routine.day'), array_combine($classDays, array_map(static fn($d) => __('batches.days.' . $d), $classDays)), '', ['class' => 'form-select form-select-sm', 'required' => true]) ?></div>
          <div class="col-3 col-md-2"><?= form_input('start_time', __('routine.start'), substr((string) ($batch['class_start_time'] ?? ''), 0, 5), ['type' => 'time', 'class' => 'form-control form-control-sm', 'required' => true]) ?></div>
          <div class="col-3 col-md-2"><?= form_input('end_time', __('routine.end'), substr((string) ($batch['class_end_time'] ?? ''), 0, 5), ['type' => 'time', 'class' => 'form-control form-control-sm', 'required' => true]) ?></div>
          <div class="col-6 col-md-3"><?= form_select('staff_id', __('routine.trainer'), options($centerTrainers), $batch['lead_trainer_id'] ?? '', ['class' => 'form-select form-select-sm', 'required' => true]) ?></div>
          <div class="col-6 col-md-3"><?= form_select('room_id', __('routine.room'), array_column($rooms, 'name', 'id'), $batch['room_id'] ?? '', ['class' => 'form-select form-select-sm']) ?></div>
          <div class="col-md-6"><?php $mods = []; foreach ($modules as $m) { $mods[$m['id']] = $m['sequence_no'] . '. ' . loc(['t_en' => $m['title_en'], 't_bn' => $m['title_bn']], 't'); } ?>
            <?= form_select('module_id', __('routine.module'), $mods, '', ['class' => 'form-select form-select-sm']) ?></div>
          <div class="col-6 col-md-3"><?= form_select('session_type', __('routine.type'), ['both' => __('routine.types.both'), 'theory' => __('routine.types.theory'), 'practical' => __('routine.types.practical')], 'both', ['class' => 'form-select form-select-sm', 'placeholder' => false]) ?></div>
          <div class="col-6 col-md-3 d-grid"><button class="btn btn-sm btn-primary"><i class="bi bi-plus-lg me-1"></i><?= e(__('routine.add')) ?></button></div>
        </div>
      </form>
      <?php endif; ?>
    </div>
  </div>
</div>
