<?php
/**
 * @var string $appName @var string $version @var string $env @var string $phpVersion
 * @var ?string $mysqlVersion @var ?string $dbError @var int $tableCount
 * @var array<string,array{label:array{0:string,1:string},count:int}> $counts
 */
$ok = $dbError === null;
?><!doctype html>
<html lang="bn">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e($appName) ?></title>
<style>
  :root { --bg:#f4f6f9; --card:#fff; --text:#1f2937; --muted:#6b7280; --line:#e5e7eb; --ok:#15803d; --bad:#b91c1c; --accent:#0d6efd; }
  @media (prefers-color-scheme: dark) { :root { --bg:#0f172a; --card:#1e293b; --text:#e5e7eb; --muted:#94a3b8; --line:#334155; --ok:#4ade80; --bad:#f87171; --accent:#60a5fa; } }
  * { box-sizing:border-box; }
  body { margin:0; background:var(--bg); color:var(--text); font-family:"Noto Sans Bengali","Hind Siliguri",system-ui,sans-serif; padding:24px 16px; }
  main { max-width:880px; margin:0 auto; }
  h1 { margin:0; font-size:26px; } .sub { color:var(--muted); margin:4px 0 24px; }
  .card { background:var(--card); border:1px solid var(--line); border-radius:12px; padding:20px; margin-bottom:16px; }
  h2 { font-size:16px; margin:0 0 12px; }
  .status { font-weight:600; color:<?= $ok ? 'var(--ok)' : 'var(--bad)' ?>; }
  dl { display:grid; grid-template-columns:max-content 1fr; gap:6px 16px; margin:0; } dt { color:var(--muted); } dd { margin:0; }
  .grid { display:grid; grid-template-columns:repeat(auto-fill,minmax(150px,1fr)); gap:12px; }
  .stat { border:1px solid var(--line); border-radius:10px; padding:12px; }
  .stat b { display:block; font-size:22px; font-variant-numeric:tabular-nums; } .stat span { color:var(--muted); font-size:13px; }
  code { background:var(--bg); padding:2px 6px; border-radius:6px; }
  a { color:var(--accent); }
</style>
</head>
<body>
<main>
  <h1><?= e($appName) ?></h1>
  <p class="sub">v<?= e($version) ?> · <?= e($env) ?> · Phase 1 &amp; 2 installed / ধাপ ১ ও ২ ইনস্টল সম্পন্ন</p>

  <section class="card">
    <h2>System status / সিস্টেম অবস্থা</h2>
    <p class="status"><?= $ok ? '● Database connected / ডাটাবেস সংযুক্ত' : '● ' . e($dbError) ?></p>
    <dl>
      <dt>PHP</dt><dd><?= e($phpVersion) ?></dd>
      <dt>MySQL</dt><dd><?= e($mysqlVersion ?? '—') ?></dd>
      <dt>Tables / টেবিল</dt><dd><?= e($tableCount) ?></dd>
      <dt>Health JSON</dt><dd><a href="<?= e(url('/health')) ?>"><?= e(url('/health')) ?></a></dd>
    </dl>
  </section>

  <?php if ($counts !== []): ?>
  <section class="card">
    <h2>Seed data / প্রাথমিক তথ্য</h2>
    <div class="grid">
      <?php foreach ($counts as $row): ?>
        <div class="stat"><b><?= e($row['count']) ?></b><span><?= e($row['label'][0]) ?> · <?= e($row['label'][1]) ?></span></div>
      <?php endforeach; ?>
    </div>
  </section>
  <?php endif; ?>

  <section class="card">
    <h2>Next / পরবর্তী ধাপ</h2>
    <p>Login, dashboard and modules arrive in Phases 3–4. Default admin: <code>admin@courier.local</code> / <code>Admin@12345</code>.</p>
  </section>
</main>
</body>
</html>
