<?php /** @var string $title */ ?><!doctype html>
<html lang="<?= e(current_locale()) ?>">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e($title ?? __('app.name')) ?> · <?= e(__('app.short')) ?></title>
<link rel="stylesheet" href="<?= e(asset('assets/vendor/bootstrap/bootstrap.min.css')) ?>">
<link rel="stylesheet" href="<?= e(asset('assets/vendor/bootstrap-icons/bootstrap-icons.min.css')) ?>">
<link rel="stylesheet" href="<?= e(asset('assets/css/app.css')) ?>">
</head>
<body class="guest-body">
<main class="guest-wrap">
  <div class="d-flex justify-content-end mb-3"><?= $this->insert('partials/lang_switch') ?></div>
  <?= $this->section('content') ?>
  <p class="text-center text-body-secondary small mt-4 mb-0"><?= e(__('app.tagline')) ?></p>
</main>
<script src="<?= e(asset('assets/vendor/bootstrap/bootstrap.bundle.min.js')) ?>"></script>
</body>
</html>
