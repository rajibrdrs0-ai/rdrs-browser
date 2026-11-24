<?php
$cards = [
  ['Total Students', number_format($stats['students']), 'people', 'blue', url('students/index')],
  ['Active Students', number_format($stats['active_students']), 'person-check', 'green', url('enrollments/index', ['status' => 'active'])],
  ['New Registrations', number_format($stats['new_students']), 'person-plus', 'purple', url('students/index', ['from' => $from, 'to' => $to])],
  ['Total Enrollments', number_format($stats['enrollments']), 'journal-check', 'teal', url('enrollments/index', ['from' => $from, 'to' => $to])],
  ['Running Courses', number_format($stats['running_courses']), 'book', 'amber', url('courses/index')],
  ['Running Batches', number_format($stats['running_batches']), 'collection-play', 'blue', url('batches/index', ['status' => 'RUNNING'])],
  ['Completed Batches', number_format($stats['completed_batches']), 'check2-all', 'green', url('batches/index', ['status' => 'COMPLETED'])],
  ['Pending Fee', money($stats['pending_fee']), 'hourglass-split', 'red', url('fees/dues')],
  ['Fee Collected', money($stats['collection']), 'cash-stack', 'green', url('fees/index', ['from' => $from, 'to' => $to])],
  ['Total Expenses', money($stats['expenses']), 'receipt', 'amber', url('expenses/index', ['from' => $from, 'to' => $to])],
  ['Total Staff', number_format($stats['staff']), 'person-badge', 'purple', url('staff/index')],
  ['Training Centers', number_format($stats['centers']), 'buildings', 'teal', url('master/index', ['g' => 'center'])],
];
?>
<div class="page-head">
  <h1><?= e(t('Dashboard')) ?></h1>
  <span class="text-muted small"><?= e(fmt_date($from)) ?> – <?= e(fmt_date($to)) ?></span>
</div>
<?= \App\Core\View::partial('partials/filters', ['f' => ['center' => true, 'district' => true, 'dates' => true, 'default_from' => $from, 'default_to' => $to]]) ?>

<?php if ($stats['pending_expenses'] && can('expenses.approve') || $stats['pending_enrollments'] && can('enrollments.approve')): ?>
<div class="alert alert-warning d-flex flex-wrap gap-3 py-2">
  <?php if ($stats['pending_expenses'] && can('expenses.approve')): ?><a href="<?= url('expenses/index', ['status' => 'pending']) ?>"><i class="bi bi-exclamation-circle"></i> <?= $stats['pending_expenses'] ?> expense voucher(s) awaiting approval</a><?php endif; ?>
  <?php if ($stats['pending_enrollments'] && can('enrollments.approve')): ?><a href="<?= url('enrollments/index', ['status' => 'pending']) ?>"><i class="bi bi-exclamation-circle"></i> <?= $stats['pending_enrollments'] ?> enrollment(s) awaiting approval</a><?php endif; ?>
</div>
<?php endif; ?>

<div class="row g-3 mb-3">
<?php foreach ($cards as [$label, $value, $icon, $color, $link]): ?>
  <div class="col-6 col-md-4 col-xl-3 col-xxl-2">
    <a href="<?= e($link) ?>" class="card stat text-decoration-none text-reset">
      <span class="ico ico-<?= $color ?>"><i class="bi bi-<?= $icon ?>"></i></span>
      <span><span class="v d-block"><?= e($value) ?></span><span class="l"><?= e(t($label)) ?></span></span>
    </a>
  </div>
<?php endforeach; ?>
</div>

