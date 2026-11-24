<div class="page-head"><h1><?= e(t('My Profile')) ?></h1></div>
<div class="row g-3">
  <div class="col-lg-5">
    <div class="card"><div class="card-body">
      <dl class="kv">
        <dt>Name</dt><dd><?= e($u['name']) ?></dd>
        <dt>Username</dt><dd><?= e($u['username']) ?></dd>
        <dt>Email</dt><dd><?= e($u['email']) ?></dd>
        <dt>Mobile</dt><dd><?= e($u['mobile']) ?></dd>
        <dt>Roles</dt><dd><?= e(implode(', ', array_column($roles, 'name'))) ?></dd>
        <dt>Centers</dt><dd><?= e(implode(', ', array_column($centers, 'name'))) ?></dd>
        <dt>Last login</dt><dd><?= e(fmt_datetime($u['last_login_at'])) ?></dd>
      </dl>
    </div></div>
    <div class="card mt-3"><div class="card-header">Recent sign-ins</div>
      <table class="table table-sm mb-0"><tbody>
      <?php foreach ($logins as $l): ?><tr><td><?= e(fmt_datetime($l['created_at'])) ?></td><td><?= e($l['ip_address']) ?></td><td><?= $l['success'] ? status_badge('success', 'success') : status_badge('failed', 'danger') ?></td></tr><?php endforeach; ?>
      </tbody></table></div>
  </div>
  <div class="col-lg-5">
    <div class="card"><div class="card-header">Change password</div><div class="card-body">
      <?php if ((int)$u['must_change_password'] === 1): ?><div class="alert alert-warning small">You must set a new password before using the system.</div><?php endif; ?>
      <form method="post" action="<?= url('auth/profile') ?>">
        <?= csrf_field() ?>
        <div class="mb-2"><label class="form-label req">Current password</label><input type="password" name="current_password" class="form-control" required></div>
        <div class="mb-2"><label class="form-label req">New password</label><input type="password" name="new_password" class="form-control" minlength="8" required><div class="form-text">At least 8 characters with letters and numbers.</div></div>
        <div class="mb-3"><label class="form-label req">Confirm new password</label><input type="password" name="confirm_password" class="form-control" required></div>
        <button class="btn btn-primary"><?= e(t('Save')) ?></button>
      </form>
    </div></div>
  </div>
</div>
