<?php if (!$documents): ?><?= \App\Core\View::partial('partials/empty', ['message' => 'No documents uploaded.']) ?><?php return; endif; ?>
<div class="table-responsive"><table class="table table-sm table-hover mb-0">
  <thead><tr><?php if (!empty($showStudent)): ?><th>Student</th><?php endif; ?><th>Category</th><th>Title</th><th>Type / size</th><th>Uploaded</th><th>Verification</th><th class="text-end">Actions</th></tr></thead>
  <tbody>
  <?php foreach ($documents as $d): $isImg = str_starts_with((string)$d['mime_type'], 'image/'); ?>
    <tr>
      <?php if (!empty($showStudent)): ?><td><a href="<?= url('students/view', ['id' => $d['sid']]) ?>"><?= e($d['student_code']) ?></a><br><small><?= e($d['student']) ?> · <?= e($d['center']) ?></small></td><?php endif; ?>
      <td><?= e($d['category']) ?></td>
      <td><?= e($d['title']) ?></td>
      <td class="small"><?= $isImg ? 'Image' : 'PDF' ?> · <?= number_format(($d['file_size'] ?? 0) / 1024, 0) ?> KB</td>
      <td class="small"><?= e(fmt_date($d['created_at'])) ?><?= isset($d['uploader']) ? '<br>' . e($d['uploader']) : '' ?></td>
      <td><?= status_badge($d['verification_status']) ?><?= $d['remarks'] ? '<br><small class="text-muted">' . e($d['remarks']) . '</small>' : '' ?></td>
      <td class="table-actions">
        <a class="btn btn-sm btn-outline-primary" target="_blank" href="<?= e(file_url($d['file_path'])) ?>" title="Preview"><i class="bi bi-eye"></i></a>
        <a class="btn btn-sm btn-outline-secondary" href="<?= e(file_url($d['file_path'])) ?>&dl=1" title="Download"><i class="bi bi-download"></i></a>
        <?php if (can('documents.verify') && $d['verification_status'] !== 'verified'): ?>
          <form method="post" action="<?= url('documents/verify') ?>" class="d-inline"><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int)$d['id'] ?>"><input type="hidden" name="status" value="verified"><button class="btn btn-sm btn-outline-success" title="Verify"><i class="bi bi-check2"></i></button></form>
          <form method="post" action="<?= url('documents/verify') ?>" class="d-inline" data-confirm="Reject this document?" data-reason><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int)$d['id'] ?>"><input type="hidden" name="status" value="rejected"><button class="btn btn-sm btn-outline-warning" title="Reject"><i class="bi bi-x"></i></button></form>
        <?php endif; ?>
        <?php if (can('documents.delete')): ?>
          <form method="post" action="<?= url('documents/delete') ?>" class="d-inline" data-confirm="Remove this document?" data-reason><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int)$d['id'] ?>"><button class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i></button></form>
        <?php endif; ?>
      </td>
    </tr>
  <?php endforeach; ?>
  </tbody>
</table></div>
