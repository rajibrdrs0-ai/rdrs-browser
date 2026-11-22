<?php
/** @var ?array $batch @var array $centers @var ?int $centerId @var array $courses @var array $trainers @var array $rooms @var array $assistants */
use App\Controllers\BatchController;
$isEdit = $batch !== null;
$b = $batch ?? ['shift' => 'morning', 'class_days' => 'sat,sun,mon,tue,wed', 'capacity' => 25, 'start_date' => '', 'end_date' => '', 'status' => 'planned'];
$this->layout('layouts/app', ['title' => __($isEdit ? 'batches.edit' : 'batches.add')]);
$days = (array) old('class_days', explode(',', (string) $b['class_days']));
$oldAssistants = array_map('intval', (array) old('assistant_ids', $assistants));
$courseOptions = '';
foreach ($courses as $c) {
    $sel = (string) old('course_id', $b['course_id'] ?? '') === (string) $c['id'] ? ' selected' : '';
    $courseOptions .= '<option value="' . (int) $c['id'] . '" data-duration="' . e($c['duration_months']) . '"' . $sel . '>' . e($c['code'] . ' — ' . loc($c, 'name')) . '</option>';
}
?>
<div class="page-head">
  <h1><?= e(__($isEdit ? 'batches.edit' : 'batches.add')) ?><?php if ($isEdit): ?> <small class="text-body-secondary fs-6"><?= e($b['batch_code']) ?></small><?php endif; ?></h1>
  <a class="btn btn-sm btn-outline-secondary" href="<?= e(url($isEdit ? '/batches/' . $b['id'] : '/batches')) ?>"><i class="bi bi-arrow-left me-1"></i><?= e(__('common.back')) ?></a>
</div>
<form method="post" action="<?= e(url($isEdit ? '/batches/' . $b['id'] : '/batches')) ?>" class="card card-body" novalidate>
  <?= csrf_field() ?><?= $isEdit ? method_field('PUT') : '' ?>
  <div class="row g-3">
    <?php if (!$isEdit): ?>
      <div class="col-md-6"><?= form_select('center_id', __('batches.center'), options($centers, 'name', 'id', 'code'), $centerId ?? '', ['required' => true, 'data-reload-param' => 'center_id']) ?></div>
      <div class="col-md-6">
        <?= form_label('course_id', __('batches.course'), true) ?>
        <select class="form-select<?= errors('course_id') ? ' is-invalid' : '' ?>" id="course_id" name="course_id" required><option value=""><?= e(__('common.select')) ?></option><?= $courseOptions ?></select>
        <?= field_error('course_id') ?>
      </div>
    <?php else: ?>
      <div class="col-md-6"><label class="form-label"><?= e(__('batches.center')) ?></label><input class="form-control" value="<?= e($b['center_code'] . ' — ' . loc($b, 'center')) ?>" disabled></div>
      <div class="col-md-6"><label class="form-label"><?= e(__('batches.course')) ?></label><input class="form-control" value="<?= e($b['course_code'] . ' — ' . loc($b, 'course')) ?>" disabled></div>
    <?php endif; ?>
    <?php if ($centerId === null): ?>
      <div class="col-12"><div class="alert alert-info mb-0"><i class="bi bi-info-circle me-1"></i><?= e(__('batches.pick_center_first')) ?></div></div>
    <?php else: ?>
      <div class="col-md-6"><?= form_input('name', __('batches.name'), $b['name'] ?? '', ['required' => true, 'maxlength' => 100]) ?></div>
      <div class="col-md-3"><?= form_select('shift', __('batches.shift'), array_combine(BatchController::SHIFTS, array_map(static fn($s) => __('batches.shifts.' . $s), BatchController::SHIFTS)), $b['shift'], ['placeholder' => false]) ?></div>
      <div class="col-md-3"><?= form_input('capacity', __('batches.capacity'), $b['capacity'], ['type' => 'number', 'min' => 1, 'max' => 500, 'required' => true]) ?></div>
      <div class="col-6 col-md-3"><?= form_input('start_date', __('batches.start_date'), $b['start_date'], ['type' => 'date', 'required' => true]) ?></div>
      <div class="col-6 col-md-3"><?= form_input('end_date', __('batches.end_date'), $b['end_date'], ['type' => 'date', 'required' => true, 'help' => $isEdit ? null : __('batches.end_date_help')]) ?></div>
      <div class="col-6 col-md-3"><?= form_input('class_start_time', __('batches.class_start'), substr((string) ($b['class_start_time'] ?? ''), 0, 5), ['type' => 'time']) ?></div>
      <div class="col-6 col-md-3"><?= form_input('class_end_time', __('batches.class_end'), substr((string) ($b['class_end_time'] ?? ''), 0, 5), ['type' => 'time']) ?></div>
      <div class="col-12">
        <label class="form-label d-block"><?= e(__('batches.class_days')) ?> <span class="text-danger">*</span></label>
        <?php foreach (BatchController::DAYS as $d): ?>
          <div class="form-check form-check-inline"><input class="form-check-input" type="checkbox" name="class_days[]" value="<?= $d ?>" id="day-<?= $d ?>" <?= in_array($d, $days, true) ? 'checked' : '' ?>><label class="form-check-label" for="day-<?= $d ?>"><?= e(__('batches.days.' . $d)) ?></label></div>
        <?php endforeach; ?>
        <?= field_error('class_days') ?>
      </div>
      <?php if ($trainers === []): ?><div class="col-12"><div class="alert alert-warning mb-0"><?= e(__('batches.no_trainers_center')) ?></div></div><?php endif; ?>
      <div class="col-md-4"><?= form_select('lead_trainer_id', __('batches.lead_trainer'), options($trainers), $b['lead_trainer_id'] ?? '') ?></div>
      <div class="col-md-4">
        <label class="form-label" for="assistant_ids"><?= e(__('batches.assistants')) ?></label>
        <select class="form-select" id="assistant_ids" name="assistant_ids[]" multiple size="3">
          <?php foreach ($trainers as $t): ?><option value="<?= (int) $t['id'] ?>" <?= in_array((int) $t['id'], $oldAssistants, true) ? 'selected' : '' ?>><?= e(loc($t, 'name')) ?></option><?php endforeach; ?>
        </select>
      </div>
      <div class="col-md-4"><?= form_select('room_id', __('batches.room'), array_column($rooms, 'name', 'id'), $b['room_id'] ?? '') ?></div>
      <div class="col-md-8"><?= form_input('funding_source', __('batches.funding'), $b['funding_source'] ?? '', ['maxlength' => 120, 'help' => __('batches.funding_help')]) ?></div>
      <?php if (!$isEdit): ?>
      <div class="col-md-4"><?= form_select('status', __('batches.initial_status'), ['planned' => __('batches.status.planned'), 'admission_open' => __('batches.status.admission_open')], 'planned', ['placeholder' => false]) ?></div>
      <?php endif; ?>
      <div class="col-12"><button class="btn btn-primary"><i class="bi bi-check2 me-1"></i><?= e(__('common.save')) ?></button></div>
    <?php endif; ?>
  </div>
</form>
