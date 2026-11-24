<?php $ro = !can('roles.manage') || ($role['slug'] ?? '') === 'super_admin'; ?>
<div class="page-head"><h1><?= e($title) ?></h1></div>
<form method="post" action="<?= url('roles/save') ?>"><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int)($role['id'] ?? 0) ?>">
<div class="card mb-3"><div class="card-body row g-2">
  <div class="col-md-4"><label class="form-label req">Role name</label><input name="name" class="form-control" value="<?= e($role['name'] ?? '') ?>" required<?= $ro ? ' disabled' : '' ?>></div>
  <div class="col-md-6"><label class="form-label">Description</label><input name="description" class="form-control" value="<?= e($role['description'] ?? '') ?>"<?= $ro ? ' disabled' : '' ?>></div>
  <div class="col-md-2 pt-4"><div class="form-check"><input class="form-check-input" type="checkbox" name="all_centers" value="1" id="ac"<?= !empty($role['all_centers']) ? ' checked' : '' ?><?= $ro ? ' disabled' : '' ?>><label class="form-check-label" for="ac">Head office (all centers)</label></div></div>
</div></div>
<?php if (($role['slug'] ?? '') === 'super_admin'): ?><div class="alert alert-info">Super Administrator always has every permission.</div><?php endif; ?>
<div class="card"><div class="card-header d-flex">Menu-wise permissions <?php if (!$ro): ?><span class="ms-auto small"><a href="#" onclick="$('.pchk').prop('checked',true);return false">all</a> · <a href="#" onclick="$('.pchk').prop('checked',false);return false">none</a></span><?php endif; ?></div>
<div class="table-responsive"><table class="table table-sm table-bordered mb-0 text-center align-middle">
  <thead><tr><th class="text-start">Module</th><?php foreach ($actions as $a): ?><th class="small"><?= e(ucwords(str_replace('_', ' ', $a))) ?></th><?php endforeach; ?></tr></thead>
  <tbody><?php foreach ($modules as $key => $m): ?><tr><td class="text-start"><a href="#" class="text-reset" onclick="$(this).closest('tr').find('.pchk').prop('checked', function(i,v){return !v});return false"><?= e($m['label']) ?></a></td>
    <?php foreach ($actions as $a): $p = $byModule[$key][$a] ?? null; ?><td><?php if ($p): ?><input type="checkbox" class="form-check-input pchk" name="perms[]" value="<?= (int)$p['id'] ?>"<?= in_array((int)$p['id'], $granted, true) ? ' checked' : '' ?><?= $ro ? ' disabled' : '' ?> title="<?= e($p['slug']) ?>"><?php endif; ?></td><?php endforeach; ?></tr><?php endforeach; ?></tbody>
</table></div>
<?php if (!$ro): ?><div class="card-footer bg-white text-end"><a class="btn btn-light" href="<?= url('roles/index') ?>">Cancel</a> <button class="btn btn-primary">Save role</button></div><?php endif; ?></div></form>
