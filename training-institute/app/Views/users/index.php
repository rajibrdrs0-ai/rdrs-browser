<?php
/** @var array $users @var int $total @var int $page @var int $pages @var array $roles @var array $centers @var array $filters */
$this->layout('layouts/app', ['title' => __('users.title')]);
$statusClass = ['active' => 'success', 'inactive' => 'secondary', 'suspended' => 'warning'];
?>
<div class="page-head">
  <h1><?= e(__('users.title')) ?></h1>
  <?php if (can('user.create')): ?>
    <a href="<?= e(url('/users/create')) ?>" class="btn btn-primary btn-sm"><i class="bi bi-person-plus me-1"></i><?= e(__('users.add')) ?></a>
  <?php endif; ?>
</div>

<form class="card card-body mb-3" method="get" action="<?= e(url('/users')) ?>">
  <div class="row g-2 align-items-end">
    <div class="col-12 col-md-4">
      <label class="form-label small mb-1" for="q"><?= e(__('common.search')) ?></label>
      <input class="form-control form-control-sm" id="q" name="q" value="<?= e($filters['q']) ?>" placeholder="<?= e(__('users.search_placeholder')) ?>">
    </div>
    <div class="col-6 col-md-2">
      <label class="form-label small mb-1" for="f_role"><?= e(__('users.role')) ?></label>
      <select class="form-select form-select-sm" id="f_role" name="role_id">
        <option value=""><?= e(__('common.all')) ?></option>
        <?php foreach ($roles as $r): ?><option value="<?= (int) $r['id'] ?>" <?= (string) $filters['role_id'] === (string) $r['id'] ? 'selected' : '' ?>><?= e(loc($r, 'name')) ?></option><?php endforeach; ?>
      </select>
    </div>
    <div class="col-6 col-md-3">
      <label class="form-label small mb-1" for="center_id"><?= e(__('users.center')) ?></label>
      <select class="form-select form-select-sm" id="center_id" name="center_id">
        <option value=""><?= e(__('common.all')) ?></option>
        <?php foreach ($centers as $c): ?><option value="<?= (int) $c['id'] ?>" <?= (string) $filters['center_id'] === (string) $c['id'] ? 'selected' : '' ?>><?= e(loc($c, 'name')) ?></option><?php endforeach; ?>
      </select>
    </div>
    <div class="col-6 col-md-2">
      <label class="form-label small mb-1" for="status"><?= e(__('common.status')) ?></label>
      <select class="form-select form-select-sm" id="status" name="status">
        <option value=""><?= e(__('common.all')) ?></option>
        <?php foreach (['active', 'inactive', 'suspended'] as $s): ?><option value="<?= $s ?>" <?= $filters['status'] === $s ? 'selected' : '' ?>><?= e(__('common.' . $s)) ?></option><?php endforeach; ?>
      </select>
    </div>
    <div class="col-6 col-md-1 d-grid"><button class="btn btn-sm btn-outline-primary"><i class="bi bi-funnel"></i></button></div>
  </div>
</form>

<div class="card">
  <div class="table-responsive">
    <table class="table table-hover mb-0">
      <thead class="table-light"><tr>
        <th><?= e(__('users.name')) ?></th><th><?= e(__('users.role')) ?></th><th class="d-none d-md-table-cell"><?= e(__('users.scope_note')) ?></th>
        <th class="d-none d-lg-table-cell"><?= e(__('users.mobile')) ?></th><th><?= e(__('common.status')) ?></th>
        <th class="d-none d-xl-table-cell"><?= e(__('common.last_login')) ?></th><th class="text-end"><?= e(__('common.actions')) ?></th>
      </tr></thead>
      <tbody>
      <?php foreach ($users as $u): ?>
        <tr>
          <td><div class="fw-semibold"><?= e(loc($u, 'name')) ?></div><div class="small text-body-secondary">@<?= e($u['username']) ?></div></td>
          <td><?= e(loc($u, 'role_name')) ?></td>
          <td class="d-none d-md-table-cell small">
            <?php if ($u['role_scope'] === 'district'): ?><i class="bi bi-geo-alt"></i> <?= e(loc($u, 'district_name')) ?>
            <?php elseif ($u['role_scope'] === 'global'): ?><i class="bi bi-globe2"></i> <?= e(__('dashboard.all_centers')) ?>
            <?php else: ?><i class="bi bi-building"></i> <?= e($u['center_name_en'] !== null ? loc($u, 'center_name') : '—') ?><?php endif; ?>
          </td>
          <td class="d-none d-lg-table-cell"><?= e($u['mobile']) ?></td>
          <td><span class="badge text-bg-<?= $statusClass[$u['status']] ?? 'secondary' ?>"><?= e(__('common.' . $u['status'])) ?></span>
              <?php if ($u['locked_until'] !== null && strtotime((string) $u['locked_until']) > time()): ?><i class="bi bi-lock-fill text-danger ms-1"></i><?php endif; ?></td>
          <td class="d-none d-xl-table-cell small text-body-secondary"><?= e($u['last_login_at'] ?? __('common.never')) ?></td>
          <td class="text-end text-nowrap">
            <?php if ($u['manageable']): ?>
              <?php if (can('user.update')): ?><a class="btn btn-sm btn-outline-secondary" href="<?= e(url('/users/' . $u['id'] . '/edit')) ?>" title="<?= e(__('common.edit')) ?>"><i class="bi bi-pencil"></i></a><?php endif; ?>
              <?php if (can('user.reset_password')): ?>
                <form class="d-inline" method="post" action="<?= e(url('/users/' . $u['id'] . '/reset-password')) ?>" data-confirm="<?= e(__('common.confirm')) ?>"><?= csrf_field() ?>
                  <button class="btn btn-sm btn-outline-secondary" title="<?= e(__('users.reset_password')) ?>"><i class="bi bi-key"></i></button></form>
              <?php endif; ?>
              <?php if (can('user.deactivate')): ?>
                <form class="d-inline" method="post" action="<?= e(url('/users/' . $u['id'] . '/status')) ?>" data-confirm="<?= e(__('common.confirm')) ?>"><?= csrf_field() ?>
                  <button class="btn btn-sm <?= $u['status'] === 'active' ? 'btn-outline-danger' : 'btn-outline-success' ?>"
                          title="<?= e(__($u['status'] === 'active' ? 'users.deactivate' : 'users.activate')) ?>">
                    <i class="bi bi-<?= $u['status'] === 'active' ? 'person-dash' : 'person-check' ?>"></i></button></form>
              <?php endif; ?>
            <?php endif; ?>
          </td>
        </tr>
      <?php endforeach; ?>
      <?php if ($users === []): ?><tr><td colspan="7" class="text-center text-body-secondary py-4"><?= e(__('common.no_records')) ?></td></tr><?php endif; ?>
      </tbody>
    </table>
  </div>
  <div class="card-footer bg-body d-flex justify-content-between align-items-center small">
    <span class="text-body-secondary"><?= e(__('common.showing', ['count' => $total])) ?></span>
    <?php if ($pages > 1): ?>
      <nav><ul class="pagination pagination-sm mb-0">
        <?php for ($i = 1; $i <= $pages; $i++): ?>
          <li class="page-item <?= $i === $page ? 'active' : '' ?>"><a class="page-link" href="<?= e(url('/users', array_filter($filters + ['page' => $i]))) ?>"><?= $i ?></a></li>
        <?php endfor; ?>
      </ul></nav>
    <?php endif; ?>
  </div>
</div>
