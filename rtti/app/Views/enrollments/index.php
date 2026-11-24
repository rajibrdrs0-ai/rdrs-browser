<div class="page-head"><h1><?= e(t('Student Enrollment')) ?></h1>
  <div class="actions"><?= \App\Core\View::partial('partials/export_buttons', ['perm' => 'enrollments.export']) ?>
  <?php if (can('enrollments.create')): ?><a class="btn btn-primary btn-sm" href="<?= url('enrollments/create') ?>"><i class="bi bi-journal-plus"></i> New Enrollment</a><?php endif; ?></div></div>
<?= \App\Core\View::partial('partials/filters', ['f' => ['q' => 'Enrollment / student ID, name, mobile', 'center' => true, 'district' => true, 'dates' => true,
  'extra' => '<div class="col-6 col-md-2"><label class="form-label">Course</label><select name="course_id" class="form-select form-select-sm">' . options($courses, $_GET['course_id'] ?? '', 'id', 'name', 'All') . '</select></div>'
  . '<div class="col-6 col-md-2"><label class="form-label">Status</label><select name="status" class="form-select form-select-sm">' . kv_options($statuses, $_GET['status'] ?? '', 'All') . '</select></div>'
  . '<div class="col-auto"><div class="form-check mb-1"><input class="form-check-input" type="checkbox" name="due" value="1" id="dueOnly"' . (($_GET['due'] ?? '') === '1' ? ' checked' : '') . '><label class="form-check-label small" for="dueOnly">With due only</label></div></div>']]) ?>
<div class="card"><div class="table-responsive"><table class="table table-sm table-hover mb-0">
  <thead><tr><th>Enrollment</th><th>Date</th><th>Student</th><th>Course / batch</th><th>Center</th><th class="text-end">Payable</th><th class="text-end">Paid</th><th class="text-end">Due</th><th>Status</th><th></th></tr></thead>
  <tbody>
  <?php foreach ($p['rows'] as $r): ?>
    <tr>
      <td><a class="fw-semibold" href="<?= url('enrollments/view', ['id' => $r['id']]) ?>"><?= e($r['enrollment_code']) ?></a></td>
      <td><?= e(fmt_date($r['enrollment_date'])) ?></td>
      <td><a href="<?= url('students/view', ['id' => $r['student_id']]) ?>"><?= e($r['student']) ?></a><br><small class="text-muted"><?= e($r['student_code']) ?> · <?= e($r['mobile']) ?></small></td>
      <td><?= e($r['course']) ?><br><small class="text-muted"><?= e($r['batch_code']) ?></small></td>
      <td><?= e($r['center']) ?></td>
      <td class="text-end"><?= money($r['payable_amount'], false) ?></td><td class="text-end"><?= money($r['paid_amount'], false) ?></td>
      <td class="text-end <?= $r['due_amount'] > 0 ? 'text-danger fw-semibold' : '' ?>"><?= money($r['due_amount'], false) ?></td>
      <td><?= status_badge($r['status']) ?></td>
      <td class="table-actions">
        <?php if (can('fees.collect') && $r['due_amount'] > 0 && in_array($r['status'], ['active', 'pending', 'completed'])): ?><a class="btn btn-sm btn-outline-success" href="<?= url('fees/collect', ['enrollment_id' => $r['id']]) ?>" title="Collect fee"><i class="bi bi-cash"></i></a><?php endif; ?>
        <a class="btn btn-sm btn-outline-primary" href="<?= url('enrollments/view', ['id' => $r['id']]) ?>"><i class="bi bi-eye"></i></a>
      </td>
    </tr>
  <?php endforeach; ?>
  </tbody></table></div>
  <?= $p['rows'] ? '' : \App\Core\View::partial('partials/empty') ?>
  <?= \App\Core\View::partial('partials/pagination', ['p' => $p]) ?>
</div>