<div class="row g-3 mb-3">
  <div class="col-lg-8"><div class="card h-100"><div class="card-header">Monthly income vs expense</div><div class="card-body"><canvas id="chIncome" height="120"></canvas></div></div></div>
  <div class="col-lg-4"><div class="card h-100"><div class="card-header">Batch status</div><div class="card-body"><canvas id="chBatch" height="200"></canvas></div></div></div>
  <div class="col-lg-8"><div class="card h-100"><div class="card-header">Monthly registrations & enrollments</div><div class="card-body"><canvas id="chEnroll" height="120"></canvas></div></div></div>
  <div class="col-lg-4"><div class="card h-100"><div class="card-header">Enrollment outcome</div><div class="card-body"><canvas id="chCompletion" height="200"></canvas></div></div></div>
  <div class="col-lg-6"><div class="card h-100"><div class="card-header">Course-wise enrollment</div><div class="card-body"><canvas id="chCourse" height="160"></canvas></div></div></div>
  <div class="col-lg-6"><div class="card h-100"><div class="card-header">District-wise enrollment</div><div class="card-body"><canvas id="chDistrict" height="160"></canvas></div></div></div>
</div>

<div class="card mb-3">
  <div class="card-header d-flex">Center-wise performance <span class="ms-auto small text-muted">for selected period</span></div>
  <div class="table-responsive"><table class="table table-sm table-hover mb-0">
    <thead><tr><th>Center</th><th>District</th><th class="text-end">Enrollments</th><th class="text-end">Running batches</th><th class="text-end">Fee collected</th><th class="text-end">Expenses</th><th class="text-end">Net</th><th class="text-end">Outstanding due</th><th class="text-end">Certificates</th></tr></thead>
    <tbody>
    <?php $tot = ['enrollments' => 0, 'running' => 0, 'income' => 0, 'expense' => 0, 'due' => 0, 'certs' => 0];
    foreach ($centerPerf as $c): foreach ($tot as $k => $_) $tot[$k] += $c[$k]; ?>
      <tr><td><a href="<?= url('dashboard', ['center_id' => $c['id'], 'from' => $from, 'to' => $to]) ?>"><?= e($c['name']) ?></a></td><td><?= e($c['district']) ?></td>
        <td class="text-end"><?= number_format((int)$c['enrollments']) ?></td><td class="text-end"><?= (int)$c['running'] ?></td>
        <td class="text-end"><?= money($c['income'], false) ?></td><td class="text-end"><?= money($c['expense'], false) ?></td>
        <td class="text-end <?= $c['income'] - $c['expense'] < 0 ? 'text-danger' : 'text-success' ?>"><?= money($c['income'] - $c['expense'], false) ?></td>
        <td class="text-end"><?= money($c['due'], false) ?></td><td class="text-end"><?= (int)$c['certs'] ?></td></tr>
    <?php endforeach; ?>
    </tbody>
    <tfoot class="fw-bold"><tr><td colspan="2">Consolidated total</td><td class="text-end"><?= number_format($tot['enrollments']) ?></td><td class="text-end"><?= $tot['running'] ?></td><td class="text-end"><?= money($tot['income'], false) ?></td><td class="text-end"><?= money($tot['expense'], false) ?></td><td class="text-end"><?= money($tot['income'] - $tot['expense'], false) ?></td><td class="text-end"><?= money($tot['due'], false) ?></td><td class="text-end"><?= $tot['certs'] ?></td></tr></tfoot>
  </table></div>
</div>

