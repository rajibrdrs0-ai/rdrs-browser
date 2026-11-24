<div class="page-head"><h1><?= e(t('Student Courses')) ?></h1><div class="actions"><?= \App\Core\View::partial('partials/export_buttons') ?></div></div>
<?= \App\Core\View::partial('partials/filters', ['f' => ['q' => 'Student ID, name, mobile, NID', 'center' => true, 'district' => true,
  'extra' => '<div class="col-6 col-md-2"><label class="form-label">Course</label><select name="course_id" class="form-select form-select-sm">' . options($courses, $_GET['course_id'] ?? '', 'id', 'name', 'All') . '</select></div>'
  . '<div class="col-6 col-md-2"><label class="form-label">Certificate</label><select name="cert" class="form-select form-select-sm">' . kv_options(['issued' => 'Issued', 'not_issued' => 'Not issued'], $_GET['cert'] ?? '', 'All') . '</select></div>']]) ?>
<div class="card"><div class="table-responsive"><table class="table table-sm table-hover mb-0">
  <thead><tr><th>Student</th><th>Course / batch</th><th>Center</th><th>Period</th><th class="text-center">Attendance</th><th>Result</th><th>Completion</th><th>Certificate</th><th>Fee status</th></tr></thead>
  <tbody>
  <?php foreach ($p['rows'] as $r): ?>
    <tr>
      <td><a href="<?= url('students/view', ['id' => $r['sid']]) ?>"><?= e($r['name']) ?></a><br><small class="text-muted"><?= e($r['student_code']) ?></small></td>
      <td><a href="<?= url('enrollments/view', ['id' => $r['id']]) ?>"><?= e($r['course']) ?></a><br><small class="text-muted"><?= e($r['batch_code']) ?></small></td>
      <td><?= e($r['center']) ?></td>
      <td class="small"><?= e(fmt_date($r['start_date'])) ?><br><?= e(fmt_date($r['end_date'])) ?></td>
      <td class="text-center"><?= $r['attendance'] === null ? '—' : e($r['attendance'] . '%') ?></td>
      <td><?= $r['result_status'] ? status_badge($r['result_status']) . ' ' . e($r['result_grade']) : '—' ?></td>
      <td><?= status_badge($r['status']) ?></td>
      <td class="small"><?= $r['certificate_no'] ? '<i class="bi bi-award text-success"></i> ' . e($r['certificate_no']) : '<span class="text-muted">Not issued</span>' ?></td>
      <td><?= $r['due_amount'] > 0 ? '<span class="badge text-bg-danger">Due ' . money($r['due_amount'], false) . '</span>' : '<span class="badge text-bg-success">Paid</span>' ?></td>
    </tr>
  <?php endforeach; ?>
  </tbody></table></div>
  <?= $p['rows'] ? '' : \App\Core\View::partial('partials/empty') ?>
  <?= \App\Core\View::partial('partials/pagination', ['p' => $p]) ?>
</div>
