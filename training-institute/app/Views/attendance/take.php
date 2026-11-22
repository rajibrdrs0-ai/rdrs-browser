<?php
/** @var array $batch @var string $date @var string $start @var array $slots @var ?array $slot @var ?array $session @var array $existing
 *  @var array $enrollments @var array $modules @var array $trainers */
use App\Services\Rbac;
$this->layout('layouts/app', ['title' => __('attendance.take') . ' · ' . $batch['batch_code']]);
$locked = $session !== null && (int) $session['is_locked'] === 1;
$marks = ['present', 'absent', 'late', 'excused'];
$mods = []; foreach ($modules as $m) { $mods[$m['id']] = $m['sequence_no'] . '. ' . loc(['t_en' => $m['title_en'], 't_bn' => $m['title_bn']], 't'); }
?>
<div class="page-head">
  <div><h1><?= e(__('attendance.take')) ?></h1><div class="small text-body-secondary"><a href="<?= e(url('/batches/' . $batch['id'])) ?>"><?= e($batch['batch_code']) ?></a> · <?= e(loc($batch, 'course')) ?></div></div>
  <a class="btn btn-sm btn-outline-secondary" href="<?= e(url('/batches/' . $batch['id'] . '/attendance')) ?>"><i class="bi bi-table me-1"></i><?= e(__('attendance.register')) ?></a>
</div>

<form method="get" class="card card-body mb-3">
  <div class="row g-2 align-items-end">
    <div class="col-6 col-md-3"><?= form_input('date', __('attendance.date'), $date, ['type' => 'date', 'class' => 'form-control form-control-sm', 'min' => $batch['start_date'], 'max' => min(date('Y-m-d'), (string) $batch['end_date']), 'data-autosubmit-date' => true]) ?></div>
    <?php if ($slots !== []): ?>
    <div class="col-6 col-md-5"><?php $o = []; foreach ($slots as $sl) { $o[substr((string) $sl['start_time'], 0, 5)] = substr((string) $sl['start_time'], 0, 5) . '–' . substr((string) $sl['end_time'], 0, 5) . ($sl['title_en'] !== null ? ' · ' . $sl['sequence_no'] . '. ' . loc(['t_en' => $sl['title_en'], 't_bn' => $sl['title_bn']], 't') : '') . ($sl['staff_name'] ? ' · ' . $sl['staff_name'] : ''); } ?>
      <?= form_select('start', __('attendance.class'), $o, $start, ['class' => 'form-select form-select-sm', 'placeholder' => false, 'data-autosubmit' => true]) ?></div>
    <?php endif; ?>
    <div class="col-12 col-md-2 d-grid"><button class="btn btn-sm btn-outline-primary"><?= e(__('common.filter')) ?></button></div>
  </div>
</form>

<?php if ($locked): ?><div class="alert alert-secondary"><i class="bi bi-lock-fill me-1"></i><?= e(__('attendance.locked')) ?></div>
<?php elseif ($session !== null): ?><div class="alert alert-info"><i class="bi bi-info-circle me-1"></i><?= e(__('attendance.existing')) ?></div><?php endif; ?>

