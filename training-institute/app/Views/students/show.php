<?php
/** @var array $student @var array $educations @var array $documents @var array $enrollments */
use App\Controllers\StudentController as SC;
$s = $student;
$this->layout('layouts/app', ['title' => loc($s, 'name')]);
$age = (new DateTimeImmutable((string) $s['date_of_birth']))->diff(new DateTimeImmutable('today'))->y;
$place = static function (array $s, string $p): string {
    $parts = array_filter([$s[$p . '_address'] ?? null, $s[$p === 'permanent' ? 'perm_upazila_' . (current_locale() === 'bn' ? 'bn' : 'en') : 'pres_upazila_' . (current_locale() === 'bn' ? 'bn' : 'en')] ?? null,
        $s[$p === 'permanent' ? 'perm_district_' . (current_locale() === 'bn' ? 'bn' : 'en') : 'pres_district_' . (current_locale() === 'bn' ? 'bn' : 'en')] ?? null]);
    return $parts === [] ? '—' : implode(', ', $parts);
};
$enrStatus = ['admitted' => 'info', 'running' => 'success', 'completed' => 'dark', 'dropped_out' => 'warning', 'transferred' => 'secondary', 'cancelled' => 'danger'];
?>
<div class="page-head">
  <div class="d-flex align-items-center gap-3">
    <?= $this->insert('students/_avatar', ['s' => $s, 'size' => 64]) ?>
    <div><h1><?= e(loc($s, 'name')) ?></h1><div class="small text-body-secondary"><?= e($s['registration_no']) ?> · <?= e(loc($s, 'center')) ?>
      <?php if ($s['status'] !== 'active'): ?><span class="badge text-bg-secondary"><?= e(__('students.statuses.' . $s['status'])) ?></span><?php endif; ?></div></div>
  </div>
  <div class="d-flex gap-2">
    <?php if (can('enrollment.create') && $s['status'] === 'active'): ?><a class="btn btn-sm btn-primary" href="<?= e(url('/enrollments/create', ['student_id' => $s['id']])) ?>"><i class="bi bi-box-arrow-in-right me-1"></i><?= e(__('students.admit')) ?></a><?php endif; ?>
    <?php if (can('student.update')): ?><a class="btn btn-sm btn-outline-secondary" href="<?= e(url('/students/' . $s['id'] . '/edit')) ?>"><i class="bi bi-pencil me-1"></i><?= e(__('common.edit')) ?></a><?php endif; ?>
  </div>
