<div class="page">
  <?= \App\Core\View::partial('partials/print_header') ?>
  <h2 class="doc-title">Student Profile</h2>
  <div class="d-flex gap-3 mb-3">
    <?php if ($s['photo']): ?><img src="<?= e(file_url($s['photo'])) ?>" style="width:110px;height:130px;object-fit:cover;border:1px solid #ccc"><?php endif; ?>
    <table class="table table-sm table-bordered mb-0">
      <tr><th style="width:22%">Student ID</th><td><b><?= e($s['student_code']) ?></b></td><th style="width:20%">Registered</th><td><?= e(fmt_date($s['registration_date'])) ?>, <?= e($s['center']) ?></td></tr>
      <tr><th>Name</th><td><?= e($s['name']) ?> <?= e($s['name_bn']) ?></td><th>Date of birth</th><td><?= e(fmt_date($s['date_of_birth'])) ?></td></tr>
      <tr><th>Father's name</th><td><?= e($s['father_name']) ?></td><th>Mother's name</th><td><?= e($s['mother_name']) ?></td></tr>
      <tr><th>Gender</th><td><?= e($s['gender']) ?></td><th>NID / BRN</th><td><?= e($s['nid'] ?: $s['birth_reg_no']) ?></td></tr>
      <tr><th>Mobile</th><td><?= e($s['mobile']) ?></td><th>Education</th><td><?= e($s['education']) ?></td></tr>
      <tr><th>Address</th><td colspan="3"><?= e($s['present_address']) ?><?= $s['district'] ? ', ' . e($s['district']) : '' ?></td></tr>
    </table>
  </div>
  <h6>Course history</h6>
  <table class="table table-sm table-bordered print-table">
    <thead><tr><th>#</th><th>Course</th><th>Batch</th><th>Center</th><th>Period</th><th>Attend.</th><th>Result</th><th>Status</th><th>Certificate</th><th class="text-end">Payable</th><th class="text-end">Paid</th><th class="text-end">Due</th></tr></thead>
    <tbody>
    <?php foreach ($rows as $i => $r): ?>
      <tr><td><?= $i + 1 ?></td><td><?= e($r['course']) ?></td><td><?= e($r['batch_code']) ?></td><td><?= e($r['center']) ?></td><td><?= e(fmt_date($r['start_date'])) ?> – <?= e(fmt_date($r['end_date'])) ?></td>
        <td><?= $r['attendance'] === null ? '—' : e($r['attendance'] . '%') ?></td><td><?= e($r['result_grade'] ?: '—') ?></td><td><?= e(ucfirst($r['status'])) ?></td><td><?= e($r['certificate_no'] ?: '—') ?></td>
        <td class="text-end"><?= money($r['payable_amount'], false) ?></td><td class="text-end"><?= money($r['paid_amount'], false) ?></td><td class="text-end"><?= money($r['due_amount'], false) ?></td></tr>
    <?php endforeach; ?>
    </tbody>
  </table>
  <div class="small text-muted">Printed on <?= date('d/m/Y h:i A') ?> by <?= e(user()['name']) ?></div>
</div>
