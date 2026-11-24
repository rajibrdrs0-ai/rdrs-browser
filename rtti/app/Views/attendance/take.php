<div class="page-head"><h1>Take attendance</h1></div>
<form class="filters row g-2 align-items-end" method="get"><input type="hidden" name="r" value="attendance/take">
  <div class="col-md-6"><label class="form-label">Batch</label><select name="batch_id" class="form-select s2" required><?= options($batches, $b['id'] ?? '') ?></select></div>
  <div class="col-md-3"><label class="form-label">Date</label><input type="date" name="date" class="form-control" value="<?= e($date) ?>" max="<?= date('Y-m-d') ?>"></div>
  <div class="col-md-3"><button class="btn btn-primary w-100">Load students</button></div>
</form>
<?php if ($b): ?>
<form method="post" action="<?= url('attendance/save') ?>"><?= csrf_field() ?><input type="hidden" name="batch_id" value="<?= (int)$b['id'] ?>"><input type="hidden" name="date" value="<?= e($date) ?>">
<div class="card">
  <div class="card-header d-flex flex-wrap gap-2 align-items-center"><b><?= e($b['batch_code']) ?></b> · <?= e($b['course']) ?> · <?= e(fmt_date($date)) ?>
    <?= $sheet ? '<span class="badge text-bg-info">Already recorded – editing</span>' : '' ?>
    <div class="ms-auto btn-group btn-group-sm"><button type="button" class="btn btn-outline-success" onclick="$('.r-present').prop('checked',true)">All present</button><button type="button" class="btn btn-outline-danger" onclick="$('.r-absent').prop('checked',true)">All absent</button></div></div>
  <div class="card-body row g-2 border-bottom">
    <div class="col-md-6"><label class="form-label">Topic / class session</label><input name="topic" class="form-control" value="<?= e($sheet['topic'] ?? '') ?>"></div>
    <div class="col-md-6"><label class="form-label">Trainer</label><select name="trainer_id" class="form-select"><?= options($trainers, $sheet['trainer_id'] ?? $b['trainer_id']) ?></select></div>
  </div>
  <div class="table-responsive"><table class="table table-sm mb-0 align-middle">
    <thead><tr><th>#</th><th>Student</th><th class="text-center">Overall %</th><th>Status</th><th>Remarks</th></tr></thead><tbody>
    <?php foreach ($rows as $i => $r): $st = $r['status'] ?? 'present'; ?>
      <tr><td><?= $i + 1 ?></td><td><?= e($r['name']) ?> <small class="text-muted"><?= e($r['student_code']) ?></small></td><td class="text-center"><?= $r['pct'] === null ? '—' : (int)$r['pct'] . '%' ?></td>
        <td class="att-radio"><div class="btn-group btn-group-sm">
          <?php foreach (['present' => ['P', 'success'], 'late' => ['L', 'warning'], 'leave' => ['LV', 'info'], 'absent' => ['A', 'danger']] as $k => [$lbl, $col]): ?>
            <input type="radio" class="btn-check r-<?= $k ?>" name="status[<?= (int)$r['id'] ?>]" id="s<?= (int)$r['id'] . $k ?>" value="<?= $k ?>"<?= $st === $k ? ' checked' : '' ?>><label class="btn btn-outline-<?= $col ?>" for="s<?= (int)$r['id'] . $k ?>"><?= $lbl ?></label>
          <?php endforeach; ?></div></td>
        <td><input name="remarks[<?= (int)$r['id'] ?>]" class="form-control form-control-sm" value="<?= e($r['remarks'] ?? '') ?>"></td></tr>
    <?php endforeach; ?></tbody></table></div>
  <?= $rows ? '' : \App\Core\View::partial('partials/empty', ['message' => 'No active students in this batch.']) ?>
  <div class="card-footer bg-white text-end"><button class="btn btn-primary"<?= $rows ? '' : ' disabled' ?>><i class="bi bi-check2"></i> Save attendance</button></div>
</div></form>
<?php endif; ?>
