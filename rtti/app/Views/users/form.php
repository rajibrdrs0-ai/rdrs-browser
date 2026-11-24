<?php $v = fn($k, $d = '') => e(old($k, $u[$k] ?? $d)); ?>
<div class="page-head"><h1><?= e($title) ?></h1></div>
<form method="post" action="<?= url('users/save') ?>" autocomplete="off"><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int)($u['id'] ?? 0) ?>">
<div class="row g-3"><div class="col-lg-6"><div class="card"><div class="card-header">Account</div><div class="card-body row g-3">
  <div class="col-12"><label class="form-label">Linked staff member</label><select name="staff_id" class="form-select s2"><?= options($staff, old('staff_id', $u['staff_id'] ?? ($staffPre['id'] ?? ''))) ?></select></div>
  <div class="col-md-6"><label class="form-label req">Full name</label><input name="name" class="form-control" value="<?= $v('name', $staffPre['name'] ?? '') ?>" required></div>
  <div class="col-md-6"><label class="form-label req">Username</label><input name="username" class="form-control" value="<?= $v('username') ?>" required></div>
  <div class="col-md-6"><label class="form-label">Email</label><input type="email" name="email" class="form-control" value="<?= $v('email', $staffPre['email'] ?? '') ?>"></div>
  <div class="col-md-6"><label class="form-label">Mobile</label><input name="mobile" class="form-control" value="<?= $v('mobile', $staffPre['mobile'] ?? '') ?>"></div>
  <div class="col-md-6"><label class="form-label<?= $u ? '' : ' req' ?>">Password<?= $u ? ' (leave blank to keep)' : '' ?></label><input type="password" name="password" class="form-control" autocomplete="new-password"<?= $u ? '' : ' required' ?>><div class="form-text">Min 8 characters, letters + numbers.</div></div>
  <div class="col-md-6 pt-4"><div class="form-check"><input class="form-check-input" type="checkbox" name="must_change" value="1" id="mc" checked><label class="form-check-label" for="mc">Must change password at next sign-in</label></div>
    <div class="form-check"><input class="form-check-input" type="checkbox" name="is_active" value="1" id="ia"<?= ($u['is_active'] ?? 1) ? ' checked' : '' ?>><label class="form-check-label" for="ia">Account active</label></div></div>
</div></div></div>
<div class="col-lg-6"><div class="card mb-3"><div class="card-header">Roles</div><div class="card-body">
  <?php foreach ($roles as $r): ?><div class="form-check"><input class="form-check-input" type="checkbox" name="roles[]" value="<?= (int)$r['id'] ?>" id="r<?= (int)$r['id'] ?>"<?= in_array($r['id'], old('roles', $userRoles)) ? ' checked' : '' ?>>
    <label class="form-check-label" for="r<?= (int)$r['id'] ?>"><b><?= e($r['name']) ?></b><?= $r['all_centers'] ? ' <span class="badge text-bg-dark">All centers</span>' : '' ?> <small class="text-muted"><?= e($r['description']) ?></small></label></div><?php endforeach; ?></div></div>
  <div class="card"><div class="card-header">Center access <small class="text-muted">(ignored for head-office roles)</small></div><div class="card-body">
  <?php foreach ($centers as $c): ?><div class="form-check form-check-inline"><input class="form-check-input" type="checkbox" name="centers[]" value="<?= (int)$c['id'] ?>" id="c<?= (int)$c['id'] ?>"<?= in_array($c['id'], old('centers', $userCenters)) ? ' checked' : '' ?>><label class="form-check-label" for="c<?= (int)$c['id'] ?>"><?= e($c['name']) ?></label></div><?php endforeach; ?></div></div>
  <div class="text-end mt-3"><a href="<?= url('users/index') ?>" class="btn btn-light">Cancel</a> <button class="btn btn-primary">Save user</button></div></div></div></form>
