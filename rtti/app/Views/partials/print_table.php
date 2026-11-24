<div class="page">
  <?= \App\Core\View::partial('partials/print_header') ?>
  <h2 class="doc-title"><?= e($title) ?></h2>
  <?php if ($subtitle): ?><div class="text-center small mb-2"><?= e($subtitle) ?></div><?php endif; ?>
  <table class="table table-sm table-bordered print-table">
    <thead><tr><?= $headHtml ?></tr></thead>
    <tbody><?= $bodyHtml ?></tbody>
  </table>
  <div class="small text-muted">Total records: <?= (int)$count ?> · Printed by <?= e(user()['name'] ?? '') ?> on <?= date('d/m/Y h:i A') ?></div>
</div>
