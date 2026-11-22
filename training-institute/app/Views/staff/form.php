<?php
/** @var ?array $staff @var array $centers @var array $designations @var array $users */
$isEdit = $staff !== null;
$s = $staff ?? ['status' => 'active', 'employment_type' => 'permanent', 'joining_date' => date('Y-m-d')];
$this->layout('layouts/app', ['title' => __($isEdit ? 'staff.edit' : 'staff.add')]);
$tr = static fn(string $group, array $keys): array => array_combine($keys, array_map(static fn($k) => __($group . '.' . $k), $keys));
$userOptions = [];
foreach ($users as $u) { $userOptions[$u['id']] = $u['name'] . ' (@' . $u['username'] . ', ' . (current_locale() === 'bn' ? $u['role_bn'] : $u['role_en']) . ')'; }
$designationOptions = [];
foreach ($designations as $d) { $designationOptions[$d['id']] = loc($d, 'name') . ((int) $d['is_trainer'] === 1 ? ' ★' : ''); }
?>
<div class="page-head">
  <h1><?= e(__($isEdit ? 'staff.edit' : 'staff.add')) ?><?php if ($isEdit): ?> <small class="text-body-secondary fs-6"><?= e($s['employee_code']) ?></small><?php endif; ?></h1>
  <a class="btn btn-sm btn-outline-secondary" href="<?= e(url('/staff')) ?>"><i class="bi bi-arrow-left me-1"></i><?= e(__('common.back')) ?></a>
</div>
<form method="post" action="<?= e(url($isEdit ? '/staff/' . $s['id'] : '/staff')) ?>" class="card card-body" novalidate>
  <?= csrf_field() ?><?= $isEdit ? method_field('PUT') : '' ?>
  <div class="row g-3">
    <div class="col-md-4"><?= form_select('center_id', __('staff.center'), options($centers, 'name', 'id', 'code'), $s['center_id'] ?? (count($centers) === 1 ? $centers[0]['id'] : ''), ['required' => true]) ?></div>
    <div class="col-md-4"><?= form_select('designation_id', __('staff.designation'), $designationOptions, $s['designation_id'] ?? '', ['required' => true]) ?></div>
    <div class="col-md-4"><?= form_select('employment_type', __('staff.employment_type'), $tr('staff.employment', ['permanent', 'contractual', 'part_time', 'guest']), $s['employment_type'], ['required' => true, 'placeholder' => false]) ?></div>
    <div class="col-md-4"><?= form_input('name', __('staff.name'), $s['name'] ?? '', ['required' => true, 'maxlength' => 120]) ?></div>
    <div class="col-md-4"><?= form_input('name_bn', __('staff.name_bn'), $s['name_bn'] ?? '', ['maxlength' => 120]) ?></div>
    <div class="col-md-4"><?= form_select('gender', __('staff.gender'), $tr('staff.genders', ['male', 'female', 'other']), $s['gender'] ?? '', ['required' => true]) ?></div>
    <div class="col-md-4"><?= form_input('mobile', __('staff.mobile'), $s['mobile'] ?? '', ['required' => true, 'inputmode' => 'tel', 'placeholder' => '01XXXXXXXXX']) ?></div>
    <div class="col-md-4"><?= form_input('email', __('staff.email'), $s['email'] ?? '', ['type' => 'email']) ?></div>
    <div class="col-md-4"><?= form_input('nid_no', __('staff.nid'), $s['nid_no'] ?? '', ['inputmode' => 'numeric', 'maxlength' => 17]) ?></div>
    <div class="col-md-4"><?= form_input('date_of_birth', __('staff.date_of_birth'), $s['date_of_birth'] ?? '', ['type' => 'date']) ?></div>
    <div class="col-md-8"><?= form_input('address', __('staff.address'), $s['address'] ?? '', ['maxlength' => 255]) ?></div>
    <div class="col-md-6"><?= form_input('qualification', __('staff.qualification'), $s['qualification'] ?? '', ['maxlength' => 150]) ?></div>
    <div class="col-md-6"><?= form_input('trainer_certificate_no', __('staff.trainer_cert'), $s['trainer_certificate_no'] ?? '', ['maxlength' => 60]) ?></div>
    <div class="col-6 col-md-3"><?= form_input('joining_date', __('staff.joining_date'), $s['joining_date'] ?? '', ['type' => 'date', 'required' => true]) ?></div>
    <div class="col-6 col-md-3"><?= form_input('leaving_date', __('staff.leaving_date'), $s['leaving_date'] ?? '', ['type' => 'date']) ?></div>
    <div class="col-6 col-md-3"><?= form_input('monthly_salary', __('staff.salary'), $s['monthly_salary'] ?? '', ['inputmode' => 'decimal']) ?></div>
    <div class="col-6 col-md-3"><?= form_select('status', __('staff.status'), $tr('staff.statuses', ['active', 'on_leave', 'resigned', 'terminated']), $s['status'], ['required' => true, 'placeholder' => false]) ?></div>
    <div class="col-md-6"><?= form_select('user_id', __('staff.login'), $userOptions, $s['user_id'] ?? '', ['placeholder' => __('staff.no_login')]) ?>
      <div class="form-text"><?= e(__('staff.login_help')) ?></div></div>
    <div class="col-12"><button class="btn btn-primary"><i class="bi bi-check2 me-1"></i><?= e(__('common.save')) ?></button></div>
  </div>
</form>
