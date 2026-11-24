<div class="page-head"><h1><?= e($c['name']) ?></h1><span class="badge text-bg-<?= e($c['status_color']) ?>"><?= e($c['status_name']) ?></span>
  <div class="actions">
    <?php if (can('batches.create')): ?><a class="btn btn-primary btn-sm" href="<?= url('batches/create', ['course_id' => $c['id']]) ?>"><i class="bi bi-plus-lg"></i> New batch</a><?php endif; ?>
    <?php if (can('courses.edit')): ?><a class="btn btn-outline-primary btn-sm" href="<?= url('courses/edit', ['id' => $c['id']]) ?>"><i class="bi bi-pencil"></i> Edit</a><?php endif; ?>
    <?php if (can('courses.delete')): ?><form method="post" action="<?= url('courses/delete', ['id' => $c['id']]) ?>" class="d-inline" data-confirm="Delete this course?"><?= csrf_field() ?><button class="btn btn-outline-danger btn-sm"><i class="bi bi-trash"></i></button></form><?php endif; ?>
  </div></div>
<div class="row g-3">
  <div class="col-lg-4">
    <div class="card mb-3"><div class="card-body"><dl class="kv small">
      <dt>Code</dt><dd><?= e($c['code']) ?></dd><dt>Trade</dt><dd><?= e($c['trade']) ?></dd><dt>Category</dt><dd><?= e($c['category']) ?></dd>
      <dt>Duration</dt><dd><?= (int)$c['duration'] ?> <?= e($c['duration_unit']) ?></dd><dt>Course fee</dt><dd><?= money($c['fee']) ?></dd>
      <dt>Min. qualification</dt><dd><?= e($c['min_education']) ?></dd><dt>Pass mark</dt><dd><?= e($c['pass_mark_percent']) ?>%</dd>
      <dt>Min. attendance</dt><dd><?= e($c['min_attendance_percent']) ?>%</dd><dt>Created</dt><dd><?= e(fmt_date($c['created_at'])) ?></dd>
    </dl>
    <?php if ($c['description']): ?><hr><p class="small mb-0"><?= nl2br(e($c['description'])) ?></p><?php endif; ?></div></div>
    <?php if ($fees): ?><div class="card mb-3"><div class="card-header">Center-wise fee</div><table class="table table-sm mb-0"><?php foreach ($fees as $f): ?><tr><td><?= e($f['center'] ?: 'All centers') ?></td><td><?= e(fmt_date($f['effective_from'])) ?></td><td class="text-end"><?= money($f['fee'], false) ?></td></tr><?php endforeach; ?></table></div><?php endif; ?>
    <div class="card mb-3"><div class="card-header">Objectives & outline</div><div class="card-body small"><b>Objectives</b><p><?= nl2br(e($c['objectives'])) ?></p><b>Outline</b><p class="mb-0"><?= nl2br(e($c['outline'])) ?></p></div></div>
  </div>
  <div class="col-lg-8">
    <div class="card mb-3" id="subjects"><div class="card-header">Curriculum – subjects & marks distribution</div>
      <table class="table table-sm mb-0"><thead><tr><th>Subject</th><th class="text-end">Theory</th><th class="text-end">Practical</th><th class="text-end">Total</th><th></th></tr></thead><tbody>
      <?php foreach ($subjects as $s): ?><tr><td><?= e($s['name']) ?></td><td class="text-end"><?= e($s['theory_full_marks']) ?></td><td class="text-end"><?= e($s['practical_full_marks']) ?></td><td class="text-end"><?= e($s['theory_full_marks'] + $s['practical_full_marks']) ?></td>
        <td class="text-end"><?php if (can('courses.edit')): ?><form method="post" action="<?= url('courses/subject', ['id' => $c['id']]) ?>" class="d-inline" data-confirm="Remove this subject?"><?= csrf_field() ?><input type="hidden" name="action" value="delete"><input type="hidden" name="subject_id" value="<?= (int)$s['id'] ?>"><button class="btn btn-sm btn-link text-danger p-0"><i class="bi bi-x-lg"></i></button></form><?php endif; ?></td></tr><?php endforeach; ?>
      <?php if (!$subjects): ?><tr><td colspan="5" class="text-muted small">No subjects – exams will use the exam's own theory/practical full marks.</td></tr><?php endif; ?>
      </tbody></table>
      <?php if (can('courses.edit')): ?><form method="post" action="<?= url('courses/subject', ['id' => $c['id']]) ?>" class="row g-2 p-2 border-top"><?= csrf_field() ?>
        <div class="col-md-5"><input name="name" class="form-control form-control-sm" placeholder="Subject name" required></div>
        <div class="col-md-2"><input name="theory_full_marks" type="number" step="0.01" class="form-control form-control-sm" placeholder="Theory"></div>
        <div class="col-md-2"><input name="practical_full_marks" type="number" step="0.01" class="form-control form-control-sm" placeholder="Practical"></div>
        <div class="col-md-3"><button class="btn btn-sm btn-primary w-100">Add subject</button></div></form><?php endif; ?>
    </div>
    <div class="card mb-3"><div class="card-header">Batches (<?= count($batches) ?>)</div><div class="table-responsive"><table class="table table-sm mb-0">
      <thead><tr><th>Batch</th><th>Center</th><th>Trainer</th><th>Period</th><th class="text-center">Seats</th><th>Status</th></tr></thead><tbody>
      <?php foreach ($batches as $b): ?><tr><td><a href="<?= url('batches/view', ['id' => $b['id']]) ?>"><?= e($b['batch_code']) ?></a></td><td><?= e($b['center']) ?></td><td><?= e($b['trainer']) ?></td><td class="small"><?= e(fmt_date($b['start_date'])) ?> – <?= e(fmt_date($b['end_date'])) ?></td><td class="text-center"><?= (int)$b['enrolled'] ?>/<?= (int)$b['max_students'] ?></td><td><span class="badge text-bg-<?= e($b['color']) ?>"><?= e($b['status']) ?></span></td></tr><?php endforeach; ?>
      </tbody></table></div></div>
    <div class="card mb-3"><div class="card-header">Course-wise student list (<?= count($students) ?>)</div><div class="table-responsive" style="max-height:360px"><table class="table table-sm mb-0">
      <thead><tr><th>Student</th><th>Batch</th><th>Center</th><th>Grade</th><th>Status</th></tr></thead><tbody>
      <?php foreach ($students as $s): ?><tr><td><a href="<?= url('students/view', ['id' => $s['sid']]) ?>"><?= e($s['name']) ?></a> <small class="text-muted"><?= e($s['student_code']) ?></small></td><td><?= e($s['batch_code']) ?></td><td><?= e($s['center']) ?></td><td><?= e($s['result_grade']) ?></td><td><?= status_badge($s['status']) ?></td></tr><?php endforeach; ?>
      </tbody></table></div></div>
    <div class="card"><div class="card-header">Course documents</div>
      <ul class="list-group list-group-flush"><?php foreach ($docs as $d): ?><li class="list-group-item small"><a target="_blank" href="<?= e(file_url($d['file_path'])) ?>"><i class="bi bi-file-earmark"></i> <?= e($d['title']) ?></a> <span class="text-muted">· <?= e($d['uname']) ?> · <?= e(fmt_date($d['created_at'])) ?></span></li><?php endforeach; ?></ul>
      <?php if (can('courses.edit')): ?><form method="post" enctype="multipart/form-data" action="<?= url('courses/document', ['id' => $c['id']]) ?>" class="row g-2 p-2 border-top"><?= csrf_field() ?>
        <div class="col-md-5"><input name="title" class="form-control form-control-sm" placeholder="Title"></div><div class="col-md-5"><input type="file" name="file" class="form-control form-control-sm" accept=".pdf,.jpg,.jpeg,.png" required></div><div class="col-md-2"><button class="btn btn-sm btn-primary w-100">Upload</button></div></form><?php endif; ?>
    </div>
  </div>
</div>
