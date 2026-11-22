<?php
/** @var array $waivers @var array $refunds @var string $status @var float $limit */
use App\Services\Finance;
$this->layout('layouts/app', ['title' => __('waivers.title')]);
$badge = ['pending' => 'warning', 'approved' => 'success', 'rejected' => 'secondary', 'paid' => 'dark'];
$me = auth()['id'];
?>
<div class="page-head"><h1><?= e(__('waivers.title')) ?></h1>
  <div class="btn-group btn-group-sm"><?php foreach (['pending', 'approved', 'rejected', 'all'] as $s): ?>
    <a class="btn <?= $status === $s ? 'btn-primary' : 'btn-outline-primary' ?>" href="<?= e(url('/waivers', ['status' => $s])) ?>"><?= e($s === 'all' ? __('common.all') : __('waivers.status.' . $s)) ?></a><?php endforeach; ?></div></div>
<p class="small text-body-secondary"><i class="bi bi-info-circle me-1"></i><?= e(__('waivers.limit_note', ['limit' => num($limit, 0)])) ?></p>

<h2 class="h6 mt-3"><?= e(__('waivers.waivers')) ?></h2>
<div class="card mb-4"><div class="table-responsive"><table class="table table-sm mb-0 align-middle">
  <thead class="table-light"><tr><th class="ps-3"><?= e(__('students.name_en')) ?></th><th><?= e(__('waivers.type')) ?></th><th class="text-end"><?= e(__('waivers.amount')) ?></th>
    <th><?= e(__('waivers.reason')) ?></th><th><?= e(__('waivers.requester')) ?></th><th><?= e(__('common.status')) ?></th><th class="pe-3"></th></tr></thead>
  <tbody>
  <?php foreach ($waivers as $w): $pct = (float) $w['gross_fee'] > 0 ? 100 * ((float) $w['approved_total'] + ($w['status'] === 'pending' ? (float) $w['amount'] : 0)) / (float) $w['gross_fee'] : 0; ?>
    <tr><td class="ps-3"><a href="<?= e(url('/enrollments/' . $w['enrollment_id'])) ?>"><?= e(loc($w, 'name')) ?></a><div class="small text-body-secondary"><?= e($w['batch_code']) ?></div></td>
      <td><?= e(__('waivers.types.' . $w['waiver_type'])) ?></td>
      <td class="text-end"><?= money($w['amount']) ?><div class="small <?= $pct > $limit ? 'text-danger' : 'text-body-secondary' ?>"><?= e(__('waivers.share', ['pct' => num($pct, 0)])) ?></div></td>
      <td class="small"><?= e($w['reason']) ?><?= $w['decision_note'] ? '<div class="text-body-secondary">→ ' . e($w['decision_note']) . '</div>' : '' ?></td>
      <td class="small"><?= e($w['requester']) ?><?= $w['approver'] ? '<div class="text-body-secondary">' . e($w['approver']) . '</div>' : '' ?></td>
      <td><span class="badge text-bg-<?= $badge[$w['status']] ?>"><?= e(__('waivers.status.' . $w['status'])) ?></span></td>
      <td class="pe-3"><?php if ($w['status'] === 'pending' && can('waiver.approve') && (int) $w['requested_by'] !== (int) $me): ?>
        <form method="post" action="<?= e(url('/waivers/' . $w['id'] . '/decide')) ?>" class="d-flex gap-1 flex-wrap" data-confirm="<?= e(__('common.confirm')) ?>"><?= csrf_field() ?>
          <input class="form-control form-control-sm" style="max-width:9rem" name="note" placeholder="<?= e(__('waivers.note')) ?>" aria-label="<?= e(__('waivers.note')) ?>">
          <button class="btn btn-sm btn-success" name="decision" value="approve"><?= e(__('waivers.approve')) ?></button>
          <button class="btn btn-sm btn-outline-danger" name="decision" value="reject"><?= e(__('waivers.reject')) ?></button></form>
      <?php endif; ?></td></tr>
  <?php endforeach; ?>
  <?php if ($waivers === []): ?><tr><td colspan="7" class="ps-3 text-body-secondary"><?= e(__('waivers.none')) ?></td></tr><?php endif; ?>
  </tbody></table></div></div>

<h2 class="h6"><?= e(__('waivers.refunds')) ?></h2>
<div class="card"><div class="table-responsive"><table class="table table-sm mb-0 align-middle">
  <thead class="table-light"><tr><th class="ps-3"><?= e(__('students.name_en')) ?></th><th>#</th><th class="text-end"><?= e(__('refunds.amount')) ?></th><th><?= e(__('refunds.reason')) ?></th>
    <th><?= e(__('waivers.requester')) ?></th><th><?= e(__('common.status')) ?></th><th class="pe-3"></th></tr></thead>
  <tbody>
  <?php foreach ($refunds as $r): ?>
    <tr><td class="ps-3"><a href="<?= e(url('/enrollments/' . $r['enrollment_id'])) ?>"><?= e(loc($r, 'name')) ?></a><div class="small text-body-secondary"><?= e($r['batch_code']) ?></div></td>
      <td class="small"><?= e($r['refund_no']) ?></td><td class="text-end"><?= money($r['amount']) ?></td>
      <td class="small"><?= e($r['reason']) ?><?= $r['decision_note'] ? '<div class="text-body-secondary">→ ' . e($r['decision_note']) . '</div>' : '' ?></td>
      <td class="small"><?= e($r['requester']) ?><?= $r['approver'] ? '<div class="text-body-secondary">' . e($r['approver']) . '</div>' : '' ?></td>
      <td><span class="badge text-bg-<?= $badge[$r['status']] ?>"><?= e(__('waivers.status.' . $r['status'])) ?></span></td>
      <td class="pe-3">
      <?php if ($r['status'] === 'pending' && can('refund.approve') && (int) $r['requested_by'] !== (int) $me): ?>
        <form method="post" action="<?= e(url('/refunds/' . $r['id'] . '/decide')) ?>" class="d-flex gap-1 flex-wrap" data-confirm="<?= e(__('common.confirm')) ?>"><?= csrf_field() ?>
          <input class="form-control form-control-sm" style="max-width:9rem" name="note" placeholder="<?= e(__('waivers.note')) ?>" aria-label="<?= e(__('waivers.note')) ?>">
          <button class="btn btn-sm btn-success" name="decision" value="approve"><?= e(__('waivers.approve')) ?></button>
          <button class="btn btn-sm btn-outline-danger" name="decision" value="reject"><?= e(__('waivers.reject')) ?></button></form>
      <?php elseif ($r['status'] === 'approved' && can('fee.collect')): $accs = Finance::accountsOf((int) $r['center_id']); ?>
        <form method="post" action="<?= e(url('/refunds/' . $r['id'] . '/pay')) ?>" class="d-flex gap-1" data-confirm="<?= e(__('common.confirm')) ?>"><?= csrf_field() ?>
          <select class="form-select form-select-sm" name="account_id" aria-label="<?= e(__('finance.account')) ?>"><?php foreach ($accs as $a): ?><option value="<?= (int) $a['id'] ?>"><?= e($a['name']) ?> (<?= money($a['balance']) ?>)</option><?php endforeach; ?></select>
          <button class="btn btn-sm btn-primary"><?= e(__('refunds.pay')) ?></button></form>
      <?php endif; ?></td></tr>
  <?php endforeach; ?>
  <?php if ($refunds === []): ?><tr><td colspan="7" class="ps-3 text-body-secondary"><?= e(__('waivers.none')) ?></td></tr><?php endif; ?>
  </tbody></table></div></div>
