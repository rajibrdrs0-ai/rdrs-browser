<?php /** @var bool $forced */ $this->layout('layouts/app', ['title' => __('auth.change_password')]) ?>
<div class="page-head"><h1><?= e(__('auth.change_password')) ?></h1></div>
<div class="row"><div class="col-lg-6">
  <?php if ($forced): ?><div class="alert alert-warning"><i class="bi bi-shield-exclamation me-1"></i><?= e(__('auth.must_change')) ?></div><?php endif; ?>
  <div class="card"><div class="card-body">
    <form method="post" action="<?= e(url('/password')) ?>">
      <?= csrf_field() ?>
      <div class="mb-3">
        <label class="form-label" for="current_password"><?= e(__('auth.current_password')) ?></label>
        <input type="password" class="form-control" id="current_password" name="current_password" autocomplete="current-password" required>
      </div>
      <div class="mb-3">
        <label class="form-label" for="new_password"><?= e(__('auth.new_password')) ?></label>
        <input type="password" class="form-control" id="new_password" name="new_password" autocomplete="new-password" required>
        <div class="form-text"><?= e(__('auth.password_rules')) ?></div>
      </div>
      <div class="mb-4">
        <label class="form-label" for="new_password_confirmation"><?= e(__('auth.confirm_password')) ?></label>
        <input type="password" class="form-control" id="new_password_confirmation" name="new_password_confirmation" autocomplete="new-password" required>
      </div>
      <button class="btn btn-primary"><?= e(__('common.save')) ?></button>
    </form>
  </div></div>
</div></div>
