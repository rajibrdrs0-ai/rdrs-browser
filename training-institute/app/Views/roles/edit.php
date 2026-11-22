<?php
/** @var array $role @var array $permissions @var array $granted */
$this->layout('layouts/app', ['title' => __('roles.edit')]);
$byModule = [];
foreach ($permissions as $p) { $byModule[$p['module']][] = $p; }
?>
<div class="page-head">
  <h1><?= e(__('roles.edit')) ?>: <?= e(loc($role, 'name')) ?></h1>
  <a href="<?= e(url('/roles')) ?>" class="btn btn-sm btn-outline-secondary"><i class="bi bi-arrow-left me-1"></i><?= e(__('common.back')) ?></a>
</div>
<p class="small text-body-secondary"><?= e(__('scope.' . $role['scope'])) ?> · <?= e(__('roles.level')) ?> <?= (int) $role['level'] ?></p>

<form method="post" action="<?= e(url('/roles/' . $role['id'])) ?>">
  <?= csrf_field() ?><?= method_field('PUT') ?>
  <div class="row g-3">
    <?php foreach ($byModule as $module => $items): ?>
    <div class="col-md-6 col-xl-4">
      <div class="card h-100">
        <div class="card-header bg-body">
          <div class="form-check mb-0">
            <input class="form-check-input" type="checkbox" id="mod-<?= e($module) ?>" data-module-toggle="<?= e($module) ?>">
            <label class="form-check-label fw-semibold" for="mod-<?= e($module) ?>"><?= e(__('modules.' . $module)) ?></label>
          </div>
        </div>
        <div class="card-body">
          <?php foreach ($items as $p): ?>
            <div class="form-check">
              <input class="form-check-input" type="checkbox" name="permissions[]" value="<?= (int) $p['id'] ?>" id="perm-<?= (int) $p['id'] ?>"
                     data-module="<?= e($module) ?>" <?= isset($granted[(int) $p['id']]) ? 'checked' : '' ?>>
              <label class="form-check-label small" for="perm-<?= (int) $p['id'] ?>"><?= e(loc($p, 'name')) ?>
                <code class="text-body-secondary ms-1 d-none d-xxl-inline"><?= e($p['slug']) ?></code></label>
            </div>
          <?php endforeach; ?>
        </div>
      </div>
    </div>
    <?php endforeach; ?>
  </div>
  <div class="mt-3">
    <button class="btn btn-primary"><i class="bi bi-check2 me-1"></i><?= e(__('common.save')) ?></button>
    <a href="<?= e(url('/roles')) ?>" class="btn btn-link"><?= e(__('common.cancel')) ?></a>
  </div>
</form>
