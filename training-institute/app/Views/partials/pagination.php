<?php /** @var int $total @var int $page @var int $pages @var string $base @var array $query */ ?>
<div class="card-footer bg-body d-flex flex-wrap gap-2 justify-content-between align-items-center small">
  <span class="text-body-secondary"><?= e(__('common.showing', ['count' => num($total)])) ?></span>
  <?php if ($pages > 1): ?>
    <nav><ul class="pagination pagination-sm mb-0 flex-wrap">
      <?php for ($i = max(1, $page - 4); $i <= min($pages, $page + 4); $i++): ?>
        <li class="page-item <?= $i === $page ? 'active' : '' ?>"><a class="page-link" href="<?= e(url($base, array_filter($query + ['page' => $i], static fn($v) => $v !== '' && $v !== null))) ?>"><?= num($i) ?></a></li>
      <?php endfor; ?>
    </ul></nav>
  <?php endif; ?>
</div>
