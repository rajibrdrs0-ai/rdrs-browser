<?php /** @var array $p paginate() result */
if (($p['total'] ?? 0) === 0) return;
$from = ($p['page'] - 1) * $p['per_page'] + 1; $to = min($p['total'], $p['page'] * $p['per_page']);
$win = 2; $start = max(1, $p['page'] - $win); $end = min($p['pages'], $p['page'] + $win);
?>
<div class="d-flex flex-wrap align-items-center gap-2 p-2 border-top small">
  <span class="text-muted">Showing <?= $from ?>–<?= $to ?> of <?= number_format($p['total']) ?></span>
  <?php if ($p['pages'] > 1): ?>
  <ul class="pagination pagination-sm mb-0 ms-auto">
    <li class="page-item<?= $p['page'] <= 1 ? ' disabled' : '' ?>"><a class="page-link" href="<?= e(current_url_with(['page' => $p['page'] - 1])) ?>">‹</a></li>
    <?php if ($start > 1): ?><li class="page-item"><a class="page-link" href="<?= e(current_url_with(['page' => 1])) ?>">1</a></li><?php if ($start > 2): ?><li class="page-item disabled"><span class="page-link">…</span></li><?php endif; endif; ?>
    <?php for ($i = $start; $i <= $end; $i++): ?>
      <li class="page-item<?= $i === $p['page'] ? ' active' : '' ?>"><a class="page-link" href="<?= e(current_url_with(['page' => $i])) ?>"><?= $i ?></a></li>
    <?php endfor; ?>
    <?php if ($end < $p['pages']): if ($end < $p['pages'] - 1): ?><li class="page-item disabled"><span class="page-link">…</span></li><?php endif; ?><li class="page-item"><a class="page-link" href="<?= e(current_url_with(['page' => $p['pages']])) ?>"><?= $p['pages'] ?></a></li><?php endif; ?>
    <li class="page-item<?= $p['page'] >= $p['pages'] ? ' disabled' : '' ?>"><a class="page-link" href="<?= e(current_url_with(['page' => $p['page'] + 1])) ?>">›</a></li>
  </ul>
  <?php endif; ?>
</div>
