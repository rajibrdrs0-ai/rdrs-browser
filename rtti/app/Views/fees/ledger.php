<div class="page-head"><h1><?= e(t('Student Ledger')) ?></h1><?php if ($student): ?><div class="actions"><?= \App\Core\View::partial('partials/export_buttons') ?></div><?php endif; ?></div>
<form class="filters row g-2 align-items-end" method="get"><input type="hidden" name="r" value="fees/ledger">
  <div class="col-md-8"><label class="form-label">Student</label><select name="student_id" class="form-select s2" data-ajax="<?= url('ajax/students') ?>" onchange="this.form.submit()">
  <?php if ($student): ?><option value="<?= (int)$student['id'] ?>" selected><?= e($student['student_code'] . ' – ' . $student['name']) ?></option><?php endif; ?></select></div></form>
<?php if ($student): $bal = 0; $dr = $cr = 0; ?>
<div class="card"><div class="card-header"><a href="<?= url('students/view', ['id' => $student['id']]) ?>"><?= e($student['name']) ?></a> · <?= e($student['student_code']) ?> · <?= e($student['center']) ?></div>
<div class="table-responsive"><table class="table table-sm mb-0">
  <thead><tr><th>Date</th><th>Enrollment / course</th><th>Type</th><th>Narration</th><th class="text-end">Debit (charge)</th><th class="text-end">Credit (paid/discount)</th><th class="text-end">Balance due</th></tr></thead>
  <tbody><?php foreach ($entries as $l): $bal += $l['debit'] - $l['credit']; $dr += $l['debit']; $cr += $l['credit']; ?>
    <tr><td><?= e(fmt_date($l['txn_date'])) ?></td><td><?= e($l['enrollment_code']) ?><br><small class="text-muted"><?= e($l['course']) ?></small></td><td><?= e(ucfirst($l['txn_type'])) ?></td>
      <td class="small"><?= e($l['narration']) ?><?php if ($l['fee_collection_id']): ?> <a target="_blank" href="<?= url('fees/receipt', ['id' => $l['fee_collection_id']]) ?>"><i class="bi bi-receipt"></i></a><?php endif; ?></td>
      <td class="text-end"><?= $l['debit'] > 0 ? money($l['debit'], false) : '' ?></td><td class="text-end"><?= $l['credit'] > 0 ? money($l['credit'], false) : '' ?></td><td class="text-end fw-semibold"><?= money($bal, false) ?></td></tr>
  <?php endforeach; ?></tbody>
  <tfoot class="fw-bold"><tr><td colspan="4">Total</td><td class="text-end"><?= money($dr, false) ?></td><td class="text-end"><?= money($cr, false) ?></td><td class="text-end"><?= money($bal, false) ?></td></tr></tfoot>
</table></div></div>
<?php endif; ?>
