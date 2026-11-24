<?php
$canManage = can("$perm.manage");
$listCols = $def['list'] ?? array_keys($def['fields']);
$lk = function ($field, $val) use ($lookups) {
    foreach ($lookups[$field] ?? [] as $r) if ((string)$r['id'] === (string)$val) return $r['name'];
    return '';
};
?>
<div class="page-head"><h1><?= e(t('Master Entry')) ?> · <?= e(t($group['label'])) ?></h1>
  <div class="actions"><?php if ($canManage): ?><button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#mForm" onclick="fillForm({})"><i class="bi bi-plus-lg"></i> <?= e(t('Add New')) ?></button><?php endif; ?></div>
</div>
<?php if (count($group['tables']) > 1): ?>
<ul class="nav nav-tabs mb-3 flex-nowrap overflow-auto">
  <?php foreach ($group['tables'] as $tb): ?>
    <li class="nav-item"><a class="nav-link text-nowrap<?= $tb === $t ? ' active' : '' ?>" href="<?= url('master/index', ['g' => $g, 't' => $tb]) ?>"><?= e($tables[$tb]['title']) ?></a></li>
  <?php endforeach; ?>
</ul>
<?php endif; ?>
<?php if (!empty($def['help'])): ?><div class="alert alert-info py-2 small"><?= e($def['help']) ?></div><?php endif; ?>
<?php if (!empty($def['filter'])): $pf = $def['filter']; ?>
  <form class="filters row g-2 align-items-end" method="get"><input type="hidden" name="r" value="master/index"><input type="hidden" name="g" value="<?= e($g) ?>"><input type="hidden" name="t" value="<?= e($t) ?>">
    <div class="col-md-4"><label class="form-label">Filter by <?= e($def['fields'][$pf][0]) ?></label><select name="parent" class="form-select form-select-sm s2" onchange="this.form.submit()"><?= options($lookups[$pf], $_GET['parent'] ?? '', 'id', 'name', 'All') ?></select></div>
  </form>
<?php endif; ?>
<div class="card"><div class="card-body">
  <div class="table-responsive"><table class="table table-sm table-hover dt">
    <thead><tr><th>#</th><?php foreach ($listCols as $c): ?><th><?= e($def['fields'][$c][0]) ?></th><?php endforeach; ?><?php if ($hasActive): ?><th>Status</th><?php endif; ?><th class="text-end">Actions</th></tr></thead>
    <tbody>
    <?php foreach ($rows as $i => $r): ?>
      <tr>
        <td><?= $i + 1 ?></td>
        <?php foreach ($listCols as $c): $f = $def['fields'][$c]; ?>
          <td><?php
            if ($f[1] === 'select') echo e($lk($c, $r[$c]));
            elseif ($f[1] === 'checkbox') echo $r[$c] ? '<i class="bi bi-check-circle-fill text-success"></i>' : '';
            elseif ($f[1] === 'enum') echo $c === 'color' ? '<span class="badge text-bg-' . e($r[$c]) . '">' . e($r['name'] ?? $r[$c]) . '</span>' : e($f[3][$r[$c]] ?? $r[$c]);
            elseif ($f[1] === 'date') echo e(fmt_date($r[$c]));
            elseif ($f[1] === 'decimal') echo e(number_format((float)$r[$c], 2));
            else echo e(mb_strimwidth((string)$r[$c], 0, 80, '…'));
          ?></td>
        <?php endforeach; ?>
        <?php if ($hasActive): ?><td><?= status_badge($r['is_active'] ? 'active' : 'inactive') ?></td><?php endif; ?>
        <td class="table-actions">
          <?php if ($canManage): ?>
          <button class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#mForm" onclick='fillForm(<?= e(json_encode($r, JSON_HEX_APOS | JSON_HEX_QUOT)) ?>)'><i class="bi bi-pencil"></i></button>
          <?php if ($hasActive): ?>
          <form method="post" action="<?= url('master/toggle', ['g' => $g, 't' => $t]) ?>" class="d-inline"><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int)$r['id'] ?>"><button class="btn btn-sm btn-outline-secondary" title="Activate / deactivate"><i class="bi bi-toggle-<?= $r['is_active'] ? 'on' : 'off' ?>"></i></button></form>
          <?php endif; ?>
          <form method="post" action="<?= url('master/delete', ['g' => $g, 't' => $t]) ?>" class="d-inline" data-confirm="Delete this record?"><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int)$r['id'] ?>"><button class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i></button></form>
          <?php endif; ?>
        </td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table></div>
