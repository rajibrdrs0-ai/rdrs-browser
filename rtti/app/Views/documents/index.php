<div class="page-head"><h1><?= e(t('Documents')) ?></h1>
  <div class="actions"><?php if (can('documents.create')): ?><button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#upModal"><i class="bi bi-upload"></i> Upload documents</button><?php endif; ?></div></div>
<?= \App\Core\View::partial('partials/filters', ['f' => ['q' => 'Student ID, name, title', 'center' => true, 'district' => true,
  'extra' => '<div class="col-6 col-md-2"><label class="form-label">Category</label><select name="category_id" class="form-select form-select-sm">' . options($categories, $_GET['category_id'] ?? '', 'id', 'name', 'All') . '</select></div>'
   . '<div class="col-6 col-md-2"><label class="form-label">Verification</label><select name="verification" class="form-select form-select-sm">' . kv_options(['pending' => 'Pending', 'verified' => 'Verified', 'rejected' => 'Rejected'], $_GET['verification'] ?? '', 'All') . '</select></div>']]) ?>
<div class="card">
  <?= \App\Core\View::partial('documents/_list', ['documents' => $p['rows'], 'showStudent' => true]) ?>
  <?= \App\Core\View::partial('partials/pagination', ['p' => $p]) ?>
</div>
<?php if (can('documents.create')): ?>
<div class="modal fade" id="upModal" tabindex="-1"><div class="modal-dialog"><form class="modal-content" method="post" action="<?= url('documents/store') ?>" enctype="multipart/form-data">
  <?= csrf_field() ?>
  <div class="modal-header"><h5 class="modal-title">Upload student documents</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
  <div class="modal-body">
    <div class="mb-2"><label class="form-label req">Student</label><select name="student_id" class="form-select s2" data-ajax="<?= url('ajax/students') ?>" required></select></div>
    <div class="mb-2"><label class="form-label req">Category</label><select name="document_category_id" class="form-select" required><?= options($categories) ?></select></div>
    <div class="mb-2"><label class="form-label">Title</label><input name="title" class="form-control"></div>
    <div class="mb-2"><label class="form-label req">Files (PDF, JPG, PNG – multiple allowed)</label><input type="file" name="files[]" multiple accept=".pdf,.jpg,.jpeg,.png" class="form-control" required></div>
  </div>
  <div class="modal-footer"><button class="btn btn-primary">Upload</button></div>
</form></div></div>
<?php endif; ?>
