<?php $v = fn($k, $d = '') => e(old($k, $c[$k] ?? $d)); ?>
<div class="page-head"><h1><?= e($title) ?></h1></div>
<form method="post" action="<?= url('courses/save') ?>"><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int)($c['id'] ?? 0) ?>">
<div class="card"><div class="card-body"><div class="row g-3">
  <div class="col-md-3"><label class="form-label req">Course code</label><input name="code" class="form-control text-uppercase" value="<?= $v('code') ?>" required maxlength="30"></div>
  <div class="col-md-6"><label class="form-label req">Course name</label><input name="name" class="form-control" value="<?= $v('name') ?>" required></div>
  <div class="col-md-3"><label class="form-label req">Status</label><select name="course_status_id" class="form-select" required><?= options($lists['statuses'], old('course_status_id', $c['course_status_id'] ?? 1), 'id', 'name', '') ?></select></div>
  <div class="col-md-3"><label class="form-label req">Trade</label><select name="trade_id" class="form-select" required><?= options($lists['trades'], old('trade_id', $c['trade_id'] ?? '')) ?></select></div>
  <div class="col-md-3"><label class="form-label">Course category</label><select name="academic_category_id" class="form-select"><?= options($lists['categories'], old('academic_category_id', $c['academic_category_id'] ?? '')) ?></select></div>
  <div class="col-md-2"><label class="form-label req">Duration</label><input type="number" min="1" name="duration" class="form-control" value="<?= $v('duration', 3) ?>" required></div>
  <div class="col-md-2"><label class="form-label req">Unit</label><select name="duration_unit" class="form-select"><?= kv_options(['hours' => 'Hours', 'days' => 'Days', 'weeks' => 'Weeks', 'months' => 'Months'], old('duration_unit', $c['duration_unit'] ?? 'months'), '') ?></select></div>
  <div class="col-md-2"><label class="form-label req">Course fee (BDT)</label><input type="number" step="0.01" min="0" name="fee" class="form-control" value="<?= $v('fee', 0) ?>" required></div>
  <div class="col-md-4"><label class="form-label">Minimum qualification</label><select name="min_education_level_id" class="form-select"><?= options($lists['education'], old('min_education_level_id', $c['min_education_level_id'] ?? '')) ?></select></div>
  <div class="col-md-4"><label class="form-label req">Pass mark (%)</label><input type="number" step="0.01" name="pass_mark_percent" class="form-control" value="<?= $v('pass_mark_percent', 40) ?>" required></div>
  <div class="col-md-4"><label class="form-label req">Minimum attendance for certificate (%)</label><input type="number" step="0.01" name="min_attendance_percent" class="form-control" value="<?= $v('min_attendance_percent', 75) ?>" required></div>
  <div class="col-12"><label class="form-label">Description</label><textarea name="description" class="form-control" rows="2"><?= $v('description') ?></textarea></div>
  <div class="col-md-6"><label class="form-label">Course objectives</label><textarea name="objectives" class="form-control" rows="5"><?= $v('objectives') ?></textarea></div>
  <div class="col-md-6"><label class="form-label">Course outline / curriculum</label><textarea name="outline" class="form-control" rows="5"><?= $v('outline') ?></textarea></div>
</div></div>
<div class="card-footer bg-white text-end"><a class="btn btn-light" href="<?= url('courses/index') ?>">Cancel</a> <button class="btn btn-primary">Save course</button></div></div>
</form>
