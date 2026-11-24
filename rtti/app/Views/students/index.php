<div class="page-head"><h1><?= e(t('Student Register')) ?></h1>
  <div class="actions">
    <?= \App\Core\View::partial('partials/export_buttons', ['perm' => 'students.export']) ?>
    <?php if (can('students.import')): ?><a class="btn btn-outline-secondary btn-sm" href="<?= url('import/index', ['type' => 'students']) ?>"><i class="bi bi-upload"></i> Import</a><?php endif; ?>
    <?php if (can('students.create')): ?><a class="btn btn-primary btn-sm" href="<?= url('students/create') ?>"><i class="bi bi-person-plus"></i> New Student</a><?php endif; ?>
  </div>
</div>
<?= \App\Core\View::partial('partials/filters', ['f' => ['q' => 'ID, name, father, mobile, NID…', 'center' => true, 'district' => true, 'dates' => true, 'date_label' => 'Registered',
  'extra' => '<div class="col-6 col-md-2"><label class="form-label">Status</label><select name="status" class="form-select form-select-sm">' . kv_options($statuses, $_GET['status'] ?? '', 'All') . '</select></div>'
   . '<div class="col-6 col-md-2"><label class="form-label">Gender</label><select name="gender_id" class="form-select form-select-sm">' . options($genders, $_GET['gender_id'] ?? '', 'id', 'name', 'All') . '</select></div>'
   . '<div class="col-6 col-md-2"><label class="form-label">Course</label><select name="course_id" class="form-select form-select-sm">' . options($courses, $_GET['course_id'] ?? '', 'id', 'name', 'All') . '</select></div>']]) ?>
<div class="card">
  <div class="table-responsive"><table class="table table-hover table-sm mb-0">
    <thead><tr><th>Student ID</th><th>Name</th><th>Father's name</th><th>Mobile</th><th>Gender</th><th>Center</th><th>Registered</th><th class="text-center">Courses</th><th>Status</th><th></th></tr></thead>
    <tbody>
    <?php foreach ($p['rows'] as $r): ?>
      <tr>
        <td><a href="<?= url('students/view', ['id' => $r['id']]) ?>" class="fw-semibold"><?= e($r['student_code']) ?></a></td>
        <td><?= e($r['name']) ?></td><td><?= e($r['father_name']) ?></td><td><?= e($r['mobile']) ?></td><td><?= e($r['gender']) ?></td>
        <td><?= e($r['center']) ?></td><td><?= e(fmt_date($r['registration_date'])) ?></td><td class="text-center"><?= (int)$r['courses'] ?></td>
        <td><?= status_badge($r['status']) ?></td>
        <td class="table-actions">
          <a class="btn btn-sm btn-outline-primary" href="<?= url('students/view', ['id' => $r['id']]) ?>" title="View"><i class="bi bi-eye"></i></a>
          <?php if (can('students.edit')): ?><a class="btn btn-sm btn-outline-secondary" href="<?= url('students/edit', ['id' => $r['id']]) ?>" title="Edit"><i class="bi bi-pencil"></i></a><?php endif; ?>
          <?php if (can('enrollments.create')): ?><a class="btn btn-sm btn-outline-success" href="<?= url('enrollments/create', ['student_id' => $r['id']]) ?>" title="Enroll in a course"><i class="bi bi-journal-plus"></i></a><?php endif; ?>
        </td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table></div>
  <?= $p['rows'] ? '' : \App\Core\View::partial('partials/empty') ?>
  <?= \App\Core\View::partial('partials/pagination', ['p' => $p]) ?>
</div>
