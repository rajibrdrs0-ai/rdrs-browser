<?php $v = fn($k, $d = '') => e(old($k, $a[$k] ?? $d)); ?>
<div class="page-head"><h1><?= e($title) ?></h1><?php if ($a): ?><span class="badge text-bg-primary fs-6"><?= e($a['asset_code']) ?></span><?php endif; ?></div>
<form method="post" action="<?= url('assets/save') ?>"><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int)($a['id'] ?? 0) ?>">
<div class="card"><div class="card-body row g-3">
  <div class="col-md-6"><label class="form-label req">Asset name</label><input name="name" class="form-control" value="<?= $v('name') ?>" required></div>
  <div class="col-md-3"><label class="form-label req">Category</label><select name="asset_category_id" class="form-select" required><?= options($lists['categories'], old('asset_category_id', $a['asset_category_id'] ?? '')) ?></select></div>
  <div class="col-md-3"><label class="form-label">Brand / model</label><input name="brand_model" class="form-control" value="<?= $v('brand_model') ?>"></div>
  <div class="col-md-3"><label class="form-label">Serial number</label><input name="serial_no" class="form-control" value="<?= $v('serial_no') ?>"></div>
  <div class="col-md-3"><label class="form-label">Purchase date</label><input type="date" name="purchase_date" class="form-control" value="<?= $v('purchase_date') ?>"></div>
  <div class="col-md-3"><label class="form-label">Purchase price</label><input type="number" step="0.01" name="purchase_price" class="form-control" value="<?= $v('purchase_price') ?>"></div>
  <div class="col-md-3"><label class="form-label">Supplier</label><input name="supplier" class="form-control" value="<?= $v('supplier') ?>"></div>
  <?php if (!$a): ?>
  <div class="col-md-4"><label class="form-label req">Center</label><select name="center_id" class="form-select" required><?= options($lists['centers'], old('center_id', \App\Core\Auth::defaultCenterId())) ?></select></div>
  <div class="col-md-2"><label class="form-label">Quantity</label><input type="number" min="1" max="100" name="quantity" class="form-control" value="<?= e(old('quantity', 1)) ?>"></div>
  <div class="col-md-3"><label class="form-label">Status</label><select name="status" class="form-select"><?= kv_options(['in_use' => 'In use', 'in_store' => 'In store'], old('status', 'in_use'), '') ?></select></div>
  <?php else: ?><div class="col-md-6"><label class="form-label">Center</label><input class="form-control" value="<?= e($a['center']) ?>" disabled><div class="form-text">Use “Transfer” on the asset page to move it to another center.</div></div><?php endif; ?>
  <div class="col-md-3"><label class="form-label">Assigned employee</label><select name="assigned_staff_id" class="form-select s2"><?= options($lists['staff'], old('assigned_staff_id', $a['assigned_staff_id'] ?? '')) ?></select></div>
  <div class="col-md-3"><label class="form-label req">Condition</label><select name="asset_condition" class="form-select"><?= kv_options($lists['conditions'], old('asset_condition', $a['asset_condition'] ?? 'new'), '') ?></select></div>
  <div class="col-md-3"><label class="form-label">Warranty expiry</label><input type="date" name="warranty_expiry" class="form-control" value="<?= $v('warranty_expiry') ?>"></div>
  <div class="col-12"><label class="form-label">Remarks</label><textarea name="remarks" class="form-control" rows="2"><?= $v('remarks') ?></textarea></div>
</div><div class="card-footer bg-white text-end"><a class="btn btn-light" href="<?= url('assets/index') ?>">Cancel</a> <button class="btn btn-primary">Save</button></div></div></form>
