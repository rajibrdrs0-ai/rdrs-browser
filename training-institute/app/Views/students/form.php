<?php
/** @var ?array $student @var array $centers @var array $districts */
use App\Controllers\StudentController as SC;
$isEdit = $student !== null;
$s = $student ?? ['disability' => 'none', 'status' => 'active'];
$this->layout('layouts/app', ['title' => __($isEdit ? 'students.edit' : 'students.add')]);
$tr = static fn(string $group, array $keys): array => array_combine($keys, array_map(static fn($k) => __($group . '.' . $k), $keys));
$districtOptions = options($districts);
?>
<div class="page-head">
  <h1><?= e(__($isEdit ? 'students.edit' : 'students.add')) ?><?php if ($isEdit): ?> <small class="text-body-secondary fs-6"><?= e($s['registration_no']) ?></small><?php endif; ?></h1>
  <a class="btn btn-sm btn-outline-secondary" href="<?= e(url($isEdit ? '/students/' . $s['id'] : '/students')) ?>"><i class="bi bi-arrow-left me-1"></i><?= e(__('common.back')) ?></a>
</div>
<form method="post" action="<?= e(url($isEdit ? '/students/' . $s['id'] : '/students')) ?>" enctype="multipart/form-data" novalidate>
  <?= csrf_field() ?><?= $isEdit ? method_field('PUT') : '' ?>
  <div class="card mb-3"><div class="card-header bg-body fw-semibold"><?= e(__('students.sections.basic')) ?></div><div class="card-body"><div class="row g-3">
    <div class="col-md-4"><?= form_select('center_id', __('students.center'), options($centers, 'name', 'id', 'code'), $s['center_id'] ?? (count($centers) === 1 ? $centers[0]['id'] : ''), ['required' => true]) ?></div>
    <div class="col-md-4"><?= form_input('name_en', __('students.name_en'), $s['name_en'] ?? '', ['required' => true, 'maxlength' => 120]) ?></div>
    <div class="col-md-4"><?= form_input('name_bn', __('students.name_bn'), $s['name_bn'] ?? '', ['maxlength' => 120]) ?></div>
    <div class="col-6 col-md-3"><?= form_select('gender', __('students.gender'), $tr('staff.genders', ['male', 'female', 'other']), $s['gender'] ?? '', ['required' => true]) ?></div>
    <div class="col-6 col-md-3"><?= form_input('date_of_birth', __('students.dob'), $s['date_of_birth'] ?? '', ['type' => 'date', 'required' => true]) ?></div>
    <div class="col-6 col-md-3"><?= form_input('nid_no', __('students.nid'), $s['nid_no'] ?? '', ['inputmode' => 'numeric', 'maxlength' => 17]) ?></div>
    <div class="col-6 col-md-3"><?= form_input('birth_reg_no', __('students.brn'), $s['birth_reg_no'] ?? '', ['inputmode' => 'numeric', 'maxlength' => 17]) ?></div>
    <div class="col-12"><div class="form-text mt-0"><?= e(__('students.identity_required')) ?></div></div>
    <div class="col-6 col-md-3"><?= form_input('mobile', __('students.mobile'), $s['mobile'] ?? '', ['required' => true, 'inputmode' => 'tel', 'placeholder' => '01XXXXXXXXX']) ?></div>
    <div class="col-6 col-md-3"><?= form_input('email', __('students.email'), $s['email'] ?? '', ['type' => 'email']) ?></div>
    <div class="col-6 col-md-3"><?= form_select('religion', __('students.religion'), $tr('students.religions', SC::RELIGION), $s['religion'] ?? '') ?></div>
    <div class="col-6 col-md-3"><?= form_select('marital_status', __('students.marital'), $tr('students.maritals', ['single', 'married', 'widowed', 'divorced']), $s['marital_status'] ?? '') ?></div>
    <div class="col-md-6"><?= form_input('highest_education', __('students.education'), $s['highest_education'] ?? '', ['maxlength' => 80, 'placeholder' => 'SSC / HSC / …']) ?></div>
    <div class="col-md-6"><?= form_input('photo', __('students.photo'), '', ['type' => 'file', 'accept' => 'image/jpeg,image/png,image/webp', 'help' => __('students.photo_help'), 'value' => null]) ?></div>
    <?php if ($isEdit): ?><div class="col-md-4"><?= form_select('status', __('students.status'), $tr('students.statuses', ['active', 'inactive', 'blacklisted']), $s['status'], ['placeholder' => false]) ?></div><?php endif; ?>
  </div></div></div>

  <div class="card mb-3"><div class="card-header bg-body fw-semibold"><?= e(__('students.sections.family')) ?></div><div class="card-body"><div class="row g-3">
    <div class="col-md-6"><?= form_input('father_name', __('students.father'), $s['father_name'] ?? '', ['maxlength' => 120]) ?></div>
    <div class="col-md-6"><?= form_input('father_mobile', __('students.father_mobile'), $s['father_mobile'] ?? '', ['inputmode' => 'tel']) ?></div>
    <div class="col-md-6"><?= form_input('mother_name', __('students.mother'), $s['mother_name'] ?? '', ['maxlength' => 120]) ?></div>
    <div class="col-md-6"><?= form_input('mother_mobile', __('students.mother_mobile'), $s['mother_mobile'] ?? '', ['inputmode' => 'tel']) ?></div>
    <div class="col-md-4"><?= form_input('guardian_name', __('students.guardian'), $s['guardian_name'] ?? '', ['maxlength' => 120]) ?></div>
    <div class="col-md-4"><?= form_input('guardian_mobile', __('students.guardian_mobile'), $s['guardian_mobile'] ?? '', ['inputmode' => 'tel']) ?></div>
    <div class="col-md-4"><?= form_input('guardian_relation', __('students.guardian_relation'), $s['guardian_relation'] ?? '', ['maxlength' => 40]) ?></div>
  </div></div></div>

  <div class="card mb-3"><div class="card-header bg-body fw-semibold"><?= e(__('students.sections.address')) ?></div><div class="card-body"><div class="row g-3">
    <?php foreach (['permanent' => 'perm', 'present' => 'pres'] as $kind => $alias): ?>
      <div class="col-12 fw-semibold small"><?= e(__('students.' . $kind)) ?></div>
      <div class="col-md-4"><?= form_select($kind . '_district', __('students.district'), $districtOptions, $s[$alias . '_district_id'] ?? '', ['id' => $kind . '_district', 'data-upazila-target' => $kind . '_upazila_id']) ?></div>
      <div class="col-md-4"><?= form_select($kind . '_upazila_id', __('students.upazila'), [], $s[$kind . '_upazila_id'] ?? '', ['data-selected' => (string) old($kind . '_upazila_id', $s[$kind . '_upazila_id'] ?? '')]) ?></div>
      <div class="col-md-4"><?= form_input($kind . '_address', __('students.address'), $s[$kind . '_address'] ?? '', ['maxlength' => 255]) ?></div>
    <?php endforeach; ?>
  </div></div></div>

  <div class="card mb-3"><div class="card-header bg-body fw-semibold"><?= e(__('students.sections.social')) ?></div><div class="card-body"><div class="row g-3">
    <div class="col-md-4"><?= form_select('disability', __('students.disability'), $tr('students.disabilities', SC::DISABILITY), $s['disability'], ['placeholder' => false]) ?></div>
    <div class="col-md-4"><?= form_select('employment_status_before', __('students.employment_before'), $tr('students.employments', SC::EMPLOYMENT), $s['employment_status_before'] ?? '') ?></div>
    <div class="col-md-4"><?= form_input('household_income_monthly', __('students.income'), $s['household_income_monthly'] ?? '', ['inputmode' => 'decimal']) ?></div>
    <div class="col-12"><?= form_check('is_ethnic_minority', __('students.ethnic'), (int) ($s['is_ethnic_minority'] ?? 0) === 1) ?></div>
  </div></div></div>
  <button class="btn btn-primary"><i class="bi bi-check2 me-1"></i><?= e(__('common.save')) ?></button>
</form>
