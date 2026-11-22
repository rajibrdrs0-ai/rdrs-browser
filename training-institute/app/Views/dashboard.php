<?php
/** @var array $user @var array $stats @var array $centers @var array $modules @var int $permCount */
use App\Services\Rbac;
$this->layout('layouts/app', ['title' => __('dashboard.title')]);
// Each card only appears for users holding the matching view permission.
$cards = array_values(array_filter([
    ['dashboard.centers', 'building', $stats['centers'], 'center.view'],
    ['dashboard.users', 'people', $stats['users'], 'user.view'],
    ['dashboard.students', 'mortarboard', $stats['students'], 'student.view'],
    ['dashboard.batches_running', 'collection', $stats['batches'], 'batch.view'],
], static fn(array $c): bool => can($c[3])));
$fmt = static fn(int $n): string => current_locale() === 'bn' ? bn_digits($n) : (string) $n;
?>
<div class="page-head">
  <h1><?= e(__('dashboard.title')) ?></h1>
  <span class="text-body-secondary small"><?= e(loc($user, 'role_name')) ?> · <?= e(__('scope.' . $user['role_scope'])) ?></span>
</div>

<div class="row g-3 mb-3">
  <?php foreach ($cards as [$label, $icon, $value]): ?>
  <div class="col-6 col-xl-<?= 12 / max(1, count($cards)) >= 3 ? (int) (12 / count($cards)) : 3 ?>">
    <div class="card stat-card h-100"><div class="card-body d-flex align-items-center gap-3">
      <div class="stat-icon"><i class="bi bi-<?= e($icon) ?>"></i></div>
      <div><div class="stat-value"><?= e($fmt((int) $value)) ?></div><div class="text-body-secondary small"><?= e(__($label)) ?></div></div>
    </div></div>
  </div>
  <?php endforeach; ?>
</div>

<div class="row g-3">
  <div class="col-lg-5">
    <div class="card h-100"><div class="card-header bg-body fw-semibold"><i class="bi bi-shield-check me-1"></i><?= e(__('dashboard.your_access')) ?></div>
      <div class="card-body">
        <dl class="row mb-0 small">
          <dt class="col-5"><?= e(__('dashboard.role')) ?></dt><dd class="col-7"><?= e(loc($user, 'role_name')) ?></dd>
          <dt class="col-5"><?= e(__('dashboard.scope')) ?></dt><dd class="col-7"><?= e(__('scope.' . $user['role_scope'])) ?></dd>
          <dt class="col-5"><?= e(__('dashboard.permissions')) ?></dt><dd class="col-7"><?= e($fmt($permCount)) ?></dd>
          <dt class="col-5"><?= e(__('dashboard.centers_visible')) ?></dt>
          <dd class="col-7"><?= Rbac::isGlobal() ? e(__('dashboard.all_centers')) : e($fmt(count($centers))) ?></dd>
        </dl>
        <hr>
        <div class="small fw-semibold mb-2"><?= e(__('dashboard.modules')) ?></div>
        <div class="d-flex flex-wrap gap-1">
          <?php foreach ($modules as $module => $count): ?>
            <span class="badge rounded-pill text-bg-light border"><?= e(__('modules.' . $module)) ?> · <?= e($fmt($count)) ?></span>
          <?php endforeach; ?>
        </div>
      </div>
    </div>
  </div>
  <div class="col-lg-7">
    <div class="card h-100"><div class="card-header bg-body fw-semibold"><i class="bi bi-building me-1"></i><?= e(__('dashboard.centers')) ?></div>
      <div class="table-responsive">
        <table class="table table-sm mb-0">
          <tbody>
          <?php foreach ($centers as $c): ?>
            <tr><td class="ps-3"><span class="badge text-bg-secondary"><?= e($c['code']) ?></span></td>
                <td><?= e(loc($c, 'name')) ?></td>
                <td class="text-body-secondary"><?= e(current_locale() === 'bn' ? $c['district_bn'] : $c['district_en']) ?></td></tr>
          <?php endforeach; ?>
          <?php if ($centers === []): ?><tr><td class="ps-3 text-body-secondary"><?= e(__('common.no_records')) ?></td></tr><?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</div>
