<?php
/** @var array $districts @var ?int $districtId @var array $upazilas */
$this->layout('layouts/app', ['title' => __('geo.title')]);
?>
<div class="page-head"><h1><?= e(__('geo.title')) ?></h1></div>
<form class="card card-body mb-3" method="get">
  <div class="row g-2 align-items-end">
    <div class="col-8 col-md-5"><?= form_select('district_id', __('centers.district'), options($districts), $districtId ?? '', ['class' => 'form-select form-select-sm']) ?></div>
    <div class="col-4 col-md-2 d-grid"><button class="btn btn-sm btn-outline-primary"><?= e(__('common.filter')) ?></button></div>
  </div>
</form>
<?php if ($districtId === null): ?>
  <p class="text-body-secondary"><?= e(__('geo.pick_district')) ?></p>
<?php else: ?>
<div class="row g-3">
  <div class="col-lg-7"><div class="card"><ul class="list-group list-group-flush">
    <?php foreach ($upazilas as $u): ?><li class="list-group-item d-flex justify-content-between"><span><?= e($u['name_bn']) ?></span><span class="text-body-secondary"><?= e($u['name_en']) ?></span></li><?php endforeach; ?>
  </ul></div></div>
  <div class="col-lg-5"><form class="card card-body" method="post" action="<?= e(url('/geo/upazilas')) ?>" novalidate><?= csrf_field() ?>
    <input type="hidden" name="district_id" value="<?= (int) $districtId ?>">
    <h2 class="h6"><?= e(__('geo.add')) ?></h2>
    <div class="mb-2"><?= form_input('name_en', __('centers.name_en'), '', ['required' => true]) ?></div>
    <div class="mb-3"><?= form_input('name_bn', __('centers.name_bn'), '', ['required' => true]) ?></div>
    <button class="btn btn-primary btn-sm"><i class="bi bi-plus-lg me-1"></i><?= e(__('geo.add')) ?></button>
  </form></div>
</div>
<?php endif; ?>
