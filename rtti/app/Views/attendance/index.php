<div class="page-head"><h1><?= e(t('Attendance')) ?></h1>
  <div class="actions">
    <a class="btn btn-outline-secondary btn-sm" href="<?= url('attendance/report') ?>"><i class="bi bi-table"></i> Monthly register</a>
    <?php if (can('attendance.staff')): ?><a class="btn btn-outline-secondary btn-sm" href="<?= url('attendance/staff') ?>"><i class="bi bi-person-badge"></i> Trainer / staff attendance</a><?php endif; ?>
    <?php if (can('attendance.take')): ?><a class="btn btn-primary btn-sm" href="<?= url('attendance/take') ?>"><i class="bi bi-calendar-check"></i> Take attendance</a><?php endif; ?></div></div>
<?= \App\Core\View::partial('partials/filters', ['f' => ['center' => true, 'district' => true, 'dates' => true,
  'extra' => '<div class="col-md-3"><label class="form-label">Batch</label><select name="batch_id" class="form-select form-select-sm s2">' . options($batches, $_GET['batch_id'] ?? '', 'id', 'name', 'All') . '</select></div>']]) ?>
<div class="card"><div class="table-responsive"><table class="table table-sm table-hover mb-0">
  <thead><tr><th>Date</th><th>Batch</th><th>Center</th><th>Topic</th><th>Trainer</th><th class="text-center">Present</th><th class="text-center">Late</th><th class="text-center">Leave</th><th class="text-center">Absent</th><th class="text-center">%</th><th></th></tr></thead>
  <tbody><?php foreach ($p['rows'] as $r): ?>
    <tr><td><?= e(fmt_date($r['attendance_date'])) ?></td><td><a href="<?= url('batches/view', ['id' => $r['batch_id']]) ?>"><?= e($r['batch_code']) ?></a><br><small class="text-muted"><?= e($r['course']) ?></small></td>
      <td><?= e($r['center']) ?></td><td><?= e($r['topic']) ?></td><td><?= e($r['trainer']) ?></td>
      <td class="text-center text-success"><?= (int)$r['present'] ?></td><td class="text-center text-warning"><?= (int)$r['late'] ?></td><td class="text-center text-info"><?= (int)$r['leave_'] ?></td><td class="text-center text-danger"><?= (int)$r['absent'] ?></td>
      <td class="text-center"><?= $r['total'] ? round(($r['present'] + $r['late']) * 100 / $r['total']) : 0 ?>%</td>
      <td class="table-actions"><?php if (can('attendance.take')): ?><a class="btn btn-sm btn-outline-primary" href="<?= url('attendance/take', ['batch_id' => $r['batch_id'], 'date' => $r['attendance_date']]) ?>"><i class="bi bi-pencil"></i></a><?php endif; ?></td></tr>
  <?php endforeach; ?></tbody></table></div>
  <?= $p['rows'] ? '' : \App\Core\View::partial('partials/empty') ?>
  <?= \App\Core\View::partial('partials/pagination', ['p' => $p]) ?>
</div>
