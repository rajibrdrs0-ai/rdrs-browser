<?php $v = fn($k, $d = '') => e(old($k, $b[$k] ?? $d)); $days = old('class_days', isset($b['class_days']) ? array_map('trim', explode(',', (string)$b['class_days'])) : ['Sat', 'Mon', 'Wed']); ?>
<div class="page-head"><h1><?= e($title) ?></h1><?php if ($isEdit): ?><span class="badge text-bg-primary fs-6"><?= e($b['batch_code']) ?></span><?php endif; ?></div>
<form method="post" action="<?= url('batches/save') ?>"><?= csrf_field() ?><input type="hidden" name="id" value="<?= $isEdit ? (int)$b['id'] : 0 ?>">
<div class="card"><div class="card-body"><div class="row g-3">
  <div class="col-md-6"><label class="form-label req">Course</label><select name="course_id" id="course" class="form-select s2" required><?= options($lists['courses'], old('course_id', $b['course_id'] ?? '')) ?></select></div>
  <div class="col-md-6"><label class="form-label req">Batch name</label><input name="name" id="bname" class="form-control" value="<?= $v('name') ?>" required placeholder="e.g. Computer Office Application – Morning"></div>
  <div class="col-md-4"><label class="form-label req">Training center</label><select name="center_id" id="center" class="form-select" required data-child="#trainer" data-source="ajax/trainers"><?= options($lists['centers'], old('center_id', $b['center_id'] ?? \App\Core\Auth::defaultCenterId())) ?></select></div>
  <div class="col-md-4"><label class="form-label">Trainer</label><select name="trainer_id" id="trainer" class="form-select" data-selected="<?= $v('trainer_id') ?>"><?= options($lists['trainers'], old('trainer_id', $b['trainer_id'] ?? '')) ?></select></div>
  <div class="col-md-4"><label class="form-label req">Status</label><select name="batch_status_id" class="form-select" required><?= options($lists['statuses'], old('batch_status_id', $b['batch_status_id'] ?? 1), 'id', 'name', '') ?></select></div>
  <div class="col-md-3"><label class="form-label req">Start date</label><input type="date" name="start_date" class="form-control" value="<?= $v('start_date') ?>" required></div>
  <div class="col-md-3"><label class="form-label req">End date</label><input type="date" name="end_date" class="form-control" value="<?= $v('end_date') ?>" required></div>
  <div class="col-md-3"><label class="form-label req">Maximum students</label><input type="number" min="1" name="max_students" class="form-control" value="<?= $v('max_students', 25) ?>" required></div>
  <div class="col-md-3"><label class="form-label">Room / lab</label><input name="room" class="form-control" value="<?= $v('room') ?>"></div>
  <div class="col-md-6"><label class="form-label">Class days</label><div class="d-flex flex-wrap gap-2">
    <?php foreach (['Sat', 'Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri'] as $d): ?><label class="btn btn-outline-primary btn-sm"><input type="checkbox" class="form-check-input me-1" name="class_days[]" value="<?= $d ?>"<?= in_array($d, (array)$days, true) ? ' checked' : '' ?>><?= $d ?></label><?php endforeach; ?></div></div>
  <div class="col-md-3"><label class="form-label">Class starts</label><input type="time" name="class_start_time" class="form-control" value="<?= $v('class_start_time') ?>"></div>
  <div class="col-md-3"><label class="form-label">Class ends</label><input type="time" name="class_end_time" class="form-control" value="<?= $v('class_end_time') ?>"></div>
  <div class="col-12"><label class="form-label">Remarks</label><textarea name="remarks" class="form-control" rows="2"><?= $v('remarks') ?></textarea></div>
</div></div>
<div class="card-footer bg-white text-end"><a class="btn btn-light" href="<?= url('batches/index') ?>">Cancel</a> <button class="btn btn-primary">Save batch</button></div></div>
</form>
<?php ob_start(); ?><script>
$('#course').on('change', function(){ if (!$('#bname').val()) $('#bname').val($(this).find(':selected').text().replace(/^.*? – /, '') + ' – Batch'); });
</script><?php \App\Core\View::share('scripts', ob_get_clean()); ?>
