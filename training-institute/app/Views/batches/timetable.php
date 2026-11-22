<?php
/** @var array $slots @var array $centers @var ?int $centerId */
use App\Services\Rbac;
$self = Rbac::scope() === 'self';
$this->layout('layouts/app', ['title' => __($self ? 'routine.my_routine' : 'routine.title')]);
?>
<div class="page-head"><h1><?= e(__($self ? 'routine.my_routine' : 'routine.title')) ?></h1></div>
<?php if (!$self && count($centers) > 1): ?>
<form class="card card-body mb-3" method="get">
  <div class="row g-2 align-items-end">
    <div class="col-8 col-md-5"><?= form_select('center_id', __('batches.center'), options($centers, 'name', 'id', 'code'), $centerId ?? '', ['class' => 'form-select form-select-sm', 'placeholder' => __('common.all')]) ?></div>
    <div class="col-4 col-md-2 d-grid"><button class="btn btn-sm btn-outline-primary"><?= e(__('common.filter')) ?></button></div>
  </div>
</form>
<?php endif; ?>
<div class="card"><?= $this->insert('batches/_routine_table', ['slots' => $slots, 'showBatch' => true, 'deleteUrl' => null]) ?></div>
