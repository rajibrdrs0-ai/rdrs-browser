<div class="page-head"><h1><?= e(t('Search')) ?><?= $q !== '' ? ': “' . e($q) . '”' : '' ?></h1></div>
<form class="filters row g-2" method="get"><input type="hidden" name="r" value="search/index">
  <div class="col-md-6"><input name="q" class="form-control" value="<?= e($q) ?>" placeholder="Student ID, name, father, mobile, NID, enrollment, course, batch, certificate, receipt, staff, asset…" autofocus></div>
  <div class="col-md-3"><select name="module" class="form-select"><?= kv_options(['students' => 'Students', 'enrollments' => 'Enrollments', 'courses' => 'Courses', 'batches' => 'Batches', 'certificates' => 'Certificates', 'receipts' => 'Money receipts', 'staff' => 'Staff', 'assets' => 'Assets', 'expenses' => 'Expenses'], $only, 'All modules') ?></select></div>
  <div class="col-md-2"><button class="btn btn-primary w-100"><i class="bi bi-search"></i> Search</button></div></form>
<?php if ($q !== '' && !$groups): ?><div class="card"><?= \App\Core\View::partial('partials/empty', ['message' => 'Nothing matched your search.']) ?></div><?php endif; ?>
<div class="row g-3"><?php foreach ($groups as $g): ?>
  <div class="col-lg-6"><div class="card h-100"><div class="card-header d-flex"><?= e($g['label']) ?> <span class="badge text-bg-secondary ms-2"><?= count($g['items']) ?></span>
    <?php if (!$only && count($g['items']) >= 25): ?><a class="ms-auto small" href="<?= url('search/index', ['q' => $q, 'module' => $g['key']]) ?>">show all</a><?php endif; ?></div>
    <div class="list-group list-group-flush"><?php foreach ($g['items'] as $it): ?><a class="list-group-item list-group-item-action" href="<?= e($it['url']) ?>"><b><?= e($it['title']) ?></b><br><small class="text-muted"><?= e($it['sub']) ?></small></a><?php endforeach; ?></div></div></div>
<?php endforeach; ?></div>
