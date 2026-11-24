<div class="page-head"><h1><?= e(t('Staff Register')) ?></h1>
  <div class="actions"><?= \App\Core\View::partial('partials/export_buttons', ['perm' => 'staff.export']) ?>
  <?php if (can('staff.import')): ?><a class="btn btn-outline-secondary btn-sm" href="<?= url('import/index', ['type' => 'staff']) ?>"><i class="bi bi-upload"></i> Import</a><?php endif; ?>
  <?php if (can('staff.create')): ?><a class="btn btn-primary btn-sm" href="<?= url('staff/create') ?>"><i class="bi bi-person-plus"></i> New Staff</a><?php endif; ?></div></div>
<?= \App\Core\View::partial('partials/filters', ['f' => ['q' => 'ID, name, mobile, NID', 'center' => true, 'district' => true,
  'extra' => '<div class="col-6 col-md-2"><label class="form-label">Designation</label><select name="designation_id" class="form-select form-select-sm">' . options($designations, $_GET['designation_id'] ?? '', 'id', 'name', 'All') . '</select></div>'
  . '<div class="col-6 col-md-2"><label class="form-label">Status</label><select name="status" class="form-select form-select-sm">' . kv_options($statuses, $_GET['status'] ?? '', 'All') . '</select></div>'
  . '<div class="col-auto"><div class="form-check mb-1"><input class="form-check-input" type="checkbox" name="trainer" value="1" id="trn"' . (($_GET['trainer'] ?? '') === '1' ? ' checked' : '') . '><label class="form-check-label small" for="trn">Trainers only</label></div></div>']]) ?>
<div class="card"><div class="table-responsive"><table class="table table-sm table-hover mb-0">
  <thead><tr><th>Employee ID</th><th>Name</th><th>Designation</th><th>Center</th><th>Mobile</th><th>Joined</th><th>Trainer</th><th>Status</th><th></th></tr></thead>
  <tbody><?php foreach ($p['rows'] as $r): ?>
    <tr><td class="fw-semibold"><a href="<?= url('staff/view', ['id' => $r['id']]) ?>"><?= e($r['employee_code']) ?></a></td><td><?= e($r['name']) ?></td><td><?= e($r['designation']) ?><br><small class="text-muted"><?= e($r['department']) ?></small></td>
      <td><?= e($r['center']) ?></td><td><?= e($r['mobile']) ?></td><td><?= e(fmt_date($r['joining_date'])) ?></td>
      <td><?= $r['is_trainer'] ? '<span class="badge text-bg-info">' . e($r['trade'] ?: 'Trainer') . '</span>' . ($r['running_batches'] ? ' <small>' . (int)$r['running_batches'] . ' running</small>' : '') : '' ?></td>
      <td><?= status_badge($r['employment_status']) ?></td>
      <td class="table-actions"><?php if (can('staff.edit')): ?><a class="btn btn-sm btn-outline-secondary" href="<?= url('staff/edit', ['id' => $r['id']]) ?>"><i class="bi bi-pencil"></i></a><?php endif; ?></td></tr>
  <?php endforeach; ?></tbody></table></div>
  <?= $p['rows'] ? '' : \App\Core\View::partial('partials/empty') ?>
  <?= \App\Core\View::partial('partials/pagination', ['p' => $p]) ?></div>
