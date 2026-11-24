<div class="page-head"><h1><?= e(t('User Accounts')) ?></h1>
  <div class="actions"><?php if (can('users.logs')): ?><a class="btn btn-outline-secondary btn-sm" href="<?= url('users/logins') ?>"><i class="bi bi-clock-history"></i> Login history</a><?php endif; ?>
  <?php if (can('audit.view')): ?><a class="btn btn-outline-secondary btn-sm" href="<?= url('audit/index') ?>"><i class="bi bi-activity"></i> Activity log</a><?php endif; ?>
  <?php if (can('roles.view')): ?><a class="btn btn-outline-secondary btn-sm" href="<?= url('roles/index') ?>"><i class="bi bi-shield-lock"></i> Roles</a><?php endif; ?>
  <?php if (can('users.create')): ?><a class="btn btn-primary btn-sm" href="<?= url('users/create') ?>"><i class="bi bi-person-plus"></i> New user</a><?php endif; ?></div></div>
<?= \App\Core\View::partial('partials/filters', ['f' => ['q' => 'Name, username, email, mobile', 'extra' => '<div class="col-md-3"><label class="form-label">Role</label><select name="role_id" class="form-select form-select-sm">' . options($roles, $_GET['role_id'] ?? '', 'id', 'name', 'All') . '</select></div>']]) ?>
<div class="card"><div class="table-responsive"><table class="table table-sm table-hover mb-0">
  <thead><tr><th>User</th><th>Roles</th><th>Centers</th><th>Last login</th><th>Status</th><th></th></tr></thead>
  <tbody><?php foreach ($p['rows'] as $u): $locked = $u['locked_until'] && strtotime($u['locked_until']) > time(); ?>
    <tr><td><b><?= e($u['name']) ?></b><br><small class="text-muted"><?= e($u['username']) ?> · <?= e($u['email']) ?></small></td><td class="small"><?= e($u['roles']) ?></td><td class="small"><?= e($u['centers'] ?: 'All (head office)') ?></td>
      <td class="small"><?= e(fmt_datetime($u['last_login_at'])) ?></td><td><?= status_badge($u['is_active'] ? 'active' : 'inactive') ?><?= $locked ? ' <span class="badge text-bg-warning">Locked</span>' : '' ?><?= $u['session_token'] ? ' <span class="badge text-bg-light border">Online</span>' : '' ?></td>
      <td class="table-actions">
        <?php if (can('users.edit')): ?><a class="btn btn-sm btn-outline-primary" href="<?= url('users/edit', ['id' => $u['id']]) ?>"><i class="bi bi-pencil"></i></a>
          <form method="post" action="<?= url('users/toggle') ?>" class="d-inline" data-confirm="<?= $u['is_active'] ? 'Deactivate' : 'Activate' ?> this account?"><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int)$u['id'] ?>"><button class="btn btn-sm btn-outline-secondary" title="Activate / deactivate"><i class="bi bi-toggle-<?= $u['is_active'] ? 'on' : 'off' ?>"></i></button></form>
          <?php if ($u['session_token']): ?><form method="post" action="<?= url('users/logout') ?>" class="d-inline" data-confirm="Force sign-out?"><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int)$u['id'] ?>"><button class="btn btn-sm btn-outline-secondary" title="Force sign-out"><i class="bi bi-box-arrow-right"></i></button></form><?php endif; ?><?php endif; ?>
        <?php if (can('users.reset_password')): ?><form method="post" action="<?= url('users/reset') ?>" class="d-inline" data-confirm="Reset password and generate a temporary one?"><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int)$u['id'] ?>"><button class="btn btn-sm btn-outline-warning" title="Reset password"><i class="bi bi-key"></i></button></form><?php endif; ?>
      </td></tr>
  <?php endforeach; ?></tbody></table></div>
  <?= \App\Core\View::partial('partials/pagination', ['p' => $p]) ?></div>
