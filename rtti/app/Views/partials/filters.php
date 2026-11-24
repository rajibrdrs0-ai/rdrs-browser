<?php
/**
 * Generic filter bar. Options:
 *  $f = ['center' => true, 'district' => true, 'dates' => true, 'q' => 'placeholder', 'extra' => html, 'date_label' => '']
 */
use App\Core\Auth;
use App\Core\DB;
$f = $f ?? [];
$centers = Auth::centerOptions(false);
$districtIds = array_unique(array_column($centers, 'district_id'));
$districts = $districtIds ? DB::all('SELECT id, name FROM districts WHERE id IN (' . DB::inInts($districtIds) . ') ORDER BY name') : [];
?>
<form class="filters row g-2 align-items-end" method="get">
  <input type="hidden" name="r" value="<?= e($_GET['r'] ?? '') ?>">
  <?php foreach (($f['hidden'] ?? []) as $hk => $hv): ?><input type="hidden" name="<?= e($hk) ?>" value="<?= e($hv) ?>"><?php endforeach; ?>
  <?php if (!empty($f['q'])): ?>
  <div class="col-12 col-md-3"><label class="form-label"><?= e(t('Search')) ?></label>
    <input type="search" name="q" class="form-control form-control-sm" value="<?= e($_GET['q'] ?? '') ?>" placeholder="<?= e($f['q']) ?>"></div>
  <?php endif; ?>
  <?php if (!empty($f['district']) && count($districts) > 1): ?>
  <div class="col-6 col-md-2"><label class="form-label"><?= e(t('District')) ?></label>
    <select name="district_id" class="form-select form-select-sm"><?= options($districts, $_GET['district_id'] ?? '', 'id', 'name', t('All Districts')) ?></select></div>
  <?php endif; ?>
  <?php if (!empty($f['center']) && count($centers) > 1): ?>
  <div class="col-6 col-md-2"><label class="form-label"><?= e(t('Center')) ?></label>
    <select name="center_id" class="form-select form-select-sm"><?= options($centers, $_GET['center_id'] ?? '', 'id', 'name', t('All Centers')) ?></select></div>
  <?php endif; ?>
  <?php if (!empty($f['dates'])): ?>
  <div class="col-6 col-md-2"><label class="form-label"><?= e(($f['date_label'] ?? '') . ' ' . t('From')) ?></label>
    <input type="date" name="from" class="form-control form-control-sm" value="<?= e($_GET['from'] ?? ($f['default_from'] ?? '')) ?>"></div>
  <div class="col-6 col-md-2"><label class="form-label"><?= e(t('To')) ?></label>
    <input type="date" name="to" class="form-control form-control-sm" value="<?= e($_GET['to'] ?? ($f['default_to'] ?? '')) ?>"></div>
  <?php endif; ?>
  <?= $f['extra'] ?? '' ?>
  <div class="col-auto">
    <button class="btn btn-primary btn-sm"><i class="bi bi-funnel"></i> <?= e(t('Filter')) ?></button>
    <a class="btn btn-light btn-sm" href="<?= e(url($_GET['r'] ?? '', $f['hidden'] ?? [])) ?>"><?= e(t('Reset')) ?></a>
  </div>
</form>
