<?php
/** @var array $rows @var int $total @var int $page @var int $pages @var array $centers @var array $filters */
$this->layout('layouts/app', ['title' => __('students.title')]);
?>
<div class="page-head">
  <h1><?= e(__('students.title')) ?></h1>
  <?php if (can('student.create')): ?><a class="btn btn-primary btn-sm" href="<?= e(url('/students/create')) ?>"><i class="bi bi-person-plus me-1"></i><?= e(__('students.add')) ?></a><?php endif; ?>
</div>
<form class="card card-body mb-3" method="get">
  <div class="row g-2 align-items-end">
    <div class="col-12 col-md-5"><?= form_input('q', __('common.search'), $filters['q'], ['class' => 'form-control form-control-sm', 'placeholder' => __('students.search_placeholder')]) ?></div>
    <div class="col-6 col-md-3"><?= form_select('center_id', __('students.center'), options($centers, 'name', 'id', 'code'), $filters['center_id'] ?? '', ['class' => 'form-select form-select-sm', 'placeholder' => __('common.all')]) ?></div>
    <div class="col-6 col-md-2"><?= form_select('gender', __('students.gender'), ['male' => __('staff.genders.male'), 'female' => __('staff.genders.female'), 'other' => __('staff.genders.other')], $filters['gender'], ['class' => 'form-select form-select-sm', 'placeholder' => __('common.all')]) ?></div>
    <?php if ($filters['batch_id'] !== null): ?><input type="hidden" name="batch_id" value="<?= (int) $filters['batch_id'] ?>"><?php endif; ?>
    <div class="col-12 col-md-2 d-grid"><button class="btn btn-sm btn-outline-primary"><i class="bi bi-search me-1"></i><?= e(__('common.search')) ?></button></div>
  </div>
</form>
<div class="card"><div class="table-responsive">
  <table class="table table-hover mb-0">
    <thead class="table-light"><tr>
      <th><?= e(__('students.reg_no')) ?></th><th><?= e(__('students.name_en')) ?></th><th class="d-none d-md-table-cell"><?= e(__('students.mobile')) ?></th>
      <th class="d-none d-lg-table-cell"><?= e(__('students.current_batches')) ?></th><th class="d-none d-sm-table-cell"><?= e(__('students.center')) ?></th>
    </tr></thead>
    <tbody>
    <?php foreach ($rows as $s): ?>
      <tr>
        <td class="small text-nowrap"><?= e($s['registration_no']) ?></td>
        <td><div class="d-flex align-items-center gap-2"><?= $this->insert('students/_avatar', ['s' => $s, 'size' => 32]) ?>
          <div><a class="fw-semibold" href="<?= e(url('/students/' . $s['id'])) ?>"><?= e(loc($s, 'name')) ?></a>
            <?php if ($s['status'] !== 'active'): ?><span class="badge text-bg-secondary ms-1"><?= e(__('students.statuses.' . $s['status'])) ?></span><?php endif; ?></div></div></td>
        <td class="d-none d-md-table-cell"><?= e($s['mobile']) ?></td>
        <td class="d-none d-lg-table-cell small"><?= e($s['current_batches'] ?? '—') ?></td>
        <td class="d-none d-sm-table-cell"><span class="badge text-bg-secondary"><?= e($s['center_code']) ?></span></td>
      </tr>
    <?php endforeach; ?>
    <?php if ($rows === []): ?><tr><td colspan="5" class="text-center text-body-secondary py-4"><?= e(__('common.no_records')) ?></td></tr><?php endif; ?>
    </tbody>
  </table></div>
  <?= $this->insert('partials/pagination', ['total' => $total, 'page' => $page, 'pages' => $pages, 'base' => '/students', 'query' => $filters]) ?>
</div>
