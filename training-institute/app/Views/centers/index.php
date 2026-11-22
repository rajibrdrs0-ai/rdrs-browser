<?php
/** @var array $rows @var int $total @var int $page @var int $pages @var array $districts @var array $filters */
$this->layout('layouts/app', ['title' => __('centers.title')]);
?>
<div class="page-head">
  <h1><?= e(__('centers.title')) ?></h1>
  <?php if (can('center.manage')): ?><a class="btn btn-primary btn-sm" href="<?= e(url('/centers/create')) ?>"><i class="bi bi-plus-lg me-1"></i><?= e(__('centers.add')) ?></a><?php endif; ?>
</div>
<form class="card card-body mb-3" method="get">
  <div class="row g-2 align-items-end">
    <div class="col-12 col-md-5"><?= form_input('q', __('common.search'), $filters['q'], ['class' => 'form-control form-control-sm']) ?></div>
    <div class="col-8 col-md-4"><?= form_select('district_id', __('centers.district'), options($districts), $filters['district_id'], ['class' => 'form-select form-select-sm', 'placeholder' => __('common.all')]) ?></div>
    <div class="col-4 col-md-3 d-grid"><button class="btn btn-sm btn-outline-primary"><i class="bi bi-funnel me-1"></i><?= e(__('common.filter')) ?></button></div>
  </div>
</form>
<div class="card">
  <div class="table-responsive">
    <table class="table table-hover mb-0">
      <thead class="table-light"><tr>
        <th><?= e(__('centers.code')) ?></th><th><?= e(__('centers.name_bn')) ?></th><th class="d-none d-md-table-cell"><?= e(__('centers.district')) ?></th>
        <th class="text-center"><?= e(__('centers.active_batches')) ?></th><th class="text-center d-none d-sm-table-cell"><?= e(__('centers.staff')) ?></th>
        <th class="text-center d-none d-sm-table-cell"><?= e(__('centers.students')) ?></th><th><?= e(__('common.status')) ?></th>
      </tr></thead>
      <tbody>
      <?php foreach ($rows as $c): ?>
        <tr>
          <td><span class="badge text-bg-secondary"><?= e($c['code']) ?></span></td>
          <td><a class="fw-semibold" href="<?= e(url('/centers/' . $c['id'])) ?>"><?= e(loc($c, 'name')) ?></a>
            <?php if ($c['center_type'] === 'head_office'): ?><span class="badge text-bg-light border ms-1"><?= e(__('centers.head_office')) ?></span><?php endif; ?></td>
          <td class="d-none d-md-table-cell"><?= e(current_locale() === 'bn' ? $c['district_bn'] : $c['district_en']) ?><?php if ($c['upazila_en'] !== null): ?>, <span class="text-body-secondary"><?= e(current_locale() === 'bn' ? $c['upazila_bn'] : $c['upazila_en']) ?></span><?php endif; ?></td>
          <td class="text-center"><?= num($c['active_batches']) ?></td>
          <td class="text-center d-none d-sm-table-cell"><?= num($c['staff_count']) ?></td>
          <td class="text-center d-none d-sm-table-cell"><?= num($c['student_count']) ?></td>
          <td><span class="badge text-bg-<?= (int) $c['is_active'] === 1 ? 'success' : 'secondary' ?>"><?= e(__((int) $c['is_active'] === 1 ? 'common.active' : 'common.inactive')) ?></span></td>
        </tr>
      <?php endforeach; ?>
      <?php if ($rows === []): ?><tr><td colspan="7" class="text-center text-body-secondary py-4"><?= e(__('common.no_records')) ?></td></tr><?php endif; ?>
      </tbody>
    </table>
  </div>
  <?= $this->insert('partials/pagination', ['total' => $total, 'page' => $page, 'pages' => $pages, 'base' => '/centers', 'query' => $filters]) ?>
</div>
