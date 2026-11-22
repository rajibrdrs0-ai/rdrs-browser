<?php
/** @var ?array $course @var array $trades */
use App\Controllers\CourseController;
$isEdit = $course !== null;
$c = $course ?? ['min_attendance_percent' => '80.00', 'pass_mark_percent' => '50.00', 'course_type' => 'short'];
$this->layout('layouts/app', ['title' => __($isEdit ? 'courses.edit' : 'courses.add')]);
$types = array_combine(CourseController::TYPES, array_map(static fn($t) => __('courses.types.' . $t), CourseController::TYPES));
?>
<div class="page-head">
  <h1><?= e(__($isEdit ? 'courses.edit' : 'courses.add')) ?></h1>
  <a class="btn btn-sm btn-outline-secondary" href="<?= e(url($isEdit ? '/courses/' . $c['id'] : '/courses')) ?>"><i class="bi bi-arrow-left me-1"></i><?= e(__('common.back')) ?></a>
</div>
<form method="post" action="<?= e(url($isEdit ? '/courses/' . $c['id'] : '/courses')) ?>" class="card card-body" novalidate>
  <?= csrf_field() ?><?= $isEdit ? method_field('PUT') : '' ?>
  <div class="row g-3">
    <div class="col-md-3"><?= form_input('code', __('courses.code'), $c['code'] ?? '', ['required' => true, 'maxlength' => 20]) ?></div>
    <div class="col-md-5"><?= form_select('trade_id', __('courses.trade'), options($trades), $c['trade_id'] ?? '', ['required' => true]) ?></div>
    <div class="col-md-4"><?= form_select('course_type', __('courses.type'), $types, $c['course_type'] ?? 'short', ['required' => true, 'placeholder' => false]) ?></div>
    <div class="col-md-6"><?= form_input('name_en', __('courses.name_en'), $c['name_en'] ?? '', ['required' => true, 'maxlength' => 150]) ?></div>
    <div class="col-md-6"><?= form_input('name_bn', __('courses.name_bn'), $c['name_bn'] ?? '', ['required' => true, 'maxlength' => 150]) ?></div>
    <div class="col-6 col-md-3"><?= form_input('duration_months', __('courses.duration_months'), $c['duration_months'] ?? '', ['required' => true, 'inputmode' => 'decimal']) ?></div>
    <div class="col-6 col-md-3"><?= form_input('total_hours', __('courses.total_hours'), $c['total_hours'] ?? '', ['required' => true, 'type' => 'number', 'min' => 1]) ?></div>
    <div class="col-6 col-md-3"><?= form_select('nsqf_level', __('courses.nsqf_level'), array_combine(range(1, 6), range(1, 6)), $c['nsqf_level'] ?? '') ?></div>
    <div class="col-6 col-md-3"><?= form_input('min_qualification', __('courses.min_qualification'), $c['min_qualification'] ?? '', ['maxlength' => 120]) ?></div>
    <div class="col-6 col-md-3"><?= form_input('min_age', __('courses.min_age'), $c['min_age'] ?? '', ['type' => 'number', 'min' => 10, 'max' => 80]) ?></div>
    <div class="col-6 col-md-3"><?= form_input('max_age', __('courses.max_age'), $c['max_age'] ?? '', ['type' => 'number', 'min' => 10, 'max' => 80]) ?></div>
    <div class="col-6 col-md-3"><?= form_input('min_attendance_percent', __('courses.min_attendance'), $c['min_attendance_percent'], ['required' => true, 'inputmode' => 'decimal']) ?></div>
    <div class="col-6 col-md-3"><?= form_input('pass_mark_percent', __('courses.pass_mark'), $c['pass_mark_percent'], ['required' => true, 'inputmode' => 'decimal']) ?></div>
    <div class="col-12"><?= form_textarea('description', __('courses.description'), $c['description'] ?? '', ['rows' => 3, 'maxlength' => 5000]) ?></div>
    <?php if ($isEdit): ?><div class="col-12"><?= form_check('is_active', __('courses.is_active'), (int) $c['is_active'] === 1) ?></div><?php endif; ?>
    <div class="col-12"><button class="btn btn-primary"><i class="bi bi-check2 me-1"></i><?= e(__('common.save')) ?></button></div>
  </div>
</form>
