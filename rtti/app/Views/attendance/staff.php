<div class="page-head"><h1>Trainer & staff attendance</h1></div>
<form class="filters row g-2 align-items-end" method="get"><input type="hidden" name="r" value="attendance/staff">
  <div class="col-md-5"><label class="form-label">Center</label><select name="center_id" class="form-select"><?= options($centers, $cid, 'id', 'name', '') ?></select></div>
  <div class="col-md-3"><label class="form-label">Date</label><input type="date" name="date" class="form-control" value="<?= e($date) ?>" max="<?= date('Y-m-d') ?>"></div>
  <div class="col-md-2"><button class="btn btn-primary w-100">Load</button></div></form>
<form method="post" action="<?= url('attendance/staff', ['center_id' => $cid, 'date' => $date]) ?>"><?= csrf_field() ?>
<div class="card"><div class="table-responsive"><table class="table table-sm mb-0 align-middle">
  <thead><tr><th>Staff</th><th>Designation</th><th class="text-center">30-day %</th><th>Status</th><th>Remarks</th></tr></thead><tbody>
  <?php foreach ($rows as $r): $st = $r['status'] ?? 'present'; ?>
    <tr><td><?= e($r['name']) ?> <small class="text-muted"><?= e($r['employee_code']) ?></small><?= $r['is_trainer'] ? ' <span class="badge text-bg-info">Trainer</span>' : '' ?></td><td><?= e($r['designation']) ?></td><td class="text-center"><?= $r['pct'] === null ? '—' : (int)$r['pct'] . '%' ?></td>
      <td><div class="btn-group btn-group-sm"><?php foreach (['present' => ['P', 'success'], 'late' => ['L', 'warning'], 'leave' => ['LV', 'info'], 'absent' => ['A', 'danger']] as $k => [$lbl, $col]): ?>
        <input type="radio" class="btn-check" name="status[<?= (int)$r['id'] ?>]" id="t<?= (int)$r['id'] . $k ?>" value="<?= $k ?>"<?= $st === $k ? ' checked' : '' ?>><label class="btn btn-outline-<?= $col ?>" for="t<?= (int)$r['id'] . $k ?>"><?= $lbl ?></label><?php endforeach; ?></div></td>
      <td><input name="remarks[<?= (int)$r['id'] ?>]" class="form-control form-control-sm" value="<?= e($r['remarks'] ?? '') ?>"></td></tr>
  <?php endforeach; ?></tbody></table></div>
  <div class="card-footer bg-white text-end"><button class="btn btn-primary">Save</button></div></div></form>
