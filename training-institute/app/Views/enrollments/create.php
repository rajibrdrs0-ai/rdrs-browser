<?php
/** @var array $student @var array $batches @var ?array $batch @var ?array $preview @var array $problems @var bool $residential @var int $installments */
$this->layout('layouts/app', ['title' => __('admission.new')]);
$batchOptions = [];
foreach ($batches as $b) {
    $batchOptions[$b['id']] = $b['batch_code'] . ' — ' . loc($b, 'course') . ' (' . $b['enrolled'] . '/' . $b['capacity'] . ')';
}
$query = ['student_id' => $student['id']];
?>
<div class="page-head">
  <div class="d-flex align-items-center gap-3"><?= $this->insert('students/_avatar', ['s' => $student, 'size' => 48]) ?>
    <div><h1><?= e(__('admission.new')) ?></h1><div class="small text-body-secondary"><a href="<?= e(url('/students/' . $student['id'])) ?>"><?= e(loc($student, 'name')) ?></a> · <?= e($student['registration_no']) ?></div></div></div>
  <a class="btn btn-sm btn-outline-secondary" href="<?= e(url('/students/' . $student['id'])) ?>"><i class="bi bi-arrow-left me-1"></i><?= e(__('common.back')) ?></a>
</div>

<?php if ($batches === []): ?>
  <div class="alert alert-warning"><?= e(__('admission.no_open_batches')) ?></div>
<?php else: ?>
<div class="row g-3">
  <div class="col-lg-6">
    <form method="get" action="<?= e(url('/enrollments/create')) ?>" class="card card-body" id="admission-calc">
      <input type="hidden" name="student_id" value="<?= (int) $student['id'] ?>">
      <div class="mb-3"><?= form_select('batch_id', __('admission.batch'), $batchOptions, $batch['id'] ?? '', ['required' => true, 'placeholder' => __('admission.choose_batch'), 'data-autosubmit' => true]) ?></div>
      <div class="row g-2 align-items-end">
        <div class="col-6"><?= form_select('installments', __('admission.installments'), array_combine(range(1, 6), array_map('num', range(1, 6))), $installments, ['placeholder' => false, 'data-autosubmit' => true]) ?></div>
        <div class="col-6"><div class="form-check mb-2"><input class="form-check-input" type="checkbox" name="residential" value="1" id="residential_calc" data-autosubmit-check <?= $residential ? 'checked' : '' ?>>
          <label class="form-check-label" for="residential_calc"><?= e(__('admission.residential')) ?></label></div></div>
      </div>
      <noscript><button class="btn btn-sm btn-outline-primary mt-2"><?= e(__('admission.calculate')) ?></button></noscript>
    </form>
  </div>

  <?php if ($batch !== null && $preview !== null): ?>
  <div class="col-lg-6">
    <div class="card mb-3"><div class="card-header bg-body fw-semibold"><?= e(__('admission.eligibility')) ?></div><div class="card-body">
      <?php if ($problems === []): ?><div class="text-success"><i class="bi bi-check-circle-fill me-1"></i><?= e(__('admission.eligible')) ?></div>
      <?php else: ?><ul class="text-danger mb-0 ps-3"><?php foreach ($problems as $p): ?><li><?= e($p) ?></li><?php endforeach; ?></ul><?php endif; ?>
    </div></div>
    <div class="card mb-3"><div class="card-header bg-body fw-semibold"><?= e(__('admission.fee_preview')) ?></div>
      <table class="table table-sm mb-0">
        <?php foreach ($preview['fees'] as $f): $charged = (int) $f['is_optional'] === 0 || ($f['code'] === 'RESIDENTIAL' && $residential); ?>
          <tr class="<?= $charged ? '' : 'text-body-secondary text-decoration-line-through' ?>"><td class="ps-3"><?= e(loc($f, 'name')) ?></td><td class="text-end pe-3"><?= money($f['amount']) ?></td></tr>
        <?php endforeach; ?>
        <tr class="fw-semibold"><td class="ps-3"><?= e(__('admission.total')) ?></td><td class="text-end pe-3"><?= money($preview['gross']) ?></td></tr>
      </table>
      <div class="card-body border-top small">
        <div class="fw-semibold mb-1"><?= e(__('admission.installment_plan')) ?></div>
        <?php foreach ($preview['installments'] as $i => $amt): ?>
          <div class="d-flex justify-content-between"><span><?= e(__('admission.installment', ['n' => num($i + 1)])) ?></span><span><?= money($amt) ?></span></div>
        <?php endforeach; ?>
      </div>
    </div>
    <?php if ($problems === []): ?>
    <form method="post" action="<?= e(url('/enrollments')) ?>" class="card card-body" novalidate><?= csrf_field() ?>
      <input type="hidden" name="student_id" value="<?= (int) $student['id'] ?>"><input type="hidden" name="batch_id" value="<?= (int) $batch['id'] ?>">
      <input type="hidden" name="installments" value="<?= (int) $installments ?>"><input type="hidden" name="is_residential" value="<?= $residential ? 1 : 0 ?>">
      <div class="row g-2">
        <div class="col-sm-5"><?= form_input('admission_date', __('admission.admission_date'), date('Y-m-d'), ['type' => 'date', 'required' => true, 'max' => date('Y-m-d')]) ?></div>
        <div class="col-sm-7"><?= form_input('sponsor', __('admission.sponsor'), '', ['maxlength' => 120]) ?></div>
      </div>
      <button class="btn btn-primary mt-3"><i class="bi bi-check2-circle me-1"></i><?= e(__('admission.admit')) ?></button>
    </form>
    <?php endif; ?>
  </div>
  <?php endif; ?>
</div>
<?php endif; ?>
