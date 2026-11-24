<?php $rev = $f['status'] === 'reversed'; ?>
<?php if (!empty($_GET['new'])): ?><div class="no-print container mt-2"><div class="alert alert-success py-2">Payment saved. <a href="<?= url('fees/collect', ['student_id' => $f['student_id']]) ?>">Collect another</a> · <a href="<?= url('fees/index') ?>">Collection register</a></div></div><?php endif; ?>
<div class="page a5">
<?php foreach (['Student copy', 'Office copy'] as $i => $copy): ?>
  <?php if ($i): ?><div class="cut-line"></div><?php endif; ?>
  <div style="position:relative">
    <?php if ($rev): ?><div style="position:absolute;top:35%;left:20%;font-size:64px;color:rgba(200,16,46,.25);transform:rotate(-15deg);font-weight:800">REVERSED</div><?php endif; ?>
    <div class="d-flex align-items-center gap-2 border-bottom pb-2 mb-2">
      <img src="<?= logo_url() ?>" style="width:52px">
      <div class="flex-grow-1"><b style="color:#1a4f9c;font-size:16px"><?= e(setting('institute_name')) ?></b><div class="small"><?= e($f['center']) ?> · <?= e($f['center_address']) ?> <?= e($f['center_mobile']) ?></div></div>
      <div class="text-end"><b class="fs-6">MONEY RECEIPT</b><br><span class="copy-label"><?= $copy ?></span></div>
    </div>
    <table class="table table-sm table-borderless mb-1 small">
      <tr><td style="width:18%">Receipt no:</td><td><b><?= e($f['receipt_no']) ?></b></td><td style="width:15%">Date:</td><td><?= e(fmt_date($f['payment_date'])) ?></td></tr>
      <tr><td>Student:</td><td><b><?= e($f['student']) ?></b> (<?= e($f['student_code']) ?>)</td><td>Mobile:</td><td><?= e($f['mobile']) ?></td></tr>
      <tr><td>Father's name:</td><td><?= e($f['father_name']) ?></td><td>Enrollment:</td><td><?= e($f['enrollment_code']) ?></td></tr>
      <tr><td>Course:</td><td><?= e($f['course']) ?></td><td>Batch:</td><td><?= e($f['batch_code']) ?></td></tr>
    </table>
    <table class="table table-sm table-bordered small mb-1">
      <tr><td>Total course fee</td><td class="text-end"><?= money($f['course_fee'], false) ?></td><td>Payment method</td><td><?= e($f['method']) ?><?= $f['transaction_ref'] ? ' – ' . e($f['transaction_ref']) : '' ?></td></tr>
      <tr><td>Discount</td><td class="text-end"><?= money($f['discount'], false) ?></td><td>Account</td><td><?= e($f['account']) ?></td></tr>
      <tr><td>Payable amount</td><td class="text-end"><?= money($f['payable_snapshot'], false) ?></td><td colspan="2" rowspan="4" class="align-middle"><div class="small text-muted">In words</div><b><?= e(amount_in_words((float)$f['amount'])) ?></b></td></tr>
      <tr><td>Previous payment</td><td class="text-end"><?= money($f['previous_paid'], false) ?></td></tr>
      <tr class="table-light"><td><b>Current payment</b></td><td class="text-end"><b><?= money($f['amount']) ?></b></td></tr>
      <tr><td>Remaining due</td><td class="text-end"><?= money($f['due_after'], false) ?></td></tr>
    </table>
    <?php if ($f['remarks']): ?><div class="small">Remarks: <?= e($f['remarks']) ?></div><?php endif; ?>
    <?php if ($rev): ?><div class="small text-danger">Reversed on <?= e(fmt_datetime($f['reversed_at'])) ?> by <?= e($f['reversed_by_name']) ?>: <?= e($f['reversal_reason']) ?></div><?php endif; ?>
    <div class="d-flex justify-content-between align-items-end mt-4 small">
      <div class="text-muted" style="max-width:60%"><?= e(setting('receipt_footer')) ?><br>Printed <?= date('d/m/Y h:i A') ?></div>
      <div class="text-center" style="border-top:1px solid #333;min-width:170px;padding-top:3px">Received by<br><?= e($f['received_by_name']) ?></div>
    </div>
  </div>
<?php endforeach; ?>
</div>
