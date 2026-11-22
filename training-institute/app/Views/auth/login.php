<?php $this->layout('layouts/guest', ['title' => __('auth.login_title')]) ?>
<div class="card shadow-sm">
  <div class="card-body p-4">
    <div class="text-center mb-4">
      <span class="brand-mark mb-2" style="width:48px;height:48px;font-size:1.4rem">R</span>
      <h1 class="h5 mb-1"><?= e(__('app.name')) ?></h1>
      <p class="text-body-secondary small mb-0"><?= e(__('auth.login_title')) ?></p>
    </div>
    <?= $this->insert('partials/flash') ?>
    <form method="post" action="<?= e(url('/login')) ?>" novalidate>
      <?= csrf_field() ?>
      <div class="mb-3">
        <label for="identifier" class="form-label"><?= e(__('auth.identifier')) ?></label>
        <input type="text" class="form-control" id="identifier" name="identifier" value="<?= e(old('identifier')) ?>"
               autocomplete="username" autofocus required maxlength="150">
      </div>
      <div class="mb-4">
        <label for="password" class="form-label"><?= e(__('auth.password')) ?></label>
        <input type="password" class="form-control" id="password" name="password" autocomplete="current-password" required>
      </div>
      <button type="submit" class="btn btn-primary w-100"><i class="bi bi-box-arrow-in-right me-1"></i><?= e(__('auth.login')) ?></button>
    </form>
  </div>
</div>
