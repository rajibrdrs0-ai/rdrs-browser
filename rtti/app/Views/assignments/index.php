<div class="page-head"><h1><?= e(t('Assignments')) ?></h1>
  <div class="actions"><?php if (can('assignments.create')): ?><button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#aModal"><i class="bi bi-plus-lg"></i> New assignment</button><?php endif; ?></div></div>
<?= \App\Core\View::partial('partials/filters', ['f' => ['q' => 'Title', 'extra' => '<div class="col-md-3"><label class="form-label">Course</label><select name="course_id" class="form-select form-select-sm">' . options($courses, $_GET['course_id'] ?? '', 'id', 'name', 'All') . '</select></div>']]) ?>
<div class="card"><div class="table-responsive"><table class="table table-sm table-hover mb-0">
  <thead><tr><th>Title</th><th>Course</th><th>Assigned to</th><th>Deadline</th><th class="text-end">Marks</th><th class="text-center">Submitted</th><th class="text-center">Evaluated</th><th></th></tr></thead>
  <tbody><?php foreach ($p['rows'] as $r): $over = strtotime($r['deadline']) < time(); ?>
    <tr><td><a href="<?= url('assignments/view', ['id' => $r['id']]) ?>"><?= e($r['title']) ?></a><?= $r['attachment'] ? ' <i class="bi bi-paperclip"></i>' : '' ?></td><td><?= e($r['course']) ?></td>
      <td><?= $r['batch_code'] ? e($r['batch_code']) : '<span class="badge text-bg-secondary">All batches</span>' ?></td>
      <td class="<?= $over ? 'text-muted' : 'text-success' ?>"><?= e(fmt_datetime($r['deadline'])) ?></td><td class="text-end"><?= e((float)$r['total_marks']) ?></td>
      <td class="text-center"><?= (int)$r['submitted'] ?></td><td class="text-center"><?= (int)$r['evaluated'] ?></td>
      <td class="table-actions"><?php if (can('assignments.delete')): ?><form method="post" action="<?= url('assignments/delete', ['id' => $r['id']]) ?>" class="d-inline" data-confirm="Delete this assignment?"><?= csrf_field() ?><button class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i></button></form><?php endif; ?></td></tr>
  <?php endforeach; ?></tbody></table></div>
  <?= $p['rows'] ? '' : \App\Core\View::partial('partials/empty') ?>
  <?= \App\Core\View::partial('partials/pagination', ['p' => $p]) ?></div>
<?php if (can('assignments.create')): ?>
<div class="modal fade" id="aModal" tabindex="-1"><div class="modal-dialog modal-lg"><form class="modal-content" method="post" action="<?= url('assignments/save') ?>" enctype="multipart/form-data"><?= csrf_field() ?>
  <div class="modal-header"><h5 class="modal-title">New assignment</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
  <div class="modal-body row g-2">
    <div class="col-12"><label class="form-label req">Title</label><input name="title" class="form-control" required></div>
    <div class="col-md-6"><label class="form-label req">Course</label><select name="course_id" id="aCourse" class="form-select" required><?= options($courses) ?></select></div>
    <div class="col-md-6"><label class="form-label">Batch <?= \App\Core\Auth::isHeadOffice() ? '(empty = all batches of the course)' : '' ?></label><select name="batch_id" id="aBatch" class="form-select"><option value="">-- All batches --</option>
      <?php foreach ($batches as $b): ?><option value="<?= (int)$b['id'] ?>" data-course="<?= (int)$b['course_id'] ?>"><?= e($b['name']) ?></option><?php endforeach; ?></select></div>
    <div class="col-md-6"><label class="form-label req">Submission deadline</label><input type="datetime-local" name="deadline" class="form-control" required value="<?= date('Y-m-d\T17:00', strtotime('+7 days')) ?>"></div>
    <div class="col-md-6"><label class="form-label req">Total marks</label><input type="number" step="0.5" name="total_marks" class="form-control" value="10" required></div>
    <div class="col-12"><label class="form-label">Instructions</label><textarea name="instructions" class="form-control" rows="4"></textarea></div>
    <div class="col-12"><label class="form-label">Attachment (PDF/JPG/PNG)</label><input type="file" name="attachment" class="form-control" accept=".pdf,.jpg,.jpeg,.png"></div>
  </div>
  <div class="modal-footer"><button class="btn btn-primary">Create</button></div></form></div></div>
<?php ob_start(); ?><script>$('#aCourse').on('change', function(){ const c = this.value; $('#aBatch option[data-course]').each(function(){ $(this).toggle(!c || this.dataset.course === c); }); $('#aBatch').val(''); });</script><?php \App\Core\View::share('scripts', ob_get_clean()); ?>
<?php endif; ?>
