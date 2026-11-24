<div class="page-head"><h1>New fee collection</h1></div>
<form class="filters row g-2 align-items-end" method="get"><input type="hidden" name="r" value="fees/collect">
  <div class="col-md-8"><label class="form-label">Find student (ID, name, mobile, NID)</label><select name="student_id" class="form-select s2" data-ajax="<?= url('ajax/students') ?>" onchange="this.form.submit()">
    <?php if ($student): ?><option value="<?= (int)$student['id'] ?>" selected><?= e($student['student_code'] . ' – ' . $student['name']) ?></option><?php endif; ?></select></div>
</form>
<?php if ($student): ?>
<form method="post" action="<?= url('fees/store') ?>" id="feeForm"><?= csrf_field() ?>
<div class="row g-3">
  <div class="col-lg-7">
    <div class="card"><div class="card-header"><?= e($student['name']) ?> <small class="text-muted"><?= e($student['student_code']) ?> · <?= e($student['mobile']) ?></small></div>
      <?php if (!$enrollments): ?><?= \App\Core\View::partial('partials/empty', ['message' => 'This student has no open enrollment in your centers.']) ?><?php endif; ?>
      <div class="list-group list-group-flush">
      <?php foreach ($enrollments as $e): ?>
        <label class="list-group-item d-flex gap-3 align-items-start">
          <input class="form-check-input mt-1 enr" type="radio" name="enrollment_id" value="<?= (int)$e['id'] ?>" data-due="<?= e($e['due_amount']) ?>" data-payable="<?= e($e['payable_amount']) ?>" data-paid="<?= e($e['paid_amount']) ?>"<?= (int)$e['id'] === (int)$selected ? ' checked' : '' ?><?= $e['due_amount'] <= 0 ? ' disabled' : '' ?>>
          <span class="flex-grow-1"><b><?= e($e['course']) ?></b> <span class="text-muted small"><?= e($e['enrollment_code']) ?> · <?= e($e['batch_code']) ?> · <?= e($e['center']) ?></span><br>
            <span class="small">Fee <?= money($e['course_fee'], false) ?> − discount <?= money($e['discount'], false) ?> = payable <b><?= money($e['payable_amount'], false) ?></b> · paid <?= money($e['paid_amount'], false) ?></span></span>
          <span class="text-end"><span class="small text-muted d-block">Due</span><b class="<?= $e['due_amount'] > 0 ? 'text-danger' : 'text-success' ?>"><?= money($e['due_amount'], false) ?></b></span>
        </label>
      <?php endforeach; ?></div></div>
  </div>
  <div class="col-lg-5"><div class="card"><div class="card-header">Payment</div><div class="card-body row g-2">
    <div class="col-6"><label class="form-label">Payable</label><input class="form-control" id="vPayable" readonly></div>
    <div class="col-6"><label class="form-label">Previous payment</label><input class="form-control" id="vPaid" readonly></div>
    <div class="col-6"><label class="form-label req">Current payment</label><input type="number" step="0.01" min="0.01" name="amount" id="amount" class="form-control fw-bold" required value="<?= e(old('amount')) ?>"></div>
    <div class="col-6"><label class="form-label">Remaining due</label><input class="form-control text-danger fw-bold" id="vDue" readonly></div>
    <div class="col-6"><label class="form-label req">Payment date</label><input type="date" name="payment_date" class="form-control" value="<?= e(old('payment_date', date('Y-m-d'))) ?>" max="<?= date('Y-m-d') ?>" required></div>
    <div class="col-6"><label class="form-label req">Method</label><select name="payment_method_id" id="pm" class="form-select" required><?php foreach ($methods as $m): ?><option value="<?= (int)$m['id'] ?>" data-ref="<?= (int)$m['requires_reference'] ?>"><?= e($m['name']) ?></option><?php endforeach; ?></select></div>
    <div class="col-12"><label class="form-label" id="refLabel">Transaction ID / reference</label><input name="transaction_ref" id="tref" class="form-control" value="<?= e(old('transaction_ref')) ?>"></div>
    <div class="col-12"><label class="form-label">Deposit to account</label><select name="account_id" class="form-select"><?= options($accounts, old('account_id'), 'id', 'name', '-- Optional --') ?></select></div>
    <div class="col-12"><label class="form-label">Remarks</label><input name="remarks" class="form-control" value="<?= e(old('remarks')) ?>"></div>
    <div class="col-12 d-grid"><button class="btn btn-primary"><i class="bi bi-check2"></i> Save & print receipt</button></div>
  </div></div></div>
</div></form>
<?php ob_start(); ?><script>
function upd() { const r = $('.enr:checked'); const due = num(r.data('due')), amt = num($('#amount').val());
  $('#vPayable').val(fmt(num(r.data('payable')))); $('#vPaid').val(fmt(num(r.data('paid')))); $('#vDue').val(fmt(Math.max(0, due - amt))); $('#amount').attr('max', due); }
$('.enr').on('change', function(){ $('#amount').val($(this).data('due')); upd(); });
$('#amount').on('input', upd);
$('#pm').on('change', function(){ const req = $(this).find(':selected').data('ref') == 1; $('#tref').prop('required', req); $('#refLabel').toggleClass('req', req); });
if (!$('#amount').val()) $('#amount').val($('.enr:checked').data('due')); upd();
</script><?php \App\Core\View::share('scripts', ob_get_clean()); ?>
<?php endif; ?>
