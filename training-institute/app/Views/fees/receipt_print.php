<?php /** @var array $p @var array $items @var array $institute */ ?><!doctype html>
<html lang="<?= e(current_locale()) ?>">
<head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e($p['receipt_no']) ?></title>
<link rel="stylesheet" href="<?= e(asset('assets/vendor/bootstrap/bootstrap.min.css')) ?>">
<link rel="stylesheet" href="<?= e(asset('assets/css/app.css')) ?>">
<link rel="stylesheet" href="<?= e(asset('assets/css/print.css')) ?>">
</head>
<body class="print-body">
  <div class="print-actions no-print"><button class="btn btn-primary btn-sm" data-print>🖨 <?= e(__('fees.print')) ?></button></div>
  <?= (new \App\Core\View())->insert('fees/_receipt_body', ['p' => $p, 'items' => $items, 'institute' => $institute, 'copy' => __('fees.copy_trainee')]) ?>
  <div class="cut-line"></div>
  <?= (new \App\Core\View())->insert('fees/_receipt_body', ['p' => $p, 'items' => $items, 'institute' => $institute, 'copy' => __('fees.copy_office')]) ?>
  <script src="<?= e(asset('assets/js/print.js')) ?>"></script>
</body>
</html>
