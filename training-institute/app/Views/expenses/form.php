<?php
/** @var array $centers @var ?int $centerId @var array $accounts @var array $categories */
$this->layout('layouts/app', ['title' => __('expenses.add')]);
$acc = [];
foreach ($accounts as $a) { $acc[$a['id']] = $a['name'] . ' — ' . __('finance.balance') . ' ' . money($a['balance']); }
?>
<div class="page-head"><h1><?= e(__('expenses.add')) ?></h1>
  <a class="btn btn-sm btn-outline-secondary" href="<?= e(url('/expenses')) ?>"><i class="bi bi-arrow-left me-1"></i><?= e(__('common.back')) ?></a></div>
<form method="post" action="<?= e(url('/expenses')) ?>" enctype="multipart/form-data" class="card card-body" novalidate><?= csrf_field() ?>
  <div class="row g-3">
    <div class="col-md-6"><?= form_select('center_id', __('expenses.center'), options($centers, 'name', 'id', 'code'), $centerId ?? '', ['required' => true, 'data-reload-param' => 'center_id']) ?></div>
    <?php if ($centerId === null): ?><div class="col-12"><div class="alert alert-info mb-0"><?= e(__('expenses.pick_center')) ?></div></div><?php else: ?>
    <div class="col-md-6"><?= form_select('account_id', __('expenses.account'), $acc, $accounts[0]['id'] ?? '', ['required' => true, 'placeholder' => false]) ?></div>
    <div class="col-md-4"><?= form_select('category_id', __('expenses.category'), options($categories), '', ['required' => true]) ?></div>
    <div class="col-6 col-md-4"><?= form_input('expense_date', __('expenses.date'), date('Y-m-d'), ['type' => 'date', 'required' => true, 'max' => date('Y-m-d')]) ?></div>
    <div class="col-6 col-md-4"><?= form_input('amount', __('expenses.amount'), '', ['required' => true, 'inputmode' => 'decimal']) ?></div>
    <div class="col-md-4"><?= form_input('payee', __('expenses.payee'), '', ['maxlength' => 120]) ?></div>
    <div class="col-md-8"><?= form_input('narration', __('expenses.narration'), '', ['required' => true, 'maxlength' => 255]) ?></div>
    <div class="col-md-6"><?= form_input('attachment', __('expenses.attachment'), '', ['type' => 'file', 'accept' => 'application/pdf,image/jpeg,image/png']) ?></div>
    <div class="col-12"><button class="btn btn-primary"><i class="bi bi-check2 me-1"></i><?= e(__('common.save')) ?></button></div>
    <?php endif; ?>
  </div>
</form>
