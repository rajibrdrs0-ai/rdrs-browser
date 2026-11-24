<?php $gone = in_array($a['status'], ['disposed', 'lost']); ?>
<div class="page-head"><h1><?= e($a['name']) ?></h1><?= status_badge($a['status']) ?>
  <div class="actions">
    <?php if (!$gone && can('assets.transfer')): ?><button class="btn btn-outline-primary btn-sm" data-bs-toggle="modal" data-bs-target="#tModal"><i class="bi bi-arrow-left-right"></i> Assign / transfer</button><?php endif; ?>
    <?php if (!$gone && can('assets.maintenance')): ?><button class="btn btn-outline-primary btn-sm" data-bs-toggle="modal" data-bs-target="#mModal"><i class="bi bi-tools"></i> Maintenance</button><?php endif; ?>
    <?php if (can('assets.edit')): ?><a class="btn btn-outline-secondary btn-sm" href="<?= url('assets/edit', ['id' => $a['id']]) ?>"><i class="bi bi-pencil"></i></a><?php endif; ?>
    <?php if (!$gone && can('assets.dispose')): ?><form method="post" action="<?= url('assets/dispose', ['id' => $a['id']]) ?>" class="d-inline" data-confirm="Dispose this asset?" data-reason><?= csrf_field() ?><button class="btn btn-outline-danger btn-sm">Dispose</button></form>
      <form method="post" action="<?= url('assets/dispose', ['id' => $a['id']]) ?>" class="d-inline" data-confirm="Report this asset as lost?" data-reason><?= csrf_field() ?><input type="hidden" name="as" value="lost"><button class="btn btn-outline-danger btn-sm">Report lost</button></form><?php endif; ?>
  </div></div>
<div class="row g-3"><div class="col-lg-5"><div class="card"><div class="card-body"><dl class="kv small">
  <dt>Asset ID</dt><dd class="fw-bold"><?= e($a['asset_code']) ?></dd><dt>Category</dt><dd><?= e($a['category']) ?></dd><dt>Brand / model</dt><dd><?= e($a['brand_model']) ?></dd>
  <dt>Serial no.</dt><dd><?= e($a['serial_no']) ?></dd><dt>Purchase</dt><dd><?= e(fmt_date($a['purchase_date'])) ?> · <?= $a['purchase_price'] !== null ? money($a['purchase_price']) : '' ?></dd>
  <dt>Supplier</dt><dd><?= e($a['supplier']) ?></dd><dt>Center</dt><dd><?= e($a['center']) ?></dd><dt>Assigned to</dt><dd><?= e($a['staff']) ?></dd>
  <dt>Condition</dt><dd><?= e(ucfirst($a['asset_condition'])) ?></dd><dt>Warranty</dt><dd><?= e(fmt_date($a['warranty_expiry'])) ?><?= $a['warranty_expiry'] && $a['warranty_expiry'] < date('Y-m-d') ? ' <span class="text-danger">(expired)</span>' : '' ?></dd>
  <?php if ($gone): ?><dt>Disposed</dt><dd class="text-danger"><?= e(fmt_date($a['disposal_date'])) ?> – <?= e($a['disposal_note']) ?></dd><?php endif; ?>
  <?php if ($a['remarks']): ?><dt>Remarks</dt><dd><?= e($a['remarks']) ?></dd><?php endif; ?></dl></div></div></div>
<div class="col-lg-7"><div class="card mb-3"><div class="card-header">Movement history</div><table class="table table-sm mb-0"><thead><tr><th>Date</th><th>Type</th><th>From</th><th>To</th><th>Employee</th><th>Note</th></tr></thead><tbody>
  <?php foreach ($moves as $m): ?><tr><td><?= e(fmt_date($m['movement_date'])) ?></td><td><?= e(ucfirst($m['movement_type'])) ?></td><td><?= e($m['from_center']) ?></td><td><?= e($m['to_center']) ?></td><td><?= e($m['staff']) ?></td><td class="small"><?= e($m['note']) ?> <span class="text-muted">(<?= e($m['uname']) ?>)</span></td></tr><?php endforeach; ?></tbody></table></div>
  <div class="card"><div class="card-header">Maintenance history</div><table class="table table-sm mb-0"><thead><tr><th>Date</th><th>Description</th><th>Vendor</th><th class="text-end">Cost</th><th>Next due</th></tr></thead><tbody>
  <?php foreach ($maint as $m): ?><tr><td><?= e(fmt_date($m['maintenance_date'])) ?></td><td><?= e($m['description']) ?></td><td><?= e($m['vendor']) ?></td><td class="text-end"><?= money($m['cost'], false) ?></td><td><?= e(fmt_date($m['next_due_date'])) ?></td></tr><?php endforeach; ?>
  <?php if (!$maint): ?><tr><td colspan="5" class="text-muted small">No maintenance recorded.</td></tr><?php endif; ?></tbody></table></div></div></div>
<?php if (!$gone && can('assets.transfer')): ?>
<div class="modal fade" id="tModal" tabindex="-1"><div class="modal-dialog"><form class="modal-content" method="post" action="<?= url('assets/transfer', ['id' => $a['id']]) ?>"><?= csrf_field() ?>
  <div class="modal-header"><h5 class="modal-title">Assign / transfer asset</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
  <div class="modal-body row g-2"><div class="col-12"><label class="form-label">Destination center</label><select name="to_center_id" class="form-select"><?= options($allCenters, $a['center_id'], 'id', 'name', '') ?></select></div>
    <div class="col-12"><label class="form-label">Employee (custodian)</label><select name="staff_id" class="form-select"><?= options($lists['staff'], '', 'id', 'name', '-- None / return to store --') ?></select></div>
    <div class="col-md-6"><label class="form-label">Date</label><input type="date" name="movement_date" class="form-control" value="<?= date('Y-m-d') ?>"></div>
    <div class="col-12"><label class="form-label">Note</label><input name="note" class="form-control"></div></div>
  <div class="modal-footer"><button class="btn btn-primary">Save movement</button></div></form></div></div>
<?php endif; ?>
<?php if (!$gone && can('assets.maintenance')): ?>
<div class="modal fade" id="mModal" tabindex="-1"><div class="modal-dialog"><form class="modal-content" method="post" action="<?= url('assets/maintenance', ['id' => $a['id']]) ?>"><?= csrf_field() ?>
  <div class="modal-header"><h5 class="modal-title">Record maintenance</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
  <div class="modal-body row g-2"><div class="col-md-6"><label class="form-label req">Date</label><input type="date" name="maintenance_date" class="form-control" value="<?= date('Y-m-d') ?>" required></div>
    <div class="col-md-6"><label class="form-label">Cost</label><input type="number" step="0.01" name="cost" class="form-control" value="0"></div>
    <div class="col-12"><label class="form-label req">Work done</label><input name="description" class="form-control" required></div>
    <div class="col-md-6"><label class="form-label">Vendor</label><input name="vendor" class="form-control"></div>
    <div class="col-md-6"><label class="form-label">Next service due</label><input type="date" name="next_due_date" class="form-control"></div>
    <div class="col-md-6"><label class="form-label">Asset status now</label><select name="status" class="form-select"><?= kv_options(['in_use' => 'In use', 'under_maintenance' => 'Under maintenance', 'in_store' => 'In store'], $a['status'], '') ?></select></div>
    <div class="col-md-6"><label class="form-label">Condition</label><select name="condition" class="form-select"><?= kv_options($lists['conditions'], $a['asset_condition'], '') ?></select></div></div>
  <div class="modal-footer"><button class="btn btn-primary">Save</button></div></form></div></div>
<?php endif; ?>
