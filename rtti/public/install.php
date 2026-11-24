<?php
/**
 * Web installer. Disabled automatically once installation is complete (storage/installed.lock).
 */
declare(strict_types=1);
require dirname(__DIR__) . '/app/Core/bootstrap.php';

$locked = is_file(ROOT_PATH . '/storage/installed.lock') || is_file(ROOT_PATH . '/config/config.php');
$errors = [];
$log = [];
$checks = [
    'PHP 8.1 or newer' => version_compare(PHP_VERSION, '8.1.0', '>='),
    'PDO MySQL extension' => extension_loaded('pdo_mysql'),
    'mbstring extension' => extension_loaded('mbstring'),
    'fileinfo extension' => extension_loaded('fileinfo'),
    'config/ folder writable' => is_writable(ROOT_PATH . '/config'),
    'storage/ folder writable' => is_writable(ROOT_PATH . '/storage') && is_writable(ROOT_PATH . '/storage/uploads'),
];
if (!$locked && ($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    csrf_check();
    $p = fn($k, $d = '') => trim((string)($_POST[$k] ?? $d));
    if (strlen($p('admin_pass')) < 8 || !preg_match('/[A-Za-z]/', $p('admin_pass')) || !preg_match('/\d/', $p('admin_pass'))) {
        $errors[] = 'Administrator password must be at least 8 characters with letters and numbers.';
    }
    if ($p('admin_pass') !== $p('admin_pass2')) {
        $errors[] = 'Administrator passwords do not match.';
    }
    if (!preg_match('/^[A-Za-z0-9_.]{3,60}$/', $p('admin_user'))) {
        $errors[] = 'Username: 3-60 letters, numbers, dot or underscore.';
    }
    if (in_array(false, $checks, true)) {
        $errors[] = 'Fix the failed requirement checks first.';
    }
    if (!$errors) {
        try {
            $log = \App\Services\Installer::install(
                ['host' => $p('db_host', '127.0.0.1'), 'port' => (int)$p('db_port', '3306'), 'name' => $p('db_name'), 'user' => $p('db_user'), 'pass' => (string)($_POST['db_pass'] ?? '')],
                ['admin_name' => $p('admin_name'), 'admin_user' => $p('admin_user'), 'admin_pass' => $p('admin_pass'), 'admin_email' => $p('admin_email'),
                 'ho_name' => $p('ho_name'), 'ho_district' => $p('ho_district'), 'ho_address' => $p('ho_address'), 'demo' => !empty($_POST['demo']),
                 'mysqldump' => $p('mysqldump')]
            );
            $locked = true;
        } catch (Throwable $e) {
            $errors[] = $e->getMessage();
        }
    }
}
$v = fn($k, $d = '') => e($_POST[$k] ?? $d);
$xampp = stripos(PHP_OS, 'WIN') === 0 ? 'C:\\xampp\\mysql\\bin\\mysqldump.exe' : 'mysqldump';
?><!doctype html>
<html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title>Install RTTI</title>
<link rel="stylesheet" href="assets/vendor/bootstrap.min.css"><link rel="stylesheet" href="assets/css/app.css">
</head><body class="guest">
<div class="verify-card" style="max-width:760px">
  <div class="text-center"><img src="assets/img/logo.jpg" style="width:72px"><h4 class="mt-2">RTTI – Training Institute Management System</h4><p class="text-muted">Installation</p></div>
  <?php if ($log): ?>
    <div class="alert alert-success"><b>Installation completed successfully.</b></div>
    <ul class="small"><?php foreach ($log as $l): ?><li><?= e($l) ?></li><?php endforeach; ?></ul>
    <div class="alert alert-warning small">For security, delete <code>public/install.php</code> from the server now. The installer is locked by <code>storage/installed.lock</code>.</div>
    <a class="btn btn-primary" href="index.php">Go to sign in</a>
  <?php elseif ($locked): ?>
    <div class="alert alert-info">The application is already installed. To reinstall, remove <code>config/config.php</code> and <code>storage/installed.lock</code> and use an empty database.</div>
    <a class="btn btn-primary" href="index.php">Go to sign in</a>
  <?php else: ?>
    <h6>Requirements</h6>
    <ul class="list-unstyled small mb-3"><?php foreach ($checks as $k => $ok): ?><li><?= $ok ? '✅' : '❌' ?> <?= e($k) ?></li><?php endforeach; ?></ul>
    <?php foreach ($errors as $er): ?><div class="alert alert-danger py-2"><?= e($er) ?></div><?php endforeach; ?>
    <form method="post"><?= csrf_field() ?>
      <h6 class="mt-2">1. MySQL database</h6>
      <div class="row g-2">
        <div class="col-md-6"><label class="form-label">Host</label><input name="db_host" class="form-control" value="<?= $v('db_host', '127.0.0.1') ?>" required></div>
        <div class="col-md-2"><label class="form-label">Port</label><input name="db_port" class="form-control" value="<?= $v('db_port', '3306') ?>" required></div>
        <div class="col-md-4"><label class="form-label">Database name</label><input name="db_name" class="form-control" value="<?= $v('db_name', 'rtti_training_management') ?>" required></div>
        <div class="col-md-6"><label class="form-label">DB user</label><input name="db_user" class="form-control" value="<?= $v('db_user', 'root') ?>" required></div>
        <div class="col-md-6"><label class="form-label">DB password</label><input type="password" name="db_pass" class="form-control"></div>
        <div class="col-12"><label class="form-label">mysqldump path (for backups)</label><input name="mysqldump" class="form-control" value="<?= $v('mysqldump', $xampp) ?>"></div>
      </div>
      <h6 class="mt-3">2. Head Office</h6>
      <div class="row g-2">
        <div class="col-md-6"><label class="form-label">Head office name</label><input name="ho_name" class="form-control" value="<?= $v('ho_name', 'RTTI Head Office') ?>" required></div>
        <div class="col-md-6"><label class="form-label">District</label><input name="ho_district" class="form-control" value="<?= $v('ho_district', 'Rangpur') ?>"></div>
        <div class="col-12"><label class="form-label">Address</label><input name="ho_address" class="form-control" value="<?= $v('ho_address', 'Jail Road, Rangpur') ?>"></div>
      </div>
      <h6 class="mt-3">3. Super administrator</h6>
      <div class="row g-2">
        <div class="col-md-6"><label class="form-label">Full name</label><input name="admin_name" class="form-control" value="<?= $v('admin_name', 'System Administrator') ?>" required></div>
        <div class="col-md-6"><label class="form-label">Email</label><input type="email" name="admin_email" class="form-control" value="<?= $v('admin_email') ?>"></div>
        <div class="col-md-4"><label class="form-label">Username</label><input name="admin_user" class="form-control" value="<?= $v('admin_user', 'admin') ?>" required></div>
        <div class="col-md-4"><label class="form-label">Password</label><input type="password" name="admin_pass" class="form-control" required minlength="8"></div>
        <div class="col-md-4"><label class="form-label">Confirm</label><input type="password" name="admin_pass2" class="form-control" required></div>
      </div>
      <div class="form-check mt-3"><input class="form-check-input" type="checkbox" name="demo" value="1" id="demo"><label class="form-check-label" for="demo">Load sample data (4 centers, students, batches, payments, certificates) – for training/testing only</label></div>
      <button class="btn btn-primary mt-3 w-100">Install</button>
    </form>
  <?php endif; ?>
</div>
</body></html>
