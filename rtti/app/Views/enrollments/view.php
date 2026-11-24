<?php $active = in_array($e['status'], ['pending', 'active']); ?>
<div class="page-head">
  <h1>Enrollment <?= e($e['enrollment_code']) ?></h1><?= status_badge($e['status']) ?>
  <div class="actions">
    <?php if ($e['status'] === 'pending' && can('enrollments.approve')): ?><form method="post" action="<?= url('enrollments/approve') ?>" class="d-inline"><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int)$e['id'] ?>"><button class="btn btn-success btn-sm"><i class="bi bi-check2-circle"></i> Approve</button></form><?php endif; ?>
    <?php if (can('fees.collect') && $e['due_amount'] > 0 && in_array($e['status'], ['active', 'pending', 'completed'])): ?><a class="btn btn-primary btn-sm" href="<?= url('fees/collect', ['enrollment_id' => $e['id']]) ?>"><i class="bi bi-cash"></i> Collect fee</a><?php endif; ?>
    <?php if (can('certificates.create') && !$certProblems): ?><a class="btn btn-success btn-sm" href="<?= url('certificates/eligible', ['batch_id' => $e['batch_id']]) ?>"><i class="bi bi-award"></i> Issue certificate</a><?php endif; ?>
    <?php if ($active && can('enrollments.edit')): ?><button class="btn btn-outline-primary btn-sm" data-bs-toggle="modal" data-bs-target="#editModal"><i class="bi bi-pencil"></i> Discount / remarks</button><?php endif; ?>
    <?php if ($active && can('enrollments.transfer')): ?><button class="btn btn-outline-secondary btn-sm" data-bs-toggle="modal" data-bs-target="#trModal"><i class="bi bi-arrow-left-right"></i> Batch transfer</button><?php endif; ?>
    <?php if ($active && can('enrollments.cancel')): ?>
      <form method="post" action="<?= url('enrollments/cancel') ?>" class="d-inline" data-confirm="Mark this enrollment as dropped?" data-reason><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int)$e['id'] ?>"><input type="hidden" name="as" value="dropped"><button class="btn btn-outline-warning btn-sm">Drop out</button></form>
      <form method="post" action="<?= url('enrollments/cancel') ?>" class="d-inline" data-confirm="Cancel this enrollment? Outstanding due will be written off; payments stay on record." data-reason><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int)$e['id'] ?>"><button class="btn btn-outline-danger btn-sm">Cancel</button></form>
    <?php endif; ?>
  </div>
