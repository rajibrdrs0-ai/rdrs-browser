<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e(($title ?? 'Sign in') . ' · ' . setting('institute_short', 'RTTI')) ?></title>
<link rel="icon" href="<?= logo_url() ?>">
<link rel="stylesheet" href="<?= asset('vendor/bootstrap.min.css') ?>">
<link rel="stylesheet" href="<?= asset('vendor/bootstrap-icons.min.css') ?>">
<link rel="stylesheet" href="<?= asset('css/app.css') ?>?v=1">
</head>
<body class="guest">
<?php foreach (get_flashes() as $f): ?>
  <div class="container mt-3" style="max-width:560px"><div class="alert alert-<?= e($f['type']) ?>"><?= e($f['message']) ?></div></div>
<?php endforeach; ?>
<?= $content ?>
<?= $scripts ?? '' ?>
</body>
</html>
