<?php
/** @var array $rows @var int $total @var int $page @var int $pages @var array $centers @var array $courses @var array $filters */
use App\Controllers\BatchController;
$this->layout('layouts/app', ['title' => __('batches.title')]);
$statusOptions = ['' => __('batches.active_only')] + array_combine(BatchController::STATUSES, array_map(static fn($s) => __('batches.status.' . $s), BatchController::STATUSES)) + ['all' => __('common.all')];
?>
<div class="page-head">
  <h1><?= e(__('batches.title')) ?></h1>
  <div class="d-flex gap-2">
    <a class="btn btn-sm btn-outline-secondary" href="<?= e(url('/routine')) ?>"><i class="bi bi-calendar3-week me-1"></i><?= e(__('routine.all')) ?></a>
    <?php if (can('batch.manage')): ?><a class="btn btn-primary btn-sm" href="<?= e(url('/batches/create')) ?>"><i class="bi bi-plus-lg me-1"></i><?= e(__('batches.add')) ?></a><?php endif; ?>
  </div>
</div>
<form class="card card-body mb-3" method="get">
  <div class="row g-2 align-items-end">
    <div class="col-12 col-md-3"><?= form_input('q', __('common.search'), $filters['q'], ['class' => 'form-control form-control-sm']) ?></div>
    <div class="col-6 col-md-3"><?= form_select('center_id', __('batches.center'), options($centers, 'name', 'id', 'code'), $filters['center_id'], ['class' => 'form-select form-select-sm', 'placeholder' => __('common.all')]) ?></div>
    <div class="col-6 col-md-3"><?= form_select('course_id', __('batches.course'), options($courses, 'name', 'id', 'code'), $filters['course_id'], ['class' => 'form-select form-select-sm', 'placeholder' => __('common.all')]) ?></div>
    <div class="col-8 col-md-2"><?= form_select('status', __('batches.status_label'), $statusOptions, $filters['status'], ['class' => 'form-select form-select-sm', 'placeholder' => false]) ?></div>
    <div class="col-4 col-md-1 d-grid"><button class="btn btn-sm btn-outline-primary"><i class="bi bi-funnel"></i></button></div>
  </div>
</form>
<div class="card"><div class="table-responsive">
  <table class="table table-hover mb-0">
    <thead class="table-light"><tr>
      <th><?= e(__('batches.code')) ?></th><th><?= e(__('batches.course')) ?></th><th class="d-none d-md-table-cell"><?= e(__('batches.start_date')) ?></th>
      <th class="d-none d-lg-table-cell"><?= e(__('batches.lead_trainer')) ?></th><th class="text-center"><?= e(__('batches.enrolled')) ?></th><th><?= e(__('batches.status_label')) ?></th>
    </tr></thead>
    <tbody>
    <?php foreach ($rows as $b): ?>
      <tr>
        <td><a class="fw-semibold" href="<?= e(url('/batches/' . $b['id'])) ?>"><?= e($b['batch_code']) ?></a><div class="small text-body-secondary"><?= e($b['name']) ?> · <?= e(__('batches.shifts.' . $b['shift'])) ?></div></td>
        <td><?= e(loc($b, 'course')) ?><div class="small text-body-secondary"><?= e($b['center_code']) ?></div></td>
        <td class="d-none d-md-table-cell small"><?= e(fmt_date($b['start_date'])) ?><br><span class="text-body-secondary"><?= e(fmt_date($b['end_date'])) ?></span></td>
        <td class="d-none d-lg-table-cell"><?= e($b['trainer_name'] !== null ? loc(['trainer_name_en' => $b['trainer_name'], 'trainer_name_bn' => $b['trainer_name_bn']], 'trainer_name') : '—') ?></td>
        <td class="text-center"><?= num($b['enrolled']) ?>/<?= num($b['capacity']) ?></td>
        <td><?= $this->insert('batches/_status', ['status' => $b['status']]) ?></td>
      </tr>
    <?php endforeach; ?>
    <?php if ($rows === []): ?><tr><td colspan="6" class="text-center text-body-secondary py-4"><?= e(__('common.no_records')) ?></td></tr><?php endif; ?>
    </tbody>
  </table></div>
  <?= $this->insert('partials/pagination', ['total' => $total, 'page' => $page, 'pages' => $pages, 'base' => '/batches', 'query' => $filters]) ?>
</div>
