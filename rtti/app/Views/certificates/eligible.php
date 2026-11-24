<div class="page-head"><h1>Issue certificates · <?= e($batch['batch_code']) ?></h1><span class="text-muted"><?= e($batch['course']) ?> · <?= e($batch['center']) ?> · <?= e(fmt_date($batch['start_date'])) ?> – <?= e(fmt_date($batch['end_date'])) ?></span></div>
<div class="alert alert-info small py-2">Requirements are set in <a href="<?= url('settings/index') ?>">System Settings</a>: pass in final exam <?= setting('cert_require_pass') === '1' ? '✔' : '✘' ?>, minimum attendance <?= setting('cert_require_attendance') === '1' ? '✔' : '✘' ?>, full fee payment <?= setting('cert_require_full_payment') === '1' ? '✔' : '✘' ?>, batch completed <?= setting('cert_require_batch_completed') === '1' ? '✔' : '✘' ?>.</div>
<form method="post" action="<?= url('certificates/issue') ?>">
<?= csrf_field() ?>
<div class="card mb-3"><div class="table-responsive"><table class="table table-sm mb-0">
  <thead><tr><th><input type="checkbox" class="form-check-input" id="chkAll"></th><th>Student</th><th>Attendance</th><th>Result</th><th class="text-end">Due</th><th>Eligibility</th></tr></thead>
  <tbody><?php foreach ($rows as $r): $ok = !$r['problems']; ?>
    <tr class="<?= $r['cert'] ? 'table-success' : '' ?>">
      <td><?php if ($ok): ?><input type="checkbox" class="form-check-input chk" name="enrollment_ids[]" value="<?= (int)$r['id'] ?>"><?php endif; ?></td>
      <td><?= e($r['student']) ?> <small class="text-muted"><?= e($r['student_code']) ?></small></td>
      <td><?= $r['attendance'] === null ? '—' : e($r['attendance'] . '%') ?></td>
      <td><?= $r['result_status'] ? status_badge($r['result_status']) . ' ' . e($r['result_grade']) : '—' ?></td>
      <td class="text-end"><?= money($r['due_amount'], false) ?></td>
      <td class="small"><?php if ($r['cert']): ?><a target="_blank" href="<?= url('certificates/view', ['id' => $r['cert']['id']]) ?>"><i class="bi bi-award"></i> <?= e($r['cert']['certificate_no']) ?></a>
        <?php elseif ($ok): ?><span class="text-success fw-semibold">Eligible</span><?php else: ?><span class="text-danger"><?= e(implode(' ', $r['problems'])) ?></span><?php endif; ?></td>
    </tr>
  <?php endforeach; ?></tbody></table></div></div>
<div class="card"><div class="card-body row g-2 align-items-end">
  <div class="col-md-3"><label class="form-label">Issue date</label><input type="date" name="issue_date" class="form-control" value="<?= date('Y-m-d') ?>" max="<?= date('Y-m-d') ?>"></div>
  <div class="col-md-3"><label class="form-label">Template</label><select name="template_id" class="form-select"><?= options($templates, '', 'id', 'name', '') ?></select></div>
  <div class="col-md-3"><button class="btn btn-primary"><i class="bi bi-award"></i> Issue selected certificates</button></div>
</div></div>
</form>
<?php ob_start(); ?><script>$('#chkAll').on('change', function(){ $('.chk').prop('checked', this.checked); });</script><?php \App\Core\View::share('scripts', ob_get_clean()); ?>
