<div class="page-head"><h1><?= e(t('Roles & Permissions')) ?></h1>
  <div class="actions"><?php if (\App\Core\Auth::isSuperAdmin()): ?><form method="post" action="<?= url('roles/sync') ?>" class="d-inline"><?= csrf_field() ?><button class="btn btn-outline-secondary btn-sm"><i class="bi bi-arrow-repeat"></i> Sync permissions</button></form><?php endif; ?>
  <?php if (can('roles.manage')): ?><a class="btn btn-primary btn-sm" href="<?= url('roles/edit') ?>"><i class="bi bi-plus-lg"></i> New role</a><?php endif; ?></div></div>
<div class="card"><table class="table table-sm table-hover mb-0"><thead><tr><th>Role</th><th>Description</th><th>Scope</th><th class="text-center">Permissions</th><th class="text-center">Users</th><th></th></tr></thead><tbody>
<?php foreach ($roles as $r): ?><tr><td><b><?= e($r['name']) ?></b><?= $r['is_system'] ? ' <span class="badge text-bg-light border">built-in</span>' : '' ?></td><td class="small"><?= e($r['description']) ?></td>
  <td><?= $r['all_centers'] ? '<span class="badge text-bg-dark">All centers</span>' : '<span class="badge text-bg-secondary">Assigned centers</span>' ?></td>
  <td class="text-center"><?= $r['slug'] === 'super_admin' ? 'All' : (int)$r['perms'] ?></td><td class="text-center"><a href="<?= url('users/index', ['role_id' => $r['id']]) ?>"><?= (int)$r['users'] ?></a></td>
  <td class="table-actions"><a class="btn btn-sm btn-outline-primary" href="<?= url('roles/edit', ['id' => $r['id']]) ?>"><i class="bi bi-<?= can('roles.manage') ? 'pencil' : 'eye' ?>"></i></a>
    <?php if (!$r['is_system'] && can('roles.manage')): ?><form method="post" action="<?= url('roles/delete') ?>" class="d-inline" data-confirm="Delete role?"><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int)$r['id'] ?>"><button class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i></button></form><?php endif; ?></td></tr><?php endforeach; ?>
</tbody></table></div>