</div></div>

<?php if ($canManage): ?>
<div class="modal fade" id="mForm" tabindex="-1"><div class="modal-dialog modal-lg"><form class="modal-content" method="post" action="<?= url('master/save', ['g' => $g, 't' => $t]) ?>">
  <?= csrf_field() ?><input type="hidden" name="id" id="f_id">
  <div class="modal-header"><h5 class="modal-title"><?= e($def['title']) ?></h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
  <div class="modal-body"><div class="row g-2">
  <?php foreach ($def['fields'] as $name => $f): [$label, $type] = $f; $req = ($f[2] ?? '') === 'required'; ?>
    <div class="<?= $type === 'textarea' ? 'col-12' : 'col-md-6' ?>">
      <?php if ($type === 'checkbox'): ?>
        <div class="form-check mt-4"><input class="form-check-input" type="checkbox" name="<?= e($name) ?>" value="1" id="f_<?= e($name) ?>"><label class="form-check-label" for="f_<?= e($name) ?>"><?= e($label) ?></label></div>
      <?php else: ?>
        <label class="form-label<?= $req ? ' req' : '' ?>"><?= e($label) ?></label>
        <?php if ($type === 'select'): ?>
          <select name="<?= e($name) ?>" id="f_<?= e($name) ?>" class="form-select s2"<?= $req ? ' required' : '' ?>><?= options($lookups[$name]) ?></select>
        <?php elseif ($type === 'enum'): ?>
          <select name="<?= e($name) ?>" id="f_<?= e($name) ?>" class="form-select"<?= $req ? ' required' : '' ?>><?= kv_options($f[3]) ?></select>
        <?php elseif ($type === 'textarea'): ?>
          <textarea name="<?= e($name) ?>" id="f_<?= e($name) ?>" class="form-control" rows="4"<?= $req ? ' required' : '' ?>></textarea>
        <?php else: ?>
          <input name="<?= e($name) ?>" id="f_<?= e($name) ?>" class="form-control" type="<?= $type === 'number' || $type === 'decimal' ? 'number' : ($type === 'date' ? 'date' : ($type === 'email' ? 'email' : 'text')) ?>"<?= $type === 'decimal' ? ' step="0.01"' : '' ?><?= $req ? ' required' : '' ?>>
        <?php endif; ?>
      <?php endif; ?>
    </div>
  <?php endforeach; ?>
  </div></div>
  <div class="modal-footer"><button type="button" class="btn btn-light" data-bs-dismiss="modal"><?= e(t('Cancel')) ?></button><button class="btn btn-primary"><?= e(t('Save')) ?></button></div>
</form></div></div>
<?php ob_start(); ?>
<script>
const FIELDS = <?= json_encode(array_map(fn($f) => $f[1], $def['fields'])) ?>;
const LOCKED = <?= json_encode(!empty($def['locked_code'])) ?>;
function fillForm(r) {
  document.getElementById('f_id').value = r.id || '';
  for (const [k, type] of Object.entries(FIELDS)) {
    const el = document.getElementById('f_' + k);
    if (!el) continue;
    if (type === 'checkbox') el.checked = r.id ? (String(r[k]) === '1') : (k === 'allows_enrollment' || k === 'is_pass');
    else { el.value = r[k] ?? ''; if (el.classList.contains('s2')) $(el).trigger('change.select2'); }
    if (k === 'code' && LOCKED) el.readOnly = !!r.id;
  }
}
</script>
<?php \App\Core\View::share('scripts', ob_get_clean()); endif; ?>
