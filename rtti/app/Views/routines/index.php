<?php $days = \App\Controllers\RoutinesController::DAYS; ?>
<div class="page-head"><h1><?= e(t('Class Routine')) ?></h1>
  <div class="actions"><?php if (can('routines.manage')): ?><button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#rModal"><i class="bi bi-plus-lg"></i> Add class</button><?php endif; ?></div></div>
<?= \App\Core\View::partial('partials/filters', ['f' => ['center' => true, 'district' => true,
  'extra' => '<div class="col-md-3"><label class="form-label">Batch</label><select name="batch_id" class="form-select form-select-sm s2">' . options($batches, $_GET['batch_id'] ?? '', 'id', 'name', 'All') . '</select></div>'
  . '<div class="col-md-3"><label class="form-label">Trainer</label><select name="trainer_id" class="form-select form-select-sm s2">' . options($trainers, $_GET['trainer_id'] ?? '', 'id', 'name', 'All') . '</select></div>']]) ?>
<div class="card mb-3"><div class="card-header">Weekly timetable (running & upcoming batches)</div><div class="table-responsive"><table class="table table-bordered table-sm mb-0">
  <tbody><?php foreach ($days as $d => $label): ?>
    <tr><th style="width:110px" class="bg-light"><?= $label ?></th><td><div class="d-flex flex-wrap gap-2">
      <?php foreach ($grid[$d] ?? [] as $r): ?>
        <div class="border rounded p-2 small" style="min-width:190px;background:#f8fbff">
          <b><?= e(substr($r['start_time'], 0, 5)) ?>–<?= e(substr($r['end_time'], 0, 5)) ?></b> <?= e($r['room'] ? '· ' . $r['room'] : '') ?><br>
          <a href="<?= url('batches/view', ['id' => $r['batch_id']]) ?>"><?= e($r['batch_code']) ?></a> <?= e($r['subject']) ?><br>
          <span class="text-muted"><?= e($r['trainer']) ?> · <?= e($r['center']) ?></span>
          <?php if (can('routines.manage')): ?><form method="post" action="<?= url('routines/delete') ?>" class="d-inline" data-confirm="Remove this class from the routine?"><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int)$r['id'] ?>"><button class="btn btn-link btn-sm p-0 text-danger"><i class="bi bi-x-circle"></i></button></form><?php endif; ?>
        </div>
      <?php endforeach; ?>
      <?php if (empty($grid[$d])): ?><span class="text-muted small">—</span><?php endif; ?>
    </div></td></tr>
  <?php endforeach; ?></tbody></table></div></div>
<div class="card"><div class="card-header">Trainer-wise workload</div><div class="table-responsive"><table class="table table-sm mb-0">
  <thead><tr><th>Trainer</th><th>Center</th><th class="text-center">Active batches</th><th class="text-center">Weekly classes</th><th class="text-center">Weekly hours</th><th class="text-center">Classes taken (30 days)</th></tr></thead>
  <tbody><?php foreach ($workload as $w): ?><tr><td><a href="<?= url('staff/view', ['id' => $w['id']]) ?>"><?= e($w['name']) ?></a> <small class="text-muted"><?= e($w['employee_code']) ?></small></td><td><?= e($w['center']) ?></td>
    <td class="text-center"><?= (int)$w['batches'] ?></td><td class="text-center"><?= (int)$w['slots'] ?></td><td class="text-center"><?= e($w['weekly_hours'] ?? 0) ?></td><td class="text-center"><?= (int)$w['classes_30d'] ?></td></tr><?php endforeach; ?></tbody></table></div></div>
<?php if (can('routines.manage')): ?>
<div class="modal fade" id="rModal" tabindex="-1"><div class="modal-dialog"><form class="modal-content" method="post" action="<?= url('routines/save') ?>"><?= csrf_field() ?>
  <div class="modal-header"><h5 class="modal-title">Add class to routine</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
  <div class="modal-body row g-2">
    <div class="col-12"><label class="form-label req">Batch</label><select name="batch_id" class="form-select s2" required><?= options($batches, $_GET['batch_id'] ?? '') ?></select></div>
    <div class="col-md-4"><label class="form-label req">Day</label><select name="day_of_week" class="form-select" required><?= kv_options($days, '', '') ?></select></div>
    <div class="col-md-4"><label class="form-label req">Start</label><input type="time" name="start_time" class="form-control" required></div>
    <div class="col-md-4"><label class="form-label req">End</label><input type="time" name="end_time" class="form-control" required></div>
    <div class="col-md-6"><label class="form-label">Subject / topic</label><input name="subject" class="form-control"></div>
    <div class="col-md-6"><label class="form-label">Room</label><input name="room" class="form-control"></div>
    <div class="col-12"><label class="form-label">Trainer (default: batch trainer)</label><select name="trainer_id" class="form-select s2"><?= options($trainers) ?></select></div>
    <div class="col-12 small text-muted">Batch, trainer and room clashes are checked automatically.</div>
  </div>
  <div class="modal-footer"><button class="btn btn-primary">Save</button></div></form></div></div>
<?php endif; ?>
