<div class="page-head"><h1>New Enrollment</h1></div>
<form method="post" action="<?= url('enrollments/store') ?>" id="enrForm">
<?= csrf_field() ?>
<div class="row g-3">
  <div class="col-lg-8">
    <div class="card mb-3"><div class="card-header">Student & course</div><div class="card-body"><div class="row g-3">
      <div class="col-12"><label class="form-label req">Student (search by ID, name, mobile, NID)</label>
        <select name="student_id" id="student" class="form-select s2" data-ajax="<?= url('ajax/students') ?>" required>
          <?php if ($student): ?><option value="<?= (int)$student['id'] ?>" selected><?= e($student['student_code'] . ' – ' . $student['name'] . ' (' . $student['mobile'] . ')') ?></option><?php endif; ?>
        </select>
        <div class="form-text">Not registered yet? <a href="<?= url('students/create') ?>">Register a new student</a> first – every person keeps one lifetime Student ID.</div></div>
      <div class="col-md-6"><label class="form-label req">Course</label><select id="course" class="form-select s2" data-child="#batch" data-source="ajax/batches" required><?= options($courses, $preCourse) ?></select></div>
      <div class="col-md-6"><label class="form-label req">Batch (open for enrollment)</label><select name="batch_id" id="batch" class="form-select s2" required data-selected="<?= (int)$preBatch ?: '' ?>"><option value="">-- Select course first --</option></select></div>
      <div class="col-12" id="batchInfo" style="display:none"></div>
      <div class="col-12" id="dupWrap" style="display:none"><div class="alert alert-warning py-2 mb-2 small" id="dupMsg"></div>
        <label class="form-label req">Authorised exception reason (duplicate enrollment)</label><input name="override_reason" class="form-control" value="<?= e(old('override_reason')) ?>"></div>
      <div class="col-md-4"><label class="form-label req">Enrollment date</label><input type="date" name="enrollment_date" class="form-control" value="<?= e(old('enrollment_date', date('Y-m-d'))) ?>" max="<?= date('Y-m-d') ?>" required></div>
      <div class="col-md-8"><label class="form-label">Remarks</label><input name="remarks" class="form-control" value="<?= e(old('remarks')) ?>"></div>
    </div></div></div>
    <?php if (can('fees.collect')): ?>
    <div class="card"><div class="card-header">Initial payment (optional)</div><div class="card-body"><div class="row g-3">
      <div class="col-md-4"><label class="form-label">Amount paid now</label><input name="initial_payment" id="initial" type="number" step="0.01" min="0" class="form-control" value="<?= e(old('initial_payment', '0')) ?>"></div>
      <div class="col-md-4"><label class="form-label">Payment method</label><select name="payment_method_id" id="pmethod" class="form-select"><?php foreach ($methods as $m): ?><option value="<?= (int)$m['id'] ?>" data-ref="<?= (int)$m['requires_reference'] ?>"><?= e($m['name']) ?></option><?php endforeach; ?></select></div>
      <div class="col-md-4"><label class="form-label">Transaction ID</label><input name="transaction_ref" id="tref" class="form-control" value="<?= e(old('transaction_ref')) ?>"></div>
      <div class="col-md-6"><label class="form-label">Deposit to account</label><select name="account_id" class="form-select"><?= options($accounts, old('account_id'), 'id', 'name', '-- Optional --') ?></select></div>
    </div></div></div>
    <?php endif; ?>
  </div>
  <div class="col-lg-4">
    <div class="card"><div class="card-header">Fee calculation</div><div class="card-body">
      <table class="table table-sm mb-3">
        <tr><td>Course fee</td><td class="text-end fw-semibold" id="feeTxt">0.00</td></tr>
        <tr><td>Discount</td><td class="text-end"><input name="discount" id="discount" type="number" step="0.01" min="0" class="form-control form-control-sm text-end" value="<?= e(old('discount', '0')) ?>"></td></tr>
        <tr><td>Payable</td><td class="text-end fw-bold" id="payableTxt">0.00</td></tr>
        <tr><td>Paid now</td><td class="text-end" id="paidTxt">0.00</td></tr>
        <tr class="table-light"><td><b>Due after enrollment</b></td><td class="text-end fw-bold text-danger" id="dueTxt">0.00</td></tr>
      </table>
      <div id="discReasonWrap" style="display:none" class="mb-3"><label class="form-label req">Discount reason</label><input name="discount_reason" class="form-control" value="<?= e(old('discount_reason')) ?>" placeholder="e.g. Freedom fighter quota, poor & meritorious"></div>
      <button class="btn btn-primary w-100"><i class="bi bi-check2"></i> Save enrollment</button>
    </div></div>
  </div>
</div>
</form>
<?php ob_start(); ?>
<script>
let FEE = 0;
function recalc() {
  const d = num($('#discount').val()), pay = Math.max(0, FEE - d), paid = num($('#initial').val());
  $('#feeTxt').text(fmt(FEE)); $('#payableTxt').text(fmt(pay)); $('#paidTxt').text(fmt(paid)); $('#dueTxt').text(fmt(Math.max(0, pay - paid)));
  $('#discReasonWrap').toggle(d > 0); $('[name=discount_reason]').prop('required', d > 0);
}
function loadBatch() {
  const id = $('#batch').val();
  if (!id) { $('#batchInfo').hide(); FEE = 0; recalc(); return; }
  $.getJSON(APP.base + 'index.php', {r: 'enrollments/batchinfo', id, student_id: $('#student').val()}).done(function (b) {
    FEE = b.fee; recalc();
    const full = b.enrolled >= b.max;
    $('#batchInfo').html('<div class="alert ' + (full || !b.open ? 'alert-danger' : 'alert-info') + ' py-2 mb-0 small">' +
      '<b>' + $('<span>').text(b.center).html() + '</b> · ' + b.start + ' – ' + b.end + ' · ' + $('<span>').text(b.schedule || '').html() +
      (b.trainer ? ' · Trainer: ' + $('<span>').text(b.trainer).html() : '') + '<br>Seats: <b>' + b.enrolled + '/' + b.max + '</b>' + (full ? ' – <b>batch is full</b>' : '') + ' · Status: ' + b.status + '</div>').show();
    $('#dupWrap').toggle(!!b.duplicate); $('[name=override_reason]').prop('required', !!b.duplicate);
    if (b.duplicate) $('#dupMsg').text('This student is already enrolled in this batch (' + b.duplicate + '). Enrolling again requires an authorised exception.');
  });
}
$('#batch, #student').on('change', loadBatch);
$('#discount, #initial').on('input', recalc);
$('#pmethod').on('change', function(){ $('#tref').prop('required', $(this).find(':selected').data('ref') == 1 && num($('#initial').val()) > 0); });
recalc();
</script>
<?php \App\Core\View::share('scripts', ob_get_clean()); ?>