</div>
<?php if (!empty($_GET['print_receipt'])): ?><div class="alert alert-success d-flex align-items-center gap-2">Payment recorded. <a class="btn btn-sm btn-success" target="_blank" href="<?= url('fees/receipt', ['id' => (int)$_GET['print_receipt'], 'autoprint' => 1]) ?>"><i class="bi bi-printer"></i> Print money receipt</a></div><?php endif; ?>
<div class="row g-3">
  <div class="col-lg-5">
    <div class="card mb-3"><div class="card-body"><dl class="kv small">
      <dt>Student</dt><dd><a href="<?= url('students/view', ['id' => $e['student_id']]) ?>"><?= e($e['student']) ?></a> (<?= e($e['student_code']) ?>)</dd>
      <dt>Father's name</dt><dd><?= e($e['father_name']) ?></dd><dt>Mobile</dt><dd><?= e($e['mobile']) ?></dd>
      <dt>Course</dt><dd><?= e($e['course']) ?> (<?= e($e['course_code']) ?>)</dd>
      <dt>Batch</dt><dd><a href="<?= url('batches/view', ['id' => $e['batch_id']]) ?>"><?= e($e['batch_code']) ?></a> – <?= e($e['batch_status']) ?></dd>
      <dt>Center</dt><dd><?= e($e['center']) ?></dd>
      <dt>Course period</dt><dd><?= e(fmt_date($e['start_date'])) ?> – <?= e(fmt_date($e['end_date'])) ?></dd>
      <dt>Schedule</dt><dd><?= e($e['class_schedule']) ?></dd>
      <dt>Enrollment date</dt><dd><?= e(fmt_date($e['enrollment_date'])) ?> by <?= e($e['created_by_name']) ?></dd>
      <dt>Approved</dt><dd><?= $e['approved_at'] ? e(fmt_datetime($e['approved_at']) . ' by ' . $e['approved_by_name']) : '—' ?></dd>
      <?php if ($e['transferred_from_code']): ?><dt>Transferred from</dt><dd><a href="<?= url('enrollments/view', ['id' => $e['transferred_from_id']]) ?>"><?= e($e['transferred_from_code']) ?></a></dd><?php endif; ?>
      <?php if ($e['duplicate_override_reason']): ?><dt>Duplicate exception</dt><dd class="text-warning"><?= e($e['duplicate_override_reason']) ?></dd><?php endif; ?>
      <?php if ($e['cancel_reason']): ?><dt>Closed</dt><dd class="text-danger"><?= e($e['cancel_reason']) ?> (<?= e($e['cancelled_by_name']) ?>, <?= e(fmt_date($e['cancelled_at'])) ?>)</dd><?php endif; ?>
      <?php if ($e['remarks']): ?><dt>Remarks</dt><dd><?= e($e['remarks']) ?></dd><?php endif; ?>
    </dl></div></div>
    <div class="card mb-3"><div class="card-header">Academic progress</div><div class="card-body small">
      <div class="mb-2"><b>Attendance:</b> <?= $attPct === null ? 'not recorded' : e($attPct . '%') ?> (required <?= e((float)$e['min_attendance_percent']) ?>%)
        <?php if ($att['total']): ?><div class="text-muted">Present <?= (int)$att['present'] ?> · Late <?= (int)$att['late'] ?> · Leave <?= (int)$att['leave_'] ?> · Absent <?= (int)$att['absent'] ?> of <?= (int)$att['total'] ?> classes</div><?php endif; ?></div>
      <div class="mb-2"><b>Final result:</b> <?= $e['result_status'] ? status_badge($e['result_status']) . ' Grade ' . e($e['result_grade']) . ' (' . e($e['result_percent']) . '%)' : 'not published' ?></div>
      <?php if ($exams): ?><table class="table table-sm"><?php foreach ($exams as $x): ?><tr><td><?= e($x['name']) ?></td><td><?= e(fmt_date($x['exam_date'])) ?></td><td class="text-end"><?= e($x['obtained']) ?></td></tr><?php endforeach; ?></table><?php endif; ?>
      <div><b>Certificate:</b>
        <?php if ($cert && $cert['status'] === 'issued'): ?><a href="<?= url('certificates/view', ['id' => $cert['id']]) ?>"><?= e($cert['certificate_no']) ?></a> issued <?= e(fmt_date($cert['issue_date'])) ?>
        <?php elseif ($certProblems): ?><span class="text-muted">Not eligible yet:</span><ul class="mb-0"><?php foreach ($certProblems as $pr): ?><li><?= e($pr) ?></li><?php endforeach; ?></ul>
        <?php else: ?><span class="text-success">Eligible for certificate</span><?php endif; ?></div>
    </div></div>
  </div>
  <div class="col-lg-7">
    <div class="row g-3 mb-3">
      <div class="col-6 col-md-3"><div class="card p-2 text-center"><div class="small text-muted">Course fee</div><b><?= money($e['course_fee'], false) ?></b></div></div>
      <div class="col-6 col-md-3"><div class="card p-2 text-center"><div class="small text-muted">Discount</div><b><?= money($e['discount'], false) ?></b></div></div>
      <div class="col-6 col-md-3"><div class="card p-2 text-center"><div class="small text-muted">Paid</div><b class="text-success"><?= money($e['paid_amount'], false) ?></b></div></div>
      <div class="col-6 col-md-3"><div class="card p-2 text-center"><div class="small text-muted">Due</div><b class="text-danger"><?= money($e['due_amount'], false) ?></b></div></div>
    </div>
    <div class="card"><div class="card-header">Fee ledger (permanent history)</div>
      <div class="table-responsive"><table class="table table-sm mb-0">
        <thead><tr><th>Date</th><th>Type</th><th>Narration</th><th class="text-end">Debit</th><th class="text-end">Credit</th><th class="text-end">Balance</th></tr></thead><tbody>
        <?php $bal = 0; foreach ($ledger as $l): $bal += $l['debit'] - $l['credit']; ?>
          <tr><td><?= e(fmt_date($l['txn_date'])) ?></td><td><?= e(ucfirst($l['txn_type'])) ?></td><td class="small"><?= e($l['narration']) ?>
            <?php if ($l['fee_collection_id']): ?> <a target="_blank" href="<?= url('fees/receipt', ['id' => $l['fee_collection_id']]) ?>"><i class="bi bi-receipt"></i></a><?php endif; ?></td>
            <td class="text-end"><?= $l['debit'] > 0 ? money($l['debit'], false) : '' ?></td><td class="text-end"><?= $l['credit'] > 0 ? money($l['credit'], false) : '' ?></td><td class="text-end fw-semibold"><?= money($bal, false) ?></td></tr>
        <?php endforeach; ?>
        </tbody></table></div></div>
  </div>
