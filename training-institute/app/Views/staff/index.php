<?php
/** @var array $rows @var int $total @var int $page @var int $pages @var array $centers @var array $designations @var array $filters */
$this->layout('layouts/app', ['title' => __('staff.title')]);
$statuses = ['active', 'on_leave', 'resigned', 'terminated'];
?>
<div class="page-head">
  <h1><?= e(__('staff.title')) ?></h1>
  <?php if (can('staff.manage')): ?><a class="btn btn-primary btn-sm" href="<?= e(url('/staff/create')) ?>"><i class="bi bi-person-plus me-1"></i><?= e(__('staff.add')) ?></a><?php endif; ?>
</div>
<form class="card card-body mb-3" method="get">
  <div class="row g-2 align-items-end">
    <div class="col-12 col-md-3"><?= form_input('q', __('common.search'), $filters['q'], ['class' => 'form-control form-control-sm']) ?></div>
    <div class="col-6 col-md-3"><?= form_select('center_id', __('staff.center'), options($centers, 'name', 'id', 'code'), $filters['center_id'], ['class' => 'form-select form-select-sm', 'placeholder' => __('common.all')]) ?></div>
    <div class="col-6 col-md-2"><?= form_select('designation_id', __('staff.designation'), options($designations), $filters['designation_id'], ['class' => 'form-select form-select-sm', 'placeholder' => __('common.all')]) ?></div>
    <div class="col-6 col-md-2"><?= form_select('status', __('staff.status'), array_combine($statuses, array_map(static fn($s) => __('staff.statuses.' . $s), $statuses)) + ['all' => __('common.all')], $filters['status'], ['class' => 'form-select form-select-sm', 'placeholder' => false]) ?></div>
    <div class="col-6 col-md-2 d-flex gap-2 align-items-center">
      <div class="form-check mb-0"><input class="form-check-input" type="checkbox" name="trainers" value="1" id="trainers" <?= $filters['trainers'] === '1' ? 'checked' : '' ?>><label class="form-check-label small" for="trainers"><?= e(__('staff.trainers_only')) ?></label></div>
      <button class="btn btn-sm btn-outline-primary ms-auto"><i class="bi bi-funnel"></i></button>
    </div>
  </div>
</form>
<div class="card"><div class="table-responsive">
  <table class="table table-hover mb-0">
    <thead class="table-light"><tr>
      <th><?= e(__('staff.code')) ?></th><th><?= e(__('staff.name')) ?></th><th><?= e(__('staff.designation')) ?></th>
      <th class="d-none d-md-table-cell"><?= e(__('staff.center')) ?></th><th class="d-none d-lg-table-cell"><?= e(__('staff.mobile')) ?></th>
      <th class="d-none d-lg-table-cell"><?= e(__('staff.login')) ?></th><th><?= e(__('staff.status')) ?></th><th></th>
    </tr></thead>
    <tbody>
    <?php foreach ($rows as $s): ?>
      <tr>
        <td class="small"><?= e($s['employee_code']) ?></td>
        <td class="fw-semibold"><?= e(loc($s, 'name')) ?><?php if ((int) $s['is_trainer'] === 1): ?> <i class="bi bi-easel text-success" title="<?= e(__('staff.trainer')) ?>"></i><?php endif; ?></td>
        <td><?= e(current_locale() === 'bn' ? $s['designation_bn'] : $s['designation_en']) ?></td>
        <td class="d-none d-md-table-cell"><span class="badge text-bg-secondary"><?= e($s['center_code']) ?></span></td>
        <td class="d-none d-lg-table-cell"><?= e($s['mobile']) ?></td>
        <td class="d-none d-lg-table-cell small"><?= $s['login_username'] !== null ? '@' . e($s['login_username']) : '<span class="text-body-secondary">' . e(__('staff.no_login')) . '</span>' ?></td>
        <td><span class="badge text-bg-<?= $s['status'] === 'active' ? 'success' : 'secondary' ?>"><?= e(__('staff.statuses.' . $s['status'])) ?></span></td>
        <td class="text-end"><?php if (can('staff.manage')): ?><a class="btn btn-sm btn-outline-secondary" href="<?= e(url('/staff/' . $s['id'] . '/edit')) ?>"><i class="bi bi-pencil"></i></a><?php endif; ?></td>
      </tr>
    <?php endforeach; ?>
    <?php if ($rows === []): ?><tr><td colspan="8" class="text-center text-body-secondary py-4"><?= e(__('common.no_records')) ?></td></tr><?php endif; ?>
    </tbody>
  </table></div>
  <?= $this->insert('partials/pagination', ['total' => $total, 'page' => $page, 'pages' => $pages, 'base' => '/staff', 'query' => $filters]) ?>
</div>
