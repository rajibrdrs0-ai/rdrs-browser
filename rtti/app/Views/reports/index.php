<?php $icons = ['Student' => 'people', 'Academic' => 'mortarboard', 'Financial' => 'cash-coin', 'Administrative' => 'building-gear']; ?>
<div class="page-head"><h1><?= e(t('Reports')) ?></h1><span class="text-muted small"><?= \App\Core\Auth::isHeadOffice() ? 'Head office: reports are consolidated across all centers; filter by district or center for separate figures.' : 'Reports cover your assigned centers.' ?></span></div>
<div class="row g-3"><?php foreach (['Student', 'Academic', 'Financial', 'Administrative'] as $g): if (empty($groups[$g]) && empty($extra[$g])) continue; ?>
  <div class="col-md-6 col-xl-3"><div class="card h-100"><div class="card-header"><i class="bi bi-<?= $icons[$g] ?> me-1"></i> <?= e($g) ?> reports</div>
    <div class="list-group list-group-flush">
      <?php foreach ($groups[$g] ?? [] as $k => $title): ?><a class="list-group-item list-group-item-action" href="<?= url('reports/run', ['type' => $k]) ?>"><?= e($title) ?></a><?php endforeach; ?>
      <?php foreach ($extra[$g] ?? [] as [$title, $link, $hint]): ?><a class="list-group-item list-group-item-action" href="<?= e($link) ?>"><?= e($title) ?><br><small class="text-muted"><?= e($hint) ?></small></a><?php endforeach; ?>
    </div></div></div>
<?php endforeach; ?></div>