</div>
<div class="row g-3">
  <div class="col-lg-5">
    <div class="card mb-3"><div class="card-header bg-body fw-semibold"><?= e(__('students.sections.basic')) ?></div><div class="card-body"><dl class="row small mb-0">
      <dt class="col-5"><?= e(__('students.gender')) ?></dt><dd class="col-7"><?= e(__('staff.genders.' . $s['gender'])) ?></dd>
      <dt class="col-5"><?= e(__('students.dob')) ?></dt><dd class="col-7"><?= e(fmt_date($s['date_of_birth'])) ?> (<?= num($age) ?>)</dd>
      <dt class="col-5"><?= e(__('students.nid')) ?></dt><dd class="col-7"><?= e($s['nid_no'] ?? '—') ?></dd>
      <dt class="col-5"><?= e(__('students.brn')) ?></dt><dd class="col-7"><?= e($s['birth_reg_no'] ?? '—') ?></dd>
      <dt class="col-5"><?= e(__('students.mobile')) ?></dt><dd class="col-7"><a href="tel:<?= e($s['mobile']) ?>"><?= e($s['mobile']) ?></a></dd>
      <dt class="col-5"><?= e(__('students.education')) ?></dt><dd class="col-7"><?= e($s['highest_education'] ?? '—') ?></dd>
      <dt class="col-5"><?= e(__('students.father')) ?></dt><dd class="col-7"><?= e($s['father_name'] ?? '—') ?><?= $s['father_mobile'] ? ' · ' . e($s['father_mobile']) : '' ?></dd>
      <dt class="col-5"><?= e(__('students.mother')) ?></dt><dd class="col-7"><?= e($s['mother_name'] ?? '—') ?><?= $s['mother_mobile'] ? ' · ' . e($s['mother_mobile']) : '' ?></dd>
      <?php if ($s['guardian_name']): ?><dt class="col-5"><?= e(__('students.guardian')) ?></dt><dd class="col-7"><?= e($s['guardian_name']) ?> (<?= e($s['guardian_relation'] ?? '') ?>) <?= e($s['guardian_mobile'] ?? '') ?></dd><?php endif; ?>
      <dt class="col-5"><?= e(__('students.permanent')) ?></dt><dd class="col-7"><?= e($place($s, 'permanent')) ?></dd>
      <dt class="col-5"><?= e(__('students.present')) ?></dt><dd class="col-7"><?= e($place($s, 'present')) ?></dd>
      <dt class="col-5"><?= e(__('students.disability')) ?></dt><dd class="col-7"><?= e(__('students.disabilities.' . $s['disability'])) ?><?= (int) $s['is_ethnic_minority'] === 1 ? ' · ' . e(__('students.ethnic')) : '' ?></dd>
      <dt class="col-5"><?= e(__('students.registered_on')) ?></dt><dd class="col-7"><?= e(fmt_date(substr((string) $s['created_at'], 0, 10))) ?></dd>
    </dl></div></div>

    <div class="card mb-3" id="education"><div class="card-header bg-body fw-semibold"><?= e(__('students.educations')) ?></div>
      <ul class="list-group list-group-flush">
        <?php foreach ($educations as $ed): ?>
          <li class="list-group-item d-flex justify-content-between small"><span><span class="fw-semibold"><?= e($ed['exam_name']) ?></span> <?= e($ed['passing_year'] ?? '') ?> · <?= e($ed['institution'] ?? '') ?> <?= $ed['result'] ? '· ' . e($ed['result']) : '' ?></span>
            <?php if (can('student.update')): ?><form method="post" action="<?= e(url('/students/' . $s['id'] . '/educations/' . $ed['id'])) ?>" data-confirm="<?= e(__('common.confirm')) ?>"><?= csrf_field() ?><?= method_field('DELETE') ?><button class="btn btn-link btn-sm p-0 text-danger"><i class="bi bi-x-lg"></i></button></form><?php endif; ?></li>
        <?php endforeach; ?>
        <?php if ($educations === []): ?><li class="list-group-item small text-body-secondary"><?= e(__('students.no_education')) ?></li><?php endif; ?>
      </ul>
      <?php if (can('student.update')): ?>
      <form method="post" action="<?= e(url('/students/' . $s['id'] . '/educations')) ?>" class="card-body border-top" novalidate><?= csrf_field() ?>
        <div class="row g-2">
          <div class="col-6"><input class="form-control form-control-sm" name="exam_name" placeholder="<?= e(__('students.exam')) ?> *" required aria-label="<?= e(__('students.exam')) ?>"></div>
          <div class="col-6"><input class="form-control form-control-sm" name="institution" placeholder="<?= e(__('students.institution')) ?>" aria-label="<?= e(__('students.institution')) ?>"></div>
          <div class="col-4"><input class="form-control form-control-sm" name="board" placeholder="<?= e(__('students.board')) ?>" aria-label="<?= e(__('students.board')) ?>"></div>
          <div class="col-3"><input class="form-control form-control-sm" name="passing_year" placeholder="<?= e(__('students.year')) ?>" inputmode="numeric" aria-label="<?= e(__('students.year')) ?>"></div>
          <div class="col-3"><input class="form-control form-control-sm" name="result" placeholder="<?= e(__('students.result')) ?>" aria-label="<?= e(__('students.result')) ?>"></div>
          <div class="col-2 d-grid"><button class="btn btn-sm btn-outline-primary"><i class="bi bi-plus-lg"></i></button></div>
        </div>
      </form>
      <?php endif; ?>
    </div>
  </div>

  <div class="col-lg-7">
    <div class="card mb-3"><div class="card-header bg-body fw-semibold"><i class="bi bi-collection me-1"></i><?= e(__('students.enrollments')) ?></div>
      <div class="table-responsive"><table class="table table-sm mb-0 align-middle">
        <thead class="table-light"><tr><th class="ps-3"><?= e(__('batches.code')) ?></th><th><?= e(__('admission.status_label')) ?></th><th class="text-end"><?= e(__('admission.net')) ?></th><th class="text-end"><?= e(__('admission.due')) ?></th><th class="text-center"><?= e(__('attendance.percent')) ?></th></tr></thead>
        <tbody>
        <?php foreach ($enrollments as $en): ?>
          <tr><td class="ps-3"><?php if ((int) $en['in_scope'] === 1 && can('enrollment.view')): ?><a href="<?= e(url('/enrollments/' . $en['id'])) ?>"><?= e($en['batch_code']) ?></a><?php else: ?><?= e($en['batch_code']) ?><?php endif; ?>
              <div class="small text-body-secondary"><?= e(loc($en, 'course')) ?> · <?= e(__('admission.roll')) ?> <?= e($en['roll_no']) ?></div></td>
            <td><span class="badge text-bg-<?= $enrStatus[$en['status']] ?? 'secondary' ?>"><?= e(__('admission.status.' . $en['status'])) ?></span></td>
            <td class="text-end"><?= money($en['net_fee']) ?></td>
            <td class="text-end <?= (float) $en['due'] > 0 ? 'text-danger' : '' ?>"><?= money($en['due']) ?></td>
            <td class="text-center"><?= $en['attendance_percent'] !== null ? num($en['attendance_percent'], 1) . '%' : '—' ?></td></tr>
        <?php endforeach; ?>
        <?php if ($enrollments === []): ?><tr><td colspan="5" class="ps-3 text-body-secondary"><?= e(__('students.no_enrollments')) ?></td></tr><?php endif; ?>
        </tbody></table></div></div>

    <div class="card" id="documents"><div class="card-header bg-body fw-semibold"><i class="bi bi-folder2-open me-1"></i><?= e(__('students.documents')) ?></div>
      <ul class="list-group list-group-flush">
        <?php foreach ($documents as $d): ?>
          <li class="list-group-item d-flex justify-content-between align-items-center small">
            <span><i class="bi bi-file-earmark<?= str_contains((string) $d['mime_type'], 'pdf') ? '-pdf' : '-image' ?> me-1"></i>
              <?php if (can('student.documents')): ?><a href="<?= e(url('/students/' . $s['id'] . '/documents/' . $d['id'])) ?>" target="_blank" rel="noopener"><?= e(__('students.doc_types.' . $d['doc_type'])) ?><?= $d['title'] ? ' — ' . e($d['title']) : '' ?></a>
              <?php else: ?><?= e(__('students.doc_types.' . $d['doc_type'])) ?><?php endif; ?>
              <span class="text-body-secondary">· <?= e(fmt_date(substr((string) $d['created_at'], 0, 10))) ?> · <?= e($d['uploader'] ?? '') ?></span></span>
            <?php if (can('student.documents')): ?><form method="post" action="<?= e(url('/students/' . $s['id'] . '/documents/' . $d['id'])) ?>" data-confirm="<?= e(__('common.confirm')) ?>"><?= csrf_field() ?><?= method_field('DELETE') ?><button class="btn btn-link btn-sm p-0 text-danger"><i class="bi bi-trash"></i></button></form><?php endif; ?>
          </li>
        <?php endforeach; ?>
        <?php if ($documents === []): ?><li class="list-group-item small text-body-secondary"><?= e(__('students.no_documents')) ?></li><?php endif; ?>
      </ul>
      <?php if (can('student.documents')): ?>
      <form method="post" action="<?= e(url('/students/' . $s['id'] . '/documents')) ?>" enctype="multipart/form-data" class="card-body border-top" novalidate><?= csrf_field() ?>
        <div class="row g-2 align-items-end">
          <div class="col-sm-3"><?= form_select('doc_type', __('students.doc_type'), array_combine(SC::DOC_TYPES, array_map(static fn($t) => __('students.doc_types.' . $t), SC::DOC_TYPES)), 'nid', ['class' => 'form-select form-select-sm', 'placeholder' => false]) ?></div>
          <div class="col-sm-3"><?= form_input('title', __('students.doc_title'), '', ['class' => 'form-control form-control-sm', 'maxlength' => 120]) ?></div>
          <div class="col-sm-4"><?= form_input('document', __('students.file'), '', ['type' => 'file', 'class' => 'form-control form-control-sm', 'accept' => 'application/pdf,image/jpeg,image/png', 'value' => null]) ?></div>
          <div class="col-sm-2 d-grid"><button class="btn btn-sm btn-outline-primary"><i class="bi bi-upload me-1"></i><?= e(__('students.upload')) ?></button></div>
          <div class="col-12 form-text mt-0"><?= e(__('students.file_help')) ?></div>
        </div>
      </form>
      <?php endif; ?>
    </div>
  </div>
</div>
