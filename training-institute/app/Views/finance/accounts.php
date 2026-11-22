<?php
/** @var array $accounts @var array $centers */
$this->layout('layouts/app', ['title' => __('finance.accounts')]);
$types = ['cash', 'mobile_wallet', 'bank'];
?>
<div class="page-head"><h1><?= e(__('finance.accounts')) ?></h1></div>
<div class="row g-3">
  <div class="<?= can('finance.accounts_manage') ? 'col-lg-8' : 'col-12' ?>"><div class="card"><div class="table-responsive"><table class="table mb-0 align-middle">
    <thead class="table-light"><tr><th><?= e(__('expenses.center')) ?></th><th><?= e(__('finance.account_name')) ?></th><th class="text-end d-none d-md-table-cell"><?= e(__('finance.in')) ?></th><th class="text-end d-none d-md-table-cell"><?= e(__('finance.out')) ?></th><th class="text-end"><?= e(__('finance.balance')) ?></th></tr></thead>
    <tbody>
    <?php foreach ($accounts as $a): ?>
      <tr><td><span class="badge text-bg-secondary"><?= e($a['center_code']) ?></span></td>
        <td><?php if (can('finance.cashbook')): ?><a href="<?= e(url('/finance/cashbook', ['account_id' => $a['id']])) ?>"><?= e($a['name']) ?></a><?php else: ?><?= e($a['name']) ?><?php endif; ?>
          <div class="small text-body-secondary"><?= e(__('finance.types.' . $a['account_type'])) ?><?= $a['account_number'] ? ' · ' . e($a['bank_name'] ?? '') . ' ' . e($a['account_number']) : '' ?></div></td>
        <td class="text-end d-none d-md-table-cell"><?= money($a['total_in']) ?></td><td class="text-end d-none d-md-table-cell"><?= money($a['total_out']) ?></td>
        <td class="text-end fw-semibold"><?= money($a['balance']) ?></td></tr>
    <?php endforeach; ?>
    </tbody></table></div></div></div>
  <?php if (can('finance.accounts_manage')): ?>
  <div class="col-lg-4"><form method="post" action="<?= e(url('/finance/accounts')) ?>" class="card card-body" novalidate><?= csrf_field() ?>
    <h2 class="h6"><?= e(__('finance.add_account')) ?></h2>
    <div class="mb-2"><?= form_select('center_id', __('expenses.center'), options($centers, 'name', 'id', 'code'), count($centers) === 1 ? $centers[0]['id'] : '', ['required' => true]) ?></div>
    <div class="mb-2"><?= form_select('account_type', __('finance.type'), array_combine($types, array_map(static fn($t) => __('finance.types.' . $t), $types)), 'bank', ['placeholder' => false]) ?></div>
    <div class="mb-2"><?= form_input('name', __('finance.account_name'), '', ['required' => true, 'maxlength' => 120]) ?></div>
    <div class="mb-2"><?= form_input('bank_name', __('finance.bank_name'), '', ['maxlength' => 120]) ?></div>
    <div class="mb-2"><?= form_input('account_number', __('finance.account_number'), '', ['maxlength' => 40]) ?></div>
    <div class="mb-3"><?= form_input('opening_balance', __('finance.opening'), '0', ['inputmode' => 'decimal']) ?></div>
    <button class="btn btn-primary btn-sm"><i class="bi bi-plus-lg me-1"></i><?= e(__('finance.add_account')) ?></button>
  </form></div>
  <?php endif; ?>
</div>
