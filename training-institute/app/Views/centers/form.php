<?php
/** @var ?array $center @var array $districts */
$isEdit = $center !== null;
$this->layout('layouts/app', ['title' => __($isEdit ? 'centers.edit' : 'centers.add')]);
$c = $center ?? [];
?>
<div class="page-head">
  <h1><?= e(__($isEdit ? 'centers.edit' : 'centers.add')) ?></h1>
  <a class="btn btn-sm btn-outline-secondary" href="<?= e(url($isEdit ? '/centers/' . $c['id'] : '/centers')) ?>"><i class="bi bi-arrow-left me-1"></i><?= e(__('common.back')) ?></a>
</div>
<form method="post" action="<?= e(url($isEdit ? '/centers/' . $c['id'] : '/centers')) ?>" class="card card-body" novalidate>
  <?= csrf_field() ?><?= $isEdit ? method_field('PUT') : '' ?>
  <div class="row g-3">
    <div class="col-md-3"><?= form_input('code', __('centers.code'), $c['code'] ?? '', ['required' => true, 'maxlength' => 12, 'help' => __('centers.code_help')]) ?></div>
    <div class="col-md-5"><?= form_input('name_en', __('centers.name_en'), $c['name_en'] ?? '', ['required' => true, 'maxlength' => 150]) ?></div>
    <div class="col-md-4"><?= form_input('name_bn', __('centers.name_bn'), $c['name_bn'] ?? '', ['required' => true, 'maxlength' => 150]) ?></div>
    <div class="col-md-4"><?= form_select('district_id', __('centers.district'), options($districts), $c['district_id'] ?? '', ['required' => true, 'data-upazila-target' => 'upazila_id']) ?></div>
    <div class="col-md-4"><?= form_select('upazila_id', __('centers.upazila'), [], $c['upazila_id'] ?? '', ['data-selected' => (string) old('upazila_id', $c['upazila_id'] ?? '')]) ?></div>
    <div class="col-md-4"><?= form_input('phone', __('centers.phone'), $c['phone'] ?? '', ['inputmode' => 'tel', 'placeholder' => '01XXXXXXXXX']) ?></div>
    <div class="col-md-8"><?= form_input('address', __('centers.address'), $c['address'] ?? '', ['required' => true, 'maxlength' => 255]) ?></div>
    <div class="col-md-4"><?= form_input('email', __('centers.email'), $c['email'] ?? '', ['type' => 'email']) ?></div>
    <div class="col-md-4"><?= form_input('registration_no', __('centers.registration_no'), $c['registration_no'] ?? '') ?></div>
    <div class="col-md-4"><?= form_input('seat_capacity', __('centers.seat_capacity'), $c['seat_capacity'] ?? 0, ['type' => 'number', 'min' => 0, 'required' => true]) ?></div>
    <div class="col-md-4"><?= form_input('established_on', __('centers.established_on'), $c['established_on'] ?? '', ['type' => 'date']) ?></div>
    <div class="col-12 d-flex gap-4">
      <?= form_check('has_hostel', __('centers.has_hostel'), (int) ($c['has_hostel'] ?? 0) === 1) ?>
      <?php if ($isEdit): ?><?= form_check('is_active', __('centers.is_active'), (int) $c['is_active'] === 1) ?><?php endif; ?>
    </div>
    <div class="col-12"><button class="btn btn-primary"><i class="bi bi-check2 me-1"></i><?= e(__('common.save')) ?></button></div>
  </div>
</form>
