<div class="auth-card">
  <img class="logo" src="<?= logo_url() ?>" alt="RDRS">
  <h4 class="text-center mb-0" style="color:#1a4f9c"><?= e(setting('institute_name')) ?></h4>
  <p class="text-center text-muted small mb-4">Training Institute Management System</p>
  <form method="post" action="<?= url('auth/dologin') ?>" autocomplete="off">
    <?= csrf_field() ?>
    <div class="mb-3"><label class="form-label">Username or email</label>
      <div class="input-group"><span class="input-group-text"><i class="bi bi-person"></i></span><input name="username" class="form-control" required autofocus></div></div>
    <div class="mb-3"><label class="form-label">Password</label>
      <div class="input-group"><span class="input-group-text"><i class="bi bi-lock"></i></span><input type="password" name="password" class="form-control" required></div></div>
    <button class="btn btn-primary w-100 py-2"><i class="bi bi-box-arrow-in-right"></i> Sign in</button>
  </form>
  <hr>
  <a class="d-block text-center small" href="<?= url('verify/index') ?>"><i class="bi bi-qr-code-scan"></i> Verify a certificate</a>
</div>
<p class="text-center text-white-50 small">© <?= date('Y') ?> <?= e(setting('organization_name', 'RDRS Bangladesh')) ?></p>
