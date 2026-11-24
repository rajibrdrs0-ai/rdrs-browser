<div class="page-head"><h1><?= e($a['title']) ?></h1></div>
<div class="row g-3">
  <div class="col-lg-4"><div class="card"><div class="card-body"><dl class="kv small">
    <dt>Course</dt><dd><?= e($a['course']) ?></dd><dt>Batch</dt><dd><?= e($a['batch_code'] ?: 'All batches of the course') ?></dd>
    <dt>Deadline</dt><dd><?= e(fmt_datetime($a['deadline'])) ?></dd><dt>Total marks</dt><dd><?= e((float)$a['total_marks']) ?></dd>
    <?php if ($a['attachment']): ?><dt>Attachment</dt><dd><a target="_blank" href="<?= e(file_url($a['attachment'])) ?>"><i class="bi bi-paperclip"></i> Open</a></dd><?php endif; ?>
  </dl><?php if ($a['instructions']): ?><hr><div class="small"><?= nl2br(e($a['instructions'])) ?></div><?php endif; ?></div></div></div>
  <div class="col-lg-8">
    <?php if ($batchOptions): ?><form class="filters row g-2" method="get"><input type="hidden" name="r" value="assignments/view"><input type="hidden" name="id" value="<?= (int)$a['id'] ?>">
      <div class="col-md-8"><select name="batch_id" class="form-select" onchange="this.form.submit()"><?= options($batchOptions, $batchId, 'id', 'name', '-- Select batch to see submissions --') ?></select></div></form><?php endif; ?>
    <?php if ($batchId): ?>
    <form method="post" action="<?= url('assignments/evaluate', ['id' => $a['id']]) ?>"><?= csrf_field() ?><input type="hidden" name="batch_id" value="<?= (int)$batchId ?>">
    <div class="card"><div class="card-header">Submissions & evaluation</div><div class="table-responsive"><table class="table table-sm mb-0 align-middle">
      <thead><tr><th>Student</th><th>Submission</th><th>Status</th><th style="width:100px">Marks</th><th>Feedback</th></tr></thead><tbody>
      <?php foreach ($rows as $r): ?><tr><td><?= e($r['name']) ?><br><small class="text-muted"><?= e($r['student_code']) ?></small></td>
        <td class="small"><?php if ($r['submitted_at']): ?><?= e(fmt_datetime($r['submitted_at'])) ?><?php if ($r['file_path']): ?> · <a target="_blank" href="<?= e(file_url($r['file_path'])) ?>">file</a><?php endif; ?><?php if ($r['submission_text']): ?><br><?= e(mb_strimwidth($r['submission_text'], 0, 80, '…')) ?><?php endif; ?>
          <?php elseif (can('assignments.evaluate')): ?><button type="button" class="btn btn-sm btn-link p-0" onclick="openSub(<?= (int)$r['enrollment_id'] ?>, <?= e(json_encode($r['name'])) ?>)">Record submission</button><?php else: ?>—<?php endif; ?></td>
        <td><?= status_badge($r['status'] ?? 'pending') ?></td>
        <td><input type="number" step="0.5" min="0" max="<?= e($a['total_marks']) ?>" name="marks[<?= (int)$r['enrollment_id'] ?>]" class="form-control form-control-sm" value="<?= e($r['marks']) ?>"<?= can('assignments.evaluate') ? '' : ' disabled' ?>></td>
        <td><input name="feedback[<?= (int)$r['enrollment_id'] ?>]" class="form-control form-control-sm" value="<?= e($r['feedback']) ?>"<?= can('assignments.evaluate') ? '' : ' disabled' ?>></td></tr><?php endforeach; ?>
      </tbody></table></div>
      <?php if (can('assignments.evaluate') && $rows): ?><div class="card-footer bg-white text-end"><button class="btn btn-primary">Save evaluation</button></div><?php endif; ?></div></form>
    <?php endif; ?>
  </div>
</div>
<div class="modal fade" id="subModal" tabindex="-1"><div class="modal-dialog"><form class="modal-content" method="post" action="<?= url('assignments/submit', ['id' => $a['id']]) ?>" enctype="multipart/form-data"><?= csrf_field() ?>
  <input type="hidden" name="enrollment_id" id="subEnr"><div class="modal-header"><h5 class="modal-title">Record submission – <span id="subName"></span></h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
  <div class="modal-body"><div class="mb-2"><label class="form-label">Submitted at</label><input type="datetime-local" name="submitted_at" class="form-control" value="<?= date('Y-m-d\TH:i') ?>"></div>
    <div class="mb-2"><label class="form-label">File</label><input type="file" name="file" class="form-control" accept=".pdf,.jpg,.jpeg,.png"></div>
    <div class="mb-2"><label class="form-label">Text / notes</label><textarea name="submission_text" class="form-control"></textarea></div></div>
  <div class="modal-footer"><button class="btn btn-primary">Save</button></div></form></div></div>
<?php ob_start(); ?><script>function openSub(id, name){ $('#subEnr').val(id); $('#subName').text(name); new bootstrap.Modal('#subModal').show(); }</script><?php \App\Core\View::share('scripts', ob_get_clean()); ?>
