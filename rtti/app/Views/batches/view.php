<?php $days = ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat']; ?>
<div class="page-head"><h1><?= e($b['batch_code']) ?></h1><span class="badge text-bg-<?= e($b['color']) ?>"><?= e($b['status']) ?></span>
  <div class="actions">
    <?php if (can('enrollments.create')): ?><a class="btn btn-success btn-sm" href="<?= url('enrollments/create', ['batch_id' => $b['id']]) ?>"><i class="bi bi-person-plus"></i> Enroll student</a><?php endif; ?>
    <?php if (can('attendance.take')): ?><a class="btn btn-outline-primary btn-sm" href="<?= url('attendance/take', ['batch_id' => $b['id']]) ?>"><i class="bi bi-calendar-check"></i> Attendance</a><?php endif; ?>
    <?php if (can('exams.view')): ?><a class="btn btn-outline-primary btn-sm" href="<?= url('exams/index', ['batch_id' => $b['id']]) ?>"><i class="bi bi-clipboard-data"></i> Exams</a><?php endif; ?>
    <?php if (can('certificates.create')): ?><a class="btn btn-outline-primary btn-sm" href="<?= url('certificates/eligible', ['batch_id' => $b['id']]) ?>"><i class="bi bi-award"></i> Certificates</a><?php endif; ?>
    <?php if (can('students.print')): ?><a class="btn btn-outline-secondary btn-sm" target="_blank" href="<?= url('students/idcard', ['batch_id' => $b['id']]) ?>"><i class="bi bi-person-vcard"></i> ID cards</a><?php endif; ?>
    <a class="btn btn-outline-secondary btn-sm" target="_blank" href="<?= url('reports/run', ['type' => 'batch_students', 'batch_id' => $b['id'], 'export' => 'print']) ?>"><i class="bi bi-printer"></i> Student list</a>
    <?php if (can('batches.edit')): ?><a class="btn btn-outline-primary btn-sm" href="<?= url('batches/edit', ['id' => $b['id']]) ?>"><i class="bi bi-pencil"></i></a><?php endif; ?>
    <?php if (can('batches.complete') && $b['status_code'] !== 'COMPLETED'): ?><form method="post" action="<?= url('batches/complete', ['id' => $b['id']]) ?>" class="d-inline" data-confirm="Mark this batch as completed?"><?= csrf_field() ?><?php if ($b['end_date'] > date('Y-m-d')): ?><input type="hidden" name="force" value="1"><?php endif; ?><button class="btn btn-outline-success btn-sm"><i class="bi bi-check2-all"></i> Complete batch</button></form><?php endif; ?>
    <?php if (can('batches.delete') && !$students): ?><form method="post" action="<?= url('batches/delete', ['id' => $b['id']]) ?>" class="d-inline" data-confirm="Delete this batch?"><?= csrf_field() ?><button class="btn btn-outline-danger btn-sm"><i class="bi bi-trash"></i></button></form><?php endif; ?>
  </div></div>