<form method="post" action="<?= e(url('/batches/' . $batch['id'] . '/attendance')) ?>" novalidate>
  <?= csrf_field() ?>
  <input type="hidden" name="session_date" value="<?= e($date) ?>">
  <fieldset <?= $locked ? 'disabled' : '' ?>>
  <div class="card mb-3"><div class="card-body"><div class="row g-2">
    <div class="col-6 col-md-2"><?= form_input('start_time', __('routine.start'), $start, ['type' => 'time', 'class' => 'form-control form-control-sm', 'required' => true]) ?></div>
    <div class="col-6 col-md-2"><?= form_input('end_time', __('routine.end'), substr((string) ($session['end_time'] ?? $slot['end_time'] ?? $batch['class_end_time'] ?? ''), 0, 5), ['type' => 'time', 'class' => 'form-control form-control-sm']) ?></div>
    <div class="col-md-4"><?= form_select('module_id', __('routine.module'), $mods, $session['module_id'] ?? $slot['module_id'] ?? '', ['class' => 'form-select form-select-sm']) ?></div>
    <?php if (Rbac::scope() !== 'self'): ?>
    <div class="col-md-4"><?= form_select('staff_id', __('routine.trainer'), options($trainers), $session['staff_id'] ?? $slot['staff_id'] ?? $batch['lead_trainer_id'] ?? '', ['class' => 'form-select form-select-sm']) ?></div>
    <?php endif; ?>
    <div class="col-md-8"><?= form_input('topic', __('attendance.topic'), $session['topic'] ?? '', ['class' => 'form-control form-control-sm', 'maxlength' => 200]) ?></div>
    <div class="col-md-4"><?= form_select('status', __('attendance.status'), ['held' => __('attendance.statuses.held'), 'cancelled' => __('attendance.statuses.cancelled'), 'holiday' => __('attendance.statuses.holiday')], $session['status'] ?? 'held', ['class' => 'form-select form-select-sm', 'placeholder' => false]) ?></div>
  </div></div></div>

  <div class="card">
    <div class="card-header bg-body d-flex justify-content-between align-items-center">
      <span class="fw-semibold"><?= e(__('students.title')) ?> (<?= num(count($enrollments)) ?>)</span>
      <button type="button" class="btn btn-sm btn-outline-success" data-mark-all="present"><i class="bi bi-check-all me-1"></i><?= e(__('attendance.all_present')) ?></button>
    </div>
    <?php if ($enrollments === []): ?><div class="card-body text-body-secondary"><?= e(__('attendance.no_students')) ?></div><?php else: ?>
    <ul class="list-group list-group-flush">
      <?php foreach ($enrollments as $en): $cur = $existing[(int) $en['id']]['status'] ?? 'present'; ?>
      <li class="list-group-item">
        <div class="d-flex flex-wrap align-items-center gap-2">
          <span class="text-body-secondary small" style="width:2rem"><?= e($en['roll_no']) ?></span>
          <?= $this->insert('students/_avatar', ['s' => ['id' => $en['student_id'], 'photo_path' => $en['photo_path']], 'size' => 32]) ?>
          <span class="flex-grow-1"><?= e(loc($en, 'name')) ?>
            <?php if ($en['attendance_percent'] !== null): ?><span class="small <?= (float) $en['attendance_percent'] < (float) $batch['min_attendance_percent'] ? 'text-danger' : 'text-body-secondary' ?>">· <?= num($en['attendance_percent'], 0) ?>%</span><?php endif; ?></span>
          <div class="btn-group btn-group-sm" role="group" aria-label="<?= e(loc($en, 'name')) ?>">
            <?php foreach ($marks as $m): $id = 'm-' . $en['id'] . '-' . $m; ?>
              <input type="radio" class="btn-check" name="marks[<?= (int) $en['id'] ?>]" id="<?= $id ?>" value="<?= $m ?>" <?= $cur === $m ? 'checked' : '' ?> data-mark="<?= $m ?>">
              <label class="btn btn-outline-<?= ['present' => 'success', 'absent' => 'danger', 'late' => 'warning', 'excused' => 'info'][$m] ?>" for="<?= $id ?>" title="<?= e(__('attendance.marks_long.' . $m)) ?>"><?= e(__('attendance.marks.' . $m)) ?></label>
            <?php endforeach; ?>
          </div>
          <input class="form-control form-control-sm" style="max-width:12rem" name="remarks[<?= (int) $en['id'] ?>]" value="<?= e($existing[(int) $en['id']]['remarks'] ?? '') ?>" placeholder="<?= e(__('attendance.remarks')) ?>" aria-label="<?= e(__('attendance.remarks')) ?>">
        </div>
      </li>
      <?php endforeach; ?>
    </ul>
    <?php endif; ?>
    <div class="card-footer bg-body"><button class="btn btn-primary"><i class="bi bi-check2 me-1"></i><?= e(__('attendance.save')) ?></button>
      <span class="small text-body-secondary ms-2"><?= e(__('attendance.legend')) ?></span></div>
  </div>
  </fieldset>
</form>
