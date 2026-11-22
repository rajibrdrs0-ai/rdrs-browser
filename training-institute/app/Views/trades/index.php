<?php
/** @var array $trades */
$this->layout('layouts/app', ['title' => __('trades.title')]);
$canManage = can('course.manage');
?>
<div class="page-head"><h1><?= e(__('trades.title')) ?></h1></div>
<div class="row g-3">
  <div class="<?= $canManage ? 'col-lg-8' : 'col-12' ?>"><div class="card"><div class="table-responsive">
    <table class="table mb-0 align-middle">
      <thead class="table-light"><tr><th><?= e(__('trades.code')) ?></th><th><?= e(__('trades.name_bn')) ?></th><th class="d-none d-md-table-cell"><?= e(__('trades.name_en')) ?></th><th class="text-center"><?= e(__('trades.courses')) ?></th><?php if ($canManage): ?><th></th><?php endif; ?></tr></thead>
      <tbody>
      <?php foreach ($trades as $t): ?>
        <tr class="<?= (int) $t['is_active'] === 1 ? '' : 'text-body-secondary' ?>">
          <td><span class="badge text-bg-secondary"><?= e($t['code']) ?></span></td><td><?= e($t['name_bn']) ?></td>
          <td class="d-none d-md-table-cell"><?= e($t['name_en']) ?></td>
          <td class="text-center"><a href="<?= e(url('/courses', ['trade_id' => $t['id']])) ?>"><?= num($t['course_count']) ?></a></td>
          <?php if ($canManage): ?><td class="text-end">
            <button class="btn btn-sm btn-outline-secondary" type="button" data-bs-toggle="collapse" data-bs-target="#trade-<?= (int) $t['id'] ?>"><i class="bi bi-pencil"></i></button>
          </td><?php endif; ?>
        </tr>
        <?php if ($canManage): ?>
        <tr class="collapse" id="trade-<?= (int) $t['id'] ?>"><td colspan="5" class="bg-body-tertiary">
          <form method="post" action="<?= e(url('/trades/' . $t['id'])) ?>" class="row g-2 align-items-end"><?= csrf_field() ?><?= method_field('PUT') ?>
            <div class="col-sm-2"><label class="form-label small"><?= e(__('trades.code')) ?></label><input class="form-control form-control-sm" name="code" value="<?= e($t['code']) ?>"></div>
            <div class="col-sm-4"><label class="form-label small"><?= e(__('trades.name_en')) ?></label><input class="form-control form-control-sm" name="name_en" value="<?= e($t['name_en']) ?>"></div>
            <div class="col-sm-4"><label class="form-label small"><?= e(__('trades.name_bn')) ?></label><input class="form-control form-control-sm" name="name_bn" value="<?= e($t['name_bn']) ?>"></div>
            <div class="col-sm-2"><?= form_check('is_active', __('common.active'), (int) $t['is_active'] === 1, ['id' => 'ta-' . $t['id']]) ?><button class="btn btn-sm btn-primary mt-1"><?= e(__('common.save')) ?></button></div>
          </form>
        </td></tr>
        <?php endif; ?>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div></div></div>
  <?php if ($canManage): ?>
  <div class="col-lg-4"><form class="card card-body" method="post" action="<?= e(url('/trades')) ?>" novalidate><?= csrf_field() ?>
    <h2 class="h6"><?= e(__('trades.add')) ?></h2>
    <div class="mb-2"><?= form_input('code', __('trades.code'), '', ['required' => true, 'maxlength' => 12]) ?></div>
    <div class="mb-2"><?= form_input('name_en', __('trades.name_en'), '', ['required' => true]) ?></div>
    <div class="mb-3"><?= form_input('name_bn', __('trades.name_bn'), '', ['required' => true]) ?></div>
    <button class="btn btn-primary btn-sm"><i class="bi bi-plus-lg me-1"></i><?= e(__('trades.add')) ?></button>
  </form></div>
  <?php endif; ?>
</div>
