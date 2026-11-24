<div class="page-head"><h1><?= e(t('Data Import')) ?></h1></div>
<ul class="nav nav-tabs mb-3"><?php foreach ($types as $k => $d): ?><li class="nav-item"><a class="nav-link<?= $k === $t ? ' active' : '' ?>" href="<?= url('import/index', ['type' => $k]) ?>"><?= e($d[0]) ?></a></li><?php endforeach; ?></ul>
<?php $d = $types[$t]; ?>
<div class="row g-3"><div class="col-lg-6"><div class="card"><div class="card-header">1. Download template</div><div class="card-body small">
  <p>Columns (<b>*</b> = required):</p><p><?php foreach ($d[2] as $c): ?><code class="me-1"><?= e($c) ?></code><?php endforeach; ?></p>
  <ul class="mb-3"><li>Dates: <code>YYYY-MM-DD</code> or <code>DD/MM/YYYY</code>.</li><li><code>center_code</code> must be one of your centers (e.g. <?= e(implode(', ', array_column(\App\Core\Auth::centerOptions(), 'code'))) ?>).</li>
    <li>Names of gender, district, education, designation, category must match Master Entry values.</li><li>Maximum 2000 rows per file.</li></ul>
  <a class="btn btn-outline-primary btn-sm" href="<?= url('import/template', ['type' => $t]) ?>"><i class="bi bi-download"></i> Download <?= e($d[0]) ?> template (CSV – opens in Excel)</a></div></div></div>
<div class="col-lg-6"><div class="card"><div class="card-header">2. Upload filled file</div><div class="card-body">
  <form method="post" action="<?= url('import/preview', ['type' => $t]) ?>" enctype="multipart/form-data"><?= csrf_field() ?>
    <input type="file" name="file" class="form-control mb-2" accept=".csv<?= $xlsx ? ',.xlsx' : '' ?>" required>
    <div class="form-text mb-3">Accepted: <?= $xlsx ? 'Excel (.xlsx) or ' : '' ?>CSV. Nothing is saved until you confirm on the preview screen.</div>
    <button class="btn btn-primary"><i class="bi bi-eye"></i> Validate & preview</button></form></div></div></div></div>
