<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e($title ?? 'Print') ?></title>
<link rel="stylesheet" href="<?= asset('vendor/bootstrap.min.css') ?>">
<link rel="stylesheet" href="<?= asset('css/print.css') ?>?v=1">
</head>
<body class="<?= e($bodyClass ?? '') ?>">
<div class="print-toolbar no-print">
  <button onclick="window.print()" class="btn btn-primary btn-sm">Print / Save as PDF</button>
  <button onclick="history.length > 1 ? history.back() : window.close()" class="btn btn-outline-secondary btn-sm">Back</button>
  <span class="text-muted small ms-2">Tip: choose “Save as PDF” as the printer to download a PDF.</span>
</div>
<?= $content ?>
<?= $scripts ?? '' ?>
<?php if (!empty($_GET['autoprint'])): ?><script>window.addEventListener('load', () => setTimeout(() => window.print(), 400));</script><?php endif; ?>
</body>
</html>
