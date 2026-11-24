<?php $v = fn($k, $d = '') => e(old($k, $s[$k] ?? $d)); ?>
<div class="page-head"><h1><?= e($title) ?></h1><?php if ($s): ?><span class="badge text-bg-primary fs-6"><?= e($s['employee_code']) ?></span><?php endif; ?></div>
<form method="post" action="<?= url('staff/save') ?>" enctype="multipart/form-data"><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int)($s['id'] ?? 0) ?>">
<div class="row g-3"><div class="col-xl-9"><div class="card"><div class="card-body row g-3">
  <div class="col-md-6"><label class="form-label req">Employee name</label><input name="name" class="form-control" value="<?= $v('name') ?>" required></div>
  <div class="col-md-3"><label class="form-label">Gender</label><select name="gender_id" class="form-select"><?= options($lists['genders'], old('gender_id', $s['gender_id'] ?? '')) ?></select></div>
  <div class="col-md-3"><label class="form-label">NID</label><input name="nid" class="form-control" value="<?= $v('nid') ?>"></div>
  <div class="col-md-4"><label class="form-label">Designation</label><select name="designation_id" class="form-select"><?= options($lists['designations'], old('designation_id', $s['designation_id'] ?? '')) ?></select></div>
  <div class="col-md-4"><label class="form-label">Department</label><select name="department_id" class="form-select"><?= options($lists['departments'], old('department_id', $s['department_id'] ?? '')) ?></select></div>
  <div class="col-md-4"><label class="form-label req">Training center</label><select name="center_id" class="form-select" required><?= options($lists['centers'], old('center_id', $s['center_id'] ?? \App\Core\Auth::defaultCenterId())) ?></select></div>
  <div class="col-md-4"><label class="form-label req">Mobile</label><input name="mobile" class="form-control" value="<?= $v('mobile') ?>" required></div>
  <div class="col-md-4"><label class="form-label">Email</label><input type="email" name="email" class="form-control" value="<?= $v('email') ?>"></div>
  <div class="col-md-4"><label class="form-label">Joining date</label><input type="date" name="joining_date" class="form-control" value="<?= $v('joining_date') ?>"></div>
  <div class="col-md-6"><label class="form-label">Present address</label><input name="present_address" class="form-control" value="<?= $v('present_address') ?>"></div>
  <div class="col-md-6"><label class="form-label">Permanent address</label><input name="permanent_address" class="form-control" value="<?= $v('permanent_address') ?>"></div>
  <div class="col-md-4"><div class="form-check mt-4"><input class="form-check-input" type="checkbox" name="is_trainer" value="1" id="isT"<?= old('is_trainer', $s['is_trainer'] ?? 0) ? ' checked' : '' ?>><label class="form-check-label" for="isT">This person is a trainer</label></div></div>
  <div class="col-md-4"><label class="form-label">Trainer trade</label><select name="trainer_trade_id" class="form-select"><?= options($lists['trades'], old('trainer_trade_id', $s['trainer_trade_id'] ?? '')) ?></select></div>
  <div class="col-md-4"><label class="form-label req">Employment status</label><select name="employment_status" class="form-select"><?= kv_options($lists['statuses'], old('employment_status', $s['employment_status'] ?? 'active'), '') ?></select></div>
  <div class="col-12"><label class="form-label">Remarks</label><textarea name="remarks" class="form-control" rows="2"><?= $v('remarks') ?></textarea></div>
</div></div></div>
<div class="col-xl-3"><div class="card"><div class="card-body text-center">
  <img id="sPrev" class="profile-photo mb-2<?= empty($s['photo']) ? ' d-none' : '' ?>" src="<?= e(file_url($s['photo'] ?? null)) ?>">
  <input type="file" name="photo" accept="image/jpeg,image/png" class="form-control form-control-sm" data-preview="#sPrev"><div class="form-text">Photograph (JPG/PNG, 2 MB)</div>
  <button class="btn btn-primary w-100 mt-3">Save</button></div></div></div></div></form>
