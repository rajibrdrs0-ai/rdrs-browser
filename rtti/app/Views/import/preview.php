<?php $ok = array_filter($result, fn($r) => !$r['errors']); $bad = array_filter($result, fn($r) => $r['errors']); $cols = array_map(fn($c) => rtrim($c, '*'), $def[2]); ?>
<div class="page-head"><h1>Import preview · <?= e($def[0]) ?></h1></div>
<div class="row g-3 mb-3">
  <div class="col-md-3"><div class="card stat"><span class="ico ico-blue"><i class="bi bi-list-ol"></i></span><span><span class="v d-block"><?= count($result) ?></span><span class="l">Rows in file</span></span></div></div>
  <div class="col-md-3"><div class="card stat"><span class="ico ico-green"><i class="bi bi-check2"></i></span><span><span class="v d-block"><?= count($ok) ?></span><span class="l">Valid – will be imported</span></span></div></div>
  <div class="col-md-3"><div class="card stat"><span class="ico ico-red"><i class="bi bi-x"></i></span><span><span class="v d-block"><?= count($bad) ?></span><span class="l">With errors – skipped</span></span></div></div>
  <div class="col-md-3 d-flex align-items-center gap-2">
    <?php if ($ok): ?><form method="post" action="<?= url('import/commit') ?>" data-confirm="Import <?= count($ok) ?> valid row(s)?"><?= csrf_field() ?><input type="hidden" name="token" value="<?= e($token) ?>"><button class="btn btn-success"><i class="bi bi-database-add"></i> Import <?= count($ok) ?> rows</button></form><?php endif; ?>
    <?php if ($bad): ?><button class="btn btn-outline-danger btn-sm" onclick="dlErrors()"><i class="bi bi-download"></i> Error report</button><?php endif; ?>
  </div></div>
<div class="card"><div class="table-responsive" style="max-height:65vh"><table class="table table-sm table-bordered mb-0 small" id="pv">
  <thead class="sticky-top"><tr><th>Row</th><th>Result</th><?php foreach ($cols as $c): ?><th><?= e($c) ?></th><?php endforeach; ?></tr></thead>
  <tbody><?php foreach ($result as $r): ?><tr class="<?= $r['errors'] ? 'table-danger' : '' ?>"><td><?= (int)$r['row'] ?></td><td class="err"><?= $r['errors'] ? e(implode('; ', $r['errors'])) : '<span class="text-success">OK</span>' ?></td>
    <?php foreach ($cols as $c): ?><td><?= e($r['raw'][$c] ?? '') ?></td><?php endforeach; ?></tr><?php endforeach; ?></tbody></table></div></div>
<?php ob_start(); ?><script>
function dlErrors(){ let csv = '﻿Row,Errors\n'; $('#pv tbody tr.table-danger').each(function(){ csv += $(this).children().eq(0).text() + ',"' + $(this).find('.err').text().replace(/"/g,'""') + '"\n'; });
  const a = document.createElement('a'); a.href = URL.createObjectURL(new Blob([csv], {type: 'text/csv'})); a.download = 'import_errors.csv'; a.click(); }
</script><?php \App\Core\View::share('scripts', ob_get_clean()); ?>
