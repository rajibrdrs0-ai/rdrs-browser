<?php
/** @var string $q @var array $results @var array $today @var float $month @var array $dues */
$this->layout('layouts/app', ['title' => __('fees.title')]);
?>
<div class="page-head">
  <h1><?= e(__('fees.title')) ?></h1>
  <div class="d-flex gap-2">
    <a class="btn btn-sm btn-outline-secondary" href="<?= e(url('/fees/dues')) ?>"><i class="bi bi-hourglass-split me-1"></i><?= e(__('fees.dues')) ?></a>
    <a class="btn btn-sm btn-outline-secondary" href="<?= e(url('/fees/receipts')) ?>"><i class="bi bi-receipt me-1"></i><?= e(__('fees.receipts')) ?></a>
  </div>
</div>
<div class="row g-3 mb-3">
  <div class="col-6 col-xl-3"><div class="card stat-card h-100"><div class="card-body"><div class="stat-value text-success"><?= money($today['total']) ?></div><div class="small text-body-secondary"><?= e(__('fees.today')) ?> · <?= num($today['receipts']) ?></div></div></div></div>
  <div class="col-6 col-xl-3"><div class="card stat-card h-100"><div class="card-body"><div class="stat-value"><?= money($month) ?></div><div class="small text-body-secondary"><?= e(__('fees.month')) ?></div></div></div></div>
  <div class="col-6 col-xl-3"><div class="card stat-card h-100"><div class="card-body"><div class="stat-value"><?= money($dues['due']) ?></div><div class="small text-body-secondary"><?= e(__('fees.outstanding')) ?></div></div></div></div>
  <div class="col-6 col-xl-3"><a class="text-decoration-none" href="<?= e(url('/fees/dues', ['overdue' => 1])) ?>"><div class="card stat-card h-100"><div class="card-body"><div class="stat-value text-danger"><?= money($dues['overdue']) ?></div><div class="small text-body-secondary"><?= e(__('fees.overdue_count', ['n' => num($dues['overdue_count'] ?? 0)])) ?></div></div></div></a></div>
</div>
<form class="card card-body mb-3" method="get" role="search">
  <label class="form-label fw-semibold" for="q"><?= e(__('fees.search')) ?></label>
  <div class="input-group"><input class="form-control" id="q" name="q" value="<?= e($q) ?>" placeholder="<?= e(__('fees.search_placeholder')) ?>" autofocus>
    <button class="btn btn-primary"><i class="bi bi-search"></i></button></div>
</form>
<?php if ($q !== ''): ?>
<div class="card"><div class="table-responsive"><table class="table table-hover mb-0 align-middle">
  <thead class="table-light"><tr><th><?= e(__('students.name_en')) ?></th><th><?= e(__('batches.code')) ?></th><th class="text-end"><?= e(__('admission.paid')) ?></th><th class="text-end"><?= e(__('admission.due')) ?></th><th></th></tr></thead>
  <tbody>
  <?php foreach ($results as $r): ?>
    <tr><td><div class="d-flex align-items-center gap-2"><?= $this->insert('students/_avatar', ['s' => ['id' => $r['student_id'], 'photo_path' => $r['photo_path']], 'size' => 32]) ?>
        <div><a href="<?= e(url('/enrollments/' . $r['id'])) ?>"><?= e(loc($r, 'name')) ?></a><div class="small text-body-secondary"><?= e($r['registration_no']) ?> · <?= e($r['mobile']) ?></div></div></div></td>
      <td><?= e($r['batch_code']) ?><?php if (!in_array($r['status'], ['admitted', 'running'], true)): ?> <span class="badge text-bg-secondary"><?= e(__('admission.status.' . $r['status'])) ?></span><?php endif; ?></td>
      <td class="text-end"><?= money($r['paid']) ?></td>
      <td class="text-end <?= (float) $r['overdue'] > 0 ? 'text-danger fw-semibold' : '' ?>"><?= money($r['due']) ?></td>
      <td class="text-end"><?php if ((float) $r['due'] > 0 && can('fee.collect')): ?><a class="btn btn-sm btn-primary" href="<?= e(url('/fees/collect/' . $r['id'])) ?>"><i class="bi bi-cash me-1"></i><?= e(__('fees.collect')) ?></a><?php endif; ?></td></tr>
  <?php endforeach; ?>
  <?php if ($results === []): ?><tr><td colspan="5" class="text-center text-body-secondary py-4"><?= e(__('fees.no_results')) ?></td></tr><?php endif; ?>
  </tbody></table></div></div>
<?php endif; ?>
