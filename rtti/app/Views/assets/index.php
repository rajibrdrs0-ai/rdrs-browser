<div class="page-head"><h1><?= e(t('Asset Register')) ?></h1><span class="text-muted small"><?= (int)$tot['n'] ?> active assets · value <?= money($tot['value']) ?></span>
  <div class="actions"><?= \App\Core\View::partial('partials/export_buttons', ['perm' => 'assets.export']) ?>
  <?php if (can('assets.import')): ?><a class="btn btn-outline-secondary btn-sm" href="<?= url('import/index', ['type' => 'assets']) ?>"><i class="bi bi-upload"></i> Import</a><?php endif; ?>
  <?php if (can('assets.create')): ?><a class="btn btn-primary btn-sm" href="<?= url('assets/create') ?>"><i class="bi bi-plus-lg"></i> Register asset</a><?php endif; ?></div></div>
<?= \App\Core\View::partial('partials/filters', ['f' => ['q' => 'Asset ID, name, serial, supplier', 'center' => true, 'district' => true,
  'extra' => '<div class="col-6 col-md-2"><label class="form-label">Category</label><select name="asset_category_id" class="form-select form-select-sm">' . options($categories, $_GET['asset_category_id'] ?? '', 'id', 'name', 'All') . '</select></div>'
  . '<div class="col-6 col-md-2"><label class="form-label">Status</label><select name="status" class="form-select form-select-sm">' . kv_options($statuses, $_GET['status'] ?? '', 'All') . '</select></div>'
  . '<div class="col-auto"><div class="form-check mb-1"><input class="form-check-input" type="checkbox" name="warranty" value="1" id="wr"' . (($_GET['warranty'] ?? '') === '1' ? ' checked' : '') . '><label class="form-check-label small" for="wr">Warranty expiring (60 days)</label></div></div>']]) ?>
<div class="card"><div class="table-responsive"><table class="table table-sm table-hover mb-0">
  <thead><tr><th>Asset ID</th><th>Asset</th><th>Category</th><th>Serial</th><th>Center</th><th>Assigned to</th><th class="text-end">Price</th><th>Condition</th><th>Status</th></tr></thead>
  <tbody><?php foreach ($p['rows'] as $r): ?>
    <tr><td class="fw-semibold"><a href="<?= url('assets/view', ['id' => $r['id']]) ?>"><?= e($r['asset_code']) ?></a></td><td><?= e($r['name']) ?><br><small class="text-muted"><?= e($r['brand_model']) ?></small></td><td><?= e($r['category']) ?></td>
      <td class="small"><?= e($r['serial_no']) ?></td><td><?= e($r['center']) ?></td><td><?= e($r['staff']) ?></td><td class="text-end"><?= $r['purchase_price'] !== null ? money($r['purchase_price'], false) : '' ?></td>
      <td><?= e(ucfirst($r['asset_condition'])) ?></td><td><?= status_badge($r['status']) ?></td></tr>
  <?php endforeach; ?></tbody></table></div>
  <?= $p['rows'] ? '' : \App\Core\View::partial('partials/empty') ?>
  <?= \App\Core\View::partial('partials/pagination', ['p' => $p]) ?></div>
