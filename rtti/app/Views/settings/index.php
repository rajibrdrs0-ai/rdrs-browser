<?php $groups = []; foreach ($fields as $k => $f) { $groups[$f[2]][$k] = $f; } $ro = !can('settings.manage'); ?>
<div class="page-head"><h1><?= e(t('System Settings')) ?></h1></div>
<div class="row g-3"><div class="col-lg-8">
<form method="post" action="<?= url('settings/save') ?>" enctype="multipart/form-data"><?= csrf_field() ?>
<?php foreach ($groups as $g => $items): ?>
  <div class="card mb-3"><div class="card-header"><?= e($g) ?></div><div class="card-body row g-3">
  <?php foreach ($items as $k => $f): $val = $values[$k] ?? ''; ?>
    <div class="<?= $f[1] === 'bool' ? 'col-12' : 'col-md-6' ?>">
    <?php if ($f[1] === 'bool'): ?><div class="form-check form-switch"><input class="form-check-input" type="checkbox" name="<?= e($k) ?>" value="1" id="s_<?= e($k) ?>"<?= $val === '1' ? ' checked' : '' ?><?= $ro ? ' disabled' : '' ?>><label class="form-check-label" for="s_<?= e($k) ?>"><?= e($f[0]) ?></label></div>
    <?php else: ?><label class="form-label"><?= e($f[0]) ?></label>
      <?php if (str_starts_with($f[1], 'select:')): $opts = []; foreach (explode(',', substr($f[1], 7)) as $o) { [$ov, $ol] = explode('=', $o, 2); $opts[$ov] = $ol; } ?>
        <select name="<?= e($k) ?>" class="form-select"<?= $ro ? ' disabled' : '' ?>><?= kv_options($opts, $val, '') ?></select>
      <?php else: ?><input name="<?= e($k) ?>" type="<?= $f[1] === 'number' ? 'number' : 'text' ?>" class="form-control" value="<?= e($val) ?>"<?= $ro ? ' disabled' : '' ?>><?php endif; ?>
      <?php if (!empty($f[3])): ?><div class="form-text"><?= e($f[3]) ?></div><?php endif; ?>
    <?php endif; ?></div>
  <?php endforeach; ?>
  <?php if ($g === 'Institute'): ?><div class="col-md-6"><label class="form-label">Institute logo</label><div class="d-flex gap-2 align-items-center"><img src="<?= e(base_url() . setting('institute_logo', 'assets/img/logo.jpg')) ?>" style="height:44px"><input type="file" name="logo" accept="image/png,image/jpeg" class="form-control"<?= $ro ? ' disabled' : '' ?>></div></div><?php endif; ?>
  </div></div>
<?php endforeach; ?>
<?php if (!$ro): ?><div class="text-end mb-3"><button class="btn btn-primary"><i class="bi bi-save"></i> Save settings</button></div><?php endif; ?>
</form></div>
<div class="col-lg-4">
  <div class="card mb-3"><div class="card-header">System information</div><div class="card-body"><dl class="kv small"><?php foreach ($info as $k => $v): ?><dt><?= e($k) ?></dt><dd><?= e($v) ?></dd><?php endforeach; ?>
    <dt>Financial year</dt><dd><?= e($fy['name'] ?? '—') ?> <a href="<?= url('master/index', ['g' => 'accounts', 't' => 'financial_years']) ?>">change</a></dd></dl></div></div>
  <?php if (can('settings.backup')): ?>
  <div class="card" id="backup"><div class="card-header d-flex">Database backup<form method="post" action="<?= url('settings/backup') ?>" class="ms-auto"><?= csrf_field() ?><button class="btn btn-primary btn-sm"><i class="bi bi-database-down"></i> Backup now</button></form></div>
    <ul class="list-group list-group-flush small">
    <?php foreach ($backups as $b): ?><li class="list-group-item d-flex align-items-center gap-2"><span class="flex-grow-1"><?= e($b['name']) ?><br><span class="text-muted"><?= date('d/m/Y h:i A', $b['time']) ?> · <?= number_format($b['size'] / 1024) ?> KB</span></span>
      <a class="btn btn-sm btn-outline-secondary" href="<?= url('settings/download', ['f' => $b['name']]) ?>"><i class="bi bi-download"></i></a>
      <?php if (\App\Core\Auth::isSuperAdmin()): ?><button class="btn btn-sm btn-outline-danger" onclick="restoreBk(<?= e(json_encode($b['name'])) ?>)" title="Restore"><i class="bi bi-arrow-counterclockwise"></i></button><?php endif; ?></li><?php endforeach; ?>
    <?php if (!$backups): ?><li class="list-group-item text-muted">No backups yet.</li><?php endif; ?></ul>
    <div class="card-body small text-muted">Backups are stored in <code>storage/backups</code>. Copy them to another computer or cloud drive regularly. See the README for scheduled backups.</div></div>
  <form method="post" action="<?= url('settings/restore') ?>" id="restoreForm" class="d-none"><?= csrf_field() ?><input name="f"><input name="confirm"></form>
  <?php ob_start(); ?><script>
  function restoreBk(f){ Swal.fire({title:'Restore database?', html:'All current data will be replaced by <b>'+$('<i>').text(f).html()+'</b>. A safety backup is taken first.<br>Type <b>RESTORE</b> to confirm.', input:'text', icon:'warning', showCancelButton:true, confirmButtonColor:'#c8102e'})
    .then(r => { if (r.isConfirmed) { $('#restoreForm [name=f]').val(f); $('#restoreForm [name=confirm]').val(r.value); loading(true); $('#restoreForm').submit(); } }); }
  </script><?php \App\Core\View::share('scripts', ob_get_clean()); ?>
  <?php endif; ?>
</div></div>
