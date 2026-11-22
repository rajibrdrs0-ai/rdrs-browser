<?php
/** @var array $rows @var array $fromAccounts @var array $toAccounts */
$this->layout('layouts/app', ['title' => __('finance.transfers')]);
$from = []; foreach ($fromAccounts as $a) { $from[$a['id']] = $a['center_code'] . ' — ' . $a['name'] . ' (' . money($a['balance']) . ')'; }
$to = []; foreach ($toAccounts as $a) { $to[$a['id']] = $a['center_code'] . ' — ' . $a['name']; }
$badge = ['pending' => 'warning', 'completed' => 'success', 'cancelled' => 'secondary'];
$me = auth()['id'];
?>
<div class="page-head"><h1><?= e(__('finance.transfers')) ?></h1></div>
<div class="row g-3">
  <div class="col-lg-4"><form method="post" action="<?= e(url('/finance/transfers')) ?>" class="card card-body" novalidate><?= csrf_field() ?>
    <h2 class="h6"><?= e(__('finance.new_transfer')) ?></h2><p class="small text-body-secondary"><?= e(__('finance.transfer_help')) ?></p>
    <div class="mb-2"><?= form_select('from_account_id', __('finance.from_account'), $from, '', ['required' => true]) ?></div>
    <div class="mb-2"><?= form_select('to_account_id', __('finance.to_account'), $to, '', ['required' => true]) ?></div>
    <div class="row g-2 mb-2"><div class="col-6"><?= form_input('amount', __('fees.amount'), '', ['required' => true, 'inputmode' => 'decimal']) ?></div>
      <div class="col-6"><?= form_input('transfer_date', __('finance.transfer_date'), date('Y-m-d'), ['type' => 'date', 'required' => true, 'max' => date('Y-m-d')]) ?></div></div>
    <div class="mb-2"><?= form_input('reference', __('finance.reference'), '', ['maxlength' => 80]) ?></div>
    <div class="mb-3"><?= form_input('narration', __('finance.narration'), '', ['maxlength' => 255]) ?></div>
    <button class="btn btn-primary btn-sm"><i class="bi bi-arrow-left-right me-1"></i><?= e(__('common.save')) ?></button>
  </form></div>
  <div class="col-lg-8"><div class="card"><div class="table-responsive"><table class="table table-sm mb-0 align-middle">
    <thead class="table-light"><tr><th class="ps-3">#</th><th><?= e(__('finance.from_account')) ?> → <?= e(__('finance.to_account')) ?></th><th class="text-end"><?= e(__('fees.amount')) ?></th><th><?= e(__('common.status')) ?></th><th class="pe-3"></th></tr></thead>
    <tbody>
    <?php foreach ($rows as $t): ?>
      <tr><td class="ps-3 small"><?= e($t['transfer_no']) ?><div class="text-body-secondary"><?= e(fmt_date($t['transfer_date'])) ?></div></td>
        <td class="small"><?= e($t['from_center'] . ' ' . $t['from_name']) ?> → <?= e($t['to_center'] . ' ' . $t['to_name']) ?><div class="text-body-secondary"><?= e($t['reference'] ?? '') ?> · <?= e(__('finance.creator')) ?>: <?= e($t['creator']) ?><?= $t['confirmer'] ? ' · ' . e(__('finance.confirmer')) . ': ' . e($t['confirmer']) : '' ?></div></td>
        <td class="text-end fw-semibold"><?= money($t['amount']) ?></td>
        <td><span class="badge text-bg-<?= $badge[$t['status']] ?>"><?= e(__('finance.transfer_status.' . $t['status'])) ?></span></td>
        <td class="pe-3 text-nowrap"><?php if ($t['status'] === 'pending'): ?>
          <form method="post" action="<?= e(url('/finance/transfers/' . $t['id'] . '/decide')) ?>" class="d-inline" data-confirm="<?= e(__('common.confirm')) ?>"><?= csrf_field() ?>
            <?php if ((int) $t['can_confirm_scope'] === 1 && (int) $t['created_by'] !== (int) $me): ?><button class="btn btn-sm btn-success" name="decision" value="confirm"><?= e(__('finance.confirm')) ?></button><?php endif; ?>
            <?php if ((int) $t['created_by'] === (int) $me): ?><button class="btn btn-sm btn-outline-secondary" name="decision" value="cancel"><?= e(__('finance.cancel')) ?></button><?php endif; ?>
          </form><?php endif; ?></td></tr>
    <?php endforeach; ?>
    <?php if ($rows === []): ?><tr><td colspan="5" class="ps-3 text-body-secondary"><?= e(__('common.no_records')) ?></td></tr><?php endif; ?>
    </tbody></table></div></div></div>
</div>