<div class="row g-3">
  <div class="col-lg-4"><div class="card h-100"><div class="card-header">Upcoming batch schedules</div>
    <ul class="list-group list-group-flush">
    <?php foreach ($upcoming as $b): ?>
      <li class="list-group-item"><a href="<?= url('batches/view', ['id' => $b['id']]) ?>"><?= e($b['batch_code']) ?></a> · <?= e($b['course']) ?><br>
        <small class="text-muted"><?= e($b['center']) ?> · starts <?= e(fmt_date($b['start_date'])) ?> · <?= (int)$b['enrolled'] ?>/<?= (int)$b['max_students'] ?> seats</small></li>
    <?php endforeach; if (!$upcoming): ?><li class="list-group-item text-muted small">No upcoming batches.</li><?php endif; ?>
    </ul></div></div>
  <div class="col-lg-4"><div class="card h-100"><div class="card-header">Recent admissions</div>
    <ul class="list-group list-group-flush">
    <?php foreach ($recent as $r): ?>
      <li class="list-group-item"><a href="<?= url('students/view', ['id' => $r['sid']]) ?>"><?= e($r['name']) ?></a> <small class="text-muted">(<?= e($r['student_code']) ?>)</small><br>
        <small class="text-muted"><?= e($r['course']) ?> · <?= e($r['center']) ?> · <?= e(fmt_date($r['enrollment_date'])) ?></small></li>
    <?php endforeach; ?>
    </ul></div></div>
  <div class="col-lg-4"><div class="card h-100"><div class="card-header">Recent activities</div>
    <ul class="list-group list-group-flush small">
    <?php foreach ($activity as $a): ?>
      <li class="list-group-item"><b><?= e($a['uname'] ?? 'System') ?></b> – <?= e($a['description'] ?: ($a['module'] . ' ' . $a['action'])) ?><br><small class="text-muted"><?= e(fmt_datetime($a['created_at'])) ?></small></li>
    <?php endforeach; ?>
    </ul></div></div>
</div>

<?php ob_start(); ?>
<script>
const C = <?= json_encode($chart) ?>;
const palette = ['#1a4f9c','#c8102e','#15803d','#b45309','#7c3aed','#0f766e','#db2777','#64748b'];
const money = v => '৳ ' + Number(v).toLocaleString('en-IN');
new Chart(chIncome, {type:'bar', data:{labels:C.labels, datasets:[{label:'Fee collection', data:C.income, backgroundColor:'#1a4f9c'},{label:'Expenses', data:C.expense, backgroundColor:'#c8102e'}]}, options:{plugins:{tooltip:{callbacks:{label:c=>c.dataset.label+': '+money(c.raw)}}}, scales:{y:{ticks:{callback:money}}}}});
new Chart(chEnroll, {type:'line', data:{labels:C.labels, datasets:[{label:'New registrations', data:C.registrations, borderColor:'#7c3aed', tension:.3},{label:'Enrollments', data:C.enrollments, borderColor:'#15803d', tension:.3}]}});
const bs = <?= json_encode($batchStatus) ?>;
const bsCol = {info:'#0dcaf0',success:'#15803d',primary:'#1a4f9c',danger:'#c8102e',warning:'#f59e0b',secondary:'#64748b',dark:'#111827'};
new Chart(chBatch, {type:'doughnut', data:{labels:bs.map(x=>x.label), datasets:[{data:bs.map(x=>x.v), backgroundColor:bs.map(x=>bsCol[x.color]||'#64748b')}]}, options:{plugins:{legend:{position:'bottom'}}}});
const cp = <?= json_encode($completion) ?>;
new Chart(chCompletion, {type:'pie', data:{labels:cp.map(x=>x.label.charAt(0).toUpperCase()+x.label.slice(1)), datasets:[{data:cp.map(x=>x.v), backgroundColor:palette}]}, options:{plugins:{legend:{position:'bottom'}}}});
const bc = <?= json_encode($byCourse) ?>;
new Chart(chCourse, {type:'bar', data:{labels:bc.map(x=>x.label), datasets:[{label:'Enrollments', data:bc.map(x=>x.v), backgroundColor:'#1a4f9c'}]}, options:{indexAxis:'y', plugins:{legend:{display:false}}}});
const bd = <?= json_encode($byDistrict) ?>;
new Chart(chDistrict, {type:'bar', data:{labels:bd.map(x=>x.label), datasets:[{label:'Enrollments', data:bd.map(x=>x.v), backgroundColor:'#c8102e'}]}, options:{plugins:{legend:{display:false}}}});
</script>
<?php $scripts = ob_get_clean(); \App\Core\View::share('scripts', $scripts); ?>
