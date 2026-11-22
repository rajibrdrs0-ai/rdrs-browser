<?php
/** @var array $roles @var array $permissions @var array $matrix */
$this->layout('layouts/app', ['title' => __('roles.title')]);
$scopeIcon = ['global' => 'globe2', 'district' => 'geo-alt', 'center' => 'building', 'self' => 'person'];
?>
<div class="page-head"><h1><?= e(__('roles.title')) ?></h1></div>
<p class="small text-body-secondary"><i class="bi bi-info-circle me-1"></i><?= e(__('roles.level_help')) ?></p>

<div class="card mb-3">
  <div class="table-responsive">
    <table class="table mb-0">
      <thead class="table-light"><tr>
        <th><?= e(__('roles.role')) ?></th><th><?= e(__('roles.scope')) ?></th><th class="text-center"><?= e(__('roles.level')) ?></th>
        <th class="text-center"><?= e(__('roles.users')) ?></th><th class="text-center"><?= e(__('roles.permissions')) ?></th><th></th>
      </tr></thead>
      <tbody>
      <?php foreach ($roles as $r): ?>
        <tr>
          <td><div class="fw-semibold"><?= e(loc($r, 'name')) ?></div><div class="small text-body-secondary d-none d-md-block"><?= e($r['description']) ?></div></td>
          <td class="small"><i class="bi bi-<?= $scopeIcon[$r['scope']] ?>"></i> <?= e(__('scope.' . $r['scope'])) ?></td>
          <td class="text-center"><?= (int) $r['level'] ?></td>
          <td class="text-center"><?= (int) $r['user_count'] ?></td>
          <td class="text-center"><?= (int) $r['permission_count'] ?></td>
          <td class="text-end">
            <?php if (can('role.manage') && $r['slug'] !== 'super_admin'): ?>
              <a class="btn btn-sm btn-outline-secondary" href="<?= e(url('/roles/' . $r['id'] . '/edit')) ?>"><i class="bi bi-pencil me-1"></i><?= e(__('roles.edit')) ?></a>
            <?php elseif ($r['slug'] === 'super_admin'): ?><i class="bi bi-lock text-body-secondary" title="<?= e(__('roles.locked')) ?>"></i><?php endif; ?>
          </td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>

<div class="card">
  <div class="card-header bg-body fw-semibold"><i class="bi bi-grid-3x3 me-1"></i><?= e(__('roles.matrix')) ?></div>
  <div class="table-responsive">
    <table class="table table-sm table-bordered table-matrix mb-0">
      <thead class="table-light"><tr>
        <th><?= e(__('roles.permissions')) ?></th>
        <?php foreach ($roles as $r): ?><th class="rot text-center"><?= e(loc($r, 'name')) ?></th><?php endforeach; ?>
      </tr></thead>
      <tbody>
      <?php $current = null; foreach ($permissions as $p): ?>
        <?php if ($current !== $p['module']): $current = $p['module']; ?>
          <tr class="table-secondary"><td colspan="<?= count($roles) + 1 ?>" class="fw-semibold small"><?= e(__('modules.' . $p['module'])) ?></td></tr>
        <?php endif; ?>
        <tr><td class="small"><?= e(loc($p, 'name')) ?></td>
          <?php foreach ($roles as $r): ?>
            <td class="mark"><?= isset($matrix[(int) $p['id']][(int) $r['id']]) ? '<i class="bi bi-check-lg text-success"></i>' : '' ?></td>
          <?php endforeach; ?>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>
