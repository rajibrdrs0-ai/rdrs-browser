<div class="verify-card">
  <div class="text-center mb-3"><img src="<?= logo_url() ?>" style="width:70px"><h4 class="mt-2 mb-0" style="color:#1a4f9c"><?= e(setting('institute_name')) ?></h4><div class="text-muted">Certificate Verification · সনদ যাচাই</div></div>
  <form method="get" class="d-flex gap-2 mb-3"><input type="hidden" name="r" value="verify/index">
    <input name="no" class="form-control" placeholder="Enter certificate number, e.g. RTTI/2026/000001" value="<?= e($no) ?>" required>
    <button class="btn btn-primary text-nowrap"><i class="bi bi-search"></i> Verify</button></form>
  <?php if ($searched && !$result): ?>
    <div class="alert alert-danger text-center"><i class="bi bi-x-octagon fs-3 d-block"></i><b>No certificate found.</b><br>This certificate number is not in our records. Please check the number or contact the institute.</div>
  <?php elseif ($result): $valid = $result['status'] === 'issued'; ?>
    <div class="alert alert-<?= $valid ? 'success' : 'danger' ?> text-center">
      <i class="bi bi-<?= $valid ? 'patch-check-fill' : 'x-octagon-fill' ?> fs-2 d-block"></i>
      <b class="fs-5"><?= $valid ? 'Valid certificate' : 'This certificate has been CANCELLED' ?></b>
    </div>
    <table class="table table-bordered">
      <tr><th style="width:38%">Certificate No.</th><td><b><?= e($result['certificate_no']) ?></b></td></tr>
      <tr><th>Name of trainee</th><td><?= e($result['student']) ?></td></tr>
      <tr><th>Father's name</th><td><?= e($result['father_name']) ?></td></tr>
      <tr><th>Course</th><td><?= e($result['course']) ?> (<?= (int)$result['duration'] ?> <?= e($result['duration_unit']) ?>)</td></tr>
      <tr><th>Training period</th><td><?= e(fmt_date($result['start_date'])) ?> – <?= e(fmt_date($result['end_date'])) ?></td></tr>
      <tr><th>Training center</th><td><?= e($result['center']) ?>, <?= e($result['district']) ?></td></tr>
      <tr><th>Grade</th><td><?= e($result['grade'] ?: '—') ?></td></tr>
      <tr><th>Issue date</th><td><?= e(fmt_date($result['issue_date'])) ?></td></tr>
      <?php if (!$valid): ?><tr><th>Cancelled on</th><td class="text-danger"><?= e(fmt_date($result['cancelled_at'])) ?></td></tr><?php endif; ?>
    </table>
  <?php endif; ?>
  <p class="small text-muted text-center mb-0">Scan the QR code printed on the certificate, or enter its number above. Only basic certificate details are shown for privacy.</p>
</div>
