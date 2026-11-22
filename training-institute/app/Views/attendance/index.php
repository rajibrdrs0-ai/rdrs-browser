<?php
/** @var array $batches */
$this->layout('layouts/app', ['title' => __('attendance.title')]);
?>
<div class="page-head"><h1><?= e(__('attendance.title')) ?></h1></div>
<?php if ($batches === []): ?><div class="alert alert-info"><?= e(__('attendance.no_batches')) ?></div><?php else: ?>
<div class="row g-3">
  <?php foreach ($batches as $b): ?>
  <div class="col-md-6 col-xl-4"><div class="card h-100">
    <div class="card-body">
      <div class="d-flex justify-content-between align-items-start mb-2">
        <div><a class="fw-semibold" href="<?= e(url('/batches/' . $b['id'])) ?>"><?= e($b['batch_code']) ?></a><div class="small text-body-secondary"><?= e(loc($b, 'course')) ?></div></div>
        <span class="badge text-bg-<?= (int) $b['today'] > 0 ? 'success' : 'warning' ?>"><?= e(__('attendance.today')) ?>: <?= e(__((int) $b['today'] > 0 ? 'attendance.taken' : 'attendance.not_taken')) ?></span>
      </div>
      <dl class="row small mb-0">
        <dt class="col-7"><?= e(__('batches.enrolled')) ?></dt><dd class="col-5 text-end"><?= num($b['active']) ?></dd>
        <dt class="col-7"><?= e(__('attendance.sessions')) ?></dt><dd class="col-5 text-end"><?= num($b['sessions']) ?></dd>
        <dt class="col-7"><?= e(__('attendance.last')) ?></dt><dd class="col-5 text-end"><?= e(fmt_date($b['last_session'])) ?></dd>
        <dt class="col-7"><?= e(__('attendance.avg')) ?></dt><dd class="col-5 text-end"><?= $b['avg_pct'] !== null ? num($b['avg_pct'], 1) . '%' : '—' ?></dd>
        <dt class="col-7"><?= e(__('attendance.below')) ?> (<?= num($b['min_attendance_percent'], 0) ?>%)</dt><dd class="col-5 text-end <?= (int) $b['below'] > 0 ? 'text-danger fw-semibold' : '' ?>"><?= num($b['below']) ?></dd>
      </dl>
    </div>
    <div class="card-footer bg-body d-flex gap-2">
      <?php if (can('attendance.take')): ?><a class="btn btn-sm btn-primary" href="<?= e(url('/batches/' . $b['id'] . '/attendance/take')) ?>"><i class="bi bi-check2-square me-1"></i><?= e(__('attendance.take')) ?></a><?php endif; ?>
      <a class="btn btn-sm btn-outline-secondary" href="<?= e(url('/batches/' . $b['id'] . '/attendance')) ?>"><i class="bi bi-table me-1"></i><?= e(__('attendance.register')) ?></a>
    </div>
  </div></div>
  <?php endforeach; ?>
</div>
<?php endif; ?>