</div>

<?php if ($active && can('enrollments.edit')): ?>
<div class="modal fade" id="editModal" tabindex="-1"><div class="modal-dialog"><form class="modal-content" method="post" action="<?= url('enrollments/update') ?>">
  <?= csrf_field() ?><input type="hidden" name="id" value="<?= (int)$e['id'] ?>">
  <div class="modal-header"><h5 class="modal-title">Change discount / remarks</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
  <div class="modal-body">
    <div class="mb-2"><label class="form-label">Discount (course fee <?= money($e['course_fee'], false) ?>)</label><input type="number" step="0.01" min="0" name="discount" class="form-control" value="<?= e($e['discount']) ?>"></div>
    <div class="mb-2"><label class="form-label">Discount reason</label><input name="discount_reason" class="form-control" value="<?= e($e['discount_reason']) ?>"></div>
    <div class="mb-2"><label class="form-label">Remarks</label><textarea name="remarks" class="form-control"><?= e($e['remarks']) ?></textarea></div>
    <div class="small text-muted">Discount changes are written to the ledger and audit log.</div>
  </div>
  <div class="modal-footer"><button class="btn btn-primary">Save</button></div>
</form></div></div>
<?php endif; ?>
<?php if ($active && can('enrollments.transfer')): ?>
<div class="modal fade" id="trModal" tabindex="-1"><div class="modal-dialog"><form class="modal-content" method="post" action="<?= url('enrollments/transfer') ?>">
  <?= csrf_field() ?><input type="hidden" name="id" value="<?= (int)$e['id'] ?>">
  <div class="modal-header"><h5 class="modal-title">Batch transfer</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
  <div class="modal-body">
    <?php if (!$otherBatches): ?><div class="alert alert-warning">No other open batch of this course is available.</div><?php endif; ?>
    <div class="mb-2"><label class="form-label req">Target batch (same course)</label><select name="new_batch_id" class="form-select" required><?= options($otherBatches) ?></select></div>
    <div class="mb-2"><label class="form-label req">Reason</label><input name="reason" class="form-control" required minlength="3"></div>
    <div class="small text-muted">Payments and fee terms move with the student. The old enrollment is kept as “transferred”.</div>
  </div>
  <div class="modal-footer"><button class="btn btn-primary">Transfer</button></div>
</form></div></div>
<?php endif; ?>
