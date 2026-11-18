<?php
/** @var int $status @var string $title @var string $message @var ?string $trace */
?><!doctype html>
<html lang="bn">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e($status) ?> · <?= e($title) ?></title>
<style>
  :root { color-scheme: light dark; }
  body { margin:0; min-height:100vh; display:flex; align-items:center; justify-content:center;
         font-family: "Hind Siliguri", "Noto Sans Bengali", system-ui, sans-serif; background:#f4f6f9; color:#1f2937; padding:16px; }
  .card { max-width:560px; width:100%; background:#fff; border-radius:12px; padding:32px; box-shadow:0 4px 24px rgba(0,0,0,.08); }
  h1 { margin:0 0 4px; font-size:48px; color:#0d6efd; } h2 { margin:0 0 16px; font-size:20px; }
  p { line-height:1.6; } a { color:#0d6efd; }
  pre { overflow:auto; font-size:12px; background:#111827; color:#e5e7eb; padding:12px; border-radius:8px; max-height:320px; }
  @media (prefers-color-scheme: dark) { body { background:#0f172a; color:#e5e7eb; } .card { background:#1e293b; } }
</style>
</head>
<body>
<div class="card">
  <h1><?= e($status) ?></h1>
  <h2><?= e($title) ?></h2>
  <p><?= e($message) ?></p>
  <?php if (!empty($trace)): ?><pre><?= e($trace) ?></pre><?php endif; ?>
  <p><a href="<?= e(function_exists('url') ? url('/') : '/') ?>">← Home / হোম</a></p>
</div>
</body>
</html>
