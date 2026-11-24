<?php $totalPayable = array_sum(array_map(fn($e) => in_array($e['status'], ['cancelled', 'transferred', 'dropped']) ? 0 : $e['payable_amount'], $enrollments));
$totalPaid = array_sum(array_column($enrollments, 'paid_amount')); $totalDue = array_sum(array_map(fn($e) => max(0, $e['due_amount']), $enrollments)); ?>
<div class="page-head">
  <h1><?= e($s['name']) ?> <?php if ($s['name_bn']): ?><small class="text-muted fs-6"><?= e($s['name_bn']) ?></small><?php endif; ?></h1>
  <?= status_badge($s['status']) ?>
  <div class="actions">
    <?php if (can('enrollments.create')): ?><a class="btn btn-success btn-sm" href="<?= url('enrollments/create', ['student_id' => $s['id']]) ?>"><i class="bi bi-journal-plus"></i> Enroll in course</a><?php endif; ?>
    <?php if (can('fees.collect') && $totalDue > 0): ?><a class="btn btn-primary btn-sm" href="<?= url('fees/collect', ['student_id' => $s['id']]) ?>"><i class="bi bi-cash"></i> Collect fee</a><?php endif; ?>
    <?php if (can('students.print')): ?><a class="btn btn-outline-secondary btn-sm" target="_blank" href="<?= url('students/idcard', ['id' => $s['id']]) ?>"><i class="bi bi-person-vcard"></i> ID card</a><?php endif; ?>
    <a class="btn btn-outline-secondary btn-sm" target="_blank" href="<?= url('studentcourses/profile', ['id' => $s['id']]) ?>"><i class="bi bi-printer"></i> Print profile</a>
    <?php if (can('students.edit')): ?><a class="btn btn-outline-primary btn-sm" href="<?= url('students/edit', ['id' => $s['id']]) ?>"><i class="bi bi-pencil"></i> <?= e(t('Edit')) ?></a><?php endif; ?>
    <?php if (can('students.delete')): ?><form method="post" action="<?= url('students/delete') ?>" class="d-inline" data-confirm="Delete this student record?" data-reason><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int)$s['id'] ?>"><button class="btn btn-outline-danger btn-sm"><i class="bi bi-trash"></i></button></form><?php endif; ?>
  </div>
