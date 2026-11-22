<?php
/** @var array $rows @var int $total @var int $page @var int $pages @var array $trades @var array $filters */
use App\Controllers\CourseController;
$this->layout('layouts/app', ['title' => __('courses.title')]);
$types = array_combine(CourseController::TYPES, array_map(static fn($t) => __('courses.types.' . $t), CourseController::TYPES));
?>
<div class="page-head">
  <h1><?= e(__('courses.title')) ?></h1>
  <?php if (can('course.manage')): ?><a class="btn btn-primary btn-sm" href="<?= e(url('/courses/create')) ?>"><i class="bi bi-plus-lg me-1"></i><?= e(__('courses.add')) ?></a><?php endif; ?>
</div>
<form class="card card-body mb-3" method="get">
  <div class="row g-2 align-items-end">
    <div class="col-12 col-md-4"><?= form_input('q', __('common.search'), $filters['q'], ['class' => 'form-control form-control-sm']) ?></div>
    <div class="col-6 col-md-3"><?= form_select('trade_id', __('courses.trade'), options($trades), $filters['trade_id'] ?? '', ['class' => 'form-select form-select-sm', 'placeholder' => __('common.all')]) ?></div>
    <div class="col-6 col-md-2"><?= form_select('course_type', __('courses.type'), $types, $filters['course_type'], ['class' => 'form-select form-select-sm', 'placeholder' => __('common.all')]) ?></div>
    <div class="col-6 col-md-2"><?= form_select('active', __('common.status'), ['1' => __('common.active'), '0' => __('common.inactive'), 'all' => __('common.all')], $filters['active'], ['class' => 'form-select form-select-sm', 'placeholder' => false]) ?></div>
    <div class="col-6 col-md-1 d-grid"><button class="btn btn-sm btn-outline-primary"><i class="bi bi-funnel"></i></button></div>
  </div>
</form>
<div class="card"><div class="table-responsive">
  <table class="table table-hover mb-0">
    <thead class="table-light"><tr>
      <th><?= e(__('courses.code')) ?></th><th><?= e(__('courses.title')) ?></th><th class="d-none d-lg-table-cell"><?= e(__('courses.trade')) ?></th>
      <th class="d-none d-md-table-cell"><?= e(__('courses.type')) ?></th><th class="text-center"><?= e(__('courses.duration_months')) ?></th>
      <th class="text-center d-none d-sm-table-cell"><?= e(__('courses.module_count')) ?></th><th class="text-center"><?= e(__('courses.active_batches')) ?></th>
    </tr></thead>
    <tbody>
    <?php foreach ($rows as $c): ?>
      <tr class="<?= (int) $c['is_active'] === 1 ? '' : 'text-body-secondary' ?>">
        <td><span class="badge text-bg-secondary"><?= e($c['code']) ?></span></td>
        <td><a class="fw-semibold" href="<?= e(url('/courses/' . $c['id'])) ?>"><?= e(loc($c, 'name')) ?></a>
          <div class="small text-body-secondary"><?= num($c['total_hours']) ?> h<?= $c['nsqf_level'] !== null ? ' · NSQF ' . num($c['nsqf_level']) : '' ?></div></td>
        <td class="d-none d-lg-table-cell"><?= e(loc($c, 'trade')) ?></td>
        <td class="d-none d-md-table-cell"><?= e(__('courses.types.' . $c['course_type'])) ?></td>
        <td class="text-center"><?= num($c['duration_months'], fmod((float) $c['duration_months'], 1.0) > 0 ? 1 : 0) ?></td>
        <td class="text-center d-none d-sm-table-cell"><?= num($c['module_count']) ?></td>
        <td class="text-center"><?= num($c['active_batches']) ?></td>
      </tr>
    <?php endforeach; ?>
    <?php if ($rows === []): ?><tr><td colspan="7" class="text-center text-body-secondary py-4"><?= e(__('common.no_records')) ?></td></tr><?php endif; ?>
    </tbody>
  </table></div>
  <?= $this->insert('partials/pagination', ['total' => $total, 'page' => $page, 'pages' => $pages, 'base' => '/courses', 'query' => $filters]) ?>
</div>