<div class="row g-3">
  <div class="col-lg-4">
    <div class="card mb-3"><div class="card-body"><dl class="kv small">
      <dt>Batch name</dt><dd><?= e($b['name']) ?></dd><dt>Course</dt><dd><a href="<?= url('courses/view', ['id' => $b['course_id']]) ?>"><?= e($b['course']) ?></a></dd>
      <dt>Center</dt><dd><?= e($b['center']) ?></dd><dt>Trainer</dt><dd><?= e($b['trainer']) ?> <?= e($b['trainer_mobile']) ?></dd>
      <dt>Period</dt><dd><?= e(fmt_date($b['start_date'])) ?> – <?= e(fmt_date($b['end_date'])) ?></dd><dt>Schedule</dt><dd><?= e($b['class_schedule']) ?></dd>
      <dt>Room</dt><dd><?= e($b['room']) ?></dd><dt>Capacity</dt><dd><?= $enrolled ?> / <?= (int)$b['max_students'] ?> (<?= max(0, $b['max_students'] - $enrolled) ?> seats free)</dd>
      <dt>Classes held</dt><dd><?= $sessions ?></dd><?php if ($b['completed_at']): ?><dt>Completed</dt><dd><?= e(fmt_datetime($b['completed_at'])) ?></dd><?php endif; ?>
      <?php if ($b['remarks']): ?><dt>Remarks</dt><dd><?= e($b['remarks']) ?></dd><?php endif; ?>
    </dl></div></div>
    <?php if (can('fees.view')): ?><div class="card mb-3"><div class="card-header">Batch finance</div><div class="card-body small"><dl class="kv"><dt>Total payable</dt><dd><?= money($fin['payable']) ?></dd><dt>Collected</dt><dd class="text-success"><?= money($fin['paid']) ?></dd><dt>Outstanding</dt><dd class="text-danger"><?= money($fin['due']) ?></dd></dl></div></div><?php endif; ?>
    <div class="card mb-3"><div class="card-header d-flex">Class routine<?php if (can('routines.manage')): ?><a class="ms-auto small" href="<?= url('routines/index', ['batch_id' => $b['id']]) ?>">Manage</a><?php endif; ?></div>
      <table class="table table-sm mb-0"><?php foreach ($routine as $r): ?><tr><td><?= $days[$r['day_of_week']] ?></td><td><?= e(substr($r['start_time'], 0, 5)) ?>–<?= e(substr($r['end_time'], 0, 5)) ?></td><td><?= e($r['subject']) ?></td><td class="small"><?= e($r['trainer']) ?></td></tr><?php endforeach; ?>
      <?php if (!$routine): ?><tr><td class="text-muted small">No routine set.</td></tr><?php endif; ?></table></div>
    <div class="card mb-3"><div class="card-header">Examinations</div><ul class="list-group list-group-flush small">
      <?php foreach ($exams as $x): ?><li class="list-group-item"><a href="<?= url('exams/marks', ['id' => $x['id']]) ?>"><?= e($x['name']) ?></a> · <?= e(fmt_date($x['exam_date'])) ?> <?= status_badge($x['status']) ?><?= $x['is_final'] ? ' <span class="badge text-bg-dark">Final</span>' : '' ?></li><?php endforeach; ?>
      <?php if (!$exams): ?><li class="list-group-item text-muted">No exams yet.</li><?php endif; ?></ul></div>
    <div class="card"><div class="card-header">Assignments</div><ul class="list-group list-group-flush small">
      <?php foreach ($assignments as $a): ?><li class="list-group-item"><a href="<?= url('assignments/view', ['id' => $a['id'], 'batch_id' => $b['id']]) ?>"><?= e($a['title']) ?></a> · due <?= e(fmt_datetime($a['deadline'])) ?></li><?php endforeach; ?>
      <?php if (!$assignments): ?><li class="list-group-item text-muted">No assignments.</li><?php endif; ?></ul></div>
  </div>
  <div class="col-lg-8">
    <div class="card"><div class="card-header">Batch-wise students (<?= count($students) ?>)</div><div class="table-responsive"><table class="table table-sm table-hover mb-0">
      <thead><tr><th>#</th><th>Student</th><th>Mobile</th><th class="text-center">Attendance</th><th>Result</th><th class="text-end">Due</th><th>Status</th><th>Certificate</th></tr></thead><tbody>
      <?php foreach ($students as $i => $s): ?>
        <tr class="<?= in_array($s['status'], ['cancelled', 'transferred', 'dropped']) ? 'text-muted' : '' ?>"><td><?= $i + 1 ?></td>
          <td><a href="<?= url('enrollments/view', ['id' => $s['id']]) ?>"><?= e($s['name']) ?></a><br><small class="text-muted"><?= e($s['student_code']) ?></small></td>
          <td><?= e($s['mobile']) ?></td><td class="text-center"><?= $s['attendance'] === null ? '—' : e($s['attendance'] . '%') ?></td>
          <td><?= $s['result_status'] ? status_badge($s['result_status']) . ' ' . e($s['result_grade']) : '—' ?></td>
          <td class="text-end <?= $s['due_amount'] > 0 ? 'text-danger' : '' ?>"><?= money($s['due_amount'], false) ?></td><td><?= status_badge($s['status']) ?></td><td class="small"><?= e($s['certificate_no']) ?></td></tr>
      <?php endforeach; ?></tbody></table></div>
      <?= $students ? '' : \App\Core\View::partial('partials/empty', ['message' => 'No students enrolled yet.']) ?></div>
  </div>
</div>