</div>
<div class="row g-3">
  <div class="col-lg-4">
    <div class="card"><div class="card-body">
      <div class="d-flex gap-3 mb-3">
        <?php if ($s['photo']): ?><img class="profile-photo" src="<?= e(file_url($s['photo'])) ?>" alt=""><?php else: ?><div class="photo-ph"><i class="bi bi-person"></i></div><?php endif; ?>
        <div><div class="text-muted small">Lifetime Student ID</div><div class="fs-4 fw-bold text-brand"><?= e($s['student_code']) ?></div>
          <div class="small mt-2"><i class="bi bi-telephone"></i> <?= e($s['mobile']) ?></div><?php if ($s['email']): ?><div class="small"><i class="bi bi-envelope"></i> <?= e($s['email']) ?></div><?php endif; ?></div>
      </div>
      <dl class="kv small">
        <dt>Father's name</dt><dd><?= e($s['father_name']) ?></dd>
        <dt>Mother's name</dt><dd><?= e($s['mother_name']) ?></dd>
        <dt>Date of birth</dt><dd><?= e(fmt_date($s['date_of_birth'])) ?> (<?= e(age($s['date_of_birth'])) ?> yrs)</dd>
        <dt>Gender</dt><dd><?= e($s['gender']) ?></dd>
        <dt>NID</dt><dd><?= e($s['nid']) ?></dd>
        <dt>Birth reg. no.</dt><dd><?= e($s['birth_reg_no']) ?></dd>
        <dt>Alt. mobile</dt><dd><?= e($s['alt_mobile']) ?></dd>
        <dt>Present address</dt><dd><?= e($s['present_address']) ?></dd>
        <dt>Permanent address</dt><dd><?= e($s['permanent_address']) ?></dd>
        <dt>District / Upazila</dt><dd><?= e(trim($s['district'] . ' / ' . $s['upazila'], ' /')) ?></dd>
        <dt>Education</dt><dd><?= e($s['education']) ?> <?= e($s['qualification_details']) ?></dd>
        <dt>Occupation</dt><dd><?= e($s['occupation']) ?></dd>
        <dt>Emergency contact</dt><dd><?= e($s['emergency_contact_name']) ?> <?= e($s['emergency_contact_mobile']) ?></dd>
        <dt>Registered</dt><dd><?= e(fmt_date($s['registration_date'])) ?> at <?= e($s['center']) ?></dd>
        <dt>Registered by</dt><dd><?= e($s['created_by_name']) ?></dd>
        <?php if ($s['remarks']): ?><dt>Remarks</dt><dd><?= nl2br(e($s['remarks'])) ?></dd><?php endif; ?>
      </dl>
    </div></div>
  </div>
  <div class="col-lg-8">
    <div class="row g-3 mb-3">
      <div class="col-4"><div class="card stat"><span class="ico ico-blue"><i class="bi bi-journal"></i></span><span><span class="v d-block"><?= count($enrollments) ?></span><span class="l">Courses enrolled</span></span></div></div>
      <div class="col-4"><div class="card stat"><span class="ico ico-green"><i class="bi bi-cash"></i></span><span><span class="v d-block"><?= money($totalPaid) ?></span><span class="l">Total paid</span></span></div></div>
      <div class="col-4"><div class="card stat"><span class="ico ico-red"><i class="bi bi-hourglass"></i></span><span><span class="v d-block"><?= money($totalDue) ?></span><span class="l">Outstanding due</span></span></div></div>
    </div>
    <div class="card">
      <div class="card-header p-0 border-0"><ul class="nav nav-tabs px-2 pt-2">
        <li class="nav-item"><button class="nav-link active" data-bs-toggle="tab" data-bs-target="#tCourses">Course history</button></li>
        <?php if (can('fees.view')): ?><li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#tPay">Payments (<?= count($payments) ?>)</button></li><?php endif; ?>
        <?php if (can('documents.view')): ?><li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#tDocs">Documents (<?= count($documents) ?>)</button></li><?php endif; ?>
        <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#tHist">Change history</button></li>
      </ul></div>
      <div class="tab-content">
        <div class="tab-pane fade show active" id="tCourses">
          <?php if (!$enrollments): ?><?= \App\Core\View::partial('partials/empty', ['message' => 'Not enrolled in any course yet.']) ?><?php endif; ?>
          <?php foreach ($enrollments as $e): ?>
          <div class="p-3 border-bottom">
            <div class="d-flex flex-wrap gap-2 align-items-center">
              <b><?= e($e['course']) ?></b> <span class="text-muted small"><?= e($e['course_code']) ?> · <?= (int)$e['duration'] ?> <?= e($e['duration_unit']) ?></span>
              <span class="ms-auto"><?= status_badge($e['status']) ?></span>
            </div>
            <div class="row small mt-2 g-2">
              <div class="col-md-4"><span class="text-muted">Enrollment:</span> <a href="<?= url('enrollments/view', ['id' => $e['id']]) ?>"><?= e($e['enrollment_code']) ?></a><br>
                <span class="text-muted">Batch:</span> <a href="<?= url('batches/view', ['id' => $e['batch_id']]) ?>"><?= e($e['batch_code']) ?></a> <span class="badge text-bg-<?= e($e['batch_color']) ?>"><?= e($e['batch_status']) ?></span><br>
                <span class="text-muted">Center:</span> <?= e($e['center']) ?></div>
              <div class="col-md-4"><span class="text-muted">Duration:</span> <?= e(fmt_date($e['start_date'])) ?> – <?= e(fmt_date($e['end_date'])) ?><br>
                <span class="text-muted">Attendance:</span> <?= $e['attendance'] === null ? '—' : e($e['attendance'] . '%') ?><br>
                <span class="text-muted">Result:</span> <?= $e['result_status'] ? status_badge($e['result_status']) . ' ' . e($e['result_grade'] . ' (' . $e['result_percent'] . '%)') : '—' ?></div>
              <div class="col-md-4"><span class="text-muted">Payable:</span> <?= money($e['payable_amount']) ?><?= $e['discount'] > 0 ? ' <small class="text-muted">(disc. ' . money($e['discount'], false) . ')</small>' : '' ?><br>
                <span class="text-muted">Paid:</span> <?= money($e['paid_amount']) ?><br>
                <span class="text-muted">Due:</span> <b class="<?= $e['due_amount'] > 0 ? 'text-danger' : 'text-success' ?>"><?= money($e['due_amount']) ?></b><br>
                <span class="text-muted">Certificate:</span> <?= $e['certificate_no'] ? '<a href="' . url('certificates/view', ['id' => $e['certificate_id']]) . '">' . e($e['certificate_no']) . '</a>' : '—' ?></div>
            </div>
          </div>
          <?php endforeach; ?>
        </div>
        <?php if (can('fees.view')): ?>
        <div class="tab-pane fade" id="tPay"><div class="table-responsive"><table class="table table-sm mb-0">
          <thead><tr><th>Receipt</th><th>Date</th><th>Enrollment</th><th>Method</th><th class="text-end">Amount</th><th>Status</th><th></th></tr></thead><tbody>
          <?php foreach ($payments as $f): ?><tr class="<?= $f['status'] === 'reversed' ? 'text-decoration-line-through text-muted' : '' ?>"><td><?= e($f['receipt_no']) ?></td><td><?= e(fmt_date($f['payment_date'])) ?></td><td><?= e($f['enrollment_code']) ?></td><td><?= e($f['method']) ?> <?= e($f['transaction_ref']) ?></td><td class="text-end"><?= money($f['amount'], false) ?></td><td><?= status_badge($f['status']) ?></td>
            <td><a class="btn btn-sm btn-outline-secondary" target="_blank" href="<?= url('fees/receipt', ['id' => $f['id']]) ?>"><i class="bi bi-printer"></i></a></td></tr><?php endforeach; ?>
          </tbody></table></div>
          <div class="p-2"><a href="<?= url('fees/ledger', ['student_id' => $s['id']]) ?>">Open student ledger →</a></div></div>
        <?php endif; ?>
        <?php if (can('documents.view')): ?>
        <div class="tab-pane fade" id="tDocs">
          <?= \App\Core\View::partial('documents/_list', ['documents' => $documents, 'back' => url('students/view', ['id' => $s['id']])]) ?>
          <?php if (can('documents.create')): ?>
          <form method="post" action="<?= url('documents/store') ?>" enctype="multipart/form-data" class="row g-2 p-3 border-top">
            <?= csrf_field() ?><input type="hidden" name="student_id" value="<?= (int)$s['id'] ?>"><input type="hidden" name="back" value="view">
            <div class="col-md-3"><select name="document_category_id" class="form-select form-select-sm" required><?= options($docCategories) ?></select></div>
            <div class="col-md-3"><input name="title" class="form-control form-control-sm" placeholder="Title (optional)"></div>
            <div class="col-md-4"><input type="file" name="files[]" multiple accept=".pdf,.jpg,.jpeg,.png" class="form-control form-control-sm" required></div>
            <div class="col-md-2"><button class="btn btn-primary btn-sm w-100"><i class="bi bi-upload"></i> Upload</button></div>
          </form>
          <?php endif; ?>
        </div>
        <?php endif; ?>
        <div class="tab-pane fade" id="tHist"><ul class="list-group list-group-flush small">
          <?php foreach ($history as $h): ?><li class="list-group-item"><b><?= e($h['uname'] ?? 'System') ?></b> · <?= e($h['action']) ?> · <?= e(fmt_datetime($h['created_at'])) ?><br><span class="text-muted"><?= e($h['description']) ?></span>
            <?php if ($h['new_values'] && $h['action'] === 'update'): $o = json_decode($h['old_values'] ?? '{}', true) ?: []; foreach ((json_decode($h['new_values'], true) ?: []) as $k => $nv): ?><div class="small"><code><?= e($k) ?></code>: <?= e($o[$k] ?? '') ?> → <?= e($nv) ?></div><?php endforeach; endif; ?></li><?php endforeach; ?>
        </ul></div>
      </div>
    </div>
  </div>
</div>
